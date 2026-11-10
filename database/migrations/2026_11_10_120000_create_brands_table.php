<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBrandsTable extends Migration
{
    /**
     * Each company owns its own brand catalog. Two companies may both
     * have a "Sony" brand; the unique key is per-company.
     *
     * `name_normalized` is a pre-computed lowercase trimmed copy of
     * `name`. MySQL's utf8mb4_unicode_ci collation would already give a
     * case-insensitive comparison but SQLite (used in tests) does not,
     * so the application layer guarantees uniqueness on this column.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('brands')) {
            return;
        }

        Schema::create('brands', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->string('name', 255);
            // Trimmed + lowercased copy used for the per-company unique
            // key. Stored to keep validation cheap and consistent across
            // MySQL / SQLite / PostgreSQL deployments.
            $table->string('name_normalized', 255);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            // Brand deletion must not cascade to items, otherwise users
            // would silently lose their assignment. The controller
            // rejects the deletion instead (see BrandsController).
            $table->unique(['company_id', 'name_normalized'], 'brands_company_name_unique');
            $table->index('company_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('brands')) {
            Schema::dropIfExists('brands');
        }
    }
}
