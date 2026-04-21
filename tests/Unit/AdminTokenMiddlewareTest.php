<?php

use App\Http\Middleware\AdminTokenMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

uses(Tests\TestCase::class);

/**
 * Build a Request with an attached array-backed session so the middleware can
 * call $request->session()->get() / ->put() without hitting a real session driver.
 */
function makeRequest(array $headers = [], array $sessionData = []): Request
{
    $request = Request::create('/admin', 'GET', [], [], [], []);

    foreach ($headers as $key => $value) {
        $request->headers->set($key, $value);
    }

    $session = new Store('test', new ArraySessionHandler(10));
    $session->start();

    foreach ($sessionData as $key => $value) {
        $session->put($key, $value);
    }

    $request->setLaravelSession($session);

    return $request;
}

/**
 * Run the middleware and return the response.
 */
function runMiddleware(Request $request): SymfonyResponse
{
    $middleware = new AdminTokenMiddleware();
    $next = fn(Request $r) => new Response('OK', 200);

    return $middleware->handle($request, $next);
}

beforeEach(function () {
    config(['app.admin_secret' => 'super-secret-token']);
});

test('valid Bearer token in Authorization header passes through', function () {
    $request = makeRequest(['Authorization' => 'Bearer super-secret-token']);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(200);
});

test('valid Bearer token sets admin_authenticated in session', function () {
    $request = makeRequest(['Authorization' => 'Bearer super-secret-token']);
    runMiddleware($request);

    expect($request->session()->get('admin_authenticated'))->toBeTrue();
});

test('invalid Bearer token returns 403', function () {
    $request = makeRequest(['Authorization' => 'Bearer wrong-token']);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});

test('missing Authorization header and no session returns 403', function () {
    $request = makeRequest();
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});

test('valid session passes through without Authorization header', function () {
    $request = makeRequest([], ['admin_authenticated' => true]);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(200);
});

test('session with admin_authenticated false returns 403', function () {
    $request = makeRequest([], ['admin_authenticated' => false]);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});

test('session with admin_authenticated null returns 403', function () {
    $request = makeRequest([], ['admin_authenticated' => null]);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});

test('query string token is not accepted (no backdoor)', function () {
    // Deliberately passing token as query param — must not authenticate
    $request = Request::create('/admin?token=super-secret-token', 'GET');

    $session = new Store('test', new ArraySessionHandler(10));
    $session->start();
    $request->setLaravelSession($session);

    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});

test('Authorization header without Bearer prefix returns 403', function () {
    $request = makeRequest(['Authorization' => 'super-secret-token']);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});

test('empty Bearer token returns 403', function () {
    $request = makeRequest(['Authorization' => 'Bearer ']);
    $response = runMiddleware($request);

    expect($response->getStatusCode())->toBe(403);
});
