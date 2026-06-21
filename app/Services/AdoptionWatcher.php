<?php

namespace App\Services;

use App\Models\AdoptionAnimal;
use App\Models\NotificationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdoptionWatcher
{
    public function __construct(
        protected RspcaScraper $scraper,
        protected TwilioNotifier $notifier,
    ) {}

    /**
     * Run a full check: scrape, detect new animals, notify, and log.
     *
     * @return array<string, mixed> Summary of what happened.
     */
    public function run(): array
    {
        $animals = $this->scraper->fetchAnimals();

        // Guard against a transient empty response wiping our notion of state.
        if (empty($animals)) {
            return [
                'fetched' => 0,
                'new' => 0,
                'notified' => false,
                'message' => 'No animals returned from source (skipping).',
            ];
        }

        $wasEmpty = AdoptionAnimal::count() === 0;

        // Upsert everything we just saw.
        foreach ($animals as $animal) {
            if (empty($animal['external_id'])) {
                continue;
            }

            AdoptionAnimal::updateOrCreate(
                ['external_id' => $animal['external_id']],
                [
                    'name' => $animal['name'],
                    'type' => $animal['type'],
                    'breed' => $animal['breed'],
                    'age' => $animal['age'],
                    'site' => $animal['site'],
                    'status' => $animal['status'],
                    'url' => $animal['url'],
                    // Note: we intentionally don't touch `notified` here so an
                    // existing animal keeps its state across runs.
                ]
            );
        }

        // Stamp first_seen_at on any brand-new rows.
        AdoptionAnimal::whereNull('first_seen_at')->update(['first_seen_at' => Carbon::now()]);

        // First ever run: establish a baseline silently (unless configured otherwise).
        if ($wasEmpty && ! config('catfinder.notify_on_first_run')) {
            AdoptionAnimal::query()->update(['notified' => true]);

            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => config('catfinder.twilio.to'),
                'status' => 'skipped',
                'body' => 'Baseline established: now tracking '.count($animals).' cat(s)/kitten(s). No alert sent for existing listings.',
                'animal_count' => count($animals),
                'animals' => $animals,
            ]);

            return [
                'fetched' => count($animals),
                'new' => 0,
                'notified' => false,
                'message' => 'Baseline established ('.count($animals).' animals). No alert sent.',
            ];
        }

        // Anything not yet notified is genuinely new (or a failed retry).
        $pending = AdoptionAnimal::where('notified', false)->get();

        if ($pending->isEmpty()) {
            return [
                'fetched' => count($animals),
                'new' => 0,
                'notified' => false,
                'message' => 'No new animals since last check.',
            ];
        }

        return $this->notify($pending->all(), count($animals));
    }

    /**
     * Send the SMS for the given new animals and record the outcome.
     *
     * @param  array<int, AdoptionAnimal>  $newAnimals
     * @return array<string, mixed>
     */
    protected function notify(array $newAnimals, int $fetched): array
    {
        $body = $this->buildMessage($newAnimals);
        $snapshot = array_map(fn (AdoptionAnimal $a) => $a->only([
            'external_id', 'name', 'type', 'breed', 'age', 'site', 'url',
        ]), $newAnimals);

        $recipients = config('catfinder.twilio.recipients') ?: [];

        // No recipients configured at all: record one failure and bail.
        if (empty($recipients)) {
            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => null,
                'status' => 'failed',
                'body' => $body,
                'error' => 'No TWILIO_TO recipient configured.',
                'animal_count' => count($newAnimals),
                'animals' => $snapshot,
            ]);

            return [
                'fetched' => $fetched,
                'new' => count($newAnimals),
                'notified' => false,
                'message' => 'Found '.count($newAnimals).' new but no recipient is configured.',
            ];
        }

        $anySent = false;
        $perRecipient = [];

        foreach ($recipients as $recipient) {
            try {
                $result = $this->notifier->sendSms($body, $recipient);
            } catch (Throwable $e) {
                Log::error('Twilio send failed', ['recipient' => $recipient, 'error' => $e->getMessage()]);
                $result = ['sid' => null, 'status' => 'failed', 'error' => $e->getMessage()];
            }

            $sent = $result['status'] !== 'failed';
            $anySent = $anySent || $sent;
            $perRecipient[] = $recipient.': '.($sent ? 'sent' : 'failed');

            // One log row per recipient so each number's status is visible.
            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => $recipient,
                'status' => $sent ? 'sent' : 'failed',
                'body' => $body,
                'provider_sid' => $result['sid'],
                'error' => $result['error'],
                'animal_count' => count($newAnimals),
                'animals' => $snapshot,
            ]);
        }

        // Mark animals as notified if at least one recipient got the text, so a
        // single misconfigured number doesn't trigger hourly re-texting forever.
        if ($anySent) {
            AdoptionAnimal::whereIn('id', array_map(fn ($a) => $a->id, $newAnimals))
                ->update(['notified' => true]);
        }

        return [
            'fetched' => $fetched,
            'new' => count($newAnimals),
            'notified' => $anySent,
            'message' => $anySent
                ? 'Texted '.count($recipients).' recipient(s) about '.count($newAnimals).' new cat(s)/kitten(s).'
                : 'Found '.count($newAnimals).' new but all sends failed ('.implode('; ', $perRecipient).').',
        ];
    }

    /**
     * Build a concise SMS body listing the new animals.
     *
     * @param  array<int, AdoptionAnimal>  $animals
     */
    protected function buildMessage(array $animals): string
    {
        $count = count($animals);
        $noun = $count === 1 ? 'cat/kitten' : 'cats/kittens';
        $lines = ["\u{1F431} {$count} new {$noun} at RSPCA ACT:"];

        $shown = array_slice($animals, 0, 5);
        foreach ($shown as $a) {
            $details = array_filter([$a->type, $a->age, $a->site]);
            $lines[] = "- {$a->name} (".implode(', ', $details).")\n  {$a->url}";
        }

        if ($count > count($shown)) {
            $lines[] = '...and '.($count - count($shown)).' more.';
        }

        return implode("\n", $lines);
    }
}
