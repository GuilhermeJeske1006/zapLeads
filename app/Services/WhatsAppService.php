<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MessageLog;
use App\Models\WhatsAppChannel;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(config('twilio.sid'), config('twilio.token'));
    }

    public function sendTextMessage(string $phone, string $message, ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $phone = $this->normalizePhone($phone);

        $lead = $this->findLeadByPhone($phone);
        if ($lead?->isOptedOut()) {
            Log::info('Opted-out lead, skipping send', ['phone' => $phone]);
            return ['success' => false, 'error' => 'opted_out'];
        }

        try {
            $result = $this->client->messages->create(
                "whatsapp:{$phone}",
                ['from' => $this->resolveFrom($channel), 'body' => $message]
            );

            $this->log(
                $empresaId ?: $lead?->empresa_id,
                $phone, $message, 'text', 'outbound', 'success',
                ['sid' => $result->sid, 'status' => $result->status]
            );

            return ['success' => true, 'data' => ['sid' => $result->sid, 'status' => $result->status]];
        } catch (\Throwable $e) {
            Log::error('WhatsApp sendTextMessage failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $this->log($empresaId ?: $lead?->empresa_id, $phone, $message, 'text', 'outbound', 'failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendImageMessage(string $phone, string $imageUrl, string $caption = '', ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $phone = $this->normalizePhone($phone);
        $lead = $this->findLeadByPhone($phone);

        try {
            $params = ['from' => $this->resolveFrom($channel), 'mediaUrl' => [$imageUrl]];
            if ($caption !== '') {
                $params['body'] = $caption;
            }

            $result = $this->client->messages->create("whatsapp:{$phone}", $params);

            $this->log(
                $empresaId ?: $lead?->empresa_id,
                $phone, $caption, 'image', 'outbound', 'success',
                ['sid' => $result->sid, 'status' => $result->status]
            );

            return ['success' => true, 'data' => ['sid' => $result->sid, 'status' => $result->status]];
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

    private function resolveFrom(?WhatsAppChannel $channel): string
    {
        return $channel?->numero ?? config('twilio.from');
    }

    private function resolveTemplate(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($phone, '55') && strlen($phone) <= 11) {
            $phone = '55' . $phone;
        }
        return '+' . $phone;
    }

    private function findLeadByPhone(string $normalizedPhone): ?Lead
    {
        $digits = preg_replace('/\D/', '', $normalizedPhone);
        $withoutCountry = str_starts_with($digits, '55') ? substr($digits, 2) : $digits;

        return Lead::query()
            ->where('telefone', $digits)
            ->orWhere('telefone', $withoutCountry)
            ->orWhere('telefone', '+' . $digits)
            ->first();
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
