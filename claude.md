# ZapLeads — CLAUDE.md

## Project

SaaS platform: digital product catalogs + WhatsApp automation + AI lead scoring.
Multi-tenant (one `Empresa` per user). Onboarding wizard before app access.

## Stack

- Laravel 12, Livewire 4, Alpine.js, Tailwind v4
- SQLite (dev), Pusher (WebSockets)
- Twilio (WhatsApp), Anthropic Claude API (AI/leads), Stripe Cashier (billing)
- Mapbox (geolocation + prospecting map)

## Dev Setup

```bash
php artisan serve       # http://localhost:8000
npm run dev             # Vite HMR
php artisan queue:work --queue=default,enrichment  # jobs; "enrichment" runs lead enrichment
php artisan schedule:work  # hourly whatsapp:check-channels (channel quality, auto-pause)
```

Migrate + seed: `php artisan migrate --seed`
Master admin: `php artisan make:master-admin`

## Auth

- Laravel Breeze (Blade)
- Dev login: `admin@catalogo.test` / `password`
- Onboarding middleware (`EnsureOnboardingComplete`) blocks app until empresa + plano set

## Key .env Vars

```
ANTHROPIC_API_KEY=
MAPBOX_TOKEN=
GOOGLE_PLACES_API_KEY=           # prospecting + Place Details
ENRICHMENT_WEB_RESEARCH=false    # web search on Claude, US$ 10 / 1,000 searches
TWILIO_LOOKUP_ENABLED=false      # line type lookup, paid per query
TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_WEBHOOK_VALIDATE=true   # "false" only outside production
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_ID=price_...
CASHIER_CURRENCY=brl
PLAN_TRIAL_DAYS=14
PLAN_PRICE_BRL=9700
```

Pusher: set `BROADCAST_CONNECTION=pusher` + `PUSHER_*` vars for real-time chat.

## Routes (web.php)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/prospeccao` | Prospecting wizard: perfil → resultados → revisar → acompanhar (`?passo=`, `?busca=`) |
| GET | `/leads` | Lead base with filters (opens the dossier panel) |
| GET | `/loja/{slug}` | Public catalog — captures lead + geo |
| POST | `/loja/{slug}/lead` | Lead capture endpoint |
| POST | `/webhook/twilio` | Twilio inbound (no CSRF, no auth; Twilio signature required) |
| POST | `/webhook/stripe` | Cashier webhook (no CSRF) |
| GET | `/lang/{locale}` | Language switcher (pt_BR / es) |
| * | `/onboarding/*` | Wizard steps (guest + auth variants) |
| * | `/admin/*` | Master admin only (`EnsureMasterAdmin`); `/admin/custos` = API costs, cost per qualified lead, prompt cache |

## App Structure

