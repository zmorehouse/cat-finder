<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioNotifier
{
    /**
     * Send an SMS via the Twilio REST API.
     *
     * Uses a plain authenticated HTTP request so we don't need the Twilio SDK
     * as a dependency.
     *
     * @return array{sid: ?string, status: string, error: ?string}
     */
    public function sendSms(string $body, ?string $to = null): array
    {
        $sid = config('catfinder.twilio.sid');
        $token = config('catfinder.twilio.token');
        $from = config('catfinder.twilio.from');
        $to = $to ?: config('catfinder.twilio.to');

        if (! $sid || ! $token || ! $from || ! $to) {
            throw new RuntimeException(
                'Twilio is not fully configured. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM and TWILIO_TO.'
            );
        }

        /** @var Response $response */
        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(30)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $body,
            ]);

        if (! $response->successful()) {
            return [
                'sid' => null,
                'status' => 'failed',
                'error' => $response->json('message') ?? $response->body(),
            ];
        }

        return [
            'sid' => $response->json('sid'),
            'status' => $response->json('status', 'sent'),
            'error' => null,
        ];
    }
}
