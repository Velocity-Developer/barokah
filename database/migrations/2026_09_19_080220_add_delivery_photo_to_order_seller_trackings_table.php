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
        Schema::table('order_seller_trackings', function (Blueprint $table) {
            $table->string('delivery_photo_path', 500)->nullable()->after('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_seller_trackings', function (Blueprint $table) {
            $table->dropColumn('delivery_photo_path');
        });
    }
};
