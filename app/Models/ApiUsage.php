<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One paid API call, priced at list price when it happened (config/costs.php). Written by UsageMeter. */
class ApiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'prospecting_search_id',
        'lead_id',
        'origem',
        'servico',
        'sku',
        'quantidade',
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
        'web_searches',
        'custo_usd',
    ];

    protected $casts = [
        'quantidade'         => 'integer',
        'input_tokens'       => 'integer',
        'output_tokens'      => 'integer',
        'cache_read_tokens'  => 'integer',
        'cache_write_tokens' => 'integer',
        'web_searches'       => 'integer',
        'custo_usd'          => 'float',
    ];

    /**
     * Totals of $query by service and SKU, as stored in prospecting_searches.custos.
     *
     * @param  Builder<ApiUsage>  $query
     * @return array{total_usd: float, itens: list<array{servico: string, sku: string, chamadas: int, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, web_searches: int, usd: float}>}
     */
    public static function summarize(Builder $query): array
    {
        $itens = $query->toBase()
            ->selectRaw('servico, sku, sum(quantidade) as chamadas, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens')
            ->selectRaw('sum(cache_read_tokens) as cache_read_tokens, sum(cache_write_tokens) as cache_write_tokens, sum(web_searches) as web_searches, sum(custo_usd) as usd')
            ->groupBy('servico', 'sku')
            ->orderByDesc('usd')
            ->get()
            ->map(fn ($row) => [
                'servico'            => $row->servico,
                'sku'                => $row->sku,
                'chamadas'           => (int) $row->chamadas,
                'input_tokens'       => (int) $row->input_tokens,
                'output_tokens'      => (int) $row->output_tokens,
                'cache_read_tokens'  => (int) $row->cache_read_tokens,
                'cache_write_tokens' => (int) $row->cache_write_tokens,
                'web_searches'       => (int) $row->web_searches,
                'usd'                => round((float) $row->usd, 6),
            ])
            ->all();

        return ['total_usd' => round(array_sum(array_column($itens, 'usd')), 6), 'itens' => $itens];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function prospectingSearch(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearch::class);
    }
}
