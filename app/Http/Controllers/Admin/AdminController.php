<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $byStatus = Event::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalEvents = array_sum($byStatus);
        $totalSources = Source::count();
        $activeSources = Source::where('active', true)->count();
        $pendingClassification = Event::pendingClassification()->count();
        $queueDepth = $this->getQueueDepth();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_events'           => $totalEvents,
                'total_sources'          => $totalSources,
                'active_sources'         => $activeSources,
                'pending_classification' => $pendingClassification,
                'queue_depth'            => max($queueDepth, 0),
                'by_status'              => $byStatus,
            ],
            'pipeline' => $this->getPipelineStatus($pendingClassification, $queueDepth),
        ]);
    }

    private function getPipelineStatus(int $pendingClassification, int $queueDepth): array
    {
        $redisOk = $this->checkRedis();
        $hasActiveRss = Source::where('active', true)->where('type', 'rss')->exists();
        $hasActiveTelegram = Source::where('active', true)->where('type', 'telegram')->exists();

        return [
            'redis'          => $redisOk ? 'ok' : 'error',
            'rss'            => $hasActiveRss ? 'running' : 'inactive',
            'telegram'       => $hasActiveTelegram ? 'running' : 'inactive',
            'classification' => $this->classificationStatus($pendingClassification),
            'corroboration'  => $redisOk ? 'ok' : 'error',
            'threading'      => $redisOk ? 'ok' : 'error',
            'reconciliation' => $redisOk ? 'ok' : 'error',
        ];
    }

    private function classificationStatus(int $pending): string
    {
        if ($pending === 0) return 'ok';
        if ($pending <= 50) return 'running';
        return 'degraded';
    }

    private function checkRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getQueueDepth(): int
    {
        try {
            return (int) Redis::llen('queues:default')
                + (int) Redis::llen('queues:geocoding');
        } catch (\Throwable) {
            return -1;
        }
    }
}
