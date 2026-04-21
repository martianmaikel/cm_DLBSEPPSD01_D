<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OgImageController extends Controller
{
    private const W = 1200;
    private const H = 630;

    // Category visual profiles: [accent color, background tint, label]
    private const CATEGORIES = [
        'airstrike'      => ['#EF4444', '#1A0808', 'AIRSTRIKE'],
        'artillery'      => ['#DC2626', '#1A0A08', 'ARTILLERY'],
        'war'            => ['#EF4444', '#1A0808', 'WAR'],
        'terrorism'      => ['#F97316', '#1A1008', 'TERRORISM'],
        'troop_movement' => ['#EAB308', '#1A1608', 'TROOP MOVEMENT'],
        'protest'        => ['#A855F7', '#140A1A', 'PROTEST'],
        'humanitarian'   => ['#3B82F6', '#08101A', 'HUMANITARIAN'],
        'infrastructure' => ['#6B7280', '#10110F', 'INFRASTRUCTURE'],
        'diplomacy'      => ['#06B6D4', '#081518', 'DIPLOMACY'],
        'cyber'          => ['#10B981', '#081A14', 'CYBER'],
        'economic'       => ['#EAB308', '#1A1608', 'ECONOMIC'],
        'disaster'       => ['#F97316', '#1A1008', 'DISASTER'],
    ];

    /**
     * Diagnostic endpoint: /og/debug
     */
    public function debug(): Response
    {
        $checks = [];

        // 1. Basic response
        $checks[] = 'route: OK';

        // 2. GD extension
        $checks[] = 'gd: ' . (extension_loaded('gd') ? 'OK' : 'MISSING');

        // 3. Font files
        foreach (['BebasNeue-Regular.ttf', 'Rajdhani-Bold.ttf', 'Rajdhani-Medium.ttf', 'ShareTechMono-Regular.ttf'] as $f) {
            $path = resource_path("fonts/{$f}");
            $checks[] = "{$f}: " . (file_exists($path) ? filesize($path) . ' bytes' : 'MISSING at ' . $path);
        }

        // 4. Intervention Image class
        $checks[] = 'ImageManager class: ' . (class_exists(\Intervention\Image\ImageManager::class) ? 'OK' : 'MISSING');
        $checks[] = 'GD Driver class: ' . (class_exists(\Intervention\Image\Drivers\Gd\Driver::class) ? 'OK' : 'MISSING');

        // 5. Create image
        try {
            $mgr = new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
            $img = $mgr->createImage(100, 100)->fill('#000000');
            $checks[] = 'createImage: OK';
        } catch (\Throwable $e) {
            $checks[] = 'createImage: FAIL - ' . $e->getMessage();
            return response(implode("\n", $checks), 200, ['Content-Type' => 'text/plain']);
        }

        // 6. Text with font
        try {
            $img->text('Test', 10, 50, function ($f) {
                $f->filename(resource_path('fonts/Rajdhani-Bold.ttf'));
                $f->size(20);
                $f->color('#FFFFFF');
            });
            $checks[] = 'text+font: OK';
        } catch (\Throwable $e) {
            $checks[] = 'text+font: FAIL - ' . $e->getMessage();
        }

        // 7. drawRectangle
        try {
            $img->drawRectangle(function ($r) {
                $r->size(50, 10)->at(0, 0)->background('#FF0000');
            });
            $checks[] = 'drawRectangle: OK';
        } catch (\Throwable $e) {
            $checks[] = 'drawRectangle: FAIL - ' . $e->getMessage();
        }

        // 8. Save
        try {
            $path = storage_path('app/public/og/debug-test.png');
            @mkdir(dirname($path), 0755, true);
            $img->save($path);
            $checks[] = 'save: OK (' . filesize($path) . ' bytes)';
            @unlink($path);
        } catch (\Throwable $e) {
            $checks[] = 'save: FAIL - ' . $e->getMessage();
        }

        // 9. border() on rectangle
        try {
            $img->drawRectangle(function ($r) {
                $r->size(100, 30)->at(0, 0)->border('#FF0000', 2);
            });
            $checks[] = 'border: OK';
        } catch (\Throwable $e) {
            $checks[] = 'border: FAIL - ' . $e->getMessage();
        }

        // 10. align center
        try {
            $img->text('Center', 50, 50, function ($f) {
                $f->filename(resource_path('fonts/ShareTechMono-Regular.ttf'));
                $f->size(16);
                $f->color('#FFFFFF');
                $f->align('center');
            });
            $checks[] = 'align center: OK';
        } catch (\Throwable $e) {
            $checks[] = 'align center: FAIL - ' . $e->getMessage();
        }

        // 11. align right
        try {
            $img->text('Right', 90, 50, function ($f) {
                $f->filename(resource_path('fonts/ShareTechMono-Regular.ttf'));
                $f->size(16);
                $f->color('#FFFFFF');
                $f->align('right');
            });
            $checks[] = 'align right: OK';
        } catch (\Throwable $e) {
            $checks[] = 'align right: FAIL - ' . $e->getMessage();
        }

        // 12. Full 1200x630 image
        try {
            $full = $mgr->createImage(1200, 630)->fill('#0C0F0B');
            $full->drawRectangle(fn($r) => $r->size(1200, 5)->at(0, 0)->background('#3D7A32'));
            $full->drawRectangle(fn($r) => $r->size(6, 630)->at(0, 0)->background('#EF4444'));
            $full->drawRectangle(fn($r) => $r->size(130, 30)->at(60, 82)->background('#391916'));
            $full->drawRectangle(fn($r) => $r->size(130, 30)->at(60, 82)->border('#EF4444', 1));
            $full->drawRectangle(fn($r) => $r->size(1080, 3)->at(60, 126)->background('#EF4444'));
            $full->drawRectangle(fn($r) => $r->size(200, 40)->at(960, 36)->background('#7F1D1D'));
            $full->drawRectangle(fn($r) => $r->size(200, 40)->at(960, 36)->border('#EF4444', 2));
            $full->text('CLASHMONITOR', 60, 58, function ($f) {
                $f->filename(resource_path('fonts/BebasNeue-Regular.ttf'));
                $f->size(30);
                $f->color('#5BBF4A');
            });
            $full->text('Test Title Here on Full Canvas', 60, 176, function ($f) {
                $f->filename(resource_path('fonts/Rajdhani-Bold.ttf'));
                $f->size(48);
                $f->color('#F1F0EC');
            });
            $full->text('CRITICAL  SEV 9', 1060, 62, function ($f) {
                $f->filename(resource_path('fonts/ShareTechMono-Regular.ttf'));
                $f->size(16);
                $f->color('#FCA5A5');
                $f->align('center');
            });
            $fullPath = storage_path('app/public/og/debug-full.png');
            $full->save($fullPath);
            $checks[] = 'full 1200x630: OK (' . filesize($fullPath) . ' bytes)';
            @unlink($fullPath);
        } catch (\Throwable $e) {
            $checks[] = 'full 1200x630: FAIL - ' . $e->getMessage();
        }

        // 13. Actual event render
        try {
            $event = Event::first();
            if ($event) {
                $checks[] = 'event found: ' . Str::limit($event->title, 40);
                $testPath = storage_path('app/public/og/debug-event.png');
                // Inline minimal render
                $cat = self::CATEGORIES[$event->category] ?? ['#3D7A32', '#0C0F0B', 'EVENT'];
                $ei = $mgr->createImage(1200, 630)->fill($cat[1]);
                $ei->drawRectangle(fn($r) => $r->size(1200, 5)->at(0, 0)->background($cat[0]));
                $ei->text(Str::limit($event->title, 80), 60, 200, function ($f) {
                    $f->filename(resource_path('fonts/Rajdhani-Bold.ttf'));
                    $f->size(48);
                    $f->color('#F1F0EC');
                });
                $ei->save($testPath);
                $checks[] = 'event render: OK (' . filesize($testPath) . ' bytes)';
                @unlink($testPath);
            } else {
                $checks[] = 'event: none in DB';
            }
        } catch (\Throwable $e) {
            $checks[] = 'event render: FAIL - ' . $e->getMessage();
        }

        // 14. Memory
        $checks[] = 'memory_limit: ' . ini_get('memory_limit');
        $checks[] = 'memory_usage: ' . round(memory_get_usage(true) / 1024 / 1024, 1) . 'MB';

        return response(implode("\n", $checks), 200, ['Content-Type' => 'text/plain']);
    }

    public function actor(Actor $actor): Response|RedirectResponse
    {
        $cachePath = storage_path("app/public/og/actor-{$actor->id}.png");

        if (file_exists($cachePath) && filemtime($cachePath) > $actor->updated_at->timestamp) {
            return $this->imageResponse($cachePath);
        }

        try {
            $isPerson = $actor->actor_type === 'person';
            $accent = $isPerson ? '#5BBF4A' : '#06B6D4';
            $bgTint = $isPerson ? '#0C0F0B' : '#081215';
            $typeLabel = $isPerson ? 'PERSON' : 'ORGANIZATION';

            $image = $this->canvas($accent, $bgTint);

            // Left accent stripe
            $image->drawRectangle(function ($r) use ($accent) {
                $r->size(6, self::H)->at(0, 0)->background($accent);
            });

            // Portrait area on the right (if image exists)
            $portraitPlaced = false;
            $contentWidth = self::W - 120;
            $portraitX = self::W - 340;
            $portraitY = 150;
            $portraitSize = 380;

            if (! empty($actor->image_url)) {
                $portraitPlaced = $this->placePortrait($image, $actor->image_url, $portraitX, $portraitY, $portraitSize);
                if ($portraitPlaced) {
                    $contentWidth = $portraitX - 80;
                }
            }

            // ── Top section ──
            $this->text($image, 'CLASHMONITOR', 60, 58, 'bebas', 30, '#5BBF4A');

            // Status badge (top right, only if portrait not placed there)
            $statusColors = [
                'active'    => '#5BBF4A',
                'inactive'  => '#6B7280',
                'deceased'  => '#EF4444',
                'dissolved' => '#EF4444',
                'unknown'   => '#6B7280',
            ];
            $statusColor = $statusColors[$actor->status] ?? '#6B7280';
            $statusLabel = strtoupper($actor->status ?? 'UNKNOWN');
            $statusBadgeX = $portraitPlaced ? 60 : self::W - 200;
            $this->text($image, $statusLabel, $statusBadgeX, 58, 'mono', 16, $statusColor, $portraitPlaced ? 'left' : 'right');

            // Type badge
            $typeBadgeWidth = strlen($typeLabel) * 12 + 24;
            $typeBadgeY = $portraitPlaced ? 82 : 82;
            $image->drawRectangle(function ($r) use ($accent, $typeBadgeWidth, $typeBadgeY) {
                $r->size($typeBadgeWidth, 30)->at(60, $typeBadgeY)->background($this->dim($accent, 0.2));
            });
            $image->drawRectangle(function ($r) use ($accent, $typeBadgeWidth, $typeBadgeY) {
                $r->size($typeBadgeWidth, 30)->at(60, $typeBadgeY)->border($accent, 1);
            });
            $this->text($image, $typeLabel, 72, $typeBadgeY + 21, 'mono', 15, $accent);

            // Country next to type badge
            if ($actor->country) {
                $this->text($image, $actor->country, 60 + $typeBadgeWidth + 16, $typeBadgeY + 21, 'mono', 15, '#6B7280');
            }

            // Accent divider
            $image->drawRectangle(function ($r) use ($accent, $contentWidth) {
                $r->size($contentWidth, 3)->at(60, 126)->background($accent);
            });

            // ── Name ──
            $nameCharsPerLine = $portraitPlaced ? 22 : 36;
            $nameLines = $this->wrap(strtoupper($actor->canonical_name), $nameCharsPerLine);
            $y = 196;
            foreach (array_slice($nameLines, 0, 2) as $line) {
                $this->text($image, $line, 60, $y, 'bebas', 72, '#F1F0EC');
                $y += 78;
            }

            // ── Role / Org type ──
            $roleLine = $isPerson ? ($actor->role_title ?? '') : (($actor->org_type ?? '') ? strtoupper(str_replace('_', ' ', $actor->org_type)) : '');
            if ($roleLine) {
                $roleWrap = $this->wrap($roleLine, $portraitPlaced ? 34 : 60);
                foreach (array_slice($roleWrap, 0, 2) as $line) {
                    $this->text($image, $line, 60, $y, 'medium', 28, $accent);
                    $y += 38;
                }
                $y += 6;
            }

            // ── Summary ──
            if ($actor->summary_short) {
                $sumChars = $portraitPlaced ? 52 : 80;
                $summaryLines = $this->wrap(Str::limit($actor->summary_short, 200), $sumChars);
                $y = max($y + 8, 390);
                foreach (array_slice($summaryLines, 0, 3) as $line) {
                    $this->text($image, $line, 60, $y, 'medium', 22, '#8B9298');
                    $y += 30;
                }
            }

            // ── Bottom bar ──
            $image->drawRectangle(function ($r) {
                $r->size(self::W, 56)->at(0, self::H - 56)->background('#0A0D09');
            });
            $image->drawRectangle(function ($r) use ($accent) {
                $r->size(self::W, 1)->at(0, self::H - 56)->background($this->dim($accent, 0.4));
            });

            $footer = sprintf('%d EVENTS  |  %d MENTIONS', (int) $actor->event_count, (int) $actor->mention_count);
            $this->text($image, $footer, 60, self::H - 24, 'mono', 14, '#6B7280');
            $this->text($image, 'CLASHMONITOR.COM', self::W - 60, self::H - 24, 'mono', 14, '#3D7A32', 'right');

            $this->save($image, $cachePath);

            return $this->imageResponse($cachePath);
        } catch (\Throwable $e) {
            Log::error('OG image failed', ['actor' => $actor->id, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);

            return $this->fallbackRedirect();
        }
    }

    private function placePortrait($canvas, string $url, int $x, int $y, int $size): bool
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'ClashMonitor/1.0 (https://clashmonitor.com)',
            ])->timeout(10)->get($url);

            if ($response->failed() || strlen($response->body()) < 1000) {
                return false;
            }

            $mgr = new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
            $portrait = $mgr->read($response->body())
                ->coverDown($size, $size);

            // Border frame
            $canvas->drawRectangle(function ($r) use ($x, $y, $size) {
                $r->size($size + 8, $size + 8)->at($x - 4, $y - 4)->background('#1A1D18');
            });

            $canvas->place($portrait, 'top-left', $x, $y);

            // Optional subtle overlay frame
            $canvas->drawRectangle(function ($r) use ($x, $y, $size) {
                $r->size($size, $size)->at($x, $y)->border('#3D7A32', 2);
            });

            return true;
        } catch (\Throwable $e) {
            Log::debug('placePortrait failed', ['url' => $url, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function briefing(string $date): Response|RedirectResponse
    {
        $briefing = DailyBriefing::forDate(now()->parse($date))->firstOrFail();
        $cachePath = storage_path("app/public/og/briefing-{$date}.png");

        if (file_exists($cachePath) && filemtime($cachePath) > $briefing->updated_at->timestamp) {
            return $this->imageResponse($cachePath);
        }

        try {
            $image = $this->canvas('#06B6D4', '#081215');

            // Left accent
            $image->drawRectangle(function ($r) {
                $r->size(6, self::H)->at(0, 0)->background('#06B6D4');
            });

            $this->text($image, 'CLASHMONITOR', 60, 58, 'bebas', 30, '#5BBF4A');

            // "INTEL BRIEFING" badge
            $image->drawRectangle(function ($r) {
                $r->size(260, 30)->at(60, 82)->background($this->dim('#06B6D4', 0.2));
            });
            $image->drawRectangle(function ($r) {
                $r->size(260, 30)->at(60, 82)->border('#06B6D4', 1);
            });
            $this->text($image, 'DAILY INTEL BRIEFING', 72, 103, 'mono', 14, '#06B6D4');

            $image->drawRectangle(function ($r) {
                $r->size(1080, 3)->at(60, 126)->background('#06B6D4');
            });

            // Date — huge
            $formattedDate = strtoupper($briefing->briefing_date->format('F j, Y'));
            $this->text($image, $formattedDate, 60, 200, 'bebas', 72, '#F1F0EC');

            // Summary
            if ($briefing->summary_en) {
                $lines = $this->wrap(Str::limit($briefing->summary_en, 220), 56);
                $y = 280;
                foreach (array_slice($lines, 0, 4) as $line) {
                    $this->text($image, $line, 60, $y, 'medium', 24, '#8B9298');
                    $y += 34;
                }
            }

            // Bottom bar
            $image->drawRectangle(function ($r) {
                $r->size(self::W, 56)->at(0, self::H - 56)->background('#0A0D09');
            });
            $image->drawRectangle(function ($r) {
                $r->size(self::W, 1)->at(0, self::H - 56)->background($this->dim('#06B6D4', 0.4));
            });
            $this->text($image, 'CLASHMONITOR.COM', self::W - 60, self::H - 24, 'mono', 14, '#3D7A32', 'right');

            $this->save($image, $cachePath);

            return $this->imageResponse($cachePath);
        } catch (\Throwable $e) {
            Log::error('OG image failed', ['briefing' => $date, 'error' => $e->getMessage()]);

            return $this->fallbackRedirect();
        }
    }

    public function conflict(string $slug): Response|RedirectResponse
    {
        $thread = ConflictThread::where('slug', $slug)->firstOrFail();
        $cachePath = storage_path("app/public/og/conflict-{$slug}.png");

        if (file_exists($cachePath) && filemtime($cachePath) > $thread->updated_at->timestamp) {
            return $this->imageResponse($cachePath);
        }

        try {
            $sevColor = ($thread->max_severity ?? 0) >= 7 ? '#EF4444' : '#F59E0B';
            $image = $this->canvas($sevColor, '#1A0808');

            $image->drawRectangle(function ($r) use ($sevColor) {
                $r->size(6, self::H)->at(0, 0)->background($sevColor);
            });

            $this->text($image, 'CLASHMONITOR', 60, 58, 'bebas', 30, '#5BBF4A');

            $image->drawRectangle(function ($r) use ($sevColor) {
                $r->size(220, 30)->at(60, 82)->background($this->dim($sevColor, 0.2));
            });
            $image->drawRectangle(function ($r) use ($sevColor) {
                $r->size(220, 30)->at(60, 82)->border($sevColor, 1);
            });
            $this->text($image, 'CONFLICT MONITOR', 72, 103, 'mono', 14, $sevColor);

            $image->drawRectangle(function ($r) use ($sevColor) {
                $r->size(1080, 3)->at(60, 126)->background($sevColor);
            });

            // Conflict name — huge
            $lines = $this->wrap(strtoupper($thread->name), 26);
            $y = 195;
            foreach (array_slice($lines, 0, 3) as $line) {
                $this->text($image, $line, 60, $y, 'bebas', 66, '#F1F0EC');
                $y += 76;
            }

            // Stats
            $statsY = max($y + 10, 400);
            $this->text($image, "{$thread->event_count_total} EVENTS TRACKED", 60, $statsY, 'mono', 20, '#8B9298');
            if ($thread->max_severity) {
                $this->text($image, "MAX SEVERITY {$thread->max_severity}/10", 60, $statsY + 30, 'mono', 18, $sevColor);
            }

            // Bottom
            $image->drawRectangle(function ($r) {
                $r->size(self::W, 56)->at(0, self::H - 56)->background('#0A0D09');
            });
            $image->drawRectangle(function ($r) use ($sevColor) {
                $r->size(self::W, 1)->at(0, self::H - 56)->background($this->dim($sevColor, 0.4));
            });
            $this->text($image, 'CLASHMONITOR.COM', self::W - 60, self::H - 24, 'mono', 14, '#3D7A32', 'right');

            $this->save($image, $cachePath);

            return $this->imageResponse($cachePath);
        } catch (\Throwable $e) {
            Log::error('OG image failed', ['conflict' => $slug, 'error' => $e->getMessage()]);

            return $this->fallbackRedirect();
        }
    }

    // ── Helpers ──

    private function canvas(string $accent = '#3D7A32', string $bgTint = '#0C0F0B')
    {
        $mgr = new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
        $image = $mgr->createImage(self::W, self::H)->fill($bgTint);

        // Top accent bar
        $image->drawRectangle(function ($r) use ($accent) {
            $r->size(self::W, 5)->at(0, 0)->background($accent);
        });

        return $image;
    }

    private function font(string $style): string
    {
        return match ($style) {
            'bebas' => resource_path('fonts/BebasNeue-Regular.ttf'),
            'bold' => resource_path('fonts/Rajdhani-Bold.ttf'),
            'medium' => resource_path('fonts/Rajdhani-Medium.ttf'),
            'mono' => resource_path('fonts/ShareTechMono-Regular.ttf'),
            default => resource_path('fonts/Rajdhani-Medium.ttf'),
        };
    }

    private function text($image, string $text, int $x, int $y, string $style, int $size, string $color, string $align = 'left'): void
    {
        $fontPath = $this->font($style);
        $image->text($text, $x, $y, function ($f) use ($fontPath, $size, $color, $align) {
            $f->filename($fontPath);
            $f->size($size);
            $f->color($color);
            $f->align($align);
        });
    }

    /**
     * Mix a hex color with dark background to simulate transparency.
     */
    private function dim(string $hex, float $opacity = 0.2): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Mix with near-black (#0C0F0B)
        $br = 12; $bg = 15; $bb = 11;
        $r = (int) ($br + ($r - $br) * $opacity);
        $g = (int) ($bg + ($g - $bg) * $opacity);
        $b = (int) ($bb + ($b - $bb) * $opacity);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function wrap(string $text, int $max): array
    {
        return explode("\n", wordwrap($text, $max, "\n", true));
    }

    private function save($image, string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $image->save($path);
    }

    private function imageResponse(string $path): Response
    {
        return response(file_get_contents($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function fallbackRedirect(): RedirectResponse
    {
        return redirect('/images/og-banner.jpg', 302);
    }
}
