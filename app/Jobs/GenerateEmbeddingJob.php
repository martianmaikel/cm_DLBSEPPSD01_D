<?php

namespace App\Jobs;

use App\Contracts\EmbeddingProvider;
use App\Models\Embedding;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private readonly string $eventId) {}

    public function handle(EmbeddingProvider $embedder): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            Log::warning('GenerateEmbeddingJob: event not found', ['event_id' => $this->eventId]);

            return;
        }

        $providerName = $embedder->getProviderName();

        // Idempotent: skip if embedding already exists for this event + provider
        if (Embedding::where('event_id', $event->id)->where('provider', $providerName)->exists()) {
            Log::debug('GenerateEmbeddingJob: embedding already exists', [
                'event_id' => $event->id,
                'provider' => $providerName,
            ]);

            return;
        }

        $text = trim($event->title . ' ' . $event->summary);

        if (empty($text)) {
            Log::warning('GenerateEmbeddingJob: empty text, skipping', ['event_id' => $event->id]);

            return;
        }

        try {
            $result = $embedder->generateEmbedding($text);

            // Store embedding with pgvector — vector stored as the native column type
            Embedding::create([
                'event_id' => $event->id,
                'provider' => $result->provider,
                'dimensions' => $result->dimensions,
                // vector column is stored via raw DB insert using pgvector format
            ]);

            // Update vector using raw SQL for pgvector compatibility
            $vectorLiteral = '[' . implode(',', $result->vector) . ']';

            \Illuminate\Support\Facades\DB::statement(
                'UPDATE embeddings SET vector = ? WHERE event_id = ? AND provider = ?',
                [$vectorLiteral, $event->id, $result->provider]
            );

            Log::info('GenerateEmbeddingJob succeeded', [
                'event_id' => $event->id,
                'provider' => $result->provider,
                'dimensions' => $result->dimensions,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateEmbeddingJob failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Allow retry
        }
    }
}