```
app/
  Http/
    Controllers/
      Admin/                   # master admin panel
      Auth/                    # Breeze auth
      BillingController.php    # Stripe Cashier
      PublicCatalogoController.php
      WebhookController.php    # Twilio + Stripe
      OnboardingController.php
  Livewire/
    Chat/ChatPanel.php         # WhatsApp Web-style real-time chat
    Dashboard/DashboardPanel.php  # metrics + Leaflet map + AI insights
    Dashboard/ProspectingMetrics.php  # funnel by period/search + A/B by angle, template, channel
    Prospecting/ProspectingWizard.php  # /prospeccao steps 1–2 (search, live progress, map, results)
    Leads/
      LeadsTable.php           # /leads
      LeadDossier.php          # side panel; open with the "open-lead-dossier" event
      OutreachQueue.php        # step 3: review queue
      PipelineBoard.php        # step 4: kanban by lead status (wire:sort)
    Onboarding/
      EmpresaStep.php
      PlanoStep.php            # Stripe trial/subscription
    Store/ProdutoManager.php
    WhatsAppChannels.php
  Models/
    Empresa, Lead, Conversation, Message, MessageLog
    Produto, WhatsAppChannel
    Sequence, SequenceStep, SequenceEnrollment
    ProspectingSearch
  Services/
    WhatsAppService.php        # Twilio send/receive
    AIService.php              # Anthropic API — lead scoring + auto-reply
    LeadService.php
    GeoService.php
    Geo/Distance.php
    Geo/GeocodingService.php
    Prospecting/ProspectingService.php
    Prospecting/Providers/     # GooglePlacesProvider, MapboxPlacesProvider
    Prospecting/OutreachService.php  # review queue, 24h window, templates
    Enrichment/                # LeadEnrichmentService + Steps/ (Place Details, site, CNPJ, web research, Lookup, resolver)
    Scoring/LeadScoringService.php  # lead_score v2 (fit + contact + pain + proximity)
    Costs/UsageMeter.php       # prices every paid call into api_usages (config/costs.php)
    Metrics/FunnelReport.php   # prospecting funnel, reply rate by template/channel
    Prospecting/AngleExperiment.php  # A/B of the first message's angle
    ChannelHealthService.php   # Twilio quality/limit per channel, pauses prospecting (+ ProspectingPaused e-mail)
    LeadMessenger.php          # catalog follow-up / lead_capture sends, 24h-window aware
    InboundMessageService.php  # opt-out + bot after an inbound message (ProcessInboundMessageJob)
  Jobs/
    SendWhatsAppMessageJob.php
    FollowUpWhatsAppJob.php    # 24h delay follow-up
    AutoRespondJob.php         # AI auto-reply
    ProcessSequenceStepJob.php
    FindInternetLeadsJob.php
  Events/
    NewMessageReceived.php     # broadcast via Pusher
    MessageSent.php
    MessageStatusUpdated.php
    ProspectingSearchUpdated.php  # search stage/progress, private channel empresa.{id}.prospecting
  Repositories/
    ConversationRepository.php
    LeadRepository.php
  Middleware/
    EnsureOnboardingComplete.php
    EnsureMasterAdmin.php
    SetLocale.php
```

## Multi-language

Supported: `pt_BR`, `es`. Files in `lang/`. Switched via `/lang/{locale}` + session.

## Billing

Stripe Cashier. Trial: 14 days. Price: R$97/mo (configurable via `PLAN_TRIAL_DAYS` / `PLAN_PRICE_BRL`).
`PlanoStep` handles subscription creation during onboarding.

## Twilio

Sandbox number: `+19899354903`
Webhook: `POST /webhook/twilio` — receives inbound messages, fires `NewMessageReceived` event → Pusher → `ChatPanel`. It only saves the message and credits the reply; opt-out check (AI for long messages), its confirmation and the bot run in `ProcessInboundMessageJob` (`InboundMessageService`).

