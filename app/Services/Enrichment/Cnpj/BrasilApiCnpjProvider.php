<?php

namespace App\Services\Enrichment\Cnpj;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** BrasilAPI (https://brasilapi.com.br), which serves the Federal Revenue's open CNPJ data. */
class BrasilApiCnpjProvider implements CnpjProviderInterface
{
    /** The public APIs throttle; keep at least this long between calls. */
    private const MIN_INTERVAL_MS = 100;

    private static float $lastCallAt = 0;

    protected function url(string $cnpj): string
    {
        return "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
    }

    public function lookup(string $cnpj): ?array
    {
        $this->throttle();

        $response = Http::timeout(10)->acceptJson()->get($this->url($cnpj));

        if ($response->notFound()) {
            return null;
        }
        if ($response->failed()) {
            throw new RuntimeException(static::class . " returned {$response->status()}");
        }

        return self::parse($response->json() ?? []);
    }

    /** Minha Receita and BrasilAPI share this format. */
    protected static function parse(array $data): array
    {
        $porteText = mb_strtoupper((string) ($data['porte'] ?? ''));

        return [
            'razao_social'  => $data['razao_social'] ?? null,
            'nome_fantasia' => ($data['nome_fantasia'] ?? '') ?: null,
            'porte'         => match (true) {
                !empty($data['opcao_pelo_mei'])          => 'MEI',
                str_contains($porteText, 'MICRO')         => 'ME',
                str_contains($porteText, 'PEQUENO PORTE') => 'EPP',
                str_contains($porteText, 'DEMAIS')        => 'DEMAIS',
                default                                   => null,
            },
            'data_abertura' => $data['data_inicio_atividade'] ?? null,
            'situacao'      => isset($data['descricao_situacao_cadastral']) ? mb_strtoupper($data['descricao_situacao_cadastral']) : null,
            'cnae'          => isset($data['cnae_fiscal']) ? trim($data['cnae_fiscal'] . ' - ' . ($data['cnae_fiscal_descricao'] ?? ''), ' -') : null,
            'telefones'     => array_values(array_filter([
                preg_replace('/\D/', '', (string) ($data['ddd_telefone_1'] ?? '')),
                preg_replace('/\D/', '', (string) ($data['ddd_telefone_2'] ?? '')),
            ])),
            'email'         => ($data['email'] ?? '') ?: null,
            'socios'        => array_values(array_map(fn (array $socio) => [
                'nome'                => (string) ($socio['nome_socio'] ?? ''),
                'qualificacao_codigo' => (int) ($socio['codigo_qualificacao_socio'] ?? 0),
                'qualificacao'        => (string) ($socio['qualificacao_socio'] ?? ''),
            ], array_filter($data['qsa'] ?? [], fn ($socio) => !empty($socio['nome_socio'])))),
        ];
    }

    private function throttle(): void
    {
        $elapsedMs = (microtime(true) - self::$lastCallAt) * 1000;
        if ($elapsedMs < self::MIN_INTERVAL_MS) {
            usleep((int) ((self::MIN_INTERVAL_MS - $elapsedMs) * 1000));
        }

        self::$lastCallAt = microtime(true);
    }
}
