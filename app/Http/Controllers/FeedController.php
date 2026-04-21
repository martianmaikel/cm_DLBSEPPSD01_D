<?php

namespace App\Http\Controllers;

use App\Models\ConflictThread;
use App\Models\DailyBriefing;
use App\Models\Event;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function events(): Response
    {
        $events = Event::whereIn('status', ['corroborated', 'confirmed'])
            ->with('source')
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        return $this->atomResponse(
            view('feeds.events', [
                'events' => $events,
                'feedTitle' => 'ClashMonitor — Latest Events',
                'feedUrl' => url('/feed/events'),
                'siteUrl' => url('/'),
            ])->render()
        );
    }

    public function eventsByCountry(string $country): Response
    {
        $country = strtoupper($country);
        $countryNames = config('geo.country_names', []);
        $countryName = $countryNames[$country] ?? $country;

        $events = Event::whereIn('status', ['corroborated', 'confirmed'])
            ->byCountry($country)
            ->with('source')
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        return $this->atomResponse(
            view('feeds.events', [
                'events' => $events,
                'feedTitle' => "ClashMonitor — {$countryName} Events",
                'feedUrl' => url("/feed/events/{$country}"),
                'siteUrl' => url("/country/{$country}"),
            ])->render()
        );
    }

    public function briefings(): Response
    {
        $briefings = DailyBriefing::orderByDesc('briefing_date')
            ->limit(30)
            ->get();

        return $this->atomResponse(
            view('feeds.briefings', [
                'briefings' => $briefings,
                'feedTitle' => 'ClashMonitor — Daily Intelligence Briefings',
                'feedUrl' => url('/feed/briefings'),
                'siteUrl' => url('/'),
            ])->render()
        );
    }

    public function conflict(string $slug): Response
    {
        $thread = ConflictThread::where('slug', $slug)->firstOrFail();

        $events = Event::whereIn('status', ['corroborated', 'confirmed'])
            ->where('conflict_thread_id', $thread->id)
            ->with('source')
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        return $this->atomResponse(
            view('feeds.events', [
                'events' => $events,
                'feedTitle' => "ClashMonitor — {$thread->name}",
                'feedUrl' => url("/feed/conflict/{$slug}"),
                'siteUrl' => url("/conflict/{$slug}"),
            ])->render()
        );
    }

    private function atomResponse(string $xml): Response
    {
        return response($xml, 200)
            ->header('Content-Type', 'application/atom+xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=900');
    }
}
