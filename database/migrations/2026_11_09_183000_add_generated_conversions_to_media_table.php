<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * spatie/laravel-medialibrary v11 introduced a `generated_conversions`
     * JSON column on the `media` table. Crater's original migration
     * (`2019_09_14_120124_create_media_table.php`) predates that column,
     * so any insert/update fails with:
     *
     *   SQLSTATE[42S22]: 1054 Unknown column 'generated_conversions' ...
     *
     * This migration adds the missing column to existing installations
     * without touching the legacy TEXT columns Crater has been writing
     * to (manipulations, custom_properties, responsive_images). Laravel
     * decodes JSON columns automatically when reading them through the
     * model's `array` casts, so they remain compatible.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->json('generated_conversions')->nullable()->after('custom_properties');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('generated_conversions');
        });
    }
};
