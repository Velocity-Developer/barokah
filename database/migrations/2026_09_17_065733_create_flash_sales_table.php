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
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->timestamps();
            $table->index(['product_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sales');
    }
};
