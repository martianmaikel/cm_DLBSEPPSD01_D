<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\SourceFamily;
use App\Services\Ingestion\ApiConnectors\AcledConnector;
use App\Services\Ingestion\ApiConnectors\GdeltConnector;
use App\Services\Ingestion\ApiConnectors\GdeltGeoConnector;
use App\Services\Ingestion\ApiConnectors\ReliefWebConnector;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder {
    public function run(): void {
        $rsshub = rtrim(config('services.rsshub.base_url', 'http://localhost:1200'), '/');

        $sources = [
            // ─── OSINT / TELEGRAM CHANNELS (via local RSSHub) ─────
            [
                'name' => 'Intel Slava Z',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/intelslavaz',
                'family' => 'Intel Slava',
                'polling_interval' => 5,
                'reliability_score' => 0.40,
            ],
            [
                'name' => 'Rybar',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/rybar',
                'family' => 'Rybar',
                'polling_interval' => 5,
                'reliability_score' => 0.50,
            ],
            [
                'name' => 'Middle East Spectator',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/Middle_East_Spectator',
                'family' => 'Middle East Spectator',
                'polling_interval' => 5,
                'reliability_score' => 0.45,
            ],
            [
                'name' => 'Intel Republic',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/IntelRepublic',
                'family' => 'Intel Republic',
                'polling_interval' => 5,
                'reliability_score' => 0.45,
            ],
            [
                'name' => 'Clash Report',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/ClashReport',
                'family' => 'Clash Report',
                'polling_interval' => 5,
                'reliability_score' => 0.55,
            ],
            [
                'name' => 'OSINT Defender',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/OSINTdefender',
                'family' => 'OSINT Defender',
                'polling_interval' => 5,
                'reliability_score' => 0.55,
            ],
            [
                'name' => 'Al Jazeera News',
                'type' => 'rss',
                'url' => $rsshub . '/telegram/channel/AJENews_Official',
                'family' => 'Al Jazeera',
                'polling_interval' => 5,
                'reliability_score' => 0.80,
            ],

            // ─── CONFLICT DATABASES (API) ─────────────────────────
            [
                'name' => 'ACLED Conflict Events',
                'type' => 'api',
                'url' => 'https://acleddata.com/api/acled/read',
                'family' => 'ACLED',
                'polling_interval' => 1440,
                'reliability_score' => 0.85,
                'connector_class' => AcledConnector::class,
                'connector_config' => [
                    'limit' => 100,
                    'days_back' => 1,
                ],
                'active' => false, // Requires registration — activate after setup
            ],
            [
                'name' => 'GDELT Global Conflicts',
                'type' => 'api',
                'url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
                'family' => 'GDELT',
                'polling_interval' => 15,
                'reliability_score' => 0.70,
                'connector_class' => GdeltConnector::class,
                'connector_config' => [
                    'query' => '(theme:MILITARY OR theme:TERROR OR theme:KILL OR theme:ARMED_CONFLICT) tone<-5',
                    'timespan' => '1h',
                    'max_records' => 50,
                    'sort' => 'datedesc',
                ],
            ],

            // ─── GDELT GEO — News Context per Conflict Region ────
            [
                'name' => 'GDELT GEO: Ukraine',
                'type' => 'api',
                'url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
                'family' => 'GDELT',
                'polling_interval' => 60,
                'reliability_score' => 0.65,
                'connector_class' => GdeltGeoConnector::class,
                'connector_config' => [
                    'query' => '(airstrike OR shelling OR missile OR drone OR offensive OR casualties) tone<-5',
                    'near' => '48.5,35.0,300km',
                    'timespan' => '1h',
                    'max_records' => 30,
                    'sort' => 'datedesc',
                ],
                'active' => false,
            ],
            [
                'name' => 'GDELT GEO: Middle East',
                'type' => 'api',
                'url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
                'family' => 'GDELT',
                'polling_interval' => 60,
                'reliability_score' => 0.65,
                'connector_class' => GdeltGeoConnector::class,
                'connector_config' => [
                    'query' => '(airstrike OR shelling OR missile OR bombing OR casualties OR military) tone<-5',
                    'near' => '31.5,35.0,500km',
                    'timespan' => '1h',
                    'max_records' => 30,
                    'sort' => 'datedesc',
                ],
                'active' => false,
            ],
            [
                'name' => 'GDELT GEO: Sub-Saharan Africa',
                'type' => 'api',
                'url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
                'family' => 'GDELT',
                'polling_interval' => 60,
                'reliability_score' => 0.65,
                'connector_class' => GdeltGeoConnector::class,
                'connector_config' => [
                    'query' => '(attack OR militia OR troops OR combat OR casualties OR bombing) tone<-5',
                    'near' => '5.0,25.0,2000km',
                    'timespan' => '1h',
                    'max_records' => 30,
                    'sort' => 'datedesc',
                ],
                'active' => false,
            ],

            // ─── HUMANITARIAN (UN) ───────────────────────────────
            [
                'name' => 'ReliefWeb Reports',
                'type' => 'api',
                'url' => 'https://api.reliefweb.int/v2/reports',
                'family' => 'ReliefWeb',
                'polling_interval' => 1440, // Daily
                'reliability_score' => 0.85,
                'connector_class' => ReliefWebConnector::class,
                'connector_config' => [
                    'appname' => 'clashmonitor',
                    'theme' => 'Conflict and Violence',
                    'limit' => 50,
                    'days_back' => 1,
                ],
                'active' => false, // Requires pre-approved appname — contact ReliefWeb
            ],

            // ─── UN NEWS RSS (Diplomacy & Peace/Security Layer) ────
            [
                'name' => 'UN News Peace & Security',
                'type' => 'rss',
                'url' => 'https://news.un.org/feed/subscribe/en/news/topic/peace-and-security/feed/rss.xml',
                'family' => 'UN Security Council',
                'polling_interval' => 30,
                'reliability_score' => 0.95,
            ],
        ];

        foreach ($sources as $data) {
            $familyName = $data['family'];
            unset($data['family']);

            $family = SourceFamily::where('name', $familyName)->first();
            if (! $family) {
                continue;
            }

            $data['source_family_id'] = $family->id;

            Source::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
