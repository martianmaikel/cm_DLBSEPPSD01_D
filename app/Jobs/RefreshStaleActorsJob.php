<?php

namespace App\Jobs;

use App\Models\Actor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshStaleActorsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(): void
    {
        $days = (int) config('actors.refresh_after_days', 30);

        $stale = Actor::stale($days)->pluck('id');

        if ($stale->isEmpty()) {
            Log::debug('RefreshStaleActorsJob: no stale actors');
            return;
        }

        foreach ($stale as $id) {
            EnrichActorJob::dispatch($id);
        }

        Log::info('RefreshStaleActorsJob: dispatched re-enrichments', [
            'count' => $stale->count(),
            'days' => $days,
        ]);
    }
}
