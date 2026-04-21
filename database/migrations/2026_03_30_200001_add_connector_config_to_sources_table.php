<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the type enum to support new connector types
        DB::statement("ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check");
        DB::statement("ALTER TABLE sources ALTER COLUMN type TYPE varchar(50)");
        DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type IN ('rss', 'telegram', 'api', 'csv_import', 'scraper', 'manual'))");

        Schema::table('sources', function (Blueprint $table) {
            $table->jsonb('connector_config')->nullable()->after('url');
            $table->string('connector_class')->nullable()->after('connector_config');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['connector_config', 'connector_class']);
        });

        DB::statement("ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check");
        DB::statement("ALTER TABLE sources ALTER COLUMN type TYPE varchar(255)");
        DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type IN ('rss', 'telegram', 'manual'))");
    }
};
