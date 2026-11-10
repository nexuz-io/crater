<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFamilyIdToItemsTable extends Migration
{
    /**
     * Adds the optional family relation to items. Existing rows start
     * with `family_id = NULL` so the migration is safe on populated
     * databases.
     *
     * `ON DELETE RESTRICT` mirrors the brand FK: deleting a family
     * must fail loudly when items still reference it; the controller
     * surfaces the error as a 422 ("items_attached").
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'family_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedInteger('family_id')->nullable()->after('brand_id');
                $table->foreign('family_id')
                    ->references('id')->on('item_families')
                    ->onDelete('restrict');
                $table->index('family_id');
            });
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'family_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropForeign(['family_id']);
                $table->dropColumn('family_id');
            });
        }
    }
}
