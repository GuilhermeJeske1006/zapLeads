<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_stores_canonical_phone_using_empresa_country(): void
    {
        $br = $this->empresa('BR');
        $ar = $this->empresa('AR');

        $this->assertSame('+5547992801006', $this->lead($br, '(47) 99280-1006')->telefone_e164);
        $this->assertSame('+541123456789', $this->lead($ar, '011 2345-6789')->telefone_e164);
        $this->assertNull($this->lead($br, '9280-1006')->telefone_e164);
    }

    public function test_catalog_capture_updates_lead_typed_in_another_format(): void
    {
        Queue::fake();
        $empresa = $this->empresa('BR');
        $service = app(LeadService::class);

        $first = $service->capturar($empresa, ['nome' => 'Carla', 'telefone' => '47992801006']);
        $second = $service->capturar($empresa, ['nome' => 'Carla Souza', 'telefone' => '5547992801006']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Lead::count());
        $this->assertSame('Carla Souza', $second->nome);
    }

    private function lead(Empresa $empresa, string $telefone): Lead
    {
        return Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Lead', 'telefone' => $telefone]);
    }

    private function empresa(string $country): Empresa
    {
        return Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Empresa', 'country' => $country]);
    }
}
