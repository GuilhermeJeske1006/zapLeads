<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'sender', 'message', 'type',
        'media_url', 'status', 'twilio_message_sid', 'ai_generated',
        'error_code', 'content_sid', 'content_variables',
    ];

    protected $casts = [
        'ai_generated'      => 'boolean',
        'content_variables' => 'array',
    ];

    /** Twilio error codes worth explaining to the user; the rest show as a generic failure. */
    public const ERROR_MESSAGES = [
        '63016' => 'messages.error_63016',
        '63024' => 'messages.error_63024',
        '63049' => 'messages.error_63049',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Why a failed message didn't arrive, for the chat. */
    public function failureReason(): string
    {
        if (isset(self::ERROR_MESSAGES[$this->error_code])) {
            return __(self::ERROR_MESSAGES[$this->error_code]);
        }

        return $this->error_code
            ? __('messages.message_not_delivered_code', ['code' => $this->error_code])
            : __('messages.message_not_delivered');
    }

    public function isTemplate(): bool
    {
        return $this->content_sid !== null;
    }

    public function isFromUser(): bool
    {
        return $this->sender === 'user';
    }
}
