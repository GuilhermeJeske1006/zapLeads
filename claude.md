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
php artisan queue:work  # jobs (sequence steps, follow-ups, auto-respond)
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
| GET | `/loja/{slug}` | Public catalog — captures lead + geo |
| POST | `/loja/{slug}/lead` | Lead capture endpoint |
| POST | `/webhook/twilio` | Twilio inbound (no CSRF, no auth; Twilio signature required) |
| POST | `/webhook/stripe` | Cashier webhook (no CSRF) |
| GET | `/lang/{locale}` | Language switcher (pt_BR / es) |
| * | `/onboarding/*` | Wizard steps (guest + auth variants) |
| * | `/admin/*` | Master admin only (`EnsureMasterAdmin`) |

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
    Leads/
      LeadsTable.php
      LeadFinder.php
      InternetProspector.php   # Mapbox/Google Places prospecting
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
Webhook: `POST /webhook/twilio` — receives inbound messages, fires `NewMessageReceived` event → Pusher → `ChatPanel`.

- Webhook requests must carry a valid `X-Twilio-Signature` (`ValidateTwilioSignature` middleware). A local tunnel that rewrites the URL can set `TWILIO_WEBHOOK_VALIDATE=false`; ignored in production.
- Inbound messages go to the empresa whose `WhatsAppChannel` owns the `To` number. Unknown numbers are dropped — never fall back to another empresa.
- No global sender: every send goes through a channel of the empresa (the conversation's, else `Empresa::defaultChannel()`); without one, `WhatsAppService` returns `no_channel`. In dev, register the WhatsApp sandbox `whatsapp:+14155238886` as a channel.
- Prospecting roadmap: `docs/PLANO_MELHORIAS_PROSPECCAO.md`.

## Patterns

- Livewire components own their own data fetching — no separate API endpoints for UI
- Jobs dispatched from Services, not Controllers
- `Empresa` scopes all tenant data — always filter by `empresa_id`
- Public catalog routes are guest-accessible; everything else requires auth + onboarding
