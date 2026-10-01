<?php

namespace App\Services\Prospecting;

use App\Jobs\GenerateOutreachDraftJob;
use App\Jobs\SendOutreachDraftJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\OutreachAttempt;
use App\Models\OutreachDraft;
use App\Models\WhatsAppChannel;
use App\Models\WhatsAppTemplate;
use App\Services\Costs\UsageMeter;

/**
 * The one place that decides whether and how a prospect is approached. Nothing goes out without
 * review: request() queues a draft (written in three angles), the user picks and edits one, then
 * either approve() schedules it on the API channel or markAssisted() records that they sent it from
 * their own WhatsApp. Once sent, FollowUpCadence queues the follow-ups the same way.
 */
class OutreachService
{
    public function __construct(
        private readonly OutreachWriter $writer,
        private readonly OutreachScheduler $scheduler,
        private readonly FollowUpCadence $cadence,
        private readonly UsageMeter $meter,
        private readonly AngleExperiment $experiment,
    ) {}

    /** @throws OutreachException when the lead can't receive WhatsApp messages */
    public function assertReachable(Lead $lead): void
    {
        if (trim((string) $lead->telefone) === '') {
            throw new OutreachException('no_phone');
        }

        if (!$lead->telefone_e164) {
            throw new OutreachException('invalid_phone');
        }

        // A contact can opt out before becoming a lead; their conversation is blocked then.
        $blocked = Conversation::where('empresa_id', $lead->empresa_id)
            ->where('telefone_e164', $lead->telefone_e164)
            ->where('status', 'blocked')
            ->exists();

        if ($lead->isOptedOut() || $blocked) {
            throw new OutreachException('opted_out');
        }
    }

    /**
     * The API sends only to probable WhatsApps: a lead whose enrichment found none can still be
     * called, or tried from the user's own WhatsApp.
     *
     * @throws OutreachException
     */
    private function assertReachableByApi(Lead $lead): void
    {
        $this->assertReachable($lead);

        if ($lead->lacksProbableWhatsApp()) {
            throw new OutreachException('no_whatsapp');
        }
    }

    /**
     * Puts the lead in the review queue; the message is written in the background. A lead already
     * in the queue keeps its draft. No channel is needed: the user may send it from their own WhatsApp.
     *
     * @throws OutreachException
     */
    public function request(Lead $lead, ?WhatsAppChannel $channel = null): OutreachDraft
    {
        $this->assertReachable($lead);

        $empresa = $lead->empresa;
        if ($channel && (int) $channel->empresa_id !== (int) $empresa->id) {
            throw new OutreachException('no_channel');
        }

        $pending = $empresa->outreachDrafts()
            ->where('lead_id', $lead->id)
            ->whereIn('status', OutreachDraft::PENDING)
            ->first();

        if ($pending) {
            return $pending;
        }

        $draft = OutreachDraft::create([
            'empresa_id'          => $empresa->id,
            'lead_id'             => $lead->id,
            'whatsapp_channel_id' => ($channel ?? $empresa->defaultChannel())?->id,
            'status'              => 'generating',
        ]);

        GenerateOutreachDraftJob::dispatch($draft->id);

        return $draft;
    }

    /**
     * Writes the message (GenerateOutreachDraftJob): the first one in three angles, with the one
     * the A/B test suggests picked (AngleExperiment), or the follow-up of the draft's cadence step. With
     * auto-send on, approves it right away.
     */
    public function prepare(OutreachDraft $draft): void
    {
        $written = $this->meter->within(
            ['empresa_id' => $draft->empresa_id, 'lead_id' => $draft->lead_id, 'origem' => 'abordagem'],
            fn () => $draft->isFollowUp() ? $this->writeFollowUp($draft) : $this->writeFirst($draft),
        );

        if ($written === null) {
            $draft->update(['status' => 'failed', 'erro' => 'generation_failed']);
            return;
        }

        $draft->update($written + ['status' => 'draft', 'erro' => null]);

        if ($draft->empresa->prospeccao_envio_automatico) {
            try {
                $this->approve($draft);
            } catch (OutreachException) {
                // Stays in the queue: the user decides how to send it.
            }
        }
    }

