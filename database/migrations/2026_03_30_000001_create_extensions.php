<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supabase images expect this role for extension management
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'supabase_admin') THEN
                CREATE ROLE supabase_admin WITH LOGIN SUPERUSER;
            END IF;
        END $$");

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
