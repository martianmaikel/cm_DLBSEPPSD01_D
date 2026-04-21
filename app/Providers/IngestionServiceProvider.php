<?php

namespace App\Providers;

use App\Services\Ingestion\ApiConnectors\AcledConnector;
use App\Services\Ingestion\ApiConnectors\GdeltConnector;
use App\Services\Ingestion\ApiConnectors\GdeltGeoConnector;
use App\Services\Ingestion\ApiConnectors\ReliefWebConnector;
use App\Services\Ingestion\ConnectorRegistry;
use App\Services\Ingestion\RssIngestionService;
use App\Services\Ingestion\TelegramIngestionService;
use Illuminate\Support\ServiceProvider;

class IngestionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class, function ($app) {
            $registry = new ConnectorRegistry;

            // Built-in type connectors
            $registry->register('rss', $app->make(RssIngestionService::class));
            $registry->register('telegram', $app->make(TelegramIngestionService::class));

            // API connectors (registered by class name for source-level override)
            $apiConnectors = [
                GdeltConnector::class,
                GdeltGeoConnector::class,
                AcledConnector::class,
                ReliefWebConnector::class,
            ];

            foreach ($apiConnectors as $connectorClass) {
                $connector = $app->make($connectorClass);
                $registry->registerClass($connectorClass, $connector);
            }

            // Generic 'api' type falls through to connector_class on the source
            // No default 'api' connector — each API source must specify its connector_class

            return $registry;
        });
    }
}
