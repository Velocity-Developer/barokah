<?php

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Jobs\ExpirePendingOrders;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function variationSeller(): User
{
    $user = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $user->id]);

    return $user->refresh();
}

/**
 * Colour (Black, Navy) × Size (M, L) with prices 20/22/25/27 and stock 1–4.
 *
 * @param  array<int, array<string, mixed>>|null  $types
 */
function variationsJson(?array $types = null, ?array $variants = null): string
{
    $types ??= [
        ['name' => 'Colour', 'options' => [['id' => null, 'name' => 'Black'], ['id' => null, 'name' => 'Navy']]],
        ['name' => 'Size', 'options' => [['id' => null, 'name' => 'M'], ['id' => null, 'name' => 'L']]],
    ];
    $variants ??= [
        ['options' => [0, 0], 'price' => '20.00', 'stock' => 1, 'weight_grams' => null],
        ['options' => [0, 1], 'price' => '22.00', 'stock' => 2, 'weight_grams' => 250],
        ['options' => [1, 0], 'price' => '25.00', 'stock' => 3, 'weight_grams' => null],
        ['options' => [1, 1], 'price' => '27.00', 'stock' => 4, 'weight_grams' => null],
    ];

    return json_encode(['types' => $types, 'variants' => $variants]);
}

function variationProductPayload(Category $category, array $overrides = []): array
{
    return array_merge([
        'category_id' => $category->id,
        'name' => 'Kerudung Bawal',
        'price' => '0',
        'stock' => 0,
        'weight_grams' => 200,
        'status' => ProductStatus::Active->value,
        'variations' => variationsJson(),
    ], $overrides);
}

/**
 * A product with variations saved through the seller API.
 */
function variationProduct(): Product
{
    $seller = variationSeller();
    test()->actingAs($seller)->postJson('/api/v1/seller/products', variationProductPayload(Category::factory()->create()))->assertCreated();
    auth()->logout();

    return Product::query()->latest('id')->firstOrFail();
}

function variantOf(Product $product, string $label): ProductVariant
{
    return $product->variants()->with(['option1', 'option2'])->get()->first(fn (ProductVariant $variant) => $variant->label() === $label);
}

function variationOrderPayload(array $overrides = []): array
{
    return array_merge([
        'buyer' => ['name' => 'Ahmad Buyer', 'address' => '1 Jalan Merdeka', 'state' => 'Selangor', 'post_code' => '40000', 'phone' => '0123456789'],
        'shipping_address' => '99 Jalan Tujuan',
        'shipping_state' => 'Selangor',
        'shipping_city' => 'Petaling Jaya',
        'shipping_post_code' => '46000',
        'payment_method' => 'fpx',
    ], $overrides);
}

test('seller saves a product with two variation types', function () {
    $seller = variationSeller();

    $this->actingAs($seller)
        ->postJson('/api/v1/seller/products', variationProductPayload(Category::factory()->create()))
        ->assertCreated()
        ->assertJsonPath('data.price', '20.00')
        ->assertJsonPath('data.stock', 10)
        ->assertJsonPath('data.variation_names', ['Colour', 'Size'])
        ->assertJsonCount(4, 'data.variations.variants')
        ->assertJsonPath('data.variations.types.1.options.1.name', 'L');

    $product = Product::query()->firstOrFail();

    expect($product->variationOptions()->count())->toBe(4)
        ->and(variantOf($product, 'Black, L')->weight_grams)->toBe(250)
        ->and(variantOf($product, 'Navy, M')->price)->toBe('25.00');
});

test('editing variations keeps matching variants and drops removed options', function () {
    $product = variationProduct();
    $black = $product->variationOptions()->where('name', 'Black')->firstOrFail();
    $m = $product->variationOptions()->where('name', 'M')->firstOrFail();
    $blackM = variantOf($product, 'Black, M');

    // Navy and L are removed; Black / M gets a new price.
    $this->actingAs($product->seller->user)->putJson("/api/v1/seller/products/{$product->id}", [
        'variations' => variationsJson(
            [
                ['name' => 'Colour', 'options' => [['id' => $black->id, 'name' => 'Black']]],
                ['name' => 'Size', 'options' => [['id' => $m->id, 'name' => 'M']]],
            ],
            [['options' => [0, 0], 'price' => '19.00', 'stock' => 6]],
        ),
    ])->assertOk();

    $product->refresh();

    expect($product->variants()->pluck('id')->all())->toBe([$blackM->id])
        ->and($product->variationOptions()->count())->toBe(2)
        ->and($product->price)->toBe('19.00')
        ->and($product->stock)->toBe(6);
});

test('sending no variation types turns a product back into a single item', function () {
    $product = variationProduct();

    $this->actingAs($product->seller->user)->putJson("/api/v1/seller/products/{$product->id}", [
        'price' => '30.00',
        'stock' => 7,
        'variations' => json_encode(['types' => [], 'variants' => []]),
    ])->assertOk()->assertJsonPath('data.variation_names', []);

    $product->refresh();

    expect($product->hasVariations())->toBeFalse()
        ->and($product->variants()->count())->toBe(0)
        ->and(ProductVariationOption::query()->count())->toBe(0)
        ->and($product->price)->toBe('30.00')
        ->and($product->stock)->toBe(7);
});

