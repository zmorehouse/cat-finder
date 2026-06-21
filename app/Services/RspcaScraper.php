<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RspcaScraper
{
    /**
     * Fetch the current cat/kitten adoption listing from RSPCA ACT.
     *
     * The public page (https://rspca-act.org.au/adopt-pet) renders its
     * "grid-item" cards client-side from this JSON endpoint, so we hit it
     * directly. Each returned animal is normalised into a flat array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAnimals(): array
    {
        $base = rtrim((string) config('catfinder.base_url'), '/');
        $url = $base.config('catfinder.api_path');

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; CatFinder/1.0; +adoption-watcher)',
            'Referer' => $base.'/adopt-pet?field_animal_type=cat%3Bkitten&field_location=All',
        ])->timeout(30)->retry(2, 1000)->get($url, [
            'AnimalStatus' => config('catfinder.animal_status'),
            'AnimalType' => config('catfinder.animal_type'),
            'Limit' => config('catfinder.limit'),
            'timeStamp' => (int) (microtime(true) * 1000),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "RSPCA API request failed with status {$response->status()}"
            );
        }

        $animals = $response->json('data.animals');

        if (! is_array($animals)) {
            Log::warning('RSPCA API returned no animals array', [
                'body' => $response->body(),
            ]);

            return [];
        }

        return array_map(fn (array $a) => $this->normalise($a), $animals);
    }

    /**
     * Flatten a raw AnimalOS record into the fields we care about.
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    protected function normalise(array $a): array
    {
        $base = rtrim((string) config('catfinder.base_url'), '/');
        $id = $a['Id'] ?? null;

        return [
            'external_id' => $id,
            'name' => $a['animalos__Animal_Name__c'] ?? ($a['Name'] ?? 'Unknown'),
            'type' => $a['animalos__Type__c'] ?? null,
            'breed' => data_get($a, 'animalos__Primary_Breed__r.Name'),
            'age' => $a['Age_for_Adoption_Website__c'] ?? null,
            'site' => data_get($a, 'animalos__Current_Site__r.Name'),
            'status' => $a['animalos__Status__c'] ?? null,
            'url' => $id ? "{$base}/pet/{$id}" : $base.'/adopt-pet',
        ];
    }
}
