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
use App\Services\AIService;

/**
 * The one place that decides whether and how a prospect is approached. Nothing goes out without
 * review: request() queues a draft, the user edits it, then either approve() schedules it on the
 * API channel or markAssisted() records that they sent it from their own WhatsApp.
 */
class OutreachService
{
    public function __construct(
        private readonly AIService $ai,
        private readonly OutreachScheduler $scheduler,
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

    /** Writes the message (GenerateOutreachDraftJob). With auto-send on, approves it right away. */
    public function prepare(OutreachDraft $draft): void
    {
        $text = trim($this->ai->gerarPrimeiraMensagemProspeccao($draft->empresa, $draft->lead));

        if ($text === '') {
            $draft->update(['status' => 'failed', 'erro' => 'generation_failed']);
            return;
        }

        $draft->update([
            'variantes'          => [['angulo' => 'padrao', 'mensagem' => $text]],
            'variante_escolhida' => 'padrao',
            'texto_final'        => $text,
            'status'             => 'draft',
            'erro'               => null,
        ]);

        if ($draft->empresa->prospeccao_envio_automatico) {
            try {
                $this->approve($draft);
            } catch (OutreachException) {
                // Stays in the queue: the user decides how to send it.
            }
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
        $this->recordAttempt($draft, 'api', $text);

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

        if (($draft->lead->status ?? 'novo') === 'novo') {
            $draft->lead->update(['status' => 'contatado']);
        }

        return $this->recordAttempt($draft, 'assisted', $draft->texto_final);
    }

    /** Takes the draft out of the queue; an approved one is not sent. */
    public function skip(OutreachDraft $draft): void
    {
        if (in_array($draft->status, ['draft', 'approved', 'failed'], true)) {
            $draft->update(['status' => 'skipped']);
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

    /** An inbound message from the number credits the last attempt to reach it. */
    public function markReplied(int $empresaId, string $e164): void
    {
        OutreachAttempt::where('empresa_id', $empresaId)
            ->whereNull('responded_at')
            ->whereHas('lead', fn ($q) => $q->where('telefone_e164', $e164))
            ->latest('id')
            ->first()
            ?->update(['responded_at' => now()]);
    }

    /** "Ele respondeu": a reply the system can't see, e.g. to a message sent from the user's own WhatsApp. */
    public function registerReply(Lead $lead): void
    {
        $lead->outreachAttempts()->whereNull('responded_at')->latest('id')->first()?->update(['responded_at' => now()]);

        if (in_array($lead->status ?? 'novo', ['novo', 'contatado'], true)) {
            $lead->update(['status' => 'interessado']);
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
     * channel or the empresa's default. Only active channels of the empresa.
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
                return $channel;
            }
        }

        throw new OutreachException('no_channel');
    }

    /**
     * Null inside the 24h session (free text goes); outside it, the empresa's approved template
     * with its variables filled for this lead.
     *
     * @return array{template: WhatsAppTemplate, variables: array<string, string>}|null
     * @throws OutreachException
     */
    private function templateFor(OutreachDraft $draft): ?array
    {
        if ($this->conversationOf($draft)?->isSessionOpen()) {
            return null;
        }

        $template = $draft->empresa->whatsappTemplates()->usable()->orderBy('id')->first();
        if (!$template) {
            throw new OutreachException('session_closed');
        }

        $variables = $template->variablesFor($draft->lead, (string) $draft->texto_final);
        if ($variables === null) {
            throw new OutreachException('template_incomplete');
        }

        return ['template' => $template, 'variables' => $variables];
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

    private function recordAttempt(OutreachDraft $draft, string $canal, string $mensagem): OutreachAttempt
    {
        return OutreachAttempt::create([
            'empresa_id'        => $draft->empresa_id,
            'lead_id'           => $draft->lead_id,
            'outreach_draft_id' => $draft->id,
            'canal'             => $canal,
            'mensagem'          => $mensagem,
            'variante'          => $draft->variante_escolhida,
        ]);
    }
}
