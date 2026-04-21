<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Resize vector column to support Gemini embedding-001 (3072 dimensions)
        // Drop existing embeddings first since dimension change is incompatible
        DB::statement('TRUNCATE TABLE embeddings');
        DB::statement('ALTER TABLE embeddings DROP COLUMN vector');
        DB::statement('ALTER TABLE embeddings ADD COLUMN vector vector(3072)');
    }

    public function down(): void
    {
        DB::statement('TRUNCATE TABLE embeddings');
        DB::statement('ALTER TABLE embeddings DROP COLUMN vector');
        DB::statement('ALTER TABLE embeddings ADD COLUMN vector vector(1536)');
    }
};
