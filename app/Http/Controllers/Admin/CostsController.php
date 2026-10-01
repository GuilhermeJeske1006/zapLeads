<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiUsage;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * What the paid APIs cost the platform (list prices, see config/costs.php), and the cost per
 * qualified lead: what finding leads cost (searches, enrichment, rescoring) over the leads found
 * that fit the ideal customer and probably have WhatsApp.
 */
class CostsController extends Controller
{
    public const PERIODS = [7, 30, 90];

    /** Spend that goes into finding leads; outreach and conversations come after. */
    public const PROSPECTING_ORIGINS = ['busca', 'enriquecimento', 'score'];

    public function index(Request $request): View
    {
        $dias = in_array((int) $request->query('dias'), self::PERIODS, true) ? (int) $request->query('dias') : 30;
        $since = now()->subDays($dias);
        $usages = fn () => ApiUsage::where('created_at', '>=', $since);

        $totais = ApiUsage::summarize($usages());

        $porOrigem = $usages()->groupBy('origem')->orderByDesc('usd')
            ->get(['origem', DB::raw('sum(custo_usd) as usd')])
            ->map(fn ($row) => ['origem' => $row->origem, 'usd' => (float) $row->usd]);

        // Prompt caching: how much of the input each model read from the cache.
        $cache = $usages()->where('servico', 'anthropic')->groupBy('sku')
            ->get(['sku', DB::raw('count(*) as chamadas'), DB::raw('sum(input_tokens) as input'), DB::raw('sum(cache_read_tokens) as lido'), DB::raw('sum(cache_write_tokens) as gravado')])
            ->map(function ($row) {
                $entrada = (int) $row->input + (int) $row->lido + (int) $row->gravado;

                return [
                    'modelo'   => $row->sku,
                    'chamadas' => (int) $row->chamadas,
                    'entrada'  => $entrada,
                    'lido'     => (int) $row->lido,
                    'gravado'  => (int) $row->gravado,
                    'taxa'     => $entrada > 0 ? (int) $row->lido / $entrada : null,
                ];
            });

        return view('admin.costs.index', [
            'dias'      => $dias,
            'totais'    => $totais,
            'porOrigem' => $porOrigem,
            'cache'     => $cache,
            'empresas'  => $this->byEmpresa($since),
            'buscas'    => $this->searches($since),
        ]);
    }

    /** @return list<array{empresa: string, total: float, prospeccao: float, buscas: int, qualificados: int, por_qualificado: ?float}> */
    private function byEmpresa($since): array
    {
        $custos = ApiUsage::where('created_at', '>=', $since)->whereNotNull('empresa_id')->groupBy('empresa_id')
            ->get([
                'empresa_id',
                DB::raw('sum(custo_usd) as total'),
                DB::raw("sum(case when origem in ('" . implode("','", self::PROSPECTING_ORIGINS) . "') then custo_usd else 0 end) as prospeccao"),
            ])
            ->keyBy('empresa_id');

        $buscas = ProspectingSearch::where('created_at', '>=', $since)->groupBy('empresa_id')
            ->selectRaw('empresa_id, count(*) as total')
            ->pluck('total', 'empresa_id');

        $qualificados = [];
        Lead::where('source', 'internet')->where('created_at', '>=', $since)
            ->select(['id', 'empresa_id', 'enrichment_status', 'contact_confidence', 'telefone_e164', 'ai_insights'])
            ->lazyById(500)
            ->each(function (Lead $lead) use (&$qualificados) {
                if ($lead->isQualified()) {
                    $qualificados[$lead->empresa_id] = ($qualificados[$lead->empresa_id] ?? 0) + 1;
                }
            });

        $ids = collect($custos->keys())->merge($buscas->keys())->unique();
        $nomes = Empresa::whereKey($ids)->pluck('nome', 'id');

        return $ids
            ->map(function ($id) use ($custos, $buscas, $qualificados, $nomes) {
                $prospeccao = (float) ($custos[$id]->prospeccao ?? 0);
                $q = $qualificados[$id] ?? 0;

                return [
                    'empresa'         => $nomes[$id] ?? "#{$id}",
                    'total'           => (float) ($custos[$id]->total ?? 0),
                    'prospeccao'      => $prospeccao,
                    'buscas'          => (int) ($buscas[$id] ?? 0),
                    'qualificados'    => $q,
                    'por_qualificado' => $q > 0 ? $prospeccao / $q : null,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** Recent searches with what each cost: the summary saved when it finished, or the running sum. */
    private function searches($since): array
    {
        $searches = ProspectingSearch::where('created_at', '>=', $since)->with('empresa:id,nome')->latest('id')->limit(50)->get();

        $live = ApiUsage::whereIn('prospecting_search_id', $searches->modelKeys())->groupBy('prospecting_search_id')
            ->selectRaw('prospecting_search_id, sum(custo_usd) as usd')
            ->pluck('usd', 'prospecting_search_id');

        return $searches->map(function (ProspectingSearch $search) use ($live) {
            $final = $search->stage === 'done' || $search->status === 'failed';
            $custo = $final && isset($search->custos['total_usd']) ? (float) $search->custos['total_usd'] : (float) ($live[$search->id] ?? 0);
            $qualificados = $search->qualifiedLeadsCount();

            $porServico = collect($search->custos['itens'] ?? [])->groupBy('servico')->map->sum('usd')->all();

            return [
                'id'              => $search->id,
                'empresa'         => $search->empresa?->nome,
                'quando'          => $search->created_at,
                'cliente'         => $search->tipo_cliente,
                'status'          => $search->status,
                'resultados'      => (int) $search->results_count,
                'qualificados'    => $qualificados,
                'custo'           => $custo,
                'parcial'         => !$final,
                'por_servico'     => $porServico,
                'por_qualificado' => $qualificados > 0 ? $custo / $qualificados : null,
            ];
        })->all();
    }
}
