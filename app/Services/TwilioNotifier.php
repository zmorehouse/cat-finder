<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioNotifier
{
    /**
     * Send an SMS via the Vonage REST API.
     *
     * @return array{sid: ?string, status: string, error: ?string}
     */
    public function sendSms(string $body, ?string $to = null): array
    {
        $apiKey = config('catfinder.vonage.api_key');
        $apiSecret = config('catfinder.vonage.api_secret');
        $from = config('catfinder.vonage.from');
        $to = $to ?: config('catfinder.vonage.to');

        if (! $apiKey || ! $apiSecret || ! $from || ! $to) {
            throw new RuntimeException(
                'Vonage is not fully configured. Set VONAGE_API_KEY, VONAGE_API_SECRET, VONAGE_FROM and VONAGE_TO.'
            );
        }

        $response = Http::timeout(30)
            ->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'from' => $from,
                'to' => $to,
                'text' => $body,
            ]);

        if (! $response->successful()) {
            return [
                'sid' => null,
                'status' => 'failed',
                'error' => $response->body(),
            ];
        }

        $message = $response->json('messages.0') ?? [];
        $errorCode = (string) ($message['status'] ?? '0');

        if ($errorCode !== '0') {
            return [
                'sid' => null,
                'status' => 'failed',
                'error' => $message['error-text'] ?? "Vonage error code {$errorCode}",
            ];
        }

        return [
            'sid' => $message['message-id'] ?? null,
            'status' => 'sent',
            'error' => null,
        ];
    }
}
