<?php

namespace Tests\Feature;

use App\Jobs\FindInternetLeadsJob;
use App\Livewire\Leads\InternetProspector;
use App\Models\Empresa;
use App\Services\Geo\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class InternetProspectorLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_selected_search_location_when_local_busca_is_filled(): void
    {
        Queue::fake();

        $user = \App\Models\User::factory()->create();
        $empresa = Empresa::create([
            'user_id' => $user->id,
            'nome' => 'Minha Empresa',
            'endereco' => 'Rua Teste, 123',
            'cidade' => 'São Paulo/SP',
            'descricao_empresa' => 'Somos uma empresa de testes com mais de dez anos de mercado.',
            'tipo_cliente_alvo' => 'Clínicas odontológicas',
            'raio_atendimento' => 7,
        ]);

        $geo = Mockery::mock(GeocodingService::class);
        $geo->shouldReceive('geocode')
            ->once()
            ->with('Av. Paulista 1000 São Paulo/SP')
            ->andReturn([
                'lat' => -23.561684,
                'lng' => -46.655981,
                'display_name' => 'Av. Paulista, 1000 - Bela Vista, São Paulo - SP, Brasil',
                'city' => 'São Paulo',
                'provider' => 'mapbox',
            ]);
        $this->app->instance(GeocodingService::class, $geo);

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->set('localBusca', 'Av. Paulista 1000')
            ->call('buscar');

        Queue::assertPushed(FindInternetLeadsJob::class, function (FindInternetLeadsJob $job) use ($empresa) {
            return $job->empresaId === $empresa->id
                && $job->customLat === -23.561684
                && $job->customLng === -46.655981
                && $job->customLocationLabel === 'Av. Paulista, 1000 - Bela Vista, São Paulo - SP, Brasil';
        });
    }

    public function test_map_click_event_sets_custom_location_and_uses_it_in_search(): void
    {
        Queue::fake();

        $user = \App\Models\User::factory()->create();
        $empresa = Empresa::create([
            'user_id' => $user->id,
            'nome' => 'Minha Empresa',
            'endereco' => 'Rua Teste, 123',
            'cidade' => 'Brusque/SC',
            'descricao_empresa' => 'Somos uma empresa de testes com mais de dez anos de mercado.',
            'tipo_cliente_alvo' => 'Clínicas odontológicas',
            'raio_atendimento' => 7,
        ]);

        $geo = Mockery::mock(GeocodingService::class);
        $geo->shouldNotReceive('geocode');
        $this->app->instance(GeocodingService::class, $geo);

        Livewire::test(InternetProspector::class, ['empresa' => $empresa])
            ->dispatch('internet-prospector:set-custom-location', lat: -26.304408, lng: -48.848111, label: 'Joinville - SC, Brasil')
            ->call('buscar');

        Queue::assertPushed(FindInternetLeadsJob::class, function (FindInternetLeadsJob $job) use ($empresa) {
            return $job->empresaId === $empresa->id
                && $job->customLat === -26.304408
                && $job->customLng === -48.848111
                && $job->customLocationLabel === 'Joinville - SC, Brasil';
        });
    }
}
