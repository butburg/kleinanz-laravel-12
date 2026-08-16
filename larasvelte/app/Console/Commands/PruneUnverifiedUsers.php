<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--hours=24 : Delete accounts older than this many hours}';

    protected $description = 'Delete accounts that have not verified their email address';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $deleted = User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("Deleted {$deleted} unverified user(s) older than {$hours} hours.");

        return self::SUCCESS;
    }
}
