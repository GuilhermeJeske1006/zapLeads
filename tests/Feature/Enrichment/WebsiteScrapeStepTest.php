<?php

namespace Tests\Feature\Enrichment;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\User;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Support\Net\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteScrapeStepTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = <<<'HTML'
        <html><head><script>var tel = "tel:+5511900000000";</script></head><body>
        <nav><a href="/contato">Fale conosco</a> <a href="https://outro-site.com/contato">Parceiro</a></nav>
        <main><h1>Studio Bella</h1><p>Agende pelo nosso <a href="https://wa.me/5547999998888?text=Oi">WhatsApp</a>.</p></main>
        <footer>
            <a href="tel:(47) 3322-1100">(47) 3322-1100</a>
            <a href="mailto:contato@studiobella.com.br">contato@studiobella.com.br</a>
            <script>{"dsn":"https://8eb368c655b84e02@sentry.wixpress.com/1"}</script>
            <a href="https://www.instagram.com/studiobella.blu/">Instagram</a>
            <a href="https://www.instagram.com/p/Cx123/">post</a>
            Studio Bella LTDA — CNPJ 11.222.333/0001-81
        </footer></body></html>
        HTML;

    private const CONTACT = <<<'HTML'
        <html><body><main><p>Atendimento pelo WhatsApp: (47) 98888-7777</p></main></body></html>
        HTML;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_places.key' => '']);
        Http::preventStrayRequests();

        $this->app->instance(DnsResolver::class, new class extends DnsResolver {
            public function resolve(string $host): array
            {
                return match (true) {
                    filter_var($host, FILTER_VALIDATE_IP) !== false => [$host],
                    $host === 'intranet.studiobella.com.br'         => ['10.0.0.5'],
                    $host === 'rebind.studiobella.com.br'           => ['93.184.216.34', '127.0.0.1'],
                    default                                         => ['93.184.216.34'],
                };
            }
        });
    }

    public function test_whatsapp_link_on_the_site_becomes_the_primary_contact(): void
    {
        Http::fake([
            'studiobella.com.br/robots.txt' => Http::response('', 404),
            'studiobella.com.br/contato'    => Http::response(self::CONTACT, 200, ['Content-Type' => 'text/html']),
            'studiobella.com.br'            => Http::response(self::HOME, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'studiobella.com.br/*'          => Http::response('', 404),
            'brasilapi.com.br/*'            => Http::response('', 404),
            'minhareceita.org/*'            => Http::response('', 404),
        ]);
        $lead = $this->lead('https://studiobella.com.br', '+55 47 3322-1100');

        app(LeadEnrichmentService::class)->enrich($lead);

        $lead->refresh();
        $whatsapp = $lead->contacts()->where('valor_e164', '+5547999998888')->sole();
        $this->assertSame('whatsapp', $whatsapp->tipo);
        $this->assertSame(95, $whatsapp->confianca);
        $this->assertSame('website_wa_link', $whatsapp->origem);
        $this->assertStringContainsString('studiobella.com.br', $whatsapp->evidencia);
        $this->assertTrue($whatsapp->is_primary);

        $this->assertSame(90, $lead->contacts()->where('valor_e164', '+5547988887777')->sole()->confianca);
        $this->assertSame(['fixed', 30], [
            ($landline = $lead->contacts()->where('valor_e164', '+554733221100')->sole())->line_type,
            $landline->confianca, // Google and the site agree: 20 + 10
        ]);

        $this->assertSame('+5547999998888', $lead->telefone_e164);
        $this->assertSame(95, $lead->contact_confidence);
        $this->assertSame('11222333000181', $lead->cnpj);
        $this->assertSame('contato@studiobella.com.br', $lead->email);
        $this->assertSame('@studiobella.blu', $lead->instagram);
        $this->assertSame('done', $lead->enrichment_status);
        $this->assertFalse($lead->contacts()->where('valor_e164', '+5511900000000')->exists(), 'numbers inside scripts are not contacts');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'outro-site.com'));
    }

    public function test_whatsapp_link_or_social_profile_given_as_site_is_used_without_scraping(): void
    {
        Http::fake();

        app(LeadEnrichmentService::class)->enrich($whatsapp = $this->lead('https://api.whatsapp.com/send/?phone=5547992350334'));
        app(LeadEnrichmentService::class)->enrich($instagram = $this->lead('https://www.instagram.com/lotus_cursos?utm_source=qr&igsh=abc'));

        Http::assertNothingSent();
        $contact = $whatsapp->contacts()->sole();
        $this->assertSame(['whatsapp', '+5547992350334', 95, true], [$contact->tipo, $contact->valor_e164, $contact->confianca, $contact->is_primary]);
        $this->assertSame('@lotus_cursos', $instagram->fresh()->instagram);
    }

    public function test_shortener_leading_to_whatsapp_counts_as_whatsapp_link(): void
    {
        Http::fake([
            'wa.link/robots.txt' => Http::response("User-agent: *\nDisallow:\n"),
            'wa.link/fbblkr'     => Http::response('', 301, ['Location' => 'https://api.whatsapp.com/send?phone=5547997291652&text=Ol%C3%A1']),
        ]);

        app(LeadEnrichmentService::class)->enrich($lead = $this->lead('https://wa.link/fbblkr'));

        $this->assertSame('+5547997291652', $lead->fresh()->telefone_e164);
        $this->assertSame(95, $lead->fresh()->contact_confidence);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'whatsapp.com'));
    }

    public function test_redirect_to_a_page_robots_txt_disallows_is_not_followed(): void
    {
        Http::fake([
            'studiobella.com.br/robots.txt' => Http::response('', 404),
            'studiobella.com.br'            => Http::response('', 301, ['Location' => 'https://loja.parceiro.com.br/studio']),
            'loja.parceiro.com.br/robots.txt' => Http::response("User-agent: *\nDisallow: /studio\n"),
        ]);

        app(LeadEnrichmentService::class)->enrich($this->lead('https://studiobella.com.br'));

        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://loja.parceiro.com.br/studio');
    }

    public function test_internal_addresses_are_never_requested(): void
    {
        Http::fake();

        foreach ([
            'http://127.0.0.1/admin',
            'http://169.254.169.254/latest/meta-data/',
            'http://[::1]/',
            'https://intranet.studiobella.com.br',
            'https://rebind.studiobella.com.br',
            'file:///etc/passwd',
            'https://studiobella.com.br:22/',
        ] as $site) {
            app(LeadEnrichmentService::class)->enrich($this->lead($site));
        }

        Http::assertNothingSent();
    }

    public function test_redirect_to_an_internal_address_is_not_followed(): void
    {
        Http::fake([
            'studiobella.com.br/robots.txt' => Http::response('', 404),
            'studiobella.com.br'            => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        app(LeadEnrichmentService::class)->enrich($this->lead('https://studiobella.com.br'));

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '169.254.169.254'));
    }

    public function test_robots_txt_is_respected(): void
    {
        Http::fake([
            'studiobella.com.br/robots.txt' => Http::response("User-agent: *\nDisallow: /\n"),
            '*'                             => Http::response(self::HOME),
        ]);

        app(LeadEnrichmentService::class)->enrich($lead = $this->lead('https://studiobella.com.br'));

        Http::assertSentCount(1);
        $this->assertSame(0, $lead->contacts()->where('origem', 'website_wa_link')->count());
    }

    private function lead(string $website, string $telefone = ''): Lead
    {
        $empresa = Empresa::create(['user_id' => User::factory()->create()->id, 'nome' => 'Agenda Fácil']);

        return Lead::create([
            'empresa_id'      => $empresa->id,
            'nome'            => 'Studio Bella',
            'telefone'        => $telefone,
            'website'         => $website,
            'source'          => 'internet',
            'external_source' => 'google_places',
            'external_id'     => 'ChIJstudiobella',
        ]);
    }
}
