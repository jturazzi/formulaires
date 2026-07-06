<?php

namespace App\Console\Commands;

use App\Models\Form;
use Illuminate\Console\Command;

class PurgeExpiredResponses extends Command
{
    protected $signature = 'responses:purge {--dry-run : List what would be deleted without deleting}';

    protected $description = 'GDPR retention: delete responses (and their files) older than each form\'s retention period';

    public function handle(): int
    {
        $purged = 0;

        Form::query()->with('user')->lazy()->each(function (Form $form) use (&$purged) {
            $cutoff = now()->subDays($form->effectiveRetentionDays());

            $expired = $form->responses()
                ->with('answers')
                ->where('submitted_at', '<', $cutoff)
                ->get();

            foreach ($expired as $response) {
                if ($this->option('dry-run')) {
                    $this->line("Would purge response #{$response->id} of form \"{$form->title}\" (submitted {$response->submitted_at})");
                } else {
                    $response->purge();
                }

                $purged++;
            }
        });

        $this->info(($this->option('dry-run') ? 'Would purge ' : 'Purged ').$purged.' response(s).');

        return self::SUCCESS;
    }
}
