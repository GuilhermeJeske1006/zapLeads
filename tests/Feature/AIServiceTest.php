<?php

namespace Tests\Feature;

use Anthropic\Client as AnthropicClient;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\AIService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sent = [];

    public function test_keywords_use_fast_tier_with_a_json_schema(): void
    {
        $this->fakeClaude([$this->answer(['keywords' => ['padaria', ' confeitaria ', '', 'padaria']])]);

        $keywords = app(AIService::class)->gerarKeywordsProspeccao('Fornecedor de embalagens', 'Padarias');

        $this->assertSame(['padaria', 'confeitaria'], $keywords);
        $body = $this->sentBody();
        $this->assertSame('claude-haiku-4-5-20251001', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertArrayNotHasKey('effort', $body['output_config']);
        $this->assertArrayNotHasKey('fallbacks', $body);
    }

    public function test_messages_use_quality_tier_with_low_effort_and_refusal_fallback(): void
    {
        $this->fakeClaude([$this->answer('Oi! Vocês ainda marcam horário só pelo WhatsApp?')]);
        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
        $lead = new Lead(['nome' => 'Studio Bella', 'cidade' => 'Blumenau']);

        $text = app(AIService::class)->gerarPrimeiraMensagemProspeccao($empresa, $lead);

        $this->assertSame('Oi! Vocês ainda marcam horário só pelo WhatsApp?', $text);
        $body = $this->sentBody();
        $this->assertSame('claude-sonnet-5-5', $body['model']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertGreaterThanOrEqual(2000, $body['max_tokens']);
        $this->assertSame('server-side-fallback-2026-07-01', $this->sent[0]['request']->getHeaderLine('anthropic-beta'));
    }

    public function test_truncated_answer_is_discarded(): void
    {
        $this->fakeClaude([$this->answer('{"keywords": ["padar', stopReason: 'max_tokens')]);

        $this->assertSame([], app(AIService::class)->gerarKeywordsProspeccao('Fornecedor', 'Padarias'));
    }

    public function test_only_text_after_a_fallback_switch_is_the_answer(): void
    {
        $this->fakeClaude([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-4-8',
            'content' => [
                ['type' => 'text', 'text' => 'Desculpe, não posso'],
                ['type' => 'fallback', 'from' => ['model' => 'claude-sonnet-5-5'], 'to' => ['model' => 'claude-opus-4-8'], 'trigger' => ['type' => 'refusal']],
                ['type' => 'text', 'text' => 'Mensagem final'],
            ],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]))]);

        $this->assertSame('Mensagem final', app(AIService::class)->text('quality', 'sys', 'oi'));
    }

    public function test_evaluates_every_lead_in_batches_and_one_bad_batch_does_not_lose_the_rest(): void
    {
        $leads = array_map(fn (int $id) => ['id' => $id, 'nome' => "Lead {$id}", 'tipos_google' => ['bakery'], 'nota_google' => 4.5], range(1, 40));
        $row = fn (int $id, int $fit, int $dor = 30, ?string $gancho = null) => [
            'id' => $id, 'fit' => $fit, 'motivo' => 'Padaria no raio', 'dor' => $dor, 'dor_provavel' => null, 'gancho' => $gancho,
        ];

        $this->fakeClaude([
            $this->answer(['leads' => [
                ...array_map(fn (int $id) => $row($id, 70), range(1, 13)),
                $row(14, 80, dor: -5, gancho: '   '),
                $row(15, 150, dor: 120, gancho: 'Nota 4,5 com 30 avaliações'),
                $row(999, 90),
            ]]),
            $this->answer('{"leads": [', stopReason: 'max_tokens'),
            $this->answer(['leads' => array_map(fn (int $id) => $row($id, 40), range(31, 40))]),
        ]);

        $evaluated = collect(app(AIService::class)->avaliarLeads(['o_que_faz' => 'Embalagens', 'cliente_ideal' => 'Padarias'], $leads))->keyBy('id');

        $this->assertCount(3, $this->sent);
        $this->assertCount(25, $evaluated);
        $this->assertSame([100, 100, 'Nota 4,5 com 30 avaliações'], [$evaluated[15]['fit'], $evaluated[15]['dor'], $evaluated[15]['gancho']]);
        $this->assertSame([0, null], [$evaluated[14]['dor'], $evaluated[14]['gancho']]);
        $this->assertFalse($evaluated->has(999));
        $this->assertFalse($evaluated->has(20));
        $this->assertSame(40, $evaluated[40]['fit']);

        $body = $this->sentBody();
        $this->assertSame('claude-haiku-4-5-20251001', $body['model']);
        $this->assertArrayNotHasKey('effort', $body['output_config']);
        $this->assertStringContainsString('Padarias', $body['messages'][0]['content']);
        $schema = $body['output_config']['format']['schema']['properties']['leads']['items'];
        $this->assertSame(['id', 'fit', 'motivo', 'dor', 'dor_provavel', 'gancho'], $schema['required']);
        $this->assertSame([['type' => 'string'], ['type' => 'null']], $schema['properties']['gancho']['anyOf']);
    }

    public function test_keywords_avoid_excluded_segments(): void
    {
        $this->fakeClaude([$this->answer(['keywords' => ['salão de beleza']])]);

        app(AIService::class)->gerarKeywordsProspeccao('Agenda online', 'Salões', 'franquias');

        $this->assertStringContainsString('Não gere termos destes segmentos: franquias', $this->sentBody()['messages'][0]['content']);
    }

    public function test_reply_classification_uses_fast_tier_and_rejects_unknown_intents(): void
    {
        $this->fakeClaude([$this->answer(['intencao' => 'opt_out']), $this->answer(['intencao' => 'talvez'])]);
        $ai = app(AIService::class);

        $this->assertSame('opt_out', $ai->classificarRespostaProspeccao('Me tira dessa lista'));
        $this->assertNull($ai->classificarRespostaProspeccao('Hmm'));

        $body = $this->sentBody();
        $this->assertSame('claude-haiku-4-5-20251001', $body['model']);
        $this->assertSame(
            ['opt_out', 'objection', 'interest', 'other'],
            $body['output_config']['format']['schema']['properties']['intencao']['enum'],
        );
        $this->assertArrayNotHasKey('effort', $body['output_config']);
    }

    public function test_web_research_returns_the_text_and_every_source_url(): void
    {
        $this->fakeClaude([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [
                ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'Studio Bella Blumenau WhatsApp']],
                ['type' => 'web_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => [
                    ['type' => 'web_search_result', 'url' => 'https://guiablumenau.com.br/studio-bella', 'title' => 'Studio Bella', 'encrypted_content' => 'x', 'page_age' => null],
                ]],
                ['type' => 'text', 'text' => 'O WhatsApp é (47) 99999-8888.', 'citations' => [
                    ['type' => 'web_search_result_location', 'url' => 'https://www.instagram.com/studiobella.blu/', 'title' => 'Instagram', 'cited_text' => '99999-8888', 'encrypted_index' => 'y'],
                ]],
            ],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 900, 'output_tokens' => 80],
        ]))]);
        config(['services.enrichment.web_research_max_uses' => 2]);

        $research = app(AIService::class)->pesquisarContatosNaWeb('Studio Bella', 'Blumenau', null);

        $this->assertSame('O WhatsApp é (47) 99999-8888.', $research['texto']);
        $this->assertSame(['https://guiablumenau.com.br/studio-bella', 'https://www.instagram.com/studiobella.blu/'], $research['urls']);
        $body = $this->sentBody();
        $this->assertSame('claude-sonnet-5-5', $body['model']);
        $this->assertEquals([['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 2]], $body['tools']);
        $this->assertArrayNotHasKey('format', $body['output_config']);
    }

    public function test_campaign_suggestion_is_not_cached_when_the_call_fails(): void
    {
        $this->fakeClaude([
            new Response(500, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"api_error","message":"boom"}}'),
            $this->answer(['mensagem' => 'Promoção de pães', 'horario' => '9h às 11h', 'segmentacao' => 'Padarias próximas']),
        ]);
        $ai = app(AIService::class);

        $this->assertSame([], $ai->sugerirCampanha([['id' => 1, 'is_nearby' => true, 'lead_score' => 50]]));
        $this->assertSame('9h às 11h', $ai->sugerirCampanha([['id' => 1, 'is_nearby' => true, 'lead_score' => 50]])['horario']);
    }

    /** @param array<int, Response> $responses */
    private function fakeClaude(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        $this->app->instance(AnthropicClient::class, new AnthropicClient(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
        ));
    }

    private function answer(array|string $content, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_' . uniqid(), 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-4-5-20251001',
            'content' => [['type' => 'text', 'text' => is_array($content) ? json_encode($content) : $content]],
            'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]));
    }

    private function sentBody(int $index = 0): array
    {
        return json_decode((string) $this->sent[$index]['request']->getBody(), true);
    }
}
