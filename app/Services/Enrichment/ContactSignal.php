<?php

namespace App\Services\Enrichment;

/**
 * One sighting of a contact. The same number seen by several sources becomes one lead_contacts
 * row whose confidence grows with the evidence (ContactScorer).
 */
final class ContactSignal
{
    /**
     * @param string      $tipo     phone | email | instagram | facebook
     * @param string      $origem   google_places | website_wa_link | website_wa_text | website_tel | cnpj_receita | web_research | manual
     * @param bool        $whatsapp the source says explicitly that it is a WhatsApp
     * @param bool        $decisor  probably the decision maker's own contact
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $valor,
        public readonly string $origem,
        public readonly ?string $e164 = null,
        public readonly bool $whatsapp = false,
        public readonly bool $decisor = false,
        public readonly ?string $evidencia = null,
    ) {}
}
