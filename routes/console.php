<?php

use App\Jobs\ProcessPendingEmbeddingsJob;
use App\Jobs\PromoteActorCandidatesJob;
use App\Jobs\RebuildDerivedRelationshipsJob;
use App\Jobs\RefreshStaleActorsJob;
use App\Jobs\ResetSocialDailyCountsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sources:poll')->everyMinute();
Schedule::job(new ProcessPendingEmbeddingsJob)->everyMinute()->withoutOverlapping();
Schedule::command('reconciliation:run')->everyThirtyMinutes();
Schedule::command('events:retry-classification')->everyFifteenMinutes();
Schedule::command('briefing:generate')->dailyAt('03:30');
Schedule::command('threat-level:compute')->everyFifteenMinutes();
Schedule::command('threads:update-stats')->everyFiveMinutes();
Schedule::command('intelligence:refresh-countries')->everyFourHours();
Schedule::command('conflicts:lifecycle')->everyThreeHours();
Schedule::command('acled:refresh-token')->weekly();
Schedule::command('newsletter:dispatch-hourly')->hourly();
Schedule::command('social:dispatch-briefing-posts')->dailyAt('03:45');
Schedule::job(new ResetSocialDailyCountsJob)->dailyAt('00:00');
Schedule::command('social:refresh-meta-tokens')->weeklyOn(1, '03:00');
Schedule::command('sitemap:generate')->dailyAt('04:00');
Schedule::command('sitemap:generate --news-only')->hourly();
Schedule::job(new PromoteActorCandidatesJob)->hourly()->withoutOverlapping();
Schedule::job(new RefreshStaleActorsJob)->weekly()->withoutOverlapping();
Schedule::job(new RebuildDerivedRelationshipsJob)->dailyAt('04:30')->withoutOverlapping();
