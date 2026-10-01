<?php

namespace App\Services\Costs;

use App\Models\ApiUsage;
use Illuminate\Support\Facades\Log;

/**
 * Records every paid call (Claude, Google Places, Twilio Lookup) priced at list price, with whose
 * work it was: the entry points wrap their work in within() and the calls below them inherit it.
 * Scoped: each request and each queued job starts with an empty context.
 */
class UsageMeter
{
    /** @var array{empresa_id?: int, prospecting_search_id?: int, lead_id?: int, origem?: string} */
    private array $context = [];

    /**
     * Runs $callback with $context added to the current one (null values are left out).
     *
     * @template T
     * @param  array{empresa_id?: ?int, prospecting_search_id?: ?int, lead_id?: ?int, origem?: ?string}  $context
     * @param  callable(): T  $callback
     * @return T
     */
    public function within(array $context, callable $callback): mixed
    {
        $previous = $this->context;
        $this->context = array_merge($previous, array_filter($context, fn ($value) => $value !== null));

        try {
            return $callback();
        } finally {
            $this->context = $previous;
        }
    }

    /** One Messages API response, thinking included in output_tokens. */
    public function claude(string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0, int $webSearches = 0): void
    {
        $this->record([
            'servico'            => 'anthropic',
            'sku'                => $model,
            'input_tokens'       => $input,
            'output_tokens'      => $output,
            'cache_read_tokens'  => $cacheRead,
            'cache_write_tokens' => $cacheWrite,
            'web_searches'       => $webSearches,
            'custo_usd'          => self::claudeCost($model, $input, $output, $cacheRead, $cacheWrite, $webSearches),
        ]);
    }

    /** A billed Places request (a page of Text Search, a Place Details). */
    public function places(string $sku): void
    {
        $this->record([
            'servico'   => 'google_places',
            'sku'       => $sku,
            'custo_usd' => (float) config("costs.google_places.{$sku}", 0),
        ]);
    }

    /** A billed Twilio Lookup (not a cache hit). */
    public function lookup(string $sku = 'line_type_intelligence'): void
    {
        $this->record([
            'servico'   => 'twilio_lookup',
            'sku'       => $sku,
            'custo_usd' => (float) config("costs.twilio_lookup.{$sku}", 0),
        ]);
    }

    public static function claudeCost(string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0, int $webSearches = 0): float
    {
        $prices = self::modelPrices($model);
        $tokens = $input * $prices['input'] + $output * $prices['output']
            + $cacheRead * $prices['cache_read'] + $cacheWrite * $prices['cache_write'];

        return $tokens / 1_000_000 + $webSearches * (float) config('costs.anthropic_web_search', 0);
    }

    /** @return array{input: float, output: float, cache_read: float, cache_write: float} zeros for an unknown model */
    private static function modelPrices(string $model): array
    {
        $match = null;
        foreach (array_keys(config('costs.anthropic', [])) as $prefix) {
            if (str_starts_with($model, $prefix) && strlen($prefix) > strlen((string) $match)) {
                $match = $prefix;
            }
        }

        if ($match === null) {
            Log::warning('UsageMeter: no price for model, recorded at 0', ['model' => $model]);
        }

        return array_merge(['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0], $match ? config("costs.anthropic.{$match}") : []);
    }

    /** Metering must never fail the work it measures. */
    private function record(array $row): void
    {
        try {
            ApiUsage::create($this->context + $row);
        } catch (\Throwable $e) {
            Log::warning('UsageMeter: could not record usage', ['servico' => $row['servico'], 'error' => $e->getMessage()]);
        }
    }
}
