<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_prices') && !Schema::hasTable('item_prices')) {
            Schema::rename('product_prices', 'item_prices');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('item_prices') && !Schema::hasTable('product_prices')) {
            Schema::rename('item_prices', 'product_prices');
        }
    }
};
