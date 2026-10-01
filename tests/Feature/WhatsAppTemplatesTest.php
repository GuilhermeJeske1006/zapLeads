<?php

namespace Tests\Feature;

use App\Livewire\WhatsAppTemplates;
use App\Models\Empresa;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;
use Twilio\Exceptions\RestException;

class WhatsAppTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private const SID = 'HX0123456789abcdef0123456789abcdef';

    public function test_adds_a_template_with_what_twilio_knows_about_it(): void
    {
        $this->twilioHas(['variaveis' => ['1', '2'], 'status' => 'approved']);
        $empresa = $this->empresa();

        Livewire::test(WhatsAppTemplates::class, ['empresa' => $empresa])
            ->set('contentSid', self::SID)
            ->call('adicionar')
            ->assertHasNoErrors()
            ->assertSet('contentSid', '');

        $template = WhatsAppTemplate::sole();
        $this->assertSame($empresa->id, $template->empresa_id);
        $this->assertSame('primeiro_contato', $template->nome);
        $this->assertSame('approved', $template->status);
        $this->assertSame(['1' => null, '2' => null], $template->variaveis);
    }

    public function test_rejects_a_malformed_sid_without_calling_twilio(): void
    {
        $this->mock(WhatsAppService::class, fn (MockInterface $m) => $m->shouldNotReceive('fetchContentTemplate'));

        Livewire::test(WhatsAppTemplates::class, ['empresa' => $this->empresa()])
            ->set('contentSid', 'abc')
            ->call('adicionar')
            ->assertHasErrors('contentSid');
    }

    public function test_unknown_template_is_not_stored(): void
    {
        $this->mock(WhatsAppService::class, fn (MockInterface $m) => $m
            ->shouldReceive('fetchContentTemplate')
            ->andThrow(new RestException('Not found', 20404, 404)));

        Livewire::test(WhatsAppTemplates::class, ['empresa' => $this->empresa()])
            ->set('contentSid', self::SID)
            ->call('adicionar')
            ->assertHasErrors('contentSid');

        $this->assertDatabaseCount('whatsapp_templates', 0);
    }

    public function test_sync_updates_status_and_keeps_mappings(): void
    {
        $this->twilioHas(['variaveis' => ['1', '2'], 'status' => 'rejected']);
        $empresa = $this->empresa();
        $template = $this->template($empresa, ['1' => 'lead_nome']);

        Livewire::test(WhatsAppTemplates::class, ['empresa' => $empresa])->call('sincronizar', $template->id);

        $this->assertSame('rejected', $template->fresh()->status);
        $this->assertSame(['1' => 'lead_nome', '2' => null], $template->fresh()->variaveis);
    }

    public function test_placeholders_only_take_known_fields(): void
    {
        $empresa = $this->empresa();
        $template = $this->template($empresa, ['1' => null, '2' => null]);

        Livewire::test(WhatsAppTemplates::class, ['empresa' => $empresa])
            ->call('mapear', $template->id, '1', 'mensagem')
            ->call('mapear', $template->id, '2', 'senha')
            ->call('mapear', $template->id, '9', 'lead_nome');

        $this->assertSame(['1' => 'mensagem', '2' => null], $template->fresh()->variaveis);
    }

    public function test_templates_of_another_empresa_cannot_be_touched(): void
    {
        $template = $this->template($this->empresa(), ['1' => null]);

        try {
            Livewire::test(WhatsAppTemplates::class, ['empresa' => $this->empresa()])->call('remover', $template->id);
        } catch (ModelNotFoundException) {
        }

        $this->assertModelExists($template);
    }

    private function twilioHas(array $overrides): void
    {
        $this->mock(WhatsAppService::class, fn (MockInterface $m) => $m
            ->shouldReceive('fetchContentTemplate')
            ->andReturn([
                'nome' => 'primeiro_contato', 'idioma' => 'pt_BR', 'categoria' => 'marketing',
                'corpo' => 'Olá {{1}}! {{2}}', 'variaveis' => ['1'], 'status' => 'pending', ...$overrides,
            ]));
    }

    private function template(Empresa $empresa, array $variaveis): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'empresa_id' => $empresa->id, 'nome' => 'primeiro_contato', 'content_sid' => self::SID,
            'corpo_preview' => 'Olá {{1}}! {{2}}', 'variaveis' => $variaveis, 'status' => 'pending',
        ]);
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);
    }
}
