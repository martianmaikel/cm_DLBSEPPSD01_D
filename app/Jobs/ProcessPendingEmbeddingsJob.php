<?php

namespace App\Jobs;

use App\Contracts\EmbeddingProvider;
use App\Models\Embedding;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPendingEmbeddingsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    private const BATCH_SIZE = 20;

    public function handle(EmbeddingProvider $embedder): void
    {
        $providerName = $embedder->getProviderName();

        $events = Event::query()
            ->whereNotIn('status', ['pending_classification'])
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->whereDoesntHave('embedding', fn ($q) => $q->where('provider', $providerName))
            ->orderBy('created_at')
            ->limit(self::BATCH_SIZE)
            ->get(['id', 'title', 'summary']);

        if ($events->isEmpty()) {
            return;
        }

        $texts = $events->map(fn (Event $e) => trim($e->title . ' ' . $e->summary))->toArray();
        $eventIds = $events->pluck('id')->toArray();

        try {
            $results = $embedder->generateBatchEmbeddings($texts);

            if (count($results) !== count($eventIds)) {
                Log::error('ProcessPendingEmbeddingsJob: result count mismatch', [
                    'expected' => count($eventIds),
                    'got' => count($results),
                ]);

                return;
            }

            foreach ($results as $i => $result) {
                $eventId = $eventIds[$i];

                // Skip if embedding was created between query and now (race condition)
                if (Embedding::where('event_id', $eventId)->where('provider', $providerName)->exists()) {
                    continue;
                }

                Embedding::create([
                    'event_id' => $eventId,
                    'provider' => $result->provider,
                    'dimensions' => $result->dimensions,
                ]);

                $vectorLiteral = '[' . implode(',', $result->vector) . ']';

                DB::statement(
                    'UPDATE embeddings SET vector = ? WHERE event_id = ? AND provider = ?',
                    [$vectorLiteral, $eventId, $result->provider]
                );
            }

            Log::info('ProcessPendingEmbeddingsJob: batch completed', [
                'count' => count($eventIds),
                'provider' => $providerName,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessPendingEmbeddingsJob: batch failed', [
                'error' => $e->getMessage(),
                'count' => count($eventIds),
            ]);
        }
    }
}