    /** The user picked another angle in the review card: its text replaces the current one. */
    public function chooseVariant(OutreachDraft $draft, string $angulo): void
    {
        $variante = $draft->variante($angulo);

        if ($draft->status === 'draft' && $variante) {
            $draft->update(['variante_escolhida' => $angulo, 'texto_final' => $variante['mensagem']]);
        }
    }

    /**
     * Schedules the draft on the API channel at its next free slot (daily cap, business hours,
     * random gap). Refused when WhatsApp wouldn't deliver it: outside the 24h session that takes
     * an approved template.
     *
     * @throws OutreachException
     */
    public function approve(OutreachDraft $draft, ?string $texto = null): OutreachDraft
    {
        $this->assertStatus($draft, 'draft');

        if ($texto !== null) {
            $draft->texto_final = trim($texto);
        }
        if (trim((string) $draft->texto_final) === '') {
            throw new OutreachException('empty_message');
        }

        $this->assertReachableByApi($draft->lead);
        $channel = $this->channelFor($draft);
        $this->templateFor($draft);

        $slot = $this->scheduler->nextSlot($channel, $draft->lead->horario_funcionamento);

        $draft->fill([
            'whatsapp_channel_id' => $channel->id,
            'status'              => 'approved',
            'scheduled_for'       => $slot,
            'erro'                => null,
        ])->save();

        SendOutreachDraftJob::dispatch($draft->id)->delay($slot);

        return $draft;
    }

    /**
     * Sends an approved draft at its slot (SendOutreachDraftJob). The checks run again: the lead
     * may have opted out or the session closed since approval.
     *
     * @throws OutreachException
     */
    public function send(OutreachDraft $draft): Message
    {
        $this->assertStatus($draft, 'approved');

        $lead = $draft->lead;
        $this->assertReachableByApi($lead);
        $channel = $this->channelFor($draft);
        $template = $this->templateFor($draft);
        $text = $template ? $template['template']->render($template['variables']) : $draft->texto_final;

        $conversation = Conversation::firstOrCreate(
            ['empresa_id' => $draft->empresa_id, 'telefone_e164' => $lead->telefone_e164],
            [
                'telefone'            => $lead->telefone,
                'lead_id'             => $lead->id,
                'nome_contato'        => $lead->nome,
                'status'              => 'active',
                'whatsapp_channel_id' => $channel->id,
            ]
        );

        $conversation->fill([
            'whatsapp_channel_id' => $channel->id,
            'lead_id'             => $conversation->lead_id ?? $lead->id,
            'last_message'        => $text,
            'last_message_at'     => now(),
            'status'              => 'active',
        ])->save();

        $message = Message::create([
            'conversation_id'   => $conversation->id,
            'sender'            => 'user',
            'message'           => $text,
            'type'              => 'text',
            'status'            => 'sending',
            'ai_generated'      => true,
            'content_sid'       => $template['template']->content_sid ?? null,
            'content_variables' => $template['variables'] ?? null,
        ]);

        SendWhatsAppMessageJob::dispatch($message);

        $draft->update(['status' => 'sent', 'sent_at' => now(), 'whatsapp_channel_id' => $channel->id]);
        $this->recordAttempt($draft, 'api', $text, $channel, $template['template'] ?? null);
        $this->cadence->onSent($draft);

        return $message;
    }

