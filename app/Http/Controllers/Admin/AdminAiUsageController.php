<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminAiUsageController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->query('days', 30);
        $since = now()->subDays($days)->startOfDay();

        return Inertia::render('Admin/AiUsage', [
            'days' => $days,
            'summary' => $this->summary($since),
            'dailyStats' => $this->dailyStats($since),
            'byProvider' => $this->byProvider($since),
            'byOperation' => $this->byOperation($since),
            'recentErrors' => $this->recentErrors(),
            'hourlyDistribution' => $this->hourlyDistribution($since),
        ]);
    }

    private function summary(\Carbon\Carbon $since): array
    {
        $row = AiUsageLog::where('created_at', '>=', $since)
            ->selectRaw('count(*) as total_requests')
            ->selectRaw('sum(tokens_input) as total_tokens_in')
            ->selectRaw('sum(tokens_output) as total_tokens_out')
            ->selectRaw('sum(estimated_cost) as total_cost')
            ->selectRaw('avg(latency_ms) as avg_latency')
            ->selectRaw('sum(case when status = \'error\' then 1 else 0 end) as error_count')
            ->first();

        $daysElapsed = max(1, (int) $since->diffInDays(now()));

        return [
            'total_requests' => (int) $row->total_requests,
            'total_tokens_in' => (int) $row->total_tokens_in,
            'total_tokens_out' => (int) $row->total_tokens_out,
            'total_cost' => round((float) $row->total_cost, 4),
            'avg_latency_ms' => (int) $row->avg_latency,
            'error_count' => (int) $row->error_count,
            'error_rate' => $row->total_requests > 0
                ? round(($row->error_count / $row->total_requests) * 100, 1)
                : 0,
            'avg_requests_per_day' => round($row->total_requests / $daysElapsed, 1),
            'avg_cost_per_day' => round((float) $row->total_cost / $daysElapsed, 4),
        ];
    }

    private function dailyStats(\Carbon\Carbon $since): array
    {
        return AiUsageLog::where('created_at', '>=', $since)
            ->selectRaw("date(created_at) as date")
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(tokens_input + tokens_output) as tokens')
            ->selectRaw('sum(estimated_cost) as cost')
            ->selectRaw('sum(case when status = \'error\' then 1 else 0 end) as errors')
            ->selectRaw('avg(latency_ms) as avg_latency')
            ->groupByRaw('date(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'requests' => (int) $row->requests,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 4),
                'errors' => (int) $row->errors,
                'avg_latency' => (int) $row->avg_latency,
            ])
            ->toArray();
    }

    private function byProvider(\Carbon\Carbon $since): array
    {
        return AiUsageLog::where('created_at', '>=', $since)
            ->select('provider', 'model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(tokens_input) as tokens_in')
            ->selectRaw('sum(tokens_output) as tokens_out')
            ->selectRaw('sum(estimated_cost) as cost')
            ->selectRaw('avg(latency_ms) as avg_latency')
            ->groupBy('provider', 'model')
            ->orderByDesc('requests')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider,
                'model' => $row->model,
                'requests' => (int) $row->requests,
                'tokens_in' => (int) $row->tokens_in,
                'tokens_out' => (int) $row->tokens_out,
                'cost' => round((float) $row->cost, 4),
                'avg_latency' => (int) $row->avg_latency,
            ])
            ->toArray();
    }

    private function byOperation(\Carbon\Carbon $since): array
    {
        return AiUsageLog::where('created_at', '>=', $since)
            ->select('operation')
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(tokens_input) as tokens_in')
            ->selectRaw('sum(tokens_output) as tokens_out')
            ->selectRaw('sum(estimated_cost) as cost')
            ->selectRaw('avg(latency_ms) as avg_latency')
            ->groupBy('operation')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'operation' => $row->operation,
                'requests' => (int) $row->requests,
                'tokens_in' => (int) $row->tokens_in,
                'tokens_out' => (int) $row->tokens_out,
                'cost' => round((float) $row->cost, 4),
                'avg_latency' => (int) $row->avg_latency,
            ])
            ->toArray();
    }

    private function recentErrors(): array
    {
        return AiUsageLog::where('status', 'error')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['provider', 'model', 'operation', 'error_message', 'latency_ms', 'created_at'])
            ->map(fn ($row) => [
                'provider' => $row->provider,
                'model' => $row->model,
                'operation' => $row->operation,
                'error' => $row->error_message,
                'latency_ms' => $row->latency_ms,
                'at' => $row->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    private function hourlyDistribution(\Carbon\Carbon $since): array
    {
        return AiUsageLog::where('created_at', '>=', $since)
            ->selectRaw("extract(hour from created_at) as hour")
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(estimated_cost) as cost')
            ->groupByRaw("extract(hour from created_at)")
            ->orderBy('hour')
            ->get()
            ->map(fn ($row) => [
                'hour' => (int) $row->hour,
                'requests' => (int) $row->requests,
                'cost' => round((float) $row->cost, 4),
            ])
            ->toArray();
    }
}
