<?php

namespace App\Services;

use App\Models\AdoptionAnimal;
use App\Models\NotificationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        $seenIds = [];

        // Upsert everything we just saw.
        foreach ($animals as $animal) {
            if (empty($animal['external_id'])) {
                continue;
            }

            $seenIds[] = $animal['external_id'];

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
                    'removed_at' => null,
                ]
            );
        }

        // Mark anything we didn't see this run as removed.
        if (! empty($seenIds)) {
            AdoptionAnimal::whereNotIn('external_id', $seenIds)
                ->whereNull('removed_at')
                ->update(['removed_at' => Carbon::now()]);
        }

        // Stamp first_seen_at on any brand-new rows.
        AdoptionAnimal::whereNull('first_seen_at')->update(['first_seen_at' => Carbon::now()]);

        // First ever run: establish a baseline silently (unless configured otherwise).
        if ($wasEmpty && ! config('catfinder.notify_on_first_run')) {
            AdoptionAnimal::query()->update(['notified' => true]);

            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => config('catfinder.vonage.to'),
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

        if (! Cache::get('notifications_enabled', true)) {
            return [
                'fetched' => count($animals),
                'new' => $pending->count(),
                'notified' => false,
                'message' => 'Found '.$pending->count().' new animal(s) but notifications are disabled.',
            ];
        }

        return $this->notify($pending->all(), count($animals));
    }

    /**
     * Send one SMS per new animal to each recipient and record the outcome.
     *
     * @param  array<int, AdoptionAnimal>  $newAnimals
     * @return array<string, mixed>
     */
    protected function notify(array $newAnimals, int $fetched): array
    {
        $recipients = config('catfinder.vonage.recipients') ?: [];

        if (empty($recipients)) {
            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => null,
                'status' => 'failed',
                'body' => 'No VONAGE_TO recipient configured.',
                'error' => 'No VONAGE_TO recipient configured.',
                'animal_count' => count($newAnimals),
                'animals' => [],
            ]);

            return [
                'fetched' => $fetched,
                'new' => count($newAnimals),
                'notified' => false,
                'message' => 'Found '.count($newAnimals).' new but no recipient is configured.',
            ];
        }

        $anySent = false;

        foreach ($newAnimals as $animal) {
            $body = $this->buildMessage($animal);
            $snapshot = $animal->only(['external_id', 'name', 'type', 'breed', 'age', 'site', 'url']);
            $animalSent = false;

            foreach ($recipients as $recipient) {
                try {
                    $result = $this->notifier->sendSms($body, $recipient);
                } catch (Throwable $e) {
                    Log::error('Vonage send failed', ['recipient' => $recipient, 'error' => $e->getMessage()]);
                    $result = ['sid' => null, 'status' => 'failed', 'error' => $e->getMessage()];
                }

                $sent = $result['status'] !== 'failed';
                $anySent = $anySent || $sent;
                $animalSent = $animalSent || $sent;

                NotificationLog::create([
                    'channel' => 'sms',
                    'recipient' => $recipient,
                    'status' => $sent ? 'sent' : 'failed',
                    'body' => $body,
                    'provider_sid' => $result['sid'],
                    'error' => $result['error'],
                    'animal_count' => 1,
                    'animals' => [$snapshot],
                ]);
            }

            if ($animalSent) {
                $animal->update(['notified' => true]);
            }
        }

        return [
            'fetched' => $fetched,
            'new' => count($newAnimals),
            'notified' => $anySent,
            'message' => $anySent
                ? 'Texted '.count($recipients).' recipient(s) about '.count($newAnimals).' new cat(s)/kitten(s).'
                : 'Found '.count($newAnimals).' new but all sends failed.',
        ];
    }

    protected function buildMessage(AdoptionAnimal $animal): string
    {
        $details = collect([$animal->type, $animal->age, $animal->site])->filter()->implode(', ');

        return implode("\n", [
            "\u{1F431} New cat/kitten at RSPCA ACT:",
            "{$animal->name} ({$details})",
            $animal->url,
        ]);
    }
}
