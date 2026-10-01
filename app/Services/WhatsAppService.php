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
        return $this->deliver($phone, ['body' => $message], $empresaId, $channel, 'text', $message);
    }

    public function sendImageMessage(string $phone, string $imageUrl, string $caption = '', ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $params = ['mediaUrl' => [$imageUrl]];
        if ($caption !== '') {
            $params['body'] = $caption;
        }

        return $this->deliver($phone, $params, $empresaId, $channel, 'image', $caption);
    }

    /**
     * An approved WhatsApp template (Twilio Content). The only way to start a conversation outside
     * the 24h session; free text there fails with 63016.
     *
     * @param array<string, string> $variables placeholder => value, e.g. ["1" => "Carla"]
     */
    public function sendContentTemplate(string $phone, string $contentSid, array $variables, ?int $empresaId = null, ?WhatsAppChannel $channel = null): array
    {
        $params = ['contentSid' => $contentSid];
        if ($variables !== []) {
            $params['contentVariables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
        }

        // message_logs.tipo only knows text|image.
        return $this->deliver($phone, $params, $empresaId, $channel, 'text', "[{$contentSid}] " . ($params['contentVariables'] ?? ''));
    }

    /**
     * The template as Twilio has it, with Meta's approval mapped to pending|approved|rejected.
     *
     * @return array{nome: string, idioma: ?string, categoria: ?string, corpo: ?string, variaveis: list<string>, status: string}
     * @throws \Twilio\Exceptions\TwilioException when the template doesn't exist in the account
     */
    public function fetchContentTemplate(string $contentSid): array
    {
        $contents = $this->client()->content->v1->contents($contentSid);
        $content = $contents->fetch();

        try {
            $whatsapp = (array) ($contents->approvalFetch()->fetch()->whatsapp ?? []);
        } catch (\Twilio\Exceptions\RestException) {
            $whatsapp = []; // never submitted to Meta
        }

        $body = null;
        foreach ((array) $content->types as $type) {
            if (is_array($type) && isset($type['body'])) {
                $body = (string) $type['body'];
                break;
            }
        }

        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', (string) $body, $placeholders);
        $variables = array_values(array_unique(array_map('strval', [
            ...array_keys((array) $content->variables),
            ...$placeholders[1],
        ])));
        sort($variables, SORT_NATURAL);

        return [
            'nome'      => (string) ($content->friendlyName ?: $contentSid),
            'idioma'    => $content->language,
            'categoria' => isset($whatsapp['category']) ? strtolower((string) $whatsapp['category']) : null,
            'corpo'     => $body,
            'variaveis' => $variables,
            'status'    => match ($whatsapp['status'] ?? null) {
                'approved'                       => 'approved',
                'rejected', 'disabled', 'paused' => 'rejected', // none of these can be sent
                default                          => 'pending',
            },
        ];
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

    /**
     * Every send: the empresa's channel, a dialable number, opt-out, and a status callback so
     * delivery errors (e.g. 63016 outside the 24h session) reach the message.
     */
    private function deliver(string $phone, array $params, ?int $empresaId, ?WhatsAppChannel $channel, string $tipo, string $logText): array
    {
        $channel = $this->resolveChannel($channel, $empresaId);
        if (!$channel) {
            return ['success' => false, 'error' => 'no_channel'];
        }

        $empresaId = (int) $channel->empresa_id;

        $phone = $this->dialable($phone, $channel);
        if ($phone === null) {
            return ['success' => false, 'error' => 'invalid_phone'];
        }

        if ($this->isOptedOut($phone, $empresaId)) {
            Log::info('Opted-out lead, skipping send', ['phone' => $phone, 'empresa_id' => $empresaId]);
            return ['success' => false, 'error' => 'opted_out'];
        }

        $params = [
            'from'           => $this->resolveFrom($channel),
            ...$params,
            'statusCallback' => route('webhook.twilio'),
        ];

        try {
            Log::debug('Twilio send', [
                'tipo' => $tipo,
                'to' => "whatsapp:{$phone}",
                'from' => $params['from'],
                'empresa_id' => $empresaId,
                'whatsapp_channel_id' => $channel->id,
            ]);

            $result = $this->createMessage("whatsapp:{$phone}", $params);

            $this->log($empresaId, $phone, $logText, $tipo, 'outbound', 'success', $result);

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            $code = $e instanceof \Twilio\Exceptions\RestException ? (string) $e->getCode() : null;
            Log::error('WhatsApp send failed', ['tipo' => $tipo, 'phone' => $phone, 'code' => $code, 'error' => $e->getMessage()]);
            $this->log($empresaId, $phone, $logText, $tipo, 'outbound', 'failed', ['error' => $e->getMessage(), 'code' => $code]);

            return ['success' => false, 'error' => $e->getMessage(), 'code' => $code];
        }
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
