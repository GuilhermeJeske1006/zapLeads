<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;
    private string $clientToken;

    public function __construct()
    {
        $instanceId = config('zapi.instance_id');
        $token = config('zapi.token');
        $this->baseUrl = "https://api.z-api.io/instances/{$instanceId}/token/{$token}";
        $this->clientToken = config('zapi.client_token');
    }

    public function sendTextMessage(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);

        $lead = Lead::where('telefone', $phone)->first();
        if ($lead?->isOptedOut()) {
            Log::info('Opted-out lead, skipping send', ['phone' => $phone]);
            return ['success' => false, 'error' => 'opted_out'];
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'Client-Token' => $this->clientToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/send-text", [
                'phone' => $phone,
                'message' => $message,
            ]);

            $result = $response->json();
            $this->log($phone, $message, 'text', 'outbound', $response->successful() ? 'success' : 'failed', $result);

            return ['success' => $response->successful(), 'data' => $result];
        } catch (\Throwable $e) {
            Log::error('WhatsApp sendTextMessage failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendImageMessage(string $phone, string $imageUrl, string $caption = ''): array
    {
        $phone = $this->normalizePhone($phone);

        try {
            $response = Http::timeout(15)->withHeaders([
                'Client-Token' => $this->clientToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/send-image", [
                'phone' => $phone,
                'image' => $imageUrl,
                'caption' => $caption,
            ]);

            $result = $response->json();
            $this->log($phone, $caption, 'image', 'outbound', $response->successful() ? 'success' : 'failed', $result);

            return ['success' => $response->successful(), 'data' => $result];
        } catch (\Throwable $e) {
            Log::error('WhatsApp sendImageMessage failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendTemplateMessage(string $phone, string $template, array $params = []): array
    {
        $message = $this->resolveTemplate($template, $params);
        return $this->sendTextMessage($phone, $message);
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
        return $phone;
    }

    private function log(string $phone, string $message, string $tipo, string $direcao, string $status, ?array $response = null): void
    {
        MessageLog::create([
            'loja_id' => null,
            'telefone' => $phone,
            'mensagem' => $message,
            'tipo' => $tipo,
            'direcao' => $direcao,
            'status' => $status,
            'zapi_response' => $response,
        ]);
    }
}
