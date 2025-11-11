<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->text('privacy_policy')->nullable();
            $table->text('terms')->nullable();
            $table->text('refund_process')->nullable();
            $table->string('license')->nullable();
            $table->boolean('isShopOpen');
            $table->string('shop_name', 255)->nullable();
            $table->string('shop_address')->nullable();
            $table->text('shop_details')->nullable();
            $table->string('shop_phone', 25)->nullable();
            $table->decimal('tax', 5, 2)->nullable();
            $table->decimal('delivery_charge', 10, 2)->nullable();
            $table->string('shop_logo')->nullable(); // absolute URL
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