    /**
     * The user sent the draft from their own WhatsApp through the wa.me link.
     *
     * @throws OutreachException
     */
    public function markAssisted(OutreachDraft $draft, string $texto): OutreachAttempt
    {
        $this->assertStatus($draft, 'draft');
        $this->assertReachable($draft->lead);

        $draft->update(['texto_final' => trim($texto), 'status' => 'sent', 'sent_at' => now()]);

        $draft->lead->markApproached();

        $attempt = $this->recordAttempt($draft, 'assisted', $draft->texto_final);
        $this->cadence->onSent($draft);

        return $attempt;
    }

    /** Takes the draft out of the queue; an approved one is not sent. A skipped follow-up moves the cadence on. */
    public function skip(OutreachDraft $draft): void
    {
        if (in_array($draft->status, ['draft', 'approved', 'failed'], true)) {
            $draft->update(['status' => 'skipped']);
            $this->cadence->onSkipped($draft);
        }
    }

    /** A failed draft goes back: rewritten when generation failed, otherwise to review with its text. */
    public function retry(OutreachDraft $draft): void
    {
        if ($draft->status !== 'failed') {
            return;
        }

        if ($draft->erro === 'generation_failed' || trim((string) $draft->texto_final) === '') {
            $draft->update(['status' => 'generating', 'erro' => null]);
            GenerateOutreachDraftJob::dispatch($draft->id);
            return;
        }

        $draft->update(['status' => 'draft', 'erro' => null, 'scheduled_for' => null]);
    }

    /** An inbound message from the number credits the last attempt to reach it, ends the cold cadence and moves the lead to "respondeu". */
    public function markReplied(int $empresaId, string $e164): void
    {
        OutreachAttempt::where('empresa_id', $empresaId)
            ->whereNull('responded_at')
            ->whereHas('lead', fn ($q) => $q->where('telefone_e164', $e164))
            ->latest('id')
            ->first()
            ?->update(['responded_at' => now()]);

        Lead::where('empresa_id', $empresaId)->where('telefone_e164', $e164)->get()
            ->each(function (Lead $lead) {
                $this->cadence->stop($lead);
                $lead->markReplied();
            });
    }

    /** "Ele respondeu": a reply the system can't see, e.g. to a message sent from the user's own WhatsApp. */
    public function registerReply(Lead $lead): void
    {
        $lead->outreachAttempts()->whereNull('responded_at')->latest('id')->first()?->update(['responded_at' => now()]);
        $this->cadence->stop($lead);
        $lead->markReplied();
    }

    /** Moves the lead in the funnel (pipeline, lists). Past "abordado" the cold cadence has nothing left to do. */
    public function setStatus(Lead $lead, string $status): void
    {
        if (!array_key_exists($status, Lead::STATUSES)) {
            return;
        }

        $lead->update(['status' => $status]);

        if (in_array($status, FollowUpCadence::FINAL_STATUSES, true)) {
            $this->cadence->stop($lead, 'stopped');
        }
    }

    /**
     * How "send through the API" would go out right now, for the review card:
     * mode session (free text), template (with the text the lead will read) or blocked (with why).
     *
     * @return array{mode: string, reason?: string, template?: string, preview?: string}
     */
    public function deliveryPlan(OutreachDraft $draft): array
    {
        try {
            $this->assertReachableByApi($draft->lead);
            $this->channelFor($draft);
            $template = $this->templateFor($draft);
        } catch (OutreachException $e) {
            return ['mode' => 'blocked', 'reason' => $e->messageKey()];
        }

        return $template === null
            ? ['mode' => 'session']
            : ['mode' => 'template', 'template' => $template['template']->nome, 'preview' => $template['template']->render($template['variables'])];
    }

    /**
     * The contact keeps hearing from the number they already talk to; otherwise the draft's
     * channel or the empresa's default. Only active channels of the empresa, and not while
     * prospecting is paused on it (quality dropped): another number would not help that contact.
     *
     * @throws OutreachException
     */
    private function channelFor(OutreachDraft $draft): WhatsAppChannel
    {
        $candidates = [
            $this->conversationOf($draft)?->whatsappChannel,
            $draft->whatsappChannel,
            $draft->empresa->defaultChannel(),
        ];

        foreach ($candidates as $channel) {
            if ($channel && $channel->ativo && (int) $channel->empresa_id === (int) $draft->empresa_id) {
                if ($channel->isProspectingPaused()) {
                    throw new OutreachException('channel_paused');
                }

                return $channel;
            }
        }

        throw new OutreachException('no_channel');
    }

