<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RSPCA ACT source
    |--------------------------------------------------------------------------
    |
    | The public adoption listing is rendered client-side from this JSON API.
    | We query it directly instead of scraping rendered HTML, which is more
    | reliable and gives us a stable unique id per animal.
    */
    'base_url' => env('RSPCA_BASE_URL', 'https://rspca-act.org.au'),

    'api_path' => env('RSPCA_API_PATH', '/api/animal_os/animals'),

    // Matches the site's "cat;kitten" filter from the adopt-pet page.
    'animal_type' => env('RSPCA_ANIMAL_TYPE', 'cat;kitten'),

    // Same status set the website uses for the public listing.
    'animal_status' => env(
        'RSPCA_ANIMAL_STATUS',
        'Available For Adoption;Available for Adoption-In Foster;Available for Adoption-No web Prescence;Available for Adoption-Offsite;Trail adoption'
    ),

    // How many records to pull per request (site default is 6, we pull more).
    'limit' => (int) env('RSPCA_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Vonage
    |--------------------------------------------------------------------------
    */
    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        // The name or number you send FROM (e.g. "CatFinder" or +61...).
        'from' => env('VONAGE_FROM', 'CatFinder'),
        // The number(s) you receive the alert ON. Supports a comma-separated
        // list, e.g. VONAGE_TO="+61400000000,+61422717726".
        'to' => env('VONAGE_TO'),
        'recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('VONAGE_TO'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Behaviour
    |--------------------------------------------------------------------------
    |
    | On the very first run the database is empty. We don't want to text you
    | about every existing animal, so the first run just records a baseline.
    */
    'notify_on_first_run' => (bool) env('CATFINDER_NOTIFY_FIRST_RUN', false),
];
