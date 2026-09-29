<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('currency_code', 3);
            $table->decimal('product_amount', 12, 2);
            $table->decimal('shipping_amount', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->unsignedInteger('orders_count');
            $table->text('bank_account');
            $table->text('seller_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            // Nullable: MariaDB rejects a second NOT NULL timestamp without a default.
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::table('order_seller_trackings', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('seller_id')->constrained('seller_payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_seller_trackings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });

        Schema::dropIfExists('seller_payouts');
    }
};