    /**
     * Null inside the 24h session (free text goes); outside it, the first approved template of the
     * empresa whose variables this lead and message fill.
     *
     * @return array{template: WhatsAppTemplate, variables: array<string, string>}|null
     * @throws OutreachException
     */
    private function templateFor(OutreachDraft $draft): ?array
    {
        if ($this->conversationOf($draft)?->isSessionOpen()) {
            return null;
        }

        $templates = $draft->empresa->whatsappTemplates()->usable()->orderBy('id')->get();
        if ($templates->isEmpty()) {
            throw new OutreachException('session_closed');
        }

        return WhatsAppTemplate::firstFilled($templates, $draft->lead, (string) $draft->texto_final)
            ?? throw new OutreachException('template_incomplete');
    }

    private function conversationOf(OutreachDraft $draft): ?Conversation
    {
        return Conversation::where('empresa_id', $draft->empresa_id)
            ->where('telefone_e164', $draft->lead->telefone_e164)
            ->first();
    }

    /** @throws OutreachException */
    private function assertStatus(OutreachDraft $draft, string $status): void
    {
        if ($draft->status !== $status) {
            throw new OutreachException('not_pending');
        }
    }

    /** The channel and template only for API sends: the A/B by template and the channel's health read them. */
    private function recordAttempt(OutreachDraft $draft, string $canal, string $mensagem, ?WhatsAppChannel $channel = null, ?WhatsAppTemplate $template = null): OutreachAttempt
    {
        return OutreachAttempt::create([
            'empresa_id'           => $draft->empresa_id,
            'lead_id'              => $draft->lead_id,
            'outreach_draft_id'    => $draft->id,
            'canal'                => $canal,
            'whatsapp_channel_id'  => $channel?->id,
            'whatsapp_template_id' => $template?->id,
            'mensagem'             => $mensagem,
            'variante'             => $draft->variante_escolhida,
            'etapa'                => $draft->etapa,
        ]);
    }

    /** @return array<string, mixed>|null the draft fields, or null when no message passed review */
    private function writeFirst(OutreachDraft $draft): ?array
    {
        $written = $this->writer->firstMessage($draft->lead);
        if ($written === null) {
            return null;
        }

        $angulo = $this->experiment->suggest($draft->empresa_id, array_column($written['variantes'], 'angulo'), !empty($draft->lead->ai_insights['gancho']));

        return [
            'variantes'          => $written['variantes'],
            'variante_escolhida' => $angulo,
            'texto_final'        => collect($written['variantes'])->firstWhere('angulo', $angulo)['mensagem'],
            'dor_hipotese'       => $written['dor_hipotese'],
        ];
    }

    /** @return array<string, mixed>|null */
    private function writeFollowUp(OutreachDraft $draft): ?array
    {
        $objetivo = $this->cadence->goalOf($draft);
        $historico = $draft->lead->outreachAttempts()->orderBy('id')->pluck('mensagem')->all();
        $dorHipotese = OutreachDraft::where('lead_id', $draft->lead_id)->where('etapa', 0)->where('status', 'sent')->latest('id')->value('dor_hipotese');

        $text = $this->writer->followUp($draft->lead, $objetivo, $historico, $dorHipotese);
        if ($text === null) {
            return null;
        }

        return [
            'variantes'          => [['angulo' => $objetivo, 'mensagem' => $text, 'gancho_usado' => null]],
            'variante_escolhida' => $objetivo,
            'texto_final'        => $text,
            'dor_hipotese'       => $dorHipotese,
        ];
    }
}
