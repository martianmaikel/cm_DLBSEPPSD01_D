<?php

namespace Database\Seeders;

use App\Models\SourceFamily;
use Illuminate\Database\Seeder;

class SourceFamilySeeder extends Seeder
{
    public function run(): void
    {
        $families = [
            // OSINT / Telegram
            ['name' => 'Intel Slava', 'editorial_ownership' => 'Independent / Anonymous', 'description' => 'Pro-Russian OSINT Telegram channel'],
            ['name' => 'Rybar', 'editorial_ownership' => 'Independent / Anonymous', 'description' => 'Russian military analysis Telegram channel'],
            ['name' => 'Middle East Spectator', 'editorial_ownership' => 'Independent / Anonymous', 'description' => 'Middle East OSINT Telegram channel'],
            ['name' => 'Intel Republic', 'editorial_ownership' => 'Independent / Anonymous', 'description' => 'OSINT aggregator Telegram channel'],
            ['name' => 'Clash Report', 'editorial_ownership' => 'Independent / Anonymous', 'description' => 'Multi-conflict OSINT Telegram channel with high post volume'],
            ['name' => 'OSINT Defender', 'editorial_ownership' => 'Independent / Anonymous', 'description' => 'Western OSINT Telegram channel covering global conflicts'],
            ['name' => 'Al Jazeera', 'editorial_ownership' => 'Al Jazeera Media Network', 'description' => 'Qatar-based international news network — Telegram channel'],

            // Conflict & Event Databases
            ['name' => 'ACLED', 'editorial_ownership' => 'Armed Conflict Location & Event Data Project', 'description' => 'Conflict event database with API access'],
            ['name' => 'GDELT', 'editorial_ownership' => 'GDELT Project / Google Jigsaw', 'description' => 'Global event database monitoring worldwide media'],

            // Humanitarian & Diplomacy
            ['name' => 'ReliefWeb', 'editorial_ownership' => 'UN OCHA', 'description' => 'United Nations Office for the Coordination of Humanitarian Affairs'],
            ['name' => 'UN Security Council', 'editorial_ownership' => 'United Nations', 'description' => 'UN Security Council official communications'],

            // OSINT Analysis
            ['name' => 'ISW', 'editorial_ownership' => 'Institute for the Study of War', 'description' => 'US-based defense think tank'],
            ['name' => 'Liveuamap', 'editorial_ownership' => 'Liveuamap LLC', 'description' => 'OSINT conflict mapping service'],
        ];

        foreach ($families as $family) {
            SourceFamily::firstOrCreate(['name' => $family['name']], $family);
        }
    }
}
