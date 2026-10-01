<?php

namespace App\Services\Enrichment\Cnpj;

interface CnpjProviderInterface
{
    /**
     * The company as the Federal Revenue registers it, or null when the provider doesn't know it.
     *
     * @return array{
     *   razao_social: ?string, nome_fantasia: ?string, porte: ?string, data_abertura: ?string,
     *   situacao: ?string, cnae: ?string, telefones: list<string>, email: ?string,
     *   socios: list<array{nome: string, qualificacao_codigo: int, qualificacao: string}>,
     * }|null
     *
     * @throws \Throwable when the provider is unavailable, so the next one is tried
     */
    public function lookup(string $cnpj): ?array;
}
