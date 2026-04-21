<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillEventSlugsCommand extends Command
{
    protected $signature = 'events:backfill-slugs';

    protected $description = 'Generate slugs for all events that do not have one';

    public function handle(): int
    {
        $count = 0;

        Event::whereNull('slug')
            ->whereNotNull('title')
            ->chunkById(500, function ($events) use (&$count) {
                foreach ($events as $event) {
                    $base = Str::slug(Str::limit($event->title, 80, '')) ?: 'event';
                    $date = $event->occurred_at?->format('d-m-Y') ?? $event->created_at->format('d-m-Y');
                    $event->slug = "{$base}-{$date}";
                    $event->saveQuietly();
                    $count++;
                }
            });

        $this->info("Generated slugs for {$count} events.");

        return self::SUCCESS;
    }
}
