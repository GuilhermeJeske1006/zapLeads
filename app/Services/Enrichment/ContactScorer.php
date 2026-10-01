<?php

namespace App\Services\Enrichment;

/**
 * How likely each number is to be a WhatsApp that reaches someone who decides. Explicit WhatsApp
 * evidence beats the line type: WhatsApp Business on a landline is common in Brazilian small
 * businesses.
 *
 *   wa.me link on the company's own site .......... 95
 *   "WhatsApp: (47) ..." in the site text ......... 90
 *   mobile from Google, the site or typed by hand . 70
 *   mobile from the CNPJ registry or web research . 55   (registry numbers are often the accountant's)
 *   other line types .............................. 30   (fixed_or_mobile, voip, unknown)
 *   landline without WhatsApp evidence ............ 20
 *   toll free ..................................... 10
 * plus 10 when two or more sources agree, plus 5 for MEI/ME mobiles (the owner's own phone, usually).
 */
final class ContactScorer
{
    private const WHATSAPP_BASE = ['website_wa_link' => 95, 'website_wa_text' => 90];
    private const MOBILE_BASE = ['google_places' => 70, 'website_tel' => 70, 'manual' => 70, 'cnpj_receita' => 55, 'web_research' => 55];

    /**
     * One entry per phone number found.
     *
     * @return array<string, array{e164: string, valor: string, line_type: string, whatsapp: bool, decisor: bool, origem: string, origens: list<string>, confianca: int, evidencia: ?string}>
     */
    public static function phones(EnrichmentContext $ctx, ?string $porte): array
    {
        $groups = [];
        foreach ($ctx->signals as $signal) {
            if ($signal->tipo === 'phone') {
                $groups[$signal->e164][] = $signal;
            }
        }

        $scored = [];
        foreach ($groups as $e164 => $signals) {
            $lineType = $ctx->lineType($e164);
            $best = null;
            $base = 0;

            foreach ($signals as $signal) {
                $score = self::base($signal, $lineType);
                if ($score > $base) {
                    [$base, $best] = [$score, $signal];
                }
            }

            $origens = array_values(array_unique(array_map(fn (ContactSignal $s) => $s->origem, $signals)));
            $whatsapp = (bool) array_filter($signals, fn (ContactSignal $s) => $s->whatsapp);
            $reachesOwner = $whatsapp || $lineType === 'mobile';

            $confianca = $base
                + (count($origens) >= 2 ? 10 : 0)
                + ($reachesOwner && in_array($porte, ['MEI', 'ME'], true) ? 5 : 0);

            $scored[$e164] = [
                'e164'      => $e164,
                'valor'     => $best->valor,
                'line_type' => $lineType,
                'whatsapp'  => $whatsapp,
                'decisor'   => (bool) array_filter($signals, fn (ContactSignal $s) => $s->decisor),
                'origem'    => $best->origem,
                'origens'   => $origens,
                'confianca' => min(100, $confianca),
                'evidencia' => self::evidence($signals),
            ];
        }

        return $scored;
    }

    /** The best confidence among the numbers found so far (0 when none). */
    public static function best(EnrichmentContext $ctx, ?string $porte): int
    {
        return max([0, ...array_column(self::phones($ctx, $porte), 'confianca')]);
    }

    private static function base(ContactSignal $signal, string $lineType): int
    {
        if ($signal->whatsapp) {
            return self::WHATSAPP_BASE[$signal->origem] ?? 55;
        }

        return match ($lineType) {
            'mobile'    => self::MOBILE_BASE[$signal->origem] ?? 55,
            'fixed'     => 20,
            'toll_free' => 10,
            default     => 30,
        };
    }

    /** @param list<ContactSignal> $signals */
    private static function evidence(array $signals): ?string
    {
        $parts = array_values(array_unique(array_filter(array_map(fn (ContactSignal $s) => $s->evidencia ?? $s->origem, $signals))));

        return $parts ? mb_substr(implode(' | ', $parts), 0, 255) : null;
    }
}
