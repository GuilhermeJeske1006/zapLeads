<?php

namespace App\Services\Enrichment;

use App\Models\Lead;
use App\Support\Phone;

/** What the steps found so far for one lead; ContactResolverStep turns it into lead_contacts. */
final class EnrichmentContext
{
    /** @var list<ContactSignal> */
    public array $signals = [];

    /** Better line types than the local heuristic (Twilio Lookup), by E.164. @var array<string, string> */
    public array $lineTypes = [];

    /** @var array<string, bool> E.164 numbers checked with Twilio Lookup */
    public array $verified = [];

    public ?string $discarded = null;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $region,
    ) {}

    /** Adds a phone in whatever format the source wrote it; unreadable numbers are ignored. */
    public function addPhone(string $raw, string $origem, bool $whatsapp = false, bool $decisor = false, ?string $evidencia = null): void
    {
        $e164 = Phone::canonical($raw, $this->region);

        if ($e164 !== null) {
            $this->signals[] = new ContactSignal('phone', trim($raw), $origem, $e164, $whatsapp, $decisor, $evidencia);
        }
    }

    public function add(string $tipo, string $valor, string $origem, ?string $evidencia = null): void
    {
        $valor = trim($valor);

        if ($valor !== '') {
            $this->signals[] = new ContactSignal($tipo, $valor, $origem, evidencia: $evidencia);
        }
    }

    /** Stops the pipeline: the business is closed or inactive. */
    public function discard(string $reason): void
    {
        $this->discarded = $reason;
    }

    public function lineType(string $e164): string
    {
        return $this->lineTypes[$e164] ?? Phone::lineType($e164);
    }

    /** @return list<string> distinct phone numbers found so far */
    public function phones(): array
    {
        return array_values(array_unique(array_map(
            fn (ContactSignal $s) => $s->e164,
            array_filter($this->signals, fn (ContactSignal $s) => $s->tipo === 'phone'),
        )));
    }
}
