<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Ingestion\ApiConnectors\AcledConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RefreshAcledTokenCommand extends Command
{
    protected $signature = 'acled:refresh-token {--force : Force full re-authentication instead of refresh}';

    protected $description = 'Refresh ACLED OAuth 2.0 access tokens for all active ACLED sources';

    private const TOKEN_URL = 'https://acleddata.com/oauth/token';

    public function handle(): int
    {
        $sources = Source::where('active', true)
            ->where('connector_class', AcledConnector::class)
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No active ACLED sources found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($sources as $source) {
            $this->info("Refreshing token for: {$source->name} (ID: {$source->id})");

            if ($this->option('force')) {
                $success = $this->authenticate($source);
            } else {
                $success = $this->refresh($source) ?? $this->authenticate($source);
            }

            if ($success) {
                $this->info('  Token refreshed successfully.');
            } else {
                $this->error("  Failed to refresh token for source {$source->id}.");
                $failed++;
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function refresh(Source $source): ?bool
    {
        $refreshToken = Redis::get("acled:refresh_token:{$source->id}");

        if (! $refreshToken) {
            $this->warn('  No cached refresh token, falling back to password auth.');

            return null;
        }

        try {
            $response = Http::timeout(15)->asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => 'acled',
            ]);

            if ($response->successful() && $response->json('access_token')) {
                $this->cacheTokens($source, $response->json());

                return true;
            }

            $this->warn("  Refresh failed (status {$response->status()}), falling back to password auth.");
        } catch (\Throwable $e) {
            $this->warn("  Refresh error: {$e->getMessage()}");
        }

        return null;
    }

    private function authenticate(Source $source): bool
    {
        $config = $source->connector_config ?? [];
        $email = $config['email'] ?? config('services.acled.email');
        $password = $config['password'] ?? config('services.acled.password');

        if (empty($email) || empty($password)) {
            $this->error('  ACLED email or password not configured.');

            return false;
        }

        try {
            $response = Http::timeout(15)->asForm()->post(self::TOKEN_URL, [
                'username' => $email,
                'password' => $password,
                'grant_type' => 'password',
                'client_id' => 'acled',
            ]);

            if ($response->successful() && $response->json('access_token')) {
                $this->cacheTokens($source, $response->json());

                return true;
            }

            $this->error("  Authentication failed (status {$response->status()}): " . $response->body());
        } catch (\Throwable $e) {
            $this->error("  Authentication error: {$e->getMessage()}");
            Log::error('ACLED token refresh command error', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function cacheTokens(Source $source, array $tokenData): void
    {
        if (! empty($tokenData['access_token'])) {
            Redis::setex("acled:access_token:{$source->id}", 82800, $tokenData['access_token']); // 23h
        }
        if (! empty($tokenData['refresh_token'])) {
            Redis::setex("acled:refresh_token:{$source->id}", 1123200, $tokenData['refresh_token']); // 13d
        }
    }
}
