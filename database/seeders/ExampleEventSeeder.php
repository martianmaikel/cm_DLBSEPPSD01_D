<?php

namespace Database\Seeders;

use App\Models\ConflictThread;
use App\Models\Source;
use App\Models\SourceFamily;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExampleEventSeeder extends Seeder
{
    // ── City coordinate lookup ──────────────────────────────────────────────

    private array $cities = [
        // Ukraine
        'Kyiv'         => ['lat' => 50.4501,  'lng' => 30.5234,  'country' => 'UA', 'region' => 'Kyiv Oblast'],
        'Kharkiv'      => ['lat' => 49.9935,  'lng' => 36.2304,  'country' => 'UA', 'region' => 'Kharkiv Oblast'],
        'Dnipro'       => ['lat' => 48.4647,  'lng' => 35.0462,  'country' => 'UA', 'region' => 'Dnipropetrovsk Oblast'],
        'Zaporizhzhia' => ['lat' => 47.8388,  'lng' => 35.1396,  'country' => 'UA', 'region' => 'Zaporizhzhia Oblast'],
        'Kherson'      => ['lat' => 46.6354,  'lng' => 32.6169,  'country' => 'UA', 'region' => 'Kherson Oblast'],
        'Donetsk'      => ['lat' => 48.0159,  'lng' => 37.8028,  'country' => 'UA', 'region' => 'Donetsk Oblast'],
        'Mariupol'     => ['lat' => 47.0951,  'lng' => 37.5434,  'country' => 'UA', 'region' => 'Donetsk Oblast'],
        'Odesa'        => ['lat' => 46.4825,  'lng' => 30.7233,  'country' => 'UA', 'region' => 'Odesa Oblast'],
        'Lviv'         => ['lat' => 49.8397,  'lng' => 24.0297,  'country' => 'UA', 'region' => 'Lviv Oblast'],
        // Gaza / Israel
        'Gaza City'        => ['lat' => 31.5017,  'lng' => 34.4668,  'country' => 'PS', 'region' => 'Gaza Strip'],
        'Khan Yunis'       => ['lat' => 31.3452,  'lng' => 34.3065,  'country' => 'PS', 'region' => 'Gaza Strip'],
        'Rafah'            => ['lat' => 31.2843,  'lng' => 34.2642,  'country' => 'PS', 'region' => 'Gaza Strip'],
        'Deir al-Balah'    => ['lat' => 31.4167,  'lng' => 34.3500,  'country' => 'PS', 'region' => 'Gaza Strip'],
        'Tel Aviv'         => ['lat' => 32.0853,  'lng' => 34.7818,  'country' => 'IL', 'region' => 'Tel Aviv District'],
        // Sudan
        'Khartoum'   => ['lat' => 15.5007,  'lng' => 32.5599,  'country' => 'SD', 'region' => 'Khartoum State'],
        'Omdurman'   => ['lat' => 15.6446,  'lng' => 32.4783,  'country' => 'SD', 'region' => 'Khartoum State'],
        'El-Fasher'  => ['lat' => 13.6300,  'lng' => 25.3500,  'country' => 'SD', 'region' => 'North Darfur'],
        'Port Sudan' => ['lat' => 19.6158,  'lng' => 37.2164,  'country' => 'SD', 'region' => 'Red Sea State'],
        'Nyala'      => ['lat' => 12.0490,  'lng' => 24.8818,  'country' => 'SD', 'region' => 'South Darfur'],
        // Syria
        'Deir ez-Zor' => ['lat' => 35.3360,  'lng' => 40.1400,  'country' => 'SY', 'region' => 'Deir ez-Zor Governorate'],
        'Abu Kamal'   => ['lat' => 34.4500,  'lng' => 40.9200,  'country' => 'SY', 'region' => 'Deir ez-Zor Governorate'],
        'Raqqa'       => ['lat' => 35.9500,  'lng' => 39.0100,  'country' => 'SY', 'region' => 'Raqqa Governorate'],
        'Palmyra'     => ['lat' => 34.5600,  'lng' => 38.2700,  'country' => 'SY', 'region' => 'Homs Governorate'],
    ];

    // ── Event templates per conflict zone ──────────────────────────────────

    private array $ukraineEvents = [
        ['title' => 'Russian ballistic missile strike on Kharkiv residential district', 'category' => 'airstrike', 'severity' => 9],
        ['title' => 'Artillery shelling reported along Zaporizhzhia front line', 'category' => 'artillery', 'severity' => 7],
        ['title' => 'Ukrainian drone strike targets Russian logistics depot near Donetsk', 'category' => 'airstrike', 'severity' => 6],
        ['title' => 'Civilian evacuation convoy departs Kherson under fire', 'category' => 'humanitarian', 'severity' => 5],
        ['title' => 'Russian Su-34 aircraft reported over northern Kharkiv Oblast', 'category' => 'airstrike', 'severity' => 7],
        ['title' => 'Power infrastructure hit in Dnipro overnight strike', 'category' => 'infrastructure', 'severity' => 8],
        ['title' => 'Ukrainian forces repel assault near Mariupol outskirts', 'category' => 'troop_movement', 'severity' => 7],
        ['title' => 'Shahed drone swarm intercepted over Kyiv — air defense active', 'category' => 'airstrike', 'severity' => 6],
        ['title' => 'Ukrainian artillery targeting Russian troop concentration in Donetsk', 'category' => 'artillery', 'severity' => 7],
        ['title' => 'Bridge destroyed near Kherson cutting supply route', 'category' => 'infrastructure', 'severity' => 6],
        ['title' => 'Russian forces advance in Avdiivka direction, ISW reports', 'category' => 'troop_movement', 'severity' => 8],
        ['title' => 'Water treatment plant damaged in Mykolaiv shelling', 'category' => 'infrastructure', 'severity' => 6],
        ['title' => 'Humanitarian aid convoy reaches Zaporizhzhia civilians', 'category' => 'humanitarian', 'severity' => 3],
        ['title' => 'Multiple explosions reported in Odesa port area', 'category' => 'airstrike', 'severity' => 7],
        ['title' => 'Ukrainian 47th Brigade repositions along eastern front', 'category' => 'troop_movement', 'severity' => 5],
        ['title' => 'Russian Iskander-M missile strike targets Lviv airfield', 'category' => 'airstrike', 'severity' => 8],
        ['title' => 'Artillery exchange intensifies near Bakhmut — casualties reported', 'category' => 'artillery', 'severity' => 8],
        ['title' => 'Drone attack damages oil refinery in Kremenchuk', 'category' => 'infrastructure', 'severity' => 7],
        ['title' => 'Ukrainian Marines hold positions south of Kherson city', 'category' => 'troop_movement', 'severity' => 6],
        ['title' => 'Hospital in Kharkiv Oblast evacuated following missile warning', 'category' => 'humanitarian', 'severity' => 5],
        ['title' => 'Russian glide bomb strikes Zaporizhzhia industrial zone', 'category' => 'airstrike', 'severity' => 8],
        ['title' => 'Ukrainian ground forces push back near Lyman — confirmed advance', 'category' => 'troop_movement', 'severity' => 7],
        ['title' => 'Rail line to Dnipro disrupted after overnight strike', 'category' => 'infrastructure', 'severity' => 6],
    ];

    private array $gazaEvents = [
        ['title' => 'IDF airstrike targets Hamas command compound in Gaza City', 'category' => 'airstrike', 'severity' => 9],
        ['title' => 'Humanitarian aid crossing at Kerem Shalom reopened briefly', 'category' => 'humanitarian', 'severity' => 4],
        ['title' => 'Rafah border crossing closed — aid trucks unable to enter', 'category' => 'humanitarian', 'severity' => 7],
        ['title' => 'Hospital in Khan Yunis reports critical fuel shortage', 'category' => 'humanitarian', 'severity' => 9],
        ['title' => 'Israeli strike on Deir al-Balah market kills multiple civilians', 'category' => 'airstrike', 'severity' => 10],
        ['title' => 'IDF ground forces advance into northern Gaza City', 'category' => 'troop_movement', 'severity' => 8],
        ['title' => 'Rocket barrage from Gaza intercepted over Tel Aviv by Iron Dome', 'category' => 'airstrike', 'severity' => 7],
        ['title' => 'UN warns of complete infrastructure collapse in northern Gaza', 'category' => 'infrastructure', 'severity' => 9],
        ['title' => 'Airstrike destroys residential tower in Rafah — casualties confirmed', 'category' => 'airstrike', 'severity' => 10],
        ['title' => 'UNRWA warehouse hit in overnight strike — food supplies destroyed', 'category' => 'infrastructure', 'severity' => 8],
        ['title' => 'IDF announces evacuation order for Khan Yunis eastern districts', 'category' => 'troop_movement', 'severity' => 7],
        ['title' => 'Water desalination plant in Gaza City non-operational — WHO statement', 'category' => 'infrastructure', 'severity' => 8],
        ['title' => 'Airstrike on Hamas naval assets in Gaza port area', 'category' => 'airstrike', 'severity' => 6],
    ];

    private array $sudanEvents = [
        ['title' => 'RSF artillery bombardment targets Omdurman residential quarter', 'category' => 'artillery', 'severity' => 8],
        ['title' => 'SAF airstrikes target RSF positions in Khartoum North', 'category' => 'airstrike', 'severity' => 7],
        ['title' => 'RSF seizes control of El-Fasher market district', 'category' => 'troop_movement', 'severity' => 8],
        ['title' => 'MSF reports mass casualty event in Nyala following shelling', 'category' => 'humanitarian', 'severity' => 9],
        ['title' => 'Sudanese Armed Forces reinforcements arrive at Port Sudan base', 'category' => 'troop_movement', 'severity' => 5],
        ['title' => 'RSF advances on SAF headquarters in central Khartoum', 'category' => 'troop_movement', 'severity' => 9],
        ['title' => 'Heavy fighting reported in El-Fasher — UN calls for ceasefire', 'category' => 'artillery', 'severity' => 8],
        ['title' => 'Refugee camp shelling near Nyala displaces thousands', 'category' => 'humanitarian', 'severity' => 8],
        ['title' => 'SAF retakes strategic bridge in Omdurman from RSF', 'category' => 'troop_movement', 'severity' => 7],
        ['title' => 'Power grid failure across Khartoum after infrastructure attack', 'category' => 'infrastructure', 'severity' => 6],
    ];

    private array $syriaEvents = [
        ['title' => 'ISIS ambush kills Syrian Democratic Forces fighters near Deir ez-Zor', 'category' => 'troop_movement', 'severity' => 7],
        ['title' => 'US-led coalition airstrike targets ISIS cell in Abu Kamal', 'category' => 'airstrike', 'severity' => 6],
        ['title' => 'ISIS IED detonates on highway between Raqqa and Deir ez-Zor', 'category' => 'infrastructure', 'severity' => 5],
        ['title' => 'SDF launches counter-operation against ISIS in Deir ez-Zor countryside', 'category' => 'troop_movement', 'severity' => 6],
        ['title' => 'ISIS recaptures small village near Palmyra, SDF confirms', 'category' => 'troop_movement', 'severity' => 7],
        ['title' => 'Airstrike destroys reported ISIS weapons cache near Abu Kamal', 'category' => 'airstrike', 'severity' => 5],
        ['title' => 'ISIS claims responsibility for attack on Raqqa checkpoint', 'category' => 'troop_movement', 'severity' => 6],
        ['title' => 'Coalition forces conduct raids on ISIS network in Deir ez-Zor province', 'category' => 'airstrike', 'severity' => 4],
    ];

    // ── AI-style summary templates ──────────────────────────────────────────

    private array $summaryTemplates = [
        'airstrike'       => [
            'Strike reported at %s. Multiple impacts observed; damage assessment ongoing. Casualties unconfirmed at time of reporting.',
            'Aerial strike confirmed at %s. Local sources report structural damage; emergency services deployed to the area.',
            'An airstrike was carried out targeting %s. Initial reports indicate significant damage to the target area.',
        ],
        'artillery'       => [
            'Artillery shelling reported in %s. Sustained bombardment lasting several minutes; civilian movement restricted.',
            'Shelling impacted %s. Multiple rounds reported; exact origin undetermined pending further analysis.',
            'Artillery fire directed at positions in %s. Front-line situation remains fluid.',
        ],
        'troop_movement'  => [
            'Military units observed repositioning in %s. The movement suggests preparation for offensive or defensive action.',
            'Troop activity confirmed in %s. Nature of movement consistent with operational regrouping.',
            'Armed forces conducting maneuvers near %s. Tactical significance under assessment.',
        ],
        'infrastructure'  => [
            'Critical infrastructure damaged in %s. Civilian services disrupted; repair timeline unknown.',
            'Infrastructure target struck in %s. Authorities assessing extent of damage to civilian systems.',
            'Attack on infrastructure in %s. Damage to utilities reported; humanitarian impact likely.',
        ],
        'humanitarian'    => [
            'Humanitarian situation deteriorating in %s. Aid access restricted; civilian population affected.',
            'Relief organizations report critical conditions in %s. Evacuation routes under pressure.',
            'Humanitarian emergency reported in %s. International organizations calling for immediate access.',
        ],
    ];

    // ── Main run ────────────────────────────────────────────────────────────

    public function run(): void
    {
        // Truncate events before re-seeding to keep runs idempotent
        DB::table('events')->delete();

        // Ensure source families exist (extend existing seeder data)
        $familyReuters  = SourceFamily::firstOrCreate(
            ['name' => 'Reuters'],
            ['editorial_ownership' => 'Thomson Reuters', 'description' => 'International news agency'],
        );
        $familyAlJ      = SourceFamily::firstOrCreate(
            ['name' => 'Al Jazeera'],
            ['editorial_ownership' => 'Al Jazeera Media Network', 'description' => 'Qatar-based news network'],
        );
        $familyOsint    = SourceFamily::firstOrCreate(
            ['name' => 'Intel Republic'],
            ['editorial_ownership' => 'Independent / Anonymous', 'description' => 'OSINT aggregator Telegram channel'],
        );

        // Ensure sources exist
        $sourceReutersRss = Source::firstOrCreate(
            ['name' => 'Reuters World News'],
            [
                'type'             => 'rss',
                'url'              => 'https://www.reutersagency.com/feed/?taxonomy=best-sectors&post_type=best',
                'source_family_id' => $familyReuters->id,
                'polling_interval' => 10,
                'reliability_score'=> 0.90,
                'active'           => true,
            ],
        );
        $sourceAlJRss = Source::firstOrCreate(
            ['name' => 'Al Jazeera English'],
            [
                'type'             => 'rss',
                'url'              => 'https://www.aljazeera.com/xml/rss/all.xml',
                'source_family_id' => $familyAlJ->id,
                'polling_interval' => 10,
                'reliability_score'=> 0.80,
                'active'           => true,
            ],
        );
        $sourceOsintTelegram = Source::firstOrCreate(
            ['name' => 'Intel Republic'],
            [
                'type'             => 'telegram',
                'url'              => 'https://t.me/IntelRepublic',
                'source_family_id' => $familyOsint->id,
                'polling_interval' => 5,
                'reliability_score'=> 0.45,
                'active'           => true,
            ],
        );
        $sourceReutersGnews = Source::firstOrCreate(
            ['name' => 'Reuters via Google News'],
            [
                'type'             => 'rss',
                'url'              => 'https://news.google.com/rss/search?q=reuters+conflict',
                'source_family_id' => $familyReuters->id,
                'polling_interval' => 15,
                'reliability_score'=> 0.75,
                'active'           => true,
            ],
        );
        $sourceAlJTelegram = Source::firstOrCreate(
            ['name' => 'Al Jazeera Telegram'],
            [
                'type'             => 'telegram',
                'url'              => 'https://t.me/aljazeera',
                'source_family_id' => $familyAlJ->id,
                'polling_interval' => 5,
                'reliability_score'=> 0.78,
                'active'           => true,
            ],
        );

        $sources = [
            $sourceReutersRss->id,
            $sourceAlJRss->id,
            $sourceOsintTelegram->id,
            $sourceReutersGnews->id,
            $sourceAlJTelegram->id,
        ];

        // Conflict threads
        $threadUkraine = ConflictThread::firstOrCreate(
            ['name' => 'Ukraine-Russia Conflict — March 2026'],
            ['summary' => 'Ongoing large-scale conflict between Russian Federation forces and Ukrainian armed forces spanning multiple fronts.', 'status' => 'open'],
        );
        $threadGaza = ConflictThread::firstOrCreate(
            ['name' => 'Gaza Strip Airstrikes — March 2026'],
            ['summary' => 'Sustained Israeli military campaign in the Gaza Strip following the October 2023 Hamas attacks, with ongoing ground and aerial operations.', 'status' => 'open'],
        );
        $threadSudan = ConflictThread::firstOrCreate(
            ['name' => 'Sudan Civil War — Khartoum Offensive'],
            ['summary' => 'Armed conflict between the Sudanese Armed Forces and the Rapid Support Forces, with fighting concentrated in Khartoum and Darfur.', 'status' => 'open'],
        );
        $threadSyria = ConflictThread::firstOrCreate(
            ['name' => 'Syria — ISIS Resurgence in Deir ez-Zor'],
            ['summary' => 'Renewed ISIS insurgency activity in eastern Syria, with increased attacks on SDF and coalition forces in Deir ez-Zor province.', 'status' => 'open'],
        );

        // Build batches: [events, cityPool, thread]
        $batches = [
            [$this->ukraineEvents, ['Kyiv', 'Kharkiv', 'Dnipro', 'Zaporizhzhia', 'Kherson', 'Donetsk', 'Mariupol', 'Odesa', 'Lviv'], $threadUkraine],
            [$this->gazaEvents,    ['Gaza City', 'Khan Yunis', 'Rafah', 'Deir al-Balah', 'Tel Aviv'],                                $threadGaza],
            [$this->sudanEvents,   ['Khartoum', 'Omdurman', 'El-Fasher', 'Port Sudan', 'Nyala'],                                     $threadSudan],
            [$this->syriaEvents,   ['Deir ez-Zor', 'Abu Kamal', 'Raqqa', 'Palmyra'],                                                 $threadSyria],
        ];

        $now = now();

        foreach ($batches as [$eventDefs, $cityPool, $thread]) {
            foreach ($eventDefs as $def) {
                $city    = $cityPool[array_rand($cityPool)];
                $geo     = $this->cities[$city];
                $summary = $this->makeSummary($def['category'], $city);

                // Spread events across last 24 hours with daytime clustering
                $occurredAt = $this->randomOccurredAt($now, $geo['country']);

                // Weight toward unverified (60%), some corroborated (30%), few confirmed (10%)
                $rand   = mt_rand(1, 10);
                $status = match (true) {
                    $rand <= 6  => 'unverified',
                    $rand <= 9  => 'corroborated',
                    default     => 'confirmed',
                };

                $corroborationCount = match ($status) {
                    'unverified'   => 0,
                    'corroborated' => mt_rand(1, 2),
                    'confirmed'    => mt_rand(2, 3),
                };

                // Add small coordinate jitter so markers don't overlap
                $jitteredLat = $geo['lat'] + (mt_rand(-50, 50) / 10000);
                $jitteredLng = $geo['lng'] + (mt_rand(-50, 50) / 10000);

                $geoApproximate = (mt_rand(1, 10) <= 2); // 20% approximate

                DB::table('events')->insert([
                    'id'                  => (string) Str::uuid(),
                    'title'               => $def['title'],
                    'summary'             => $summary,
                    'raw_content'         => $def['title'],
                    'category'            => $def['category'],
                    'severity'            => $def['severity'],
                    'confidence'          => mt_rand(4, 9),
                    'status'              => $status,
                    'country'             => $geo['country'],
                    'region'              => $geo['region'],
                    'coordinates'         => DB::raw(sprintf(
                        "ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography",
                        $jitteredLng,
                        $jitteredLat,
                    )),
                    'geo_approximate'     => $geoApproximate,
                    'occurred_at'         => $occurredAt,
                    'source_id'           => $sources[array_rand($sources)],
                    'conflict_thread_id'  => $thread->id,
                    'hash'                => Str::random(64),
                    'corroboration_count' => $corroborationCount,
                    'created_at'          => $occurredAt,
                    'updated_at'          => $occurredAt,
                ]);
            }
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeSummary(string $category, string $city): string
    {
        $templates = $this->summaryTemplates[$category] ?? $this->summaryTemplates['airstrike'];
        $template  = $templates[array_rand($templates)];

        return sprintf($template, $city);
    }

    /**
     * Returns a Carbon timestamp within the last 24 hours, biased toward
     * local daytime hours (07:00–22:00) in the event's timezone.
     */
    private function randomOccurredAt(\Illuminate\Support\Carbon $now, string $country): string
    {
        // Rough UTC offset per country for daytime bias
        $utcOffset = match ($country) {
            'UA' => 2,   // EET (UTC+2)
            'PS' => 2,   // Palestine standard time
            'IL' => 2,   // IST
            'SD' => 3,   // CAT
            'SY' => 3,   // EEST
            default => 0,
        };

        // Pick a random hour in local time, weighted to daytime (07–22)
        // 70% chance of daytime, 30% overnight
        if (mt_rand(1, 10) <= 7) {
            $localHour = mt_rand(7, 22);
        } else {
            $localHour = mt_rand(0, 6);
        }

        // Convert local hour back to UTC
        $utcHour = ($localHour - $utcOffset + 24) % 24;

        // Random day offset within last 24 h (0 = today, 1 = yesterday)
        $daysAgo = (mt_rand(0, 23) < 20) ? 0 : 1; // mostly today

        $base = $now->copy()->subDays($daysAgo)->setTime($utcHour, mt_rand(0, 59), mt_rand(0, 59));

        // Ensure within last 24 h
        if ($base->gt($now)) {
            $base->subDay();
        }

        return $base->toDateTimeString();
    }
}
