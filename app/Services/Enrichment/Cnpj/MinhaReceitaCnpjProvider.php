<?php

namespace App\Services\Enrichment\Cnpj;

/** Minha Receita (https://minhareceita.org): same data and format as BrasilAPI, used as fallback. */
class MinhaReceitaCnpjProvider extends BrasilApiCnpjProvider
{
    protected function url(string $cnpj): string
    {
        return "https://minhareceita.org/{$cnpj}";
    }
}
