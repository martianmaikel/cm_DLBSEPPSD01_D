<?php

use App\Http\Controllers\ActorController;
use App\Http\Controllers\Admin\AdminActorController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminEventController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\AdminPipelineController;
use App\Http\Controllers\Admin\AdminRelationshipController;
use App\Http\Controllers\Admin\AdminAffiliateController;
use App\Http\Controllers\Admin\AdminAiUsageController;
use App\Http\Controllers\Admin\AdminNewsletterController;
use App\Http\Controllers\Admin\AdminSocialChannelController;
use App\Http\Controllers\Admin\AdminSourceController;
use App\Http\Controllers\Admin\AdminSourceFamilyController;
use App\Http\Controllers\Admin\AdminSubscriberController;
use App\Http\Controllers\Admin\AdminThreadController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\MapApiController;
use App\Http\Controllers\Api\ThreadApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\ConflictsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DigestController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\AffiliateRedirectController;
use App\Http\Controllers\MethodologyController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Webhooks\SesWebhookController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GraphController;
use Illuminate\Support\Facades\Route;

// SEO: Dynamic robots.txt
Route::get('/robots.txt', function () {
    $sitemap = url('/sitemap.xml');
    $content = implode("\n", [
        'User-agent: *',
        'Allow: /',
        '',
        'Disallow: /admin',
        'Disallow: /admin/',
        'Disallow: /api/',
        'Disallow: /newsletter/confirm/',
        'Disallow: /newsletter/preferences/',
        'Disallow: /newsletter/unsubscribe/',
        'Disallow: /r/a/',
        '',
        "Sitemap: {$sitemap}",
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain');
});

// OG Image generation
// Route::get('/og/debug', [OgImageController::class, 'debug']);
Route::get('/og/briefing/{date}', [OgImageController::class, 'briefing']);
Route::get('/og/conflict/{slug}', [OgImageController::class, 'conflict']);
Route::get('/og/actor/{actor}', [OgImageController::class, 'actor']);

// Atom feeds
Route::get('/feed/events', [FeedController::class, 'events'])->name('feed.events');
Route::get('/feed/events/{country}', [FeedController::class, 'eventsByCountry'])->name('feed.events.country');
Route::get('/feed/briefings', [FeedController::class, 'briefings'])->name('feed.briefings');
Route::get('/feed/conflict/{slug}', [FeedController::class, 'conflict'])->name('feed.conflict');

// Public routes (Inertia)
Route::get('/', [DashboardController::class, 'index']);
Route::get('/conflicts', [ConflictsController::class, 'index']);
Route::get('/conflict/{slug}/timeline', [ConflictsController::class, 'timeline']);
Route::get('/conflict/{slug}', [ConflictsController::class, 'show']);
Route::get('/region/{slug}', [RegionController::class, 'show']);
Route::get('/map/hotzones', [MapController::class, 'hotzones']);
Route::get('/country/{code}/dossier', [MapController::class, 'dossier']);
Route::get('/country/{code}', [MapController::class, 'country']);
Route::get('/event/{event}-{slug?}', [EventController::class, 'show'])->where('event', '[0-9a-f-]{36}');
Route::get('/event/{event}', [EventController::class, 'show'])->where('event', '[0-9a-f-]{36}');
Route::get('/thread/{thread}', [ThreadController::class, 'show']);
Route::get('/actors', [ActorController::class, 'index'])->name('actors.index');
Route::get('/actor/{slug}', [ActorController::class, 'show'])->name('actors.show');
Route::get('/graph', [GraphController::class, 'index'])->name('graph.index');
Route::get('/influence', [\App\Http\Controllers\InfluenceController::class, 'index'])->name('influence.index');
Route::get('/influence/compare', [\App\Http\Controllers\InfluenceController::class, 'compare'])->name('influence.compare');
Route::get('/briefing/{date?}', [BriefingController::class, 'show']);
Route::get('/digest', [DigestController::class, 'latest']);
Route::get('/digest/{week}', [DigestController::class, 'show'])->where('week', '\d{4}-W\d{1,2}');
Route::get('/methodology', [MethodologyController::class, 'index']);
Route::get('/impressum', [LegalController::class, 'impressum']);
Route::get('/datenschutz', [LegalController::class, 'privacy']);

// API routes for frontend polling
Route::prefix('api')->group(function () {
    Route::get('/dashboard', [DashboardApiController::class, 'index']);
    Route::get('/dashboard/ticker', [DashboardApiController::class, 'ticker']);
    Route::get('/dashboard/briefing', [DashboardApiController::class, 'briefing']);
    Route::get('/dashboard/briefings', [DashboardApiController::class, 'briefings']);
    Route::get('/map/world', [MapApiController::class, 'worldData']);
    Route::get('/map/hotzones', [MapApiController::class, 'hotzones']);
    Route::get('/map/country-heat', [MapApiController::class, 'countryHeat']);
    Route::get('/map/threat-level', [MapApiController::class, 'threatLevel']);
    Route::get('/map/country-brief/{code}', [MapApiController::class, 'countryBrief']);
    Route::get('/events', [EventApiController::class, 'index']);
    Route::get('/events/{event}', [EventApiController::class, 'show']);
    Route::get('/threads/{thread}/events', [ThreadApiController::class, 'events']);

    // Relationship graph
    Route::get('/graph/global', [\App\Http\Controllers\Api\GraphApiController::class, 'globalGraph']);
    Route::get('/graph/centrality', [\App\Http\Controllers\Api\CentralityController::class, 'index']);
    Route::get('/graph/node/{type}/{id}', [\App\Http\Controllers\Api\GraphApiController::class, 'node'])
        ->where(['type' => 'actor|country|conflict|event']);
});

// Newsletter (public)
Route::get('/newsletter', [NewsletterController::class, 'subscribeForm'])->name('newsletter.form');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:5,1')
    ->name('newsletter.subscribe');
Route::get('/newsletter/subscribed', [NewsletterController::class, 'subscribed'])->name('newsletter.subscribed');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/preferences/{token}', [NewsletterController::class, 'preferences'])->name('newsletter.preferences');
Route::patch('/newsletter/preferences/{token}', [NewsletterController::class, 'updatePreferences'])->name('newsletter.preferences.update');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribeForm'])->name('newsletter.unsubscribe');
Route::post('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribePost'])
    ->name('newsletter.unsubscribe.post');

// Affiliate click tracking + redirect (public)
Route::get('/r/a/{slug}', [AffiliateRedirectController::class, 'redirect'])->name('affiliate.redirect');

// SES / SNS webhook (public, CSRF excluded via bootstrap/app.php)
Route::post('/webhooks/ses/notifications', [SesWebhookController::class, 'handle'])->name('webhooks.ses');

// Admin auth (no middleware)
Route::middleware(\App\Http\Middleware\NoIndexMiddleware::class)->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

// Admin routes (token-protected)
Route::prefix('admin')->middleware(['admin.token', \App\Http\Middleware\NoIndexMiddleware::class])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::resource('sources', AdminSourceController::class);
    Route::resource('source-families', AdminSourceFamilyController::class);
    Route::get('/events', [AdminEventController::class, 'index'])->name('admin.events.index');
    Route::patch('/events/{event}/status', [AdminEventController::class, 'updateStatus']);
    Route::patch('/events/{event}/thread', [AdminEventController::class, 'reassignThread']);
    Route::resource('threads', AdminThreadController::class)->only(['index', 'show', 'update']);
    Route::get('/pipeline', [AdminPipelineController::class, 'status'])->name('admin.pipeline');

    // Newsletter
    Route::get('/subscribers', [AdminSubscriberController::class, 'index'])->name('admin.subscribers.index');
    Route::get('/subscribers/{subscriber}', [AdminSubscriberController::class, 'show'])->name('admin.subscribers.show');
    Route::post('/subscribers/{subscriber}/send-test', [AdminSubscriberController::class, 'sendTest'])->name('admin.subscribers.send-test');
    Route::post('/subscribers/{subscriber}/send-daily', [AdminSubscriberController::class, 'sendDaily'])->name('admin.subscribers.send-daily');
    Route::get('/subscribers/{subscriber}/preview/daily', [AdminSubscriberController::class, 'previewDaily'])->name('admin.subscribers.preview-daily');
    Route::get('/subscribers/{subscriber}/preview/critical', [AdminSubscriberController::class, 'previewCritical'])->name('admin.subscribers.preview-critical');
    Route::delete('/subscribers/{subscriber}', [AdminSubscriberController::class, 'destroy'])->name('admin.subscribers.destroy');
    Route::post('/newsletter/toggle-alerts', [AdminSubscriberController::class, 'toggleAlerts'])->name('admin.newsletter.toggle-alerts');

    // Affiliates
    Route::get('/affiliates', [AdminAffiliateController::class, 'index'])->name('admin.affiliates.index');
    Route::post('/affiliates', [AdminAffiliateController::class, 'store'])->name('admin.affiliates.store');
    Route::put('/affiliates/{affiliate}', [AdminAffiliateController::class, 'update'])->name('admin.affiliates.update');
    Route::delete('/affiliates/{affiliate}', [AdminAffiliateController::class, 'destroy'])->name('admin.affiliates.destroy');

    // Social channels
    Route::get('/social-channels', [AdminSocialChannelController::class, 'index'])->name('admin.social-channels.index');
    Route::post('/social-channels', [AdminSocialChannelController::class, 'store'])->name('admin.social-channels.store');
    Route::put('/social-channels/{socialChannel}', [AdminSocialChannelController::class, 'update'])->name('admin.social-channels.update');
    Route::delete('/social-channels/{socialChannel}', [AdminSocialChannelController::class, 'destroy'])->name('admin.social-channels.destroy');

    // Newsletter stats
    Route::get('/newsletter/stats', [AdminNewsletterController::class, 'stats'])->name('admin.newsletter.stats');

    // Relationship graph
    Route::get('/relationships', [AdminRelationshipController::class, 'index'])->name('admin.relationships.index');
    Route::post('/relationships', [AdminRelationshipController::class, 'store'])->name('admin.relationships.store');
    Route::put('/relationships/{relationship}', [AdminRelationshipController::class, 'update'])->name('admin.relationships.update');
    Route::delete('/relationships/{relationship}', [AdminRelationshipController::class, 'destroy'])->name('admin.relationships.destroy');
    Route::post('/relationships/rebuild-derived', [AdminRelationshipController::class, 'rebuildDerived'])->name('admin.relationships.rebuild');

    // Actors directory
    Route::get('/actors', [AdminActorController::class, 'index'])->name('admin.actors.index');
    Route::get('/actors/candidates', [AdminActorController::class, 'candidates'])->name('admin.actors.candidates');
    Route::post('/actors/candidates/{candidate}/promote', [AdminActorController::class, 'promoteCandidate'])->name('admin.actors.candidates.promote');
    Route::post('/actors/candidates/{candidate}/block', [AdminActorController::class, 'blockCandidate'])->name('admin.actors.candidates.block');
    Route::post('/actors/candidates/{candidate}/unblock', [AdminActorController::class, 'unblockCandidate'])->name('admin.actors.candidates.unblock');
    Route::get('/actors/{actor}', [AdminActorController::class, 'show'])->name('admin.actors.show');
    Route::put('/actors/{actor}', [AdminActorController::class, 'update'])->name('admin.actors.update');
    Route::post('/actors/{actor}/reenrich', [AdminActorController::class, 'reenrich'])->name('admin.actors.reenrich');
    Route::post('/actors/{actor}/merge', [AdminActorController::class, 'merge'])->name('admin.actors.merge');

    // Logs
    Route::get('/logs', [AdminLogController::class, 'index'])->name('admin.logs');
    Route::get('/logs/download', [AdminLogController::class, 'download'])->name('admin.logs.download');
    Route::post('/logs/clear', [AdminLogController::class, 'clear'])->name('admin.logs.clear');

    // AI Usage
    Route::get('/ai-usage', [AdminAiUsageController::class, 'index'])->name('admin.ai-usage');
});
