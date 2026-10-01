<?php

namespace App\Services\Enrichment\Steps;

use App\Models\Lead;
use App\Services\Enrichment\Cnpj\BrasilApiCnpjProvider;
use App\Services\Enrichment\Cnpj\CnpjProviderInterface;
use App\Services\Enrichment\Cnpj\MinhaReceitaCnpjProvider;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\EnrichmentStep;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Federal Revenue data for Brazilian leads whose site shows a CNPJ: size, status and who runs the
 * company (the managing partner, or the single partner). Registry phones are often the
 * accountant's, so they count little.
 */
class CnpjStep implements EnrichmentStep
{
    private const PROVIDERS = [
        'brasilapi'    => BrasilApiCnpjProvider::class,
        'minhareceita' => MinhaReceitaCnpjProvider::class,
    ];

    /** Sócio-Administrador, Administrador, Titular (single-owner companies). */
    private const MANAGING_QUALIFICATIONS = [49, 5, 65];

    private const NAME_PARTICLES = ['da', 'das', 'de', 'do', 'dos', 'e'];

    public function run(Lead $lead, EnrichmentContext $ctx): void
    {
        if ($ctx->region !== 'BR' || !$lead->cnpj) {
            return;
        }

        $data = $this->lookup($lead->cnpj);
        if ($data === null) {
            return;
        }

        $lead->fill([
            'razao_social'       => $data['razao_social'],
            'porte'              => $data['porte'],
            'data_abertura'      => $data['data_abertura'],
            'situacao_cadastral' => $data['situacao'],
            'dossie'             => array_merge($lead->dossie ?? [], array_filter([
                'cnae'          => $data['cnae'],
                'nome_fantasia' => $data['nome_fantasia'],
            ])),
        ]);

        if ($data['situacao'] !== null && $data['situacao'] !== 'ATIVA') {
            $ctx->discard('cnpj_' . Str::slug($data['situacao'], '_'));
            return;
        }

        if ($decisor = self::decisor($data)) {
            $lead->decisor_nome = $decisor['nome'];
            $lead->decisor_cargo = $decisor['cargo'];
        }

        // In MEI/ME the registry phone is usually the owner's own.
        $ownersPhone = in_array($data['porte'], ['MEI', 'ME'], true);
        foreach ($data['telefones'] as $phone) {
            $ctx->addPhone($phone, 'cnpj_receita', decisor: $ownersPhone, evidencia: "Receita Federal (CNPJ {$lead->cnpj})");
        }

        if ($data['email']) {
            $ctx->add('email', strtolower($data['email']), 'cnpj_receita', "Receita Federal (CNPJ {$lead->cnpj})");
        }
    }

    /** First provider that answers; answers are cached for 30 days, failures aren't. */
    private function lookup(string $cnpj): ?array
    {
        $key = "cnpj:{$cnpj}";
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        foreach ((array) config('services.cnpj.providers', ['brasilapi', 'minhareceita']) as $name) {
            $class = self::PROVIDERS[trim($name)] ?? null;
            if ($class === null) {
                continue;
            }

            try {
                /** @var CnpjProviderInterface $provider */
                $provider = app($class);
                $data = $provider->lookup($cnpj);
            } catch (\Throwable $e) {
                Log::info('CNPJ provider failed', ['provider' => $name, 'error' => $e->getMessage()]);
                continue;
            }

            if ($data !== null) {
                Cache::put($key, $data, now()->addDays(30));
                return $data;
            }
        }

        return null;
    }

    /** @return array{nome: string, cargo: string}|null */
    private static function decisor(array $data): ?array
    {
        $socios = $data['socios'];
        $managing = collect($socios)->first(fn (array $s) => in_array($s['qualificacao_codigo'], self::MANAGING_QUALIFICATIONS, true));
        $socio = $managing ?? (count($socios) === 1 ? $socios[0] : null);

        if ($socio !== null) {
            return ['nome' => self::personName($socio['nome']), 'cargo' => $socio['qualificacao'] ?: 'Sócio'];
        }

        // A MEI has no partners listed: the owner's name is the company name, with the CNPJ or CPF digits.
        if ($data['porte'] === 'MEI' && $data['razao_social']) {
            $name = trim(preg_replace('/^[\d.\/\s-]+|[\d.\/\s-]+$/', '', $data['razao_social']));
            if ($name !== '') {
                return ['nome' => self::personName($name), 'cargo' => 'Titular (MEI)'];
            }
        }

        return null;
    }

    /** "MARIA DA SILVA" → "Maria da Silva" */
    private static function personName(string $name): string
    {
        $words = explode(' ', Str::title(mb_strtolower(preg_replace('/\s+/', ' ', trim($name)))));

        foreach ($words as $i => $word) {
            if ($i > 0 && in_array(mb_strtolower($word), self::NAME_PARTICLES, true)) {
                $words[$i] = mb_strtolower($word);
            }
        }

        return implode(' ', $words);
    }
}
