<?php

namespace App\Console\Commands;

use App\Jobs\EnrichActorJob;
use App\Models\Actor;
use Illuminate\Console\Command;

class EnrichActorCommand extends Command
{
    protected $signature = 'actors:enrich
        {id? : Actor UUID or slug. If omitted, --all-failed or --all-pending must be set.}
        {--all-failed : Re-enrich all actors with status=failed}
        {--all-pending : Enrich all actors with status=pending}
        {--sync : Run synchronously in this process (shows errors immediately)}';

    protected $description = 'Dispatch actor enrichment from the terminal for debugging or bulk retry';

    public function handle(): int
    {
        if ($this->option('all-failed') || $this->option('all-pending')) {
            $status = $this->option('all-failed') ? 'failed' : 'pending';
            $actors = Actor::where('enrichment_status', $status)->get();

            if ($actors->isEmpty()) {
                $this->info("No actors with enrichment_status={$status}.");
                return self::SUCCESS;
            }

            $this->info("Dispatching enrichment for {$actors->count()} actor(s) [{$status}]");
            foreach ($actors as $actor) {
                $this->runOne($actor);
            }

            return self::SUCCESS;
        }

        $id = $this->argument('id');
        if (! $id) {
            $this->error('Provide an actor ID/slug or use --all-failed / --all-pending');
            return self::INVALID;
        }

        $actor = Actor::where('id', $id)->orWhere('slug', $id)->first();
        if (! $actor) {
            $this->error("Actor not found: {$id}");
            return self::FAILURE;
        }

        $this->runOne($actor);

        return self::SUCCESS;
    }

    private function runOne(Actor $actor): void
    {
        $this->line("→ {$actor->canonical_name} ({$actor->actor_type}, {$actor->id})");

        $actor->update(['enrichment_status' => 'pending']);

        if ($this->option('sync')) {
            try {
                EnrichActorJob::dispatchSync($actor->id);
                $fresh = $actor->fresh();
                $this->info("  ✓ status={$fresh->enrichment_status}  mode={$fresh->enrichment_mode_used}");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$e->getMessage()}");
                $this->line("    " . $e->getFile() . ':' . $e->getLine());
            }
        } else {
            EnrichActorJob::dispatch($actor->id);
            $this->info('  ↪ queued');
        }
    }
}