test('every combination needs exactly one price row', function () {
    $seller = variationSeller();

    $this->actingAs($seller)->postJson('/api/v1/seller/products', variationProductPayload(Category::factory()->create(), [
        'variations' => variationsJson(null, [['options' => [0, 0], 'price' => '20.00', 'stock' => 1]]),
    ]))->assertUnprocessable()->assertJsonValidationErrors('payload');

    expect(Product::query()->count())->toBe(0);
});

test('option names must differ within a variation type', function () {
    $seller = variationSeller();

    $this->actingAs($seller)->postJson('/api/v1/seller/products', variationProductPayload(Category::factory()->create(), [
        'variations' => variationsJson(
            [['name' => 'Colour', 'options' => [['name' => 'Black'], ['name' => 'black ']]]],
            [['options' => [0], 'price' => '1', 'stock' => 1], ['options' => [1], 'price' => '1', 'stock' => 1]],
        ),
    ]))->assertUnprocessable()->assertJsonValidationErrors('payload');
});

test('first-type options take a photo that can be replaced and removed', function () {
    Storage::fake('public');
    $seller = variationSeller();

    $this->actingAs($seller)->post('/api/v1/seller/products', variationProductPayload(Category::factory()->create(), [
        'variation_images' => [1 => UploadedFile::fake()->image('navy.jpg')],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $product = Product::query()->firstOrFail();
    $navy = $product->variationOptions()->where('name', 'Navy')->firstOrFail();

    expect($navy->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($navy->image_path);

    $this->get("/products/{$product->slug}")->assertInertia(fn (Assert $page) => $page
        ->where('product.data.variations.types.0.options.1.image_url', $navy->image_url)
        ->where('product.data.variations.types.0.options.0.image_url', null));

    $options = $product->variationOptions()->get();
    $types = [
        ['name' => 'Colour', 'options' => [
            ['id' => $options->firstWhere('name', 'Black')->id, 'name' => 'Black'],
            ['id' => $navy->id, 'name' => 'Navy', 'remove_image' => true],
        ]],
        ['name' => 'Size', 'options' => [
            ['id' => $options->firstWhere('name', 'M')->id, 'name' => 'M'],
            ['id' => $options->firstWhere('name', 'L')->id, 'name' => 'L'],
        ]],
    ];

    $this->actingAs($seller)->putJson("/api/v1/seller/products/{$product->id}", ['variations' => variationsJson($types)])->assertOk();

    Storage::disk('public')->assertMissing($navy->image_path);
    expect($navy->refresh()->image_path)->toBeNull();
});

test('another seller cannot edit the variations', function () {
    $product = variationProduct();

    $this->actingAs(variationSeller())->putJson("/api/v1/seller/products/{$product->id}", ['variations' => variationsJson()])
        ->assertForbidden();
});

test('checkout charges the chosen variant and takes its stock', function () {
    $product = variationProduct();
    $navyL = variantOf($product, 'Navy, L');

    $this->postJson('/api/v1/orders', variationOrderPayload([
        'items' => [['product_id' => $product->id, 'variant_id' => $navyL->id, 'quantity' => 2]],
    ]))->assertCreated()->assertJsonPath('data.subtotal', '54.00');

    $item = Order::query()->firstOrFail()->items->first();

    expect($item->product_variant_id)->toBe($navyL->id)
        ->and($item->variant_label)->toBe('Navy, L')
        ->and($item->product_name_snapshot)->toBe('Kerudung Bawal (Navy, L)')
        ->and($item->price_snapshot)->toBe('27.00')
        ->and($navyL->refresh()->stock)->toBe(2)
        ->and($product->refresh()->stock)->toBe(8);
});

test('one order can hold two variants of the same product', function () {
    $product = variationProduct();

    $this->postJson('/api/v1/orders', variationOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => variantOf($product, 'Black, M')->id, 'quantity' => 1],
            ['product_id' => $product->id, 'variant_id' => variantOf($product, 'Navy, M')->id, 'quantity' => 1],
        ],
    ]))->assertCreated()->assertJsonPath('data.subtotal', '45.00')->assertJsonCount(2, 'data.items');
});

test('checkout of a product with variations needs a variant of that product', function () {
    $product = variationProduct();
    $other = variationProduct();

    $this->postJson('/api/v1/orders', variationOrderPayload(['product_id' => $product->id, 'quantity' => 1]))
        ->assertStatus(409);
    $this->postJson('/api/v1/orders', variationOrderPayload(['product_id' => $product->id, 'variant_id' => variantOf($other, 'Black, M')->id, 'quantity' => 1]))
        ->assertStatus(409);

    expect(Order::query()->count())->toBe(0);
});

test('checkout refuses more than the variant has in stock', function () {
    $product = variationProduct();
    $blackM = variantOf($product, 'Black, M');

    $this->postJson('/api/v1/orders', variationOrderPayload(['product_id' => $product->id, 'variant_id' => $blackM->id, 'quantity' => 2]))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Insufficient stock for Kerudung Bawal (Black, M).');

    expect($blackM->refresh()->stock)->toBe(1);
});

