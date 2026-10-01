<?php

namespace App\Services\Metrics;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachAttempt;
use Illuminate\Support\Facades\DB;

/**
 * The prospecting funnel of an empresa: the leads its searches found (in a period, or in one search)
 * and how far each got. A stage counts every lead that reached it, even if it moved on or was
 * dropped later: a lead discarded after replying still counts as approached and replied.
 */
class FunnelReport
{
    public const STAGES = ['buscados', 'whatsapp', 'abordados', 'responderam', 'reuniao', 'convertidos'];

    /** Lead statuses at or past each stage ("descartado" says nothing about how far the lead got). */
    private const APPROACHED = ['abordado', 'respondeu', 'reuniao', 'proposta', 'convertido'];
    private const REPLIED = ['respondeu', 'reuniao', 'proposta', 'convertido'];
    private const MEETING = ['reuniao', 'proposta', 'convertido'];

    /**
     * Leads per stage. With $searchId the search is the cohort; otherwise the leads found in the
     * last $days days (all of them when null).
     *
     * @return array<string, int> by STAGES
     */
    public function funnel(Empresa $empresa, ?int $days = null, ?int $searchId = null): array
    {
        $counts = array_fill_keys(self::STAGES, 0);

        $empresa->leads()
            ->where('source', 'internet')
            ->when($searchId, fn ($q) => $q->where('prospecting_search_id', $searchId))
            ->when(!$searchId && $days, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->select(['id', 'status', 'enrichment_status', 'contact_confidence', 'telefone_e164'])
            ->withExists([
                'outreachAttempts as foi_abordado',
                'outreachAttempts as respondeu_abordagem' => fn ($q) => $q->whereNotNull('responded_at'),
            ])
            ->lazyById(500)
            ->each(function (Lead $lead) use (&$counts) {
                $counts['buscados']++;
                $counts['whatsapp'] += (int) $lead->hasProbableWhatsApp();
                $counts['abordados'] += (int) ($lead->foi_abordado || in_array($lead->status, self::APPROACHED, true));
                $counts['responderam'] += (int) ($lead->respondeu_abordagem || in_array($lead->status, self::REPLIED, true));
                $counts['reuniao'] += (int) in_array($lead->status, self::MEETING, true);
                $counts['convertidos'] += (int) ($lead->status === 'convertido');
            });

        return $counts;
    }

    /**
     * Replies to the empresa's API messages by WhatsApp template (any cadence step).
     *
     * @return list<array{template: string, envios: int, respostas: int, taxa: ?float}>
     */
    public function byTemplate(int $empresaId): array
    {
        return OutreachAttempt::query()
            ->join('whatsapp_templates', 'whatsapp_templates.id', '=', 'outreach_attempts.whatsapp_template_id')
            ->where('outreach_attempts.empresa_id', $empresaId)
            ->groupBy('whatsapp_templates.id', 'whatsapp_templates.nome')
            ->orderByDesc('envios')
            ->get([
                'whatsapp_templates.nome as template',
                DB::raw('count(*) as envios'),
                DB::raw('count(outreach_attempts.responded_at) as respostas'),
            ])
            ->map(fn ($row) => self::rate($row->template, (int) $row->envios, (int) $row->respostas, 'template'))
            ->all();
    }

    /**
     * Replies to first messages sent through the API and from the user's own WhatsApp.
     *
     * @return list<array{canal: string, envios: int, respostas: int, taxa: ?float}>
     */
    public function byChannel(int $empresaId): array
    {
        return OutreachAttempt::where('empresa_id', $empresaId)
            ->where('etapa', 0)
            ->groupBy('canal')
            ->orderBy('canal')
            ->get(['canal', DB::raw('count(*) as envios'), DB::raw('count(responded_at) as respostas')])
            ->map(fn ($row) => self::rate($row->canal, (int) $row->envios, (int) $row->respostas, 'canal'))
            ->all();
    }

    private static function rate(string $label, int $envios, int $respostas, string $key): array
    {
        return [$key => $label, 'envios' => $envios, 'respostas' => $respostas, 'taxa' => $envios > 0 ? (float) $respostas / $envios : null];
    }
}
