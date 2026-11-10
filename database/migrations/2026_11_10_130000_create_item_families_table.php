<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemFamiliesTable extends Migration
{
    /**
     * Self-referential adjacency list for item families.
     *
     * `parent_id` is nullable: rows with `parent_id = NULL` are roots.
     * Multiple roots per company are allowed. `name_normalized` is a
     * trimmed + lowercased copy of `name` used for the siblings-unique
     * validation. We can't rely on a `UNIQUE (company_id, parent_id,
     * name_normalized)` index because MySQL/SQLite treat `NULL` as
     * distinct and would still allow two identical roots; the unique
     * contract is therefore enforced in `ItemFamilyService` inside a
     * serialised transaction (lockForUpdate on a parent row).
     *
     * FK semantics:
     *   - company_id → companies.id ON DELETE CASCADE (matches Unit/Brand)
     *   - parent_id → item_families.id ON DELETE RESTRICT (must delete
     *     leaves before ancestors; this is what stops accidental
     *     branch wipes).
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('item_families')) {
            return;
        }

        Schema::create('item_families', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('name', 255);
            $table->string('name_normalized', 255);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('parent_id')
                ->references('id')->on('item_families')
                ->onDelete('restrict');

            $table->index(['company_id', 'parent_id'], 'item_families_company_parent_index');
            $table->index('company_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('item_families')) {
            Schema::dropIfExists('item_families');
        }
    }
}