test('a product without variations refuses a variant id', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Active, 'stock' => 5, 'seller_id' => variationSeller()->seller->id]);

    $this->postJson('/api/v1/orders', variationOrderPayload(['product_id' => $product->id, 'variant_id' => 1, 'quantity' => 1]))
        ->assertStatus(409);
});

test('expired orders give the stock back to the variant', function () {
    $product = variationProduct();
    $navyM = variantOf($product, 'Navy, M');

    $this->postJson('/api/v1/orders', variationOrderPayload(['product_id' => $product->id, 'variant_id' => $navyM->id, 'quantity' => 3]))
        ->assertCreated();
    expect($navyM->refresh()->stock)->toBe(0);

    Order::query()->update(['expired_at' => now()->subMinute()]);
    (new ExpirePendingOrders)->handle();

    expect(Order::query()->first()->status)->toBe(OrderStatus::Expired)
        ->and($navyM->refresh()->stock)->toBe(3)
        ->and($product->refresh()->stock)->toBe(10);
});

test('a percentage flash sale takes the same share off every variant', function () {
    $product = variationProduct();
    FlashSale::query()->create([
        'product_id' => $product->id, 'price' => '15.00', 'discount_type' => 'percentage', 'discount_value' => 25,
        'quantity' => 5, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
    ]);
    $navyL = variantOf($product, 'Navy, L');

    $this->postJson('/api/v1/orders', variationOrderPayload(['product_id' => $product->id, 'variant_id' => $navyL->id, 'quantity' => 1]))
        ->assertCreated()->assertJsonPath('data.subtotal', '20.25');
});

test('a fixed flash sale price keeps the same saving on dearer variants', function () {
    $product = variationProduct();
    // Cheapest variant is 20.00, so a 16.00 promo saves 4.00 on each variant.
    $sale = FlashSale::query()->create([
        'product_id' => $product->id, 'price' => '16.00', 'discount_type' => 'fixed', 'discount_value' => 16,
        'quantity' => 5, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
    ]);

    expect($sale->priceFor($product, variantOf($product, 'Navy, L')))->toBe(23.0)
        ->and($sale->priceFor($product, variantOf($product, 'Black, M')))->toBe(16.0);
});

test('two variants share the flash sale quota', function () {
    $product = variationProduct();
    FlashSale::query()->create([
        'product_id' => $product->id, 'price' => '15.00', 'discount_type' => 'percentage', 'discount_value' => 25,
        'quantity' => 2, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
    ]);

    $this->postJson('/api/v1/orders', variationOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => variantOf($product, 'Navy, M')->id, 'quantity' => 2],
            ['product_id' => $product->id, 'variant_id' => variantOf($product, 'Navy, L')->id, 'quantity' => 1],
        ],
    ]))->assertStatus(409);
});

test('coupon preview prices each variant line', function () {
    $product = variationProduct();
    Coupon::query()->create([
        'code' => 'HEMAT10', 'name' => 'Hemat 10%', 'discount_type' => 'percentage', 'discount_value' => 10,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $this->postJson('/api/v1/coupons/validate', [
        'coupon_code' => 'HEMAT10',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => variantOf($product, 'Black, M')->id, 'quantity' => 1],
            ['product_id' => $product->id, 'variant_id' => variantOf($product, 'Navy, L')->id, 'quantity' => 1],
        ],
    ])->assertOk()->assertJsonPath('discount', 4.7);
});

test('product page sends the variation picker data', function () {
    $product = variationProduct();
    $navyL = variantOf($product, 'Navy, L');

    $this->get("/products/{$product->slug}")->assertInertia(fn (Assert $page) => $page
        ->component('Product/Show')
        ->where('product.data.variations.types.0.name', 'Colour')
        ->where('product.data.variations.variants.3.id', $navyL->id)
        ->where('product.data.variations.variants.3.options', [1, 1])
        ->where('product.data.variations.variants.3.label', 'Navy, L')
        ->where('product.data.variations.variants.3.effective_price', '27.00'));
});

test('seller edit page receives the saved variations', function () {
    $product = variationProduct();

    $this->actingAs($product->seller->user)->get("/seller/products/{$product->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->component('Seller/Products/Edit')
        ->where('variations.types.1.name', 'Size')
        ->has('variations.variants', 4));
});

test('deleting a product removes its variation photos', function () {
    Storage::fake('public');
    $seller = variationSeller();
    $this->actingAs($seller)->post('/api/v1/seller/products', variationProductPayload(Category::factory()->create(), [
        'variation_images' => [0 => UploadedFile::fake()->image('black.jpg')],
    ]), ['Accept' => 'application/json'])->assertCreated();
    $product = Product::query()->firstOrFail();
    $path = $product->variationOptions()->whereNotNull('image_path')->value('image_path');

    $this->actingAs($seller)->deleteJson("/api/v1/seller/products/{$product->id}")->assertNoContent();

    Storage::disk('public')->assertMissing($path);
    expect(ProductVariant::query()->count())->toBe(0);
});
