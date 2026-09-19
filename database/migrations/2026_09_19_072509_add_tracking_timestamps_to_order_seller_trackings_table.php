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
            $table->timestamp('received_at')->nullable()->after('tracking_status');
            $table->timestamp('packed_at')->nullable()->after('received_at');
            $table->timestamp('picked_up_at')->nullable()->after('packed_at');
            $table->timestamp('delivered_at')->nullable()->after('picked_up_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_seller_trackings', function (Blueprint $table) {
            $table->dropColumn(['received_at', 'packed_at', 'picked_up_at', 'delivered_at']);
        });
    }
};
