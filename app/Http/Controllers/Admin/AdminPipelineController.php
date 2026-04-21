<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AdminPipelineController extends Controller
{
    public function status(): JsonResponse
    {
        $lastPollTimes = Source::query()
            ->select('id', 'name', 'type', 'last_polled_at', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn(Source $s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'type'           => $s->type,
                'last_polled_at' => $s->last_polled_at,
                'active'         => $s->active,
            ]);

        $failedJobCount = DB::table('failed_jobs')->count();

        $queueDepth = $this->getQueueDepth();

        return response()->json([
            'sources'          => $lastPollTimes,
            'queue_depth'      => $queueDepth,
            'failed_job_count' => $failedJobCount,
        ]);
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
