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
    | Twilio
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        // The number you send FROM (a Twilio number, e.g. +61...).
        'from' => env('TWILIO_FROM'),
        // The number you receive the alert ON.
        'to' => env('TWILIO_TO'),
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
