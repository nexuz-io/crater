<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBrandIdToItemsTable extends Migration
{
    /**
     * Items gain an optional `brand_id`. Existing rows stay unbranded;
     * edits can later assign a brand from the company catalog.
     *
     * Following the project convention (see units / items.unit_id):
     * deleting a brand does not cascade-delete its items, so the
     * controller is responsible for rejecting the deletion when there
     * are still attached items.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'brand_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedInteger('brand_id')->nullable()->after('unit_id');
                $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
                $table->index('brand_id');
            });
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'brand_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropForeign(['brand_id']);
                $table->dropColumn('brand_id');
            });
        }
    }
}
