<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCategoriesCommand extends Command
{
    protected $signature = 'events:migrate-categories';

    protected $description = 'Migrate existing event categories to the new 7-category system with subcategories';

    private const CATEGORY_MAP = [
        'airstrike' => ['category' => 'war', 'subcategory' => 'airstrike'],
        'artillery' => ['category' => 'war', 'subcategory' => 'artillery'],
        'troop_movement' => ['category' => 'war', 'subcategory' => 'troop_movement'],
        'naval' => ['category' => 'war', 'subcategory' => 'naval_operation'],
        'infrastructure' => ['category' => 'war', 'subcategory' => 'infrastructure_attack'],
        'humanitarian' => ['category' => 'disaster', 'subcategory' => 'humanitarian_crisis'],
        'diplomatic' => ['category' => 'diplomacy', 'subcategory' => null],
        'cyber' => ['category' => 'cyber', 'subcategory' => null],
        'protest' => ['category' => 'protest', 'subcategory' => null],
        'other' => ['category' => 'war', 'subcategory' => 'other'],
    ];

    public function handle(): int
    {
        $this->info('Migrating event categories...');

        $total = 0;

        foreach (self::CATEGORY_MAP as $old => $new) {
            $count = DB::table('events')
                ->where('category', $old)
                ->update([
                    'subcategory' => $new['subcategory'],
                    'category' => $new['category'],
                ]);

            if ($count > 0) {
                $this->line("  {$old} → {$new['category']}/{$new['subcategory']}: {$count} events");
                $total += $count;
            }
        }

        $this->info("Migration complete. {$total} events updated.");

        return self::SUCCESS;
    }
}
