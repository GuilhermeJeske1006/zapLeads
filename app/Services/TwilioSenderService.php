<?php

namespace App\Services;

use App\Models\WhatsAppChannel;
use App\Models\WhatsAppSenderRegistration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Rest\Messaging\V2\ChannelsSenderModels;

class TwilioSenderService
{
    private Client $client;
    private string $baseUrl = 'https://messaging.twilio.com/v2';

    public function __construct()
    {
        $this->client = new Client(config('twilio.sid'), config('twilio.token'));
    }

    /**
     * Step 1: Submit sender registration to Twilio.
     * Twilio will send OTP to the number via SMS or VOICE.
     */
    public function initiate(WhatsAppSenderRegistration $reg): array
    {
        try {
            $createRequest = ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id'     => $reg->numero,
                'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id'             => $reg->waba_id,
                    'verification_method' => $reg->verification_method,
                ]),
                'webhook' => ChannelsSenderModels::createMessagingV2ChannelsSenderWebhook([
                    'callback_url'           => route('webhook.twilio'),
                    'callback_method'        => 'POST',
                    'status_callback_url'    => route('webhook.twilio'),
                    'status_callback_method' => 'POST',
                ]),
                'profile' => ChannelsSenderModels::createMessagingV2ChannelsSenderProfile(
                    $reg->profile_data ?? []
                ),
            ]);

            $sender = $this->client->messaging->v2->channelsSenders->create($createRequest);

            $reg->update([
                'twilio_sid' => $sender->sid,
                'status'     => WhatsAppSenderRegistration::STATUS_PENDING_OTP,
            ]);

            Log::info('TwilioSenderService: sender created', ['sid' => $sender->sid, 'numero' => $reg->numero]);

            return ['success' => true, 'sid' => $sender->sid];
        } catch (\Throwable $e) {
            Log::error('TwilioSenderService: initiate failed', ['error' => $e->getMessage(), 'numero' => $reg->numero]);
            $reg->update(['status' => WhatsAppSenderRegistration::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Step 2: Confirm OTP received on the phone.
     * Twilio SDK update() is broken in v8.x (undefined variable bug), so call REST directly.
     */
    public function confirmOtp(WhatsAppSenderRegistration $reg, string $code): array
    {
        if (!$reg->twilio_sid) {
            return ['success' => false, 'error' => 'Registro não iniciado. Twilio SID ausente.'];
        }

        try {
            $response = Http::withBasicAuth(config('twilio.sid'), config('twilio.token'))
                ->withHeaders(['Accept' => 'application/json'])
                ->asJson()
                ->post("{$this->baseUrl}/Channels/Senders/{$reg->twilio_sid}", [
                    'configuration' => ['verification_code' => $code],
                ]);

            if ($response->failed()) {
                $error = $response->json('message') ?? $response->body();
                Log::error('TwilioSenderService: OTP confirmation failed', ['sid' => $reg->twilio_sid, 'error' => $error]);
                return ['success' => false, 'error' => $error];
            }

            $reg->update(['status' => WhatsAppSenderRegistration::STATUS_PENDING_APPROVAL]);

            Log::info('TwilioSenderService: OTP confirmed', ['sid' => $reg->twilio_sid]);

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TwilioSenderService: confirmOtp exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Poll Twilio for current sender status and sync to DB.
     * Returns the new local status string.
     */
    public function syncStatus(WhatsAppSenderRegistration $reg): string
    {
        if (!$reg->twilio_sid) {
            return $reg->status;
        }

        try {
            $sender = $this->client->messaging->v2->channelsSenders($reg->twilio_sid)->fetch();
            $twilioStatus = strtolower($sender->status ?? '');

            $localStatus = $this->mapTwilioStatus($twilioStatus);

            $reg->update(['status' => $localStatus]);

            if ($localStatus === WhatsAppSenderRegistration::STATUS_APPROVED && !$reg->whatsapp_channel_id) {
                $this->createChannelFromRegistration($reg);
            }

            return $localStatus;
        } catch (\Throwable $e) {
            Log::error('TwilioSenderService: syncStatus failed', ['sid' => $reg->twilio_sid, 'error' => $e->getMessage()]);
            return $reg->status;
        }
    }

    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match (true) {
            in_array($twilioStatus, ['active', 'approved', 'connected']) => WhatsAppSenderRegistration::STATUS_APPROVED,
            in_array($twilioStatus, ['rejected', 'suspended', 'deleted']) => WhatsAppSenderRegistration::STATUS_REJECTED,
            in_array($twilioStatus, ['pending_review', 'in_review', 'pending'])  => WhatsAppSenderRegistration::STATUS_PENDING_APPROVAL,
            default                                                        => WhatsAppSenderRegistration::STATUS_PENDING_APPROVAL,
        };
    }

    private function createChannelFromRegistration(WhatsAppSenderRegistration $reg): void
    {
        $profile = $reg->profile_data ?? [];
        $nome    = $profile['name'] ?? $reg->numero;

        $channel = WhatsAppChannel::create([
            'empresa_id' => $reg->empresa_id,
            'nome'       => $nome,
            'numero'     => $reg->numero,
            'is_default' => false,
            'ativo'      => true,
        ]);

        $reg->update(['whatsapp_channel_id' => $channel->id]);

        Log::info('TwilioSenderService: WhatsAppChannel created from registration', [
            'channel_id' => $channel->id,
            'numero'     => $reg->numero,
        ]);
    }
}
