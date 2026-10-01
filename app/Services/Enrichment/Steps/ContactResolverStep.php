<?php

namespace App\Services\Enrichment\Steps;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Services\Enrichment\ContactScorer;
use App\Services\Enrichment\ContactSignal;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\EnrichmentStep;

/**
 * Turns what the steps found into lead_contacts and picks the primary contact: the most reliable
 * WhatsApp or mobile. It becomes leads.telefone; the original number stays in lead_contacts.
 * Contacts found in earlier runs and not seen now are kept as they were.
 */
class ContactResolverStep implements EnrichmentStep
{
    /** Confidence of non-phone contacts, by where they were published. */
    private const OTHER_CONFIDENCE = ['website' => 80, 'cnpj_receita' => 60, 'web_research' => 50, 'manual' => 90];

    public function run(Lead $lead, EnrichmentContext $ctx): void
    {
        foreach (ContactScorer::phones($ctx, $lead->porte) as $phone) {
            $this->savePhone($lead, $ctx, $phone);
        }

        $this->saveOthers($lead, $ctx);
        $this->pickPrimary($lead);
    }

    private function savePhone(Lead $lead, EnrichmentContext $ctx, array $phone): void
    {
        $rows = $lead->contacts()->where('valor_e164', $phone['e164'])->orderBy('id')->get();

        // One row per number: a number first seen as "telefone" becomes "whatsapp" when evidence shows up.
        $rows->slice(1)->each->delete();
        $contact = $rows->first() ?? new LeadContact(['lead_id' => $lead->id, 'empresa_id' => $lead->empresa_id]);

        $contact->fill([
            'tipo'             => $phone['whatsapp'] ? 'whatsapp' : 'telefone',
            'valor'            => $phone['valor'],
            'valor_e164'       => $phone['e164'],
            'line_type'        => $phone['line_type'],
            'origem'           => $phone['origem'],
            'confianca'        => $phone['confianca'],
            'provavel_decisor' => $phone['decisor'] && ($phone['whatsapp'] || $phone['line_type'] === 'mobile'),
            'evidencia'        => $phone['evidencia'],
            'verificado_em'    => isset($ctx->verified[$phone['e164']]) ? now() : $contact->verificado_em,
        ])->save();
    }

    private function saveOthers(Lead $lead, EnrichmentContext $ctx): void
    {
        $seen = [];
        foreach ($ctx->signals as $signal) {
            /** @var ContactSignal $signal */
            $key = $signal->tipo . '|' . mb_strtolower($signal->valor);
            if ($signal->tipo === 'phone' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            LeadContact::updateOrCreate(
                ['lead_id' => $lead->id, 'tipo' => $signal->tipo, 'valor' => $signal->valor],
                [
                    'empresa_id' => $lead->empresa_id,
                    'origem'     => $signal->origem,
                    'confianca'  => self::OTHER_CONFIDENCE[$signal->origem] ?? 50,
                    'evidencia'  => $signal->evidencia ? mb_substr($signal->evidencia, 0, 255) : null,
                ],
            );
        }
    }

    private function pickPrimary(Lead $lead): void
    {
        $contacts = $lead->contacts()->get();

        $primary = $contacts
            ->filter(fn (LeadContact $c) => $c->tipo === 'whatsapp' || ($c->tipo === 'telefone' && $c->line_type === 'mobile'))
            // Highest confidence; on a tie, explicit WhatsApp, then the oldest.
            ->sortByDesc(fn (LeadContact $c) => [$c->confianca, $c->tipo === 'whatsapp' ? 1 : 0, -$c->id])
            ->first();

        $lead->contacts()->where('is_primary', true)->when($primary, fn ($q) => $q->whereKeyNot($primary->id))->update(['is_primary' => false]);
        $primary?->update(['is_primary' => true]);

        $phones = $contacts->filter(fn (LeadContact $c) => $c->isPhone());
        $lead->contact_confidence = $primary?->confianca ?? (int) ($phones->max('confianca') ?? 0);

        if ($primary && $primary->valor_e164 !== $lead->telefone_e164) {
            $lead->telefone = $primary->valor;
        }

        $lead->email = $lead->email ?: $contacts->firstWhere('tipo', 'email')?->valor;
        $lead->instagram = $lead->instagram ?: $contacts->firstWhere('tipo', 'instagram')?->valor;
    }
}
