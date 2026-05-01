<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSenderRegistration extends Model
{
    protected $fillable = [
        'empresa_id',
        'whatsapp_channel_id',
        'numero',
        'waba_id',
        'twilio_sid',
        'verification_method',
        'status',
        'profile_data',
        'error_message',
    ];

    protected $casts = [
        'profile_data' => 'array',
    ];

    // Status constants
    const STATUS_PENDING_OTP      = 'pending_otp';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED         = 'approved';
    const STATUS_REJECTED         = 'rejected';
    const STATUS_FAILED           = 'failed';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChannel::class, 'whatsapp_channel_id');
    }
}
