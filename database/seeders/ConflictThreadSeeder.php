<?php

namespace Database\Seeders;

use App\Models\ConflictThread;
use Illuminate\Database\Seeder;

class ConflictThreadSeeder extends Seeder
{
    public function run(): void
    {
        $conflicts = [
            [
                'name' => 'Russia-Ukraine War',
                'summary' => 'Full-scale armed conflict between Russia and Ukraine, ongoing since February 2022. Involves large-scale conventional warfare, territorial disputes, and significant international involvement.',
            ],
            [
                'name' => 'Israeli-Palestinian Conflict',
                'summary' => 'Longstanding armed conflict between Israel and Palestinian groups, with periodic escalations involving military operations in Gaza and the West Bank.',
            ],
            [
                'name' => 'Iran-Israel Military Conflict',
                'summary' => 'Direct and proxy military confrontation between Iran and Israel, including missile exchanges, cyber operations, and regional proxy warfare.',
            ],
            [
                'name' => 'US-Iran Conflict',
                'summary' => 'Military and geopolitical confrontation between the United States and Iran, including sanctions, naval operations in the Persian Gulf, and diplomatic tensions.',
            ],
            [
                'name' => 'Syrian Civil War',
                'summary' => 'Multi-faction armed conflict in Syria involving government forces, rebel groups, Kurdish forces, and international military intervention.',
            ],
            [
                'name' => 'Sudan Civil War',
                'summary' => 'Armed conflict between the Sudanese Armed Forces and the Rapid Support Forces, causing mass displacement and humanitarian crisis.',
            ],
            [
                'name' => 'Myanmar Civil War',
                'summary' => 'Armed resistance against Myanmar military junta following the 2021 coup, involving ethnic armed organizations and civilian resistance forces.',
            ],
            [
                'name' => 'Yemen Conflict',
                'summary' => 'Armed conflict in Yemen involving Houthi forces, government forces, and international coalition operations. Includes Houthi attacks on Red Sea shipping.',
            ],
            [
                'name' => 'Ethiopia Conflict',
                'summary' => 'Armed conflicts in Ethiopia including the Tigray conflict and ongoing ethnic violence in multiple regions.',
            ],
            [
                'name' => 'DR Congo Conflict',
                'summary' => 'Ongoing armed conflict in eastern Democratic Republic of Congo involving government forces, M23 rebels, and numerous other armed groups.',
            ],
            [
                'name' => 'Somalia Conflict',
                'summary' => 'Armed conflict involving Al-Shabaab insurgency, clan militias, and government forces supported by international peacekeeping operations.',
            ],
            [
                'name' => 'Taiwan Strait Tensions',
                'summary' => 'Military tensions between China and Taiwan, including Chinese military exercises, airspace incursions, and U.S. involvement in regional security.',
            ],
            [
                'name' => 'North Korea Tensions',
                'summary' => 'Military provocations by North Korea including missile tests, nuclear weapons development, and tensions with South Korea and the United States.',
            ],
        ];

        foreach ($conflicts as $conflict) {
            ConflictThread::firstOrCreate(
                ['name' => $conflict['name']],
                [
                    'summary' => $conflict['summary'],
                    'status' => 'open',
                ]
            );
        }
    }
}
