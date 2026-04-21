<?php

namespace App\Console\Commands;

use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use App\Models\Event;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate {--news-only : Only generate the Google News sitemap}';

    protected $description = 'Generate XML sitemaps for ClashMonitor';

    public function handle(): int
    {
        $baseUrl = config('app.url');

        if ($this->option('news-only')) {
            $this->generateNewsSitemap($baseUrl);
            $this->info('News sitemap generated.');

            return self::SUCCESS;
        }

        $this->generateStaticSitemap($baseUrl);
        $this->generateEventsSitemap($baseUrl);
        $this->generateBriefingsSitemap($baseUrl);
        $this->generateConflictsSitemap($baseUrl);
        $this->generateRegionsSitemap($baseUrl);
        $this->generateNewsSitemap($baseUrl);
        $this->generateSitemapIndex($baseUrl);

        $this->info('All sitemaps generated.');

        return self::SUCCESS;
    }

    private function generateStaticSitemap(string $baseUrl): void
    {
        Sitemap::create()
            ->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_HOURLY))
            ->add(Url::create('/conflicts')->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY))
            ->add(Url::create('/methodology')->setPriority(0.5)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/newsletter')->setPriority(0.6)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/impressum')->setPriority(0.3)->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY))
            ->add(Url::create('/datenschutz')->setPriority(0.3)->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY))
            ->writeToFile(public_path('sitemap-static.xml'));
    }

    private function generateEventsSitemap(string $baseUrl): void
    {
        $sitemap = Sitemap::create();

        Event::whereIn('status', ['corroborated', 'confirmed'])
            ->orderByDesc('occurred_at')
            ->select(['id', 'slug', 'occurred_at', 'updated_at'])
            ->chunk(500, function ($events) use ($sitemap) {
                foreach ($events as $event) {
                    $path = "/event/{$event->id}" . ($event->slug ? "-{$event->slug}" : '');
                    $sitemap->add(
                        Url::create($path)
                            ->setLastModificationDate($event->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7)
                    );
                }
            });

        $sitemap->writeToFile(public_path('sitemap-events.xml'));
    }

    private function generateBriefingsSitemap(string $baseUrl): void
    {
        $sitemap = Sitemap::create();

        DailyBriefing::orderByDesc('briefing_date')
            ->each(function ($briefing) use ($sitemap) {
                $sitemap->add(
                    Url::create("/briefing/{$briefing->briefing_date->format('Y-m-d')}")
                        ->setLastModificationDate($briefing->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        ->setPriority(0.8)
                );
            });

        $sitemap->writeToFile(public_path('sitemap-briefings.xml'));
    }

    private function generateConflictsSitemap(string $baseUrl): void
    {
        $sitemap = Sitemap::create();

        ConflictThread::whereNotNull('slug')
            ->open()
            ->each(function ($thread) use ($sitemap) {
                $sitemap->add(
                    Url::create("/conflict/{$thread->slug}")
                        ->setLastModificationDate($thread->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        ->setPriority(0.8)
                );
            });

        $sitemap->writeToFile(public_path('sitemap-conflicts.xml'));
    }

    private function generateRegionsSitemap(string $baseUrl): void
    {
        $sitemap = Sitemap::create();

        $continents = config('geo.continents', []);
        foreach (array_keys($continents) as $slug) {
            $sitemap->add(
                Url::create("/region/{$slug}")
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.7)
            );
        }

        // Countries with events
        $countryCodes = Event::whereNotIn('status', ['pending_classification', 'retracted'])
            ->distinct()
            ->pluck('country')
            ->filter();

        foreach ($countryCodes as $code) {
            $sitemap->add(
                Url::create("/country/{$code}")
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.6)
            );
        }

        $sitemap->writeToFile(public_path('sitemap-regions.xml'));
    }

    private function generateNewsSitemap(string $baseUrl): void
    {
        $sitemap = Sitemap::create();

        Event::whereIn('status', ['corroborated', 'confirmed'])
            ->where('occurred_at', '>=', now()->subHours(48))
            ->orderByDesc('occurred_at')
            ->select(['id', 'slug', 'title', 'occurred_at', 'updated_at'])
            ->each(function ($event) use ($sitemap) {
                $path = "/event/{$event->id}" . ($event->slug ? "-{$event->slug}" : '');
                $sitemap->add(
                    Url::create($path)
                        ->setLastModificationDate($event->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_ALWAYS)
                        ->setPriority(0.9)
                        ->addNews(
                            name: 'ClashMonitor',
                            language: 'en',
                            title: $event->title,
                            publicationDate: $event->occurred_at,
                        )
                );
            });

        $sitemap->writeToFile(public_path('sitemap-news.xml'));
    }

    private function generateSitemapIndex(string $baseUrl): void
    {
        SitemapIndex::create()
            ->add("{$baseUrl}/sitemap-static.xml")
            ->add("{$baseUrl}/sitemap-events.xml")
            ->add("{$baseUrl}/sitemap-briefings.xml")
            ->add("{$baseUrl}/sitemap-conflicts.xml")
            ->add("{$baseUrl}/sitemap-regions.xml")
            ->add("{$baseUrl}/sitemap-news.xml")
            ->writeToFile(public_path('sitemap.xml'));
    }
}
