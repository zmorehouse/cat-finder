# Cat Finder — RSPCA ACT adoption watcher

A small Laravel app that checks the [RSPCA ACT cat & kitten adoption listings](https://rspca-act.org.au/adopt-pet?field_animal_type=cat%3Bkitten&field_location=All)
**every hour** and sends you an **SMS via Twilio** whenever a new cat or kitten appears.

The frontend is a simple dashboard showing the log of notifications sent and the animals currently being tracked.

## How it works

The public adoption page renders its `grid-item` cards client-side from a JSON API
(`/api/animal_os/animals`), so instead of scraping rendered HTML we query that API
directly. It's more reliable and gives each animal a stable id.

Each hour the app:

1. Fetches the current cat/kitten listing.
2. Compares it against animals it has already seen (stored in the database).
3. For any **new** animals, sends you an SMS and records it in the notification log.

On the **first run** it just records a baseline (no text) so you aren't spammed about
every existing animal. Flip `CATFINDER_NOTIFY_FIRST_RUN=true` if you want the first run
to text you too.

If an SMS fails (e.g. Twilio misconfigured), the animal stays "unnotified" and is retried
on the next run.

## Key files

| Path | Purpose |
| --- | --- |
| `app/Services/RspcaScraper.php` | Fetches & normalises the RSPCA JSON API |
| `app/Services/TwilioNotifier.php` | Sends SMS via the Twilio REST API |
| `app/Services/AdoptionWatcher.php` | Diff logic: detect new animals, notify, log |
| `app/Console/Commands/CheckAdoptions.php` | `php artisan app:check-adoptions` |
| `routes/console.php` | Hourly schedule |
| `app/Http/Controllers/DashboardController.php` + `resources/views/dashboard.blade.php` | Dashboard / log UI |
| `config/catfinder.php` | All configurable settings |

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# Fill in your Twilio details in .env, then run a check manually:
php artisan app:check-adoptions

# Or start the dashboard:
php artisan serve
```

Visit `http://localhost:8000` for the dashboard, which has a **Run check now** button.

To actually exercise the hourly schedule locally, run:

```bash
php artisan schedule:work
```

## Environment variables

| Variable | Required | Description |
| --- | --- | --- |
| `TWILIO_ACCOUNT_SID` | yes | From the Twilio console |
| `TWILIO_AUTH_TOKEN` | yes | From the Twilio console |
| `TWILIO_FROM` | yes | A Twilio phone number (E.164, e.g. `+1...`) |
| `TWILIO_TO` | yes | Your mobile number (E.164, e.g. `+61...`) |
| `CATFINDER_NOTIFY_FIRST_RUN` | no | `true` to text on the first run too (default `false`) |
| `RSPCA_ANIMAL_TYPE` | no | Defaults to `cat;kitten` |
| `RSPCA_LIMIT` | no | Records to pull per request (default `100`) |

## Deploying to Railway

1. Push this repo to GitHub and create a new Railway project from it.
2. Railway will build it with Nixpacks (PHP is auto-detected). The start command is
   defined in `railway.json` and runs `start.sh`, which:
   - runs migrations,
   - starts the hourly scheduler (`php artisan schedule:work`),
   - serves the dashboard on `$PORT`.
3. In the service **Variables**, set at minimum:
   - `APP_KEY` — generate one locally with `php artisan key:generate --show` (optional;
     `start.sh` generates an ephemeral one if omitted, but a fixed value keeps sessions stable).
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM`, `TWILIO_TO`
4. **Persist the database (recommended).** The default SQLite file lives on the
   container's ephemeral disk and resets on each redeploy. To keep your "seen animals"
   history across deploys, add a Railway **Volume** mounted at `/data` and set:
   - `DB_DATABASE=/data/database.sqlite`

   (Or attach a Railway Postgres plugin and set `DB_CONNECTION=pgsql` plus the usual
   `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`.)

That's it — the scheduler runs inside the web container, so a single Railway service
covers both the hourly check and the dashboard.

## Notes

- The dashboard uses Laravel's built-in `php artisan serve`, which is fine for a personal,
  low-traffic app. Swap in nginx/php-fpm or Octane if you need more throughput.
- Twilio trial accounts can only text verified numbers and prepend a trial banner.
