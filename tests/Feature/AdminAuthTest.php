<?php

use App\Models\Event;
use App\Models\Source;
use App\Models\SourceFamily;
use Tests\Support\CreatesSqliteSchema;

uses(Tests\TestCase::class, CreatesSqliteSchema::class);

beforeEach(function () {
    $this->createTestSchema();
    config(['app.admin_secret' => 'test-admin-secret']);
});

afterEach(function () {
    $this->dropTestSchema();
});

// ── Login ─────────────────────────────────────────────────────────────────────

test('POST /admin/login with correct token sets session and redirects to dashboard', function () {
    $response = $this->post('/admin/login', ['token' => 'test-admin-secret']);

    $response->assertRedirectToRoute('admin.dashboard');
    $this->assertTrue(session()->get('admin_authenticated') === true);
});

test('POST /admin/login with wrong token redirects back with error', function () {
    $response = $this->post('/admin/login', ['token' => 'wrong-token']);

    $response->assertRedirect();
    $response->assertSessionHasErrors('token');
});

test('POST /admin/login with empty token fails validation', function () {
    $response = $this->post('/admin/login', ['token' => '']);

    $response->assertRedirect();
    $response->assertSessionHasErrors('token');
});

test('POST /admin/login with missing token fails validation', function () {
    $response = $this->post('/admin/login', []);

    $response->assertRedirect();
    $response->assertSessionHasErrors('token');
});

// ── Admin routes require authentication ───────────────────────────────────────

test('GET /admin without auth returns 403', function () {
    $response = $this->getJson('/admin');

    $response->assertStatus(403);
});

test('GET /admin with valid Bearer token passes middleware', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer test-admin-secret',
    ])->get('/admin');

    // Should not be 403 — could be 200 or redirect depending on Inertia
    expect($response->getStatusCode())->not->toBe(403);
});

test('GET /admin/events without auth returns 403', function () {
    $response = $this->getJson('/admin/events');

    $response->assertStatus(403);
});

test('GET /admin/events with valid session is accessible', function () {
    $this->withSession(['admin_authenticated' => true]);

    $response = $this->get('/admin/events');

    expect($response->getStatusCode())->not->toBe(403);
});

// ── Admin event status update ─────────────────────────────────────────────────

test('PATCH /admin/events/{event}/status updates status to disputed', function () {
    $family = SourceFamily::create(['name' => 'Admin Test Family']);
    $source = Source::create([
        'name' => 'Admin Source',
        'type' => 'rss',
        'url' => 'https://example.com',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    $event = Event::create([
        'title' => 'Event to dispute',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer test-admin-secret',
    ])->patch("/admin/events/{$event->id}/status", ['status' => 'disputed']);

    $response->assertRedirect();

    $event->refresh();
    expect($event->status)->toBe('disputed');
});

test('PATCH /admin/events/{event}/status updates status to retracted', function () {
    $family = SourceFamily::create(['name' => 'Retract Test Family']);
    $source = Source::create([
        'name' => 'Retract Source',
        'type' => 'rss',
        'url' => 'https://example.com',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    $event = Event::create([
        'title' => 'Event to retract',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'corroborated',
        'country' => 'UA',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 1,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer test-admin-secret',
    ])->patch("/admin/events/{$event->id}/status", ['status' => 'retracted']);

    $response->assertRedirect();

    $event->refresh();
    expect($event->status)->toBe('retracted');
});

test('PATCH /admin/events/{event}/status rejects invalid status', function () {
    $family = SourceFamily::create(['name' => 'Invalid Status Family']);
    $source = Source::create([
        'name' => 'Invalid Status Source',
        'type' => 'rss',
        'url' => 'https://example.com',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    $event = Event::create([
        'title' => 'Event status validation',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer test-admin-secret',
    ])->patch("/admin/events/{$event->id}/status", ['status' => 'confirmed']);

    $response->assertSessionHasErrors('status');

    $event->refresh();
    expect($event->status)->toBe('unverified');
});

test('PATCH /admin/events/{event}/status without auth returns 403', function () {
    $family = SourceFamily::create(['name' => 'No Auth Family']);
    $source = Source::create([
        'name' => 'No Auth Source',
        'type' => 'rss',
        'url' => 'https://example.com',
        'source_family_id' => $family->id,
        'polling_interval' => 10,
        'active' => true,
    ]);

    $event = Event::create([
        'title' => 'Unauthorized patch',
        'raw_content' => 'Content.',
        'summary' => 'Summary.',
        'category' => 'airstrike',
        'severity' => 5,
        'confidence' => 5,
        'status' => 'unverified',
        'country' => 'UA',
        'geo_approximate' => true,
        'source_id' => $source->id,
        'corroboration_count' => 0,
        'hash' => md5(uniqid()),
        'occurred_at' => now(),
        'classification_attempts' => 1,
    ]);

    $response = $this->patchJson("/admin/events/{$event->id}/status", ['status' => 'disputed']);

    $response->assertStatus(403);
});

// ── Logout ────────────────────────────────────────────────────────────────────

test('POST /admin/logout clears session and redirects to login', function () {
    $this->withSession(['admin_authenticated' => true]);

    $response = $this->post('/admin/logout');

    $response->assertRedirectToRoute('admin.login');
    $this->assertFalse(session()->has('admin_authenticated'));
});
