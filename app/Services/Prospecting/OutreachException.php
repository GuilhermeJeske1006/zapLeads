<?php

namespace App\Services\Prospecting;

use RuntimeException;

/** Why a lead can't be approached right now; messageKey() is the text for the user. */
class OutreachException extends RuntimeException
{
    private const MESSAGES = [
        'no_phone'            => 'messages.lead_no_phone',
        'invalid_phone'       => 'messages.lead_invalid_phone',
        'opted_out'           => 'messages.lead_opted_out',
        'no_channel'          => 'messages.whatsapp_channel_required',
        'generation_failed'   => 'messages.message_generation_failed',
        'empty_message'       => 'messages.outreach_empty_message',
        'session_closed'      => 'messages.outreach_session_closed',
        'template_incomplete' => 'messages.outreach_template_incomplete',
        'not_pending'         => 'messages.outreach_not_pending',
        'no_whatsapp'         => 'messages.lead_no_whatsapp',
        'channel_paused'      => 'messages.outreach_channel_paused',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Outreach blocked: {$reason}");
    }

    public function messageKey(): string
    {
        return self::messageKeyFor($this->reason);
    }

    /** Also for reasons stored on a failed draft (outreach_drafts.erro). */
    public static function messageKeyFor(string $reason): string
    {
        return self::MESSAGES[$reason] ?? 'messages.outreach_failed';
    }
}
