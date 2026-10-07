<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product variations, Shopee style: up to two variation types per product
 * (e.g. Colour × Size). Each option of a type may carry its own photo; each
 * combination of options is a variant with its own price, stock and weight.
 *
 * products.price/stock stay in use as the cheapest variant price and the
 * total variant stock, so listings, filters and dashboards keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // e.g. ["Colour", "Size"]; null when the product has no variations.
            $table->json('variation_names')->nullable()->after('weight_grams');
        });

        Schema::create('product_variation_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('name', 50);
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'level', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option1_id')->constrained('product_variation_options')->cascadeOnDelete();
            $table->foreignId('option2_id')->nullable()->constrained('product_variation_options')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'option1_id', 'option2_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_label')->nullable()->after('product_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_label');
        });
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_variation_options');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('variation_names');
        });
    }
};
