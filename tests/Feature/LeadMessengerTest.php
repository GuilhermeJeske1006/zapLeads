<?php

namespace Tests\Feature;

use App\Jobs\FollowUpWhatsAppJob;
use App\Jobs\ProcessSequenceStepJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppTemplate;
use App\Services\LeadMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Catalog follow-ups and lead_capture sequences only go out the way WhatsApp delivers them. */
class LeadMessengerTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private WhatsAppChannel $channel;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Doce Lar']);
        $this->channel = WhatsAppChannel::create(['empresa_id' => $this->empresa->id, 'nome' => 'Loja', 'numero' => 'whatsapp:+5547900000001', 'is_default' => true, 'ativo' => true]);
        $this->lead = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Carla', 'telefone' => '(47) 99280-1006', 'source' => 'internal']);
    }

    public function test_inside_the_session_free_text_goes_and_shows_in_the_chat(): void
    {
        $this->conversation(lastInbound: now()->subHours(2));

        $outcome = $this->messenger()->send($this->lead, 'Oi Carla! Posso ajudar?', 'https://loja.test/storage/bolo.jpg');

        $this->assertSame(LeadMessenger::SENT, $outcome);
        $message = Message::sole();
        $this->assertSame(['Oi Carla! Posso ajudar?', 'image', 'https://loja.test/storage/bolo.jpg', null], [$message->message, $message->type, $message->media_url, $message->content_sid]);
        $this->assertSame('Oi Carla! Posso ajudar?', $message->conversation->last_message);
        Queue::assertPushed(SendWhatsAppMessageJob::class);
    }

    public function test_outside_the_session_the_first_template_the_text_fills_goes_without_the_image(): void
    {
        $this->template('com_contato', ['1' => 'contato_nome', '2' => 'mensagem_sem_saudacao']); // catalog leads have no decision maker
        $this->template('sem_nome', ['1' => 'mensagem_sem_saudacao'], 'Olá! {{1}}');

        $outcome = $this->messenger()->send($this->lead, 'Oi Carla! Viu o catálogo? Posso ajudar?', 'https://loja.test/storage/bolo.jpg');

        $this->assertSame(LeadMessenger::TEMPLATE, $outcome);
        $message = Message::sole();
        $this->assertSame('Olá! Viu o catálogo? Posso ajudar?', $message->message);
        $this->assertSame(['text', null, ['1' => 'Viu o catálogo? Posso ajudar?']], [$message->type, $message->media_url, $message->content_variables]);
        $this->assertTrue($message->isTemplate());
        $this->assertSame($this->channel->id, $message->conversation->whatsapp_channel_id);
    }

    public function test_outside_the_session_without_a_template_nothing_is_sent(): void
    {
        $this->conversation(lastInbound: now()->subDays(3));

        $this->assertSame(LeadMessenger::NO_TEMPLATE, $this->messenger()->send($this->lead, 'Oi!'));

        $this->assertDatabaseCount('messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_opted_out_blocked_or_unreachable_leads_get_nothing(): void
    {
        $this->conversation(lastInbound: now(), status: 'blocked');
        $this->assertSame(LeadMessenger::OPTED_OUT, $this->messenger()->send($this->lead, 'Oi!'));

        $other = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Sem DDD', 'telefone' => '9280-1006']);
        $this->assertSame(LeadMessenger::INVALID_PHONE, $this->messenger()->send($other, 'Oi!'));

        $this->channel->update(['ativo' => false]);
        $third = Lead::create(['empresa_id' => $this->empresa->id, 'nome' => 'Ana', 'telefone' => '(47) 99111-2222']);
        $this->assertSame(LeadMessenger::NO_CHANNEL, $this->messenger()->send($third, 'Oi!'));

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_catalog_follow_up_goes_in_the_empresa_language_unless_the_conversation_moved(): void
    {
        // A day later and nobody wrote: the session is closed, so the follow-up is always a template.
        $this->empresa->update(['locale' => 'es']);
        $this->template('texto', ['1' => 'mensagem'], '{{1}}');
        $this->conversation(lastInbound: now()->subHours(25));

        (new FollowUpWhatsAppJob($this->lead->fresh()))->handle($this->messenger());

        $this->assertSame(__('messages.follow_up_message', ['nome' => 'Carla', 'loja' => 'Doce Lar'], 'es'), Message::sole()->message);

        // The conversation moved: someone wrote in the last day, or the lead left "novo".
        Message::query()->delete();
        (new FollowUpWhatsAppJob($this->lead->fresh()))->handle($this->messenger());
        $this->lead->update(['status' => 'respondeu']);
        Conversation::query()->update(['last_message_at' => now()->subDays(2)]);
        (new FollowUpWhatsAppJob($this->lead->fresh()))->handle($this->messenger());
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_sequence_step_without_a_way_to_deliver_moves_on_and_an_opt_out_ends_it(): void
    {
        $sequence = Sequence::create(['empresa_id' => $this->empresa->id, 'nome' => 'Boas-vindas', 'trigger' => 'lead_capture', 'status' => 'active']);
        $sequence->steps()->create(['ordem' => 1, 'tipo' => 'text', 'mensagem' => 'Oi {nome}, aqui é da {loja}!', 'delay_hours' => 0]);
        $sequence->steps()->create(['ordem' => 2, 'tipo' => 'text', 'mensagem' => 'Ainda precisa de algo?', 'delay_hours' => 24]);
        $enrollment = SequenceEnrollment::create(['sequence_id' => $sequence->id, 'lead_id' => $this->lead->id, 'current_step' => 0, 'status' => 'active', 'next_send_at' => now()]);

        (new ProcessSequenceStepJob($enrollment))->handle($this->messenger());

        $this->assertDatabaseCount('messages', 0); // no session, no template
        $this->assertSame([1, 'active'], [$enrollment->fresh()->current_step, $enrollment->fresh()->status]);
        Queue::assertPushed(ProcessSequenceStepJob::class);

        $this->conversation(lastInbound: now(), status: 'blocked');
        (new ProcessSequenceStepJob($enrollment->fresh()))->handle($this->messenger());
        $this->assertSame('opted_out', $enrollment->fresh()->status);
    }

    private function messenger(): LeadMessenger
    {
        return app(LeadMessenger::class);
    }

    private function conversation($lastInbound, string $status = 'active'): Conversation
    {
        return Conversation::create([
            'empresa_id'          => $this->empresa->id,
            'telefone'            => '+5547992801006',
            'lead_id'             => $this->lead->id,
            'whatsapp_channel_id' => $this->channel->id,
            'status'              => $status,
            'last_inbound_at'     => $lastInbound,
            'last_message_at'     => $lastInbound,
        ]);
    }

    private function template(string $nome, array $variaveis, string $corpo = 'Olá, {{1}}! {{2}}'): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'empresa_id'    => $this->empresa->id,
            'nome'          => $nome,
            'content_sid'   => 'HX' . str_pad((string) WhatsAppTemplate::count(), 32, 'a'),
            'corpo_preview' => $corpo,
            'variaveis'     => $variaveis,
            'status'        => 'approved',
        ]);
    }
}
