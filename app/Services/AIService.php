<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Models\Lead;
use App\Models\Conversation;
use App\Models\Empresa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIService
{
    private string $model = 'claude-opus-4-7';

    private AnthropicClient $client;

    public function __construct()
    {
        $this->client = new AnthropicClient(apiKey: config('services.anthropic.key'));
    }

    public function classificarLead(Lead $lead): array
    {
        $prompt = $this->buildLeadClassificationPrompt($lead);

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 1024,
                messages: [['role' => 'user', 'content' => $prompt]],
                system: 'Você é um especialista em vendas e análise de leads. Responda sempre em JSON válido sem markdown, apenas o objeto JSON puro.',
            );

            $text = $response->content[0]->text;
            Log::debug('AIService classificarLead response', ['raw' => $text]);
            return json_decode($text, true) ?? [];
        } catch (\Throwable $e) {
            Log::error('AIService classificarLead failed', ['error' => $e->getMessage()]);
            return ['score' => $lead->lead_score, 'categoria' => 'cold', 'insights' => []];
        }
    }

    public function gerarMensagem(Conversation $conversation, string $contexto = ''): string
    {
        $ultimasMensagens = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => "[{$m->sender}]: {$m->message}")
            ->join("\n");

        $prompt = "Histórico de conversa:\n{$ultimasMensagens}\n\n";
        if ($contexto) {
            $prompt .= "Contexto adicional: {$contexto}\n\n";
        }
        $prompt .= "Gere uma resposta profissional e amigável para o cliente, em português do Brasil. Máximo 200 caracteres.";

        $empresa = $conversation->empresa;
        $systemPrompt = ($empresa?->ai_persona && trim($empresa->ai_persona) !== '')
            ? trim($empresa->ai_persona)
            : 'Você é um atendente de loja virtual. Seja prestativo, cordial e objetivo.';

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 300,
                messages: [['role' => 'user', 'content' => $prompt]],
                system: $systemPrompt,
            );

            $text = trim($response->content[0]->text);
            Log::debug('AIService gerarMensagem response', ['raw' => $text]);
            return $text;
        } catch (\Throwable $e) {
            Log::error('AIService gerarMensagem failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    public function sugerirCampanha(array $leads, string $objetivo = ''): array
    {
        $cacheKey = 'ai_campanha_' . md5(serialize(array_column($leads, 'id')) . $objetivo);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($leads, $objetivo) {
            $stats = [
                'total' => count($leads),
                'proximos' => collect($leads)->where('is_nearby', true)->count(),
                'score_medio' => collect($leads)->avg('lead_score'),
            ];

            $prompt = "Dados da campanha:\n" . json_encode($stats, JSON_PRETTY_PRINT);
            if ($objetivo) {
                $prompt .= "\nObjetivo: {$objetivo}";
            }
            $prompt .= "\n\nSugira: mensagem de campanha, melhor horário de envio e segmentação. Responda em JSON puro sem markdown, apenas o objeto JSON.";

            try {
                $response = $this->client->messages->create(
                    model: $this->model,
                    maxTokens: 1024,
                    messages: [['role' => 'user', 'content' => $prompt]],
                    system: 'Você é especialista em marketing digital e WhatsApp marketing. Responda sempre em JSON válido sem markdown, apenas o objeto JSON puro.',
                );

                $text = $response->content[0]->text;
                Log::debug('AIService sugerirCampanha response', ['raw' => $text]);
                $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
                $text = preg_replace('/\s*```$/m', '', $text);

                return json_decode(trim($text), true) ?? [];
            } catch (\Throwable $e) {
                Log::error('AIService sugerirCampanha failed', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    public function buscarLeadsPorPerfil(string $descricaoEmpresa, string $tipoCliente, array $leads): array
    {
        if (empty($leads)) {
            return [];
        }

        $leadsJson = json_encode(array_map(fn ($l) => [
            'id'           => $l['id'],
            'nome'         => $l['nome'],
            'telefone'     => $l['telefone'],
            'cidade'       => $l['cidade'],
            'distancia_km' => $l['distancia_km'],
            'is_nearby'    => $l['is_nearby'],
            'lead_score'   => $l['lead_score'],
            'ai_insights'  => $l['ai_insights'] ?? null,
        ], $leads), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Empresa: {$descricaoEmpresa}
Perfil de cliente desejado: {$tipoCliente}

Lista de leads disponíveis:
{$leadsJson}

Analise cada lead e retorne APENAS os que têm fit com o perfil desejado, ordenados do mais ao menos relevante.
Para cada lead retornado inclua todos os campos originais mais:
- "match_score": número 0-100 indicando compatibilidade
- "match_motivo": string curta explicando por que esse lead é um bom cliente para essa empresa

Retorne JSON puro (sem markdown), array de objetos:
[{"id":..., "nome":..., "telefone":..., "cidade":..., "distancia_km":..., "is_nearby":..., "lead_score":..., "match_score":..., "match_motivo":...}]
PROMPT;

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 4096,
                messages: [['role' => 'user', 'content' => $prompt]],
                system: 'Você é especialista em análise de leads e segmentação de clientes. Retorne sempre JSON válido puro, sem markdown.',
            );

            $raw  = $response->content[0]->text;
            Log::debug('AIService buscarLeadsPorPerfil response', ['raw' => $raw]);
            $text = preg_replace('/^```(?:json)?\s*/m', '', $raw);
            $text = preg_replace('/\s*```$/m', '', $text);

            return json_decode(trim($text), true) ?? [];
        } catch (\Throwable $e) {
            Log::error('AIService buscarLeadsPorPerfil failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function gerarKeywordsProspeccao(string $descricaoEmpresa, string $tipoCliente): array
    {
        $prompt = <<<PROMPT
Empresa: {$descricaoEmpresa}
Cliente ideal: {$tipoCliente}

Gere termos de busca (keywords) para encontrar no Google Maps empresas que tenham esse perfil (B2B/B2C conforme fizer sentido).
Regras:
- Retorne APENAS JSON puro, sem markdown.
- Chave "keywords": array com 5 a 10 strings curtas.
- Evite termos genéricos demais. Prefira segmentos/tipos de negócio.

Formato:
{"keywords":["...","..."]}
PROMPT;

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 600,
                messages: [['role' => 'user', 'content' => $prompt]],
                system: 'Você é especialista em prospecção comercial. Responda sempre em JSON válido puro, sem markdown.',
            );

            $raw  = $response->content[0]->text;
            Log::debug('AIService gerarKeywordsProspeccao response', ['raw' => $raw]);
            $text = preg_replace('/^```(?:json)?\s*/m', '', $raw);
            $text = preg_replace('/\s*```$/m', '', $text);
            $json = json_decode(trim($text), true) ?? [];

            $keywords = $json['keywords'] ?? [];
            if (!is_array($keywords)) {
                return [];
            }
            $keywords = array_values(array_filter(array_map(fn ($k) => is_string($k) ? trim($k) : '', $keywords)));
            return array_slice($keywords, 0, 10);
        } catch (\Throwable $e) {
            Log::error('AIService gerarKeywordsProspeccao failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function gerarPrimeiraMensagemProspeccao(Empresa $empresa, Lead $lead): string
    {
        $contexto = [
            'empresa' => [
                'nome' => $empresa->nome,
                'descricao' => $empresa->descricao_empresa,
            ],
            'lead' => [
                'nome' => $lead->nome,
                'cidade' => $lead->cidade,
                'endereco' => $lead->endereco,
                'website' => $lead->website,
            ],
        ];

        $prompt = "Crie uma primeira mensagem curta de prospecção via WhatsApp (PT-BR), educada e não invasiva.\n";
        $prompt .= "Objetivo: abrir conversa e entender se faz sentido.\n";
        $prompt .= "Dados:\n" . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        $prompt .= "Regras: máximo 240 caracteres, sem emojis em excesso, inclua uma pergunta no final.";

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 300,
                messages: [['role' => 'user', 'content' => $prompt]],
                system: 'Você é especialista em SDR e prospecção por WhatsApp. Seja direto, cordial e objetivo.',
            );

            $text = trim($response->content[0]->text);
            Log::debug('AIService gerarPrimeiraMensagemProspeccao response', ['raw' => $text]);
            return $text;
        } catch (\Throwable $e) {
            Log::error('AIService gerarPrimeiraMensagemProspeccao failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function buildLeadClassificationPrompt(Lead $lead): string
    {
        return sprintf(
            "Classifique este lead:\nNome: %s\nCidade: %s\nDistância: %s km\nScore atual: %d\n\nRetorne JSON com: score (0-100), categoria (hot/warm/cold), motivo, sugestao_mensagem.",
            $lead->nome,
            $lead->cidade,
            $lead->distancia_km,
            $lead->lead_score
        );
    }
}