- Webhook requests must carry a valid `X-Twilio-Signature` (`ValidateTwilioSignature` middleware). A local tunnel that rewrites the URL can set `TWILIO_WEBHOOK_VALIDATE=false`; ignored in production.
- Inbound messages go to the empresa whose `WhatsAppChannel` owns the `To` number. Unknown numbers are dropped — never fall back to another empresa.
- No global sender: every send goes through a channel of the empresa (the conversation's, else `Empresa::defaultChannel()`); without one, `WhatsAppService` returns `no_channel`. In dev, register the WhatsApp sandbox `whatsapp:+14155238886` as a channel.
- 24h session: free text only within 24h of the contact's last message (`Conversation::isSessionOpen()`, `last_inbound_at`); outside it, an approved `WhatsAppTemplate` (Twilio ContentSid, `WhatsAppService::sendContentTemplate`). Every send passes `statusCallback`; Twilio errors (e.g. 63016) land in `messages.error_code`.
- Automatic messages to catalog leads (24h follow-up, `lead_capture` sequences) go through `LeadMessenger`: free text inside the session, else the first approved template the text fills, else nothing (logged).
- Prospect outreach never goes out unreviewed: `OutreachService::request()` → `GenerateOutreachDraftJob` → review queue (`Leads/OutreachQueue`) → `approve()` (API, scheduled by `OutreachScheduler`: daily cap per channel, business hours in `empresas.timezone`, 45–120 s gap) or `markAssisted()` (user sends from their own WhatsApp via `wa.me`).
- Outreach text: `OutreachWriter` writes the first message in three angles (observacao, dor_do_segmento, roteamento) and the follow-ups; every AI message must pass `OutreachMessageValidator` (≤300 chars, no links, ≤1 emoji, no number missing from the data). Template variables come from the reviewed text (`MessageParts`), not from the AI. After the first send, `FollowUpCadence` queues D2/D5/D10 follow-ups into the same review queue; any inbound message, "Ele respondeu" or opt-out ends it.
- Prospecting roadmap: `docs/PLANO_MELHORIAS_PROSPECCAO.md`.

## Patterns

- Livewire components own their own data fetching — no separate API endpoints for UI
- Jobs dispatched from Services, not Controllers
- `Empresa` scopes all tenant data — always filter by `empresa_id`
- Public catalog routes are guest-accessible; everything else requires auth + onboarding
- AI: every Claude call goes through `AIService` (`structured()` for JSON via structured outputs, `text()` for prose) with a tier — `fast` (Haiku: keywords, ranking, classification; never pass effort) or `quality` (Sonnet 5.5: messages people read; effort `low`, maxTokens ≥ 2000 since thinking counts). Clamp numbers yourself: schemas can't express min/max. Tests fake the API with a Guzzle `MockHandler` transporter bound to `Anthropic\Client`.
- Lead enrichment: `LeadEnrichmentService` runs `Services/Enrichment/Steps` as a cascade; steps add `ContactSignal`s to the context and `ContactResolverStep` writes `lead_contacts` (origem + evidencia on every contact, for LGPD) and picks the primary, which becomes `leads.telefone`. Paid steps (web search, Twilio Lookup) are off by default and only run for good leads.
- Fetch URLs we don't control (lead sites, anything typed by users) only through `App\Support\Net\SafeHttp` (SSRF: public IPs only, pinned, redirects checked, 1 MB cap).
- Lead score (v2): `lead_score` = 40% fit + 25% contact confidence + 20% pain + 15% proximity, from `LeadScoringService`; fit/pain/hook come from `AIService::avaliarLeads` and live in `ai_insights` (breakdown in `ai_insights.score_breakdown`). Re-evaluated after each search and enrichment; `php artisan leads:score` recomputes without AI. Only `internet`/`manual` leads; catalog leads keep their distance score. Sales profile (offer, pain, proofs, excluded segments) is on `empresas`, edited in `/empresa` → Vendas.
- Lead status is the funnel: `novo, abordado, respondeu, reuniao, proposta, convertido, descartado` (labels in `lang`, `messages.status_*`). Use `Lead::markApproached()` / `markReplied()` for automatic moves and `OutreachService::setStatus()` for user moves (it ends the cold cadence).
- Prospecting search progress: `prospecting_searches.stage` + `progress`, advanced with `ProspectingSearch::advance()`, which broadcasts (`ShouldBroadcastNow`; a broadcaster that is down only logs). Screens also poll while `isActive()`. `error` stores a translation key.
- Paid calls are metered: `AIService` records Claude usage itself; Places/Lookup call `UsageMeter::places()`/`lookup()` after a successful response. Entry points wrap their work in `UsageMeter::within(['empresa_id', 'prospecting_search_id', 'lead_id', 'origem'])` so rows land on who caused them. Prices: `config/costs.php` (list prices). "Lead qualificado" = `Lead::isQualified()` (fit ≥ 70 and `hasProbableWhatsApp()`).
- A/B: `AngleExperiment::suggest()` picks the first message's angle (least sent until 30 sends per angle, then the best reply rate 80% / explore 20%). Only `etapa = 0` attempts count.
- Channel health: `ChannelHealthService` pauses prospecting on a channel (quality dropped to LOW, sender OFFLINE, ≥10% opt-outs among API prospects in 7 days); `OutreachService` then refuses with `channel_paused`. Only the user resumes it (`WhatsAppChannels::retomarProspeccao`).
- Phones: match, dedupe and send by `telefone_e164` (`App\Support\Phone::canonical()`, read with `empresas.country`); `telefone` keeps the raw input. Never compare raw `telefone` strings. `Lead`/`Conversation` fill `telefone_e164` on save; `php artisan leads:normalize-phones` re-runs the backfill.
