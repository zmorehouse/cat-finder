<?php

namespace App\Console\Commands;

use App\Services\AdoptionWatcher;
use Illuminate\Console\Command;
use Throwable;

class CheckAdoptions extends Command
{
    protected $signature = 'app:check-adoptions';

    protected $description = 'Scrape RSPCA ACT cat/kitten listings and text on new arrivals';

    public function handle(AdoptionWatcher $watcher): int
    {
        $this->info('Checking RSPCA ACT adoption listings...');

        try {
            $summary = $watcher->run();
        } catch (Throwable $e) {
            $this->error('Check failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fetched: %d | New: %d | Notified: %s',
            $summary['fetched'],
            $summary['new'],
            $summary['notified'] ? 'yes' : 'no',
        ));
        $this->line($summary['message']);

        return self::SUCCESS;
    }
}
