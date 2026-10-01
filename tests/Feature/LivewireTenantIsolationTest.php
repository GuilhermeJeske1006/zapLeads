<?php

namespace Tests\Feature;

use App\Livewire\Chat\ChatPanel;
use App\Livewire\WhatsAppChannels;
use App\Livewire\WhatsAppSenderWizard;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\User;
use App\Models\WhatsAppChannel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_opens_only_the_empresa_conversations(): void
    {
        [$user, $empresa] = $this->userWithEmpresa();
        [, $other] = $this->userWithEmpresa();
        $own = Conversation::create(['empresa_id' => $empresa->id, 'telefone' => '5547911112222', 'nome_contato' => 'Cliente meu', 'status' => 'active']);
        $foreign = Conversation::create(['empresa_id' => $other->id, 'telefone' => '5547933334444', 'nome_contato' => 'Cliente alheio', 'status' => 'active']);

        $chat = Livewire::actingAs($user)->test(ChatPanel::class)
            ->call('selectConversation', $own->id)
            ->assertSet('activeConversationId', $own->id);

        $this->expectException(ModelNotFoundException::class);
        $chat->call('selectConversation', $foreign->id);
    }

    public function test_chat_conversation_id_cannot_be_set_from_the_browser(): void
    {
        [$user] = $this->userWithEmpresa();
        [, $other] = $this->userWithEmpresa();
        $foreign = Conversation::create(['empresa_id' => $other->id, 'telefone' => '5547933334444', 'status' => 'active']);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($user)->test(ChatPanel::class)->set('activeConversationId', $foreign->id);
    }

    public function test_channels_empresa_cannot_be_swapped_from_the_browser(): void
    {
        [, $empresa] = $this->userWithEmpresa();
        [, $other] = $this->userWithEmpresa();
        WhatsAppChannel::create(['empresa_id' => $other->id, 'nome' => 'Vendas', 'numero' => 'whatsapp:+5547900000002', 'is_default' => true, 'ativo' => true]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(WhatsAppChannels::class, ['empresaId' => $empresa->id])->set('empresaId', $other->id);
    }

    public function test_sender_wizard_empresa_cannot_be_swapped_from_the_browser(): void
    {
        [, $empresa] = $this->userWithEmpresa();
        [, $other] = $this->userWithEmpresa();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(WhatsAppSenderWizard::class, ['empresaId' => $empresa->id])->set('empresaId', $other->id);
    }

    /** @return array{User, Empresa} */
    private function userWithEmpresa(): array
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        return [$user, Empresa::create(['user_id' => $user->id, 'nome' => 'Empresa ' . $user->id])];
    }
}
