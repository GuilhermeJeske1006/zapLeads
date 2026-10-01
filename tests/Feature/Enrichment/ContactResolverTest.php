<?php

namespace Tests\Feature\Enrichment;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\User;
use App\Services\Enrichment\EnrichmentContext;
use App\Services\Enrichment\Steps\ContactResolverStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_from_the_site_beats_the_google_landline(): void
    {
        $lead = $this->lead('(47) 3322-1100');

        $this->resolve($lead, function (EnrichmentContext $ctx) {
            $ctx->addPhone('(47) 3322-1100', 'google_places');
            $ctx->addPhone('+5547999998888', 'website_wa_link', whatsapp: true);
        });

        $primary = $lead->contacts()->where('is_primary', true)->sole();
        $this->assertSame(['whatsapp', '+5547999998888', 95], [$primary->tipo, $primary->valor_e164, $primary->confianca]);
        $this->assertSame('+5547999998888', $lead->telefone_e164);
        $this->assertSame(20, $lead->contacts()->where('valor_e164', '+554733221100')->sole()->confianca);
    }

    public function test_whatsapp_link_to_a_landline_is_still_the_primary(): void
    {
        $lead = $this->lead('(47) 3322-1100');

        $this->resolve($lead, function (EnrichmentContext $ctx) {
            $ctx->addPhone('(47) 3322-1100', 'google_places');
            $ctx->addPhone('+554733221100', 'website_wa_link', whatsapp: true);
            $ctx->addPhone('(47) 98888-1111', 'cnpj_receita');
        });

        $primary = $lead->contacts()->where('is_primary', true)->sole();
        $this->assertSame(['whatsapp', 'fixed', '+554733221100'], [$primary->tipo, $primary->line_type, $primary->valor_e164]);
        $this->assertSame(100, $primary->confianca); // 95 + 10 (Google and the site agree), capped
        $this->assertSame(1, $lead->contacts()->where('valor_e164', '+554733221100')->count());
    }

    public function test_same_mobile_in_two_sources_gains_confidence(): void
    {
        $lead = $this->lead('+55 47 99999-8888');

        $this->resolve($lead, function (EnrichmentContext $ctx) {
            $ctx->addPhone('+55 47 99999-8888', 'google_places');
            $ctx->addPhone('(47) 99999-8888', 'website_tel');
        });

        $this->assertSame(80, $lead->contacts()->sole()->confianca);
        $this->assertSame(80, $lead->contact_confidence);
    }

    public function test_landline_only_means_no_probable_whatsapp(): void
    {
        $lead = $this->lead('(47) 3322-1100');

        $this->resolve($lead, fn (EnrichmentContext $ctx) => $ctx->addPhone('(47) 3322-1100', 'google_places'));
        $lead->update(['enrichment_status' => 'done']);

        $this->assertSame(20, $lead->contact_confidence);
        $this->assertFalse($lead->contacts()->where('is_primary', true)->exists());
        $this->assertTrue($lead->lacksProbableWhatsApp());
        $this->assertSame('(47) 3322-1100', $lead->telefone);
    }

    public function test_a_number_found_again_with_whatsapp_evidence_is_upgraded_in_place(): void
    {
        $lead = $this->lead('(47) 99999-8888');
        $this->resolve($lead, fn (EnrichmentContext $ctx) => $ctx->addPhone('(47) 99999-8888', 'google_places'));
        $this->assertSame('telefone', $lead->contacts()->sole()->tipo);

        $this->resolve($lead, function (EnrichmentContext $ctx) {
            $ctx->addPhone('(47) 99999-8888', 'google_places');
            $ctx->addPhone('5547999998888', 'website_wa_link', whatsapp: true);
        });

        $contact = $lead->contacts()->sole();
        $this->assertSame(['whatsapp', 100], [$contact->tipo, $contact->confianca]);
    }

    public function test_contacts_not_seen_again_are_kept(): void
    {
        $lead = $this->lead('(47) 99999-8888');
        LeadContact::create([
            'lead_id' => $lead->id, 'empresa_id' => $lead->empresa_id, 'tipo' => 'whatsapp', 'valor' => '+5547988887777',
            'valor_e164' => '+5547988887777', 'line_type' => 'mobile', 'origem' => 'website_wa_link', 'confianca' => 95,
        ]);

        $this->resolve($lead, fn (EnrichmentContext $ctx) => $ctx->addPhone('(47) 99999-8888', 'google_places'));

        $this->assertSame('+5547988887777', $lead->contacts()->where('is_primary', true)->sole()->valor_e164);
        $this->assertSame(2, $lead->contacts()->count());
    }

    private function resolve(Lead $lead, callable $signals): void
    {
        $ctx = new EnrichmentContext($lead, 'BR');
        $signals($ctx);

        app(ContactResolverStep::class)->run($lead, $ctx);
        $lead->save();
    }

    private function lead(string $telefone): Lead
    {
        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);

        return Lead::create(['empresa_id' => $empresa->id, 'nome' => 'Studio Bella', 'telefone' => $telefone]);
    }
}
