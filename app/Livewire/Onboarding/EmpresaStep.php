<?php

namespace App\Livewire\Onboarding;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Http;

#[Layout('components.onboarding-layout', ['step' => 2])]
class EmpresaStep extends Component
{
    public string $nome = '';
    public string $whatsapp = '';
    public string $cidade = '';
    public string $endereco = '';
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $locationStatus = null;
    public bool $shouldRequestLocation = false;

    public function mount(): void
    {
        $empresa = auth()->user()->empresa;
        if ($empresa) {
            $this->nome = $empresa->nome ?? '';
            $this->whatsapp = $empresa->whatsapp ?? '';
            $this->cidade = $empresa->cidade ?? '';
            $this->endereco = $empresa->endereco ?? '';
            $this->latitude = $empresa->latitude !== null ? (float) $empresa->latitude : null;
            $this->longitude = $empresa->longitude !== null ? (float) $empresa->longitude : null;
        }

        if (
            is_null($this->latitude)
            && is_null($this->longitude)
            && !$this->cidade
            && !$this->endereco
            && !session()->has('onboarding_location_autoprompted')
        ) {
            session()->put('onboarding_location_autoprompted', true);
            $this->shouldRequestLocation = true;
            $this->dispatch('onboarding-request-location');
        }
    }

    public function pedirLocalizacao(): void
    {
        $this->locationStatus = 'Capturando sua localização...';
        $this->dispatch('onboarding-request-location');
    }

    public function falhaLocalizacao(?string $mensagem = null): void
    {
        $this->locationStatus = $mensagem
            ? "Não foi possível capturar a localização: {$mensagem}"
            : 'Não foi possível capturar a localização.';
    }

    public function receberLocalizacao(float $latitude, float $longitude): void
    {
        validator(
            ['latitude' => $latitude, 'longitude' => $longitude],
            [
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ],
            [],
            ['latitude' => 'latitude', 'longitude' => 'longitude']
        )->validate();

        $this->latitude = round($latitude, 7);
        $this->longitude = round($longitude, 7);

        $this->locationStatus = 'Localização capturada. Preenchendo endereço...';

        try {
            $data = $this->reverseGeocode($this->latitude, $this->longitude);
            if ($data) {
                if (!$this->cidade && !empty($data['cidade'])) {
                    $this->cidade = $data['cidade'];
                }
                if (!$this->endereco && !empty($data['endereco'])) {
                    $this->endereco = $data['endereco'];
                }
            }
        } catch (\Throwable) {
            // Best-effort: keep coordinates even if reverse geocoding fails.
        }

        $this->locationStatus = $this->endereco || $this->cidade
            ? 'Endereço preenchido automaticamente.'
            : 'Localização capturada (preencha o endereço se necessário).';
    }

    public function salvar(): void
    {
        $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = auth()->user();
        $empresa = $user->empresa()->firstOrCreate([]);
        $empresa->update([
            'nome' => $this->nome,
            'whatsapp' => $this->whatsapp,
            'cidade' => $this->cidade,
            'endereco' => $this->endereco,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        $this->redirect(route('onboarding.pagamento'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.empresa-step');
    }

    private function reverseGeocode(float $lat, float $lon): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/reverse';

        $response = Http::timeout(6)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => sprintf('%s (%s)', config('app.name', 'Laravel'), config('app.url', '')),
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.5',
            ])
            ->get($url, [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lon,
                'addressdetails' => 1,
                'zoom' => 18,
            ]);

        if (!$response->ok()) {
            return null;
        }

        $json = $response->json();
        $addr = $json['address'] ?? [];

        $city = $addr['city']
            ?? $addr['town']
            ?? $addr['village']
            ?? $addr['municipality']
            ?? null;

        $state = $addr['state'] ?? null;
        $cidade = $city ? trim($city . ($state ? ' - ' . $state : '')) : null;

        $road = $addr['road'] ?? $addr['pedestrian'] ?? $addr['residential'] ?? null;
        $number = $addr['house_number'] ?? null;
        $suburb = $addr['suburb'] ?? $addr['neighbourhood'] ?? null;

        $enderecoParts = array_values(array_filter([
            $road ? trim($road . ($number ? ', ' . $number : '')) : null,
            $suburb,
        ]));

        $endereco = $enderecoParts ? implode(' - ', $enderecoParts) : ($json['display_name'] ?? null);
        if ($endereco) {
            $endereco = mb_substr($endereco, 0, 255);
        }

        if ($cidade) {
            $cidade = mb_substr($cidade, 0, 100);
        }

        return [
            'cidade' => $cidade,
            'endereco' => $endereco,
        ];
    }
}
