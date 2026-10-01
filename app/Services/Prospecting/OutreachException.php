<?php

namespace App\Services\Prospecting;

use RuntimeException;

/** Why a lead can't be approached right now; messageKey() is the text for the user. */
class OutreachException extends RuntimeException
{
    private const MESSAGES = [
        'no_phone'          => 'messages.lead_no_phone',
        'invalid_phone'     => 'messages.lead_invalid_phone',
        'opted_out'         => 'messages.lead_opted_out',
        'no_channel'        => 'messages.whatsapp_channel_required',
        'generation_failed' => 'messages.message_generation_failed',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Outreach blocked: {$reason}");
    }

    public function messageKey(): string
    {
        return self::MESSAGES[$this->reason];
    }
}
