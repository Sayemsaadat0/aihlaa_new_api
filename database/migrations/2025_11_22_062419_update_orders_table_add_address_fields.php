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
        Schema::table('orders', function (Blueprint $table) {
            // Drop foreign key and column for address_id
            $table->dropForeign(['address_id']);
            $table->dropColumn('address_id');
            
            // Add address fields directly to orders table
            $table->foreignId('city_id')->nullable()->after('cart_id')->constrained('cities')->nullOnDelete();
            $table->string('state')->nullable()->after('city_id');
            $table->string('zip_code')->nullable()->after('state');
            $table->string('street_address')->nullable()->after('zip_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop new address fields
            $table->dropForeign(['city_id']);
            $table->dropColumn(['city_id', 'state', 'zip_code', 'street_address']);
            
            // Restore address_id
            $table->foreignId('address_id')->nullable()->after('cart_id')->constrained('addresses')->nullOnDelete();
        });
    }
};
