<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\MessageLog;
use App\Models\WhatsAppChannel;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    private ?Client $client = null;

    public function sendTextMessage(string $phone, string $message, ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $channel = $this->resolveChannel($channel, $empresaId);
        if (!$channel) {
            return ['success' => false, 'error' => 'no_channel'];
        }

        $empresaId = (int) $channel->empresa_id;
        $from = $this->resolveFrom($channel);

        $phone = $this->dialable($phone, $channel);
        if ($phone === null) {
            return ['success' => false, 'error' => 'invalid_phone'];
        }

        if ($this->isOptedOut($phone, $empresaId)) {
            Log::info('Opted-out lead, skipping send', ['phone' => $phone, 'empresa_id' => $empresaId]);
            return ['success' => false, 'error' => 'opted_out'];
        }

        try {
            Log::debug('Twilio sendTextMessage', [
                'to' => "whatsapp:{$phone}",
                'from' => $from,
                'empresa_id' => $empresaId,
                'whatsapp_channel_id' => $channel->id,
            ]);

            $result = $this->createMessage("whatsapp:{$phone}", ['from' => $from, 'body' => $message]);

            $this->log($empresaId, $phone, $message, 'text', 'outbound', 'success', $result);

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            Log::error('WhatsApp sendTextMessage failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $this->log($empresaId, $phone, $message, 'text', 'outbound', 'failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendImageMessage(string $phone, string $imageUrl, string $caption = '', ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $channel = $this->resolveChannel($channel, $empresaId);
        if (!$channel) {
            return ['success' => false, 'error' => 'no_channel'];
        }

        $empresaId = (int) $channel->empresa_id;
        $from = $this->resolveFrom($channel);

        $phone = $this->dialable($phone, $channel);
        if ($phone === null) {
            return ['success' => false, 'error' => 'invalid_phone'];
        }

        if ($this->isOptedOut($phone, $empresaId)) {
            Log::info('Opted-out lead, skipping send', ['phone' => $phone, 'empresa_id' => $empresaId]);
            return ['success' => false, 'error' => 'opted_out'];
        }

        try {
            Log::debug('Twilio sendImageMessage', [
                'to' => "whatsapp:{$phone}",
                'from' => $from,
                'empresa_id' => $empresaId,
                'whatsapp_channel_id' => $channel->id,
                'has_caption' => $caption !== '',
            ]);

            $params = ['from' => $from, 'mediaUrl' => [$imageUrl]];
            if ($caption !== '') {
                $params['body'] = $caption;
            }

            $result = $this->createMessage("whatsapp:{$phone}", $params);

            $this->log($empresaId, $phone, $caption, 'image', 'outbound', 'success', $result);

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            Log::error('WhatsApp sendImageMessage failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendTemplateMessage(string $phone, string $template, array $params = [], ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $message = $this->resolveTemplate($template, $params);
        return $this->sendTextMessage($phone, $message, $empresaId, $channel);
    }

    public function registerWebhook(string $numero): array
    {
        $phone = preg_replace('/^whatsapp:/i', '', trim($numero));
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . ltrim($phone, '+');
        }

        $webhookUrl = route('webhook.twilio');

        try {
            $numbers = $this->client()->incomingPhoneNumbers->read(['phoneNumber' => $phone]);

            if (empty($numbers)) {
                Log::warning('registerWebhook: number not found in Twilio account', ['numero' => $phone]);
                return ['success' => false, 'error' => "Número {$phone} não encontrado na conta Twilio."];
            }

            $sid = $numbers[0]->sid;

            $this->client()->incomingPhoneNumbers($sid)->update([
                'smsUrl'    => $webhookUrl,
                'smsMethod' => 'POST',
            ]);

            Log::info('registerWebhook: webhook configured', ['numero' => $phone, 'url' => $webhookUrl, 'sid' => $sid]);

            return ['success' => true, 'webhook_url' => $webhookUrl];
        } catch (\Throwable $e) {
            Log::error('registerWebhook failed', ['numero' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{sid: string, status: string} */
    protected function createMessage(string $to, array $params): array
    {
        $result = $this->client()->messages->create($to, $params);

        return ['sid' => $result->sid, 'status' => $result->status];
    }

    private function client(): Client
    {
        return $this->client ??= new Client(config('twilio.sid'), config('twilio.token'));
    }

    /**
     * Tenant messages always leave through one of the empresa's own channels. There is no
     * platform-wide fallback sender: a lead must never hear from another tenant's number.
     */
    private function resolveChannel(?WhatsAppChannel $channel, ?int $empresaId): ?WhatsAppChannel
    {
        $channel ??= $empresaId ? Empresa::find($empresaId)?->defaultChannel() : null;

        if (!$channel) {
            Log::warning('WhatsApp send skipped: no active channel', ['empresa_id' => $empresaId]);
            return null;
        }

        if ($empresaId && (int) $channel->empresa_id !== $empresaId) {
            Log::warning('WhatsApp send skipped: channel belongs to another empresa', [
                'empresa_id' => $empresaId,
                'whatsapp_channel_id' => $channel->id,
            ]);
            return null;
        }

        return $channel;
    }

    private function resolveFrom(WhatsAppChannel $channel): string
    {
        $from = trim((string) $channel->numero);

        // Twilio WhatsApp requires "whatsapp:+E164" format.
        if (!str_starts_with($from, 'whatsapp:')) {
            $from = 'whatsapp:' . $from;
        }

        $afterPrefix = substr($from, strlen('whatsapp:'));
        if ($afterPrefix !== '' && !str_starts_with($afterPrefix, '+')) {
            $from = 'whatsapp:+' . ltrim($afterPrefix, '+');
        }

        return $from;
    }

    private function resolveTemplate(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    /** The number in E.164, read with the empresa's country; null when it can't be dialed. */
    private function dialable(string $phone, WhatsAppChannel $channel): ?string
    {
        $e164 = Phone::canonical($phone, $channel->empresa?->country ?? 'BR');

        if ($e164 === null) {
            Log::warning('WhatsApp send skipped: invalid phone', ['phone' => $phone, 'empresa_id' => $channel->empresa_id]);
        }

        return $e164;
    }

    private function isOptedOut(string $e164, int $empresaId): bool
    {
        return Lead::query()
            ->where('empresa_id', $empresaId)
            ->where('telefone_e164', $e164)
            ->whereNotNull('opted_out_at')
            ->exists();
    }

    private function log(?int $empresaId, string $phone, string $message, string $tipo, string $direcao, string $status, ?array $response = null): void
    {
        if (!$empresaId) {
            Log::warning('Skipping MessageLog insert: missing empresa_id', ['phone' => $phone]);
            return;
        }

        MessageLog::create([
            'empresa_id'        => $empresaId,
            'telefone'          => $phone,
            'mensagem'          => $message,
            'tipo'              => $tipo,
            'direcao'           => $direcao,
            'status'            => $status,
            'provider_response' => $response,
        ]);
    }
}
