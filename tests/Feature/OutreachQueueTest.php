<?php

namespace Tests\Feature;

use App\Jobs\SendOutreachDraftJob;
use App\Livewire\Leads\OutreachQueue;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OutreachQueueTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->empresa = $this->empresa();
    }

    public function test_shows_only_the_empresa_drafts_with_a_wa_me_link(): void
    {
        $this->draft($this->lead('Studio Bella', '(47) 99280-1006'));
        $other = $this->empresa();
        $this->draft($this->lead('Outra Loja', '(47) 99999-0000', $other));

        Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])
            ->assertSee('Studio Bella')
            ->assertSee('https://wa.me/5547992801006')
            ->assertDontSee('Outra Loja');
    }

    public function test_drafts_of_another_empresa_cannot_be_touched(): void
    {
        $foreign = $this->draft($this->lead('Outra Loja', '(47) 99999-0000', $this->empresa()));

        try {
            Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])->call('pular', $foreign->id);
        } catch (ModelNotFoundException) {
        }

        $this->assertSame('draft', $foreign->fresh()->status);
    }

    public function test_open_in_my_whatsapp_records_the_edited_message(): void
    {
        $draft = $this->draft($this->lead('Studio Bella', '(47) 99280-1006'));

        Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])
            ->call('enviadoPeloMeuWhatsApp', $draft->id, 'Oi, Carla! Texto editado.')
            ->assertDispatched('toast', type: 'success');

        $attempt = OutreachAttempt::sole();
        $this->assertSame('assisted', $attempt->canal);
        $this->assertSame('Oi, Carla! Texto editado.', $attempt->mensagem);
        $this->assertSame('sent', $draft->fresh()->status);
    }

    public function test_card_explains_why_the_api_cannot_send(): void
    {
        $this->channel();
        $draft = $this->draft($this->lead('Studio Bella', '(47) 99280-1006'));

        Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])
            ->assertSee(__('messages.outreach_session_closed'))
            ->call('enviarPelaApi', $draft->id, 'Oi!')
            ->assertDispatched('toast', type: 'error', message: __('messages.outreach_session_closed'));

        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_batch_approval_schedules_what_can_go_through_the_api(): void
    {
        $this->channel();
        $this->template();
        $ok = $this->draft($this->lead('Studio Bella', '(47) 99280-1006'));
        $optedOut = $this->draft($this->lead('Barbearia X', '(47) 99280-1007'));
        $optedOut->lead->update(['opted_out_at' => now()]);

        Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])
            ->set('selecionados', [$ok->id, $optedOut->id])
            ->call('aprovarSelecionados')
            ->assertSet('selecionados', [])
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('approved', $ok->fresh()->status);
        $this->assertSame('draft', $optedOut->fresh()->status);
        Queue::assertPushed(SendOutreachDraftJob::class, 1);
    }

    public function test_edits_are_kept_while_in_review(): void
    {
        $draft = $this->draft($this->lead('Studio Bella', '(47) 99280-1006'));

        Livewire::test(OutreachQueue::class, ['empresa' => $this->empresa])
            ->call('salvarTexto', $draft->id, '  Oi! Texto novo.  ');

        $this->assertSame('Oi! Texto novo.', $draft->fresh()->texto_final);
    }

    private function draft(Lead $lead): OutreachDraft
    {
        return OutreachDraft::create([
            'empresa_id'  => $lead->empresa_id,
            'lead_id'     => $lead->id,
            'texto_final' => 'Oi! Vocês ainda marcam horário só pelo WhatsApp?',
            'status'      => 'draft',
        ]);
    }

    private function lead(string $nome, string $telefone, ?Empresa $empresa = null): Lead
    {
        return Lead::create(['empresa_id' => ($empresa ?? $this->empresa)->id, 'nome' => $nome, 'telefone' => $telefone]);
    }

    private function channel(): WhatsAppChannel
    {
        return WhatsAppChannel::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Vendas', 'numero' => 'whatsapp:+5547900000001', 'is_default' => true, 'ativo' => true,
        ]);
    }

    private function template(): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'primeiro_contato', 'content_sid' => 'HX' . str_repeat('a', 32),
            'corpo_preview' => 'Olá {{1}}!', 'variaveis' => ['1' => 'lead_nome'], 'status' => 'approved',
        ]);
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
    }
}
