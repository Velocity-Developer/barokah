<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::forget('sitemap.xml'));

it('lists the pages shoppers can open', function () {
    $category = Category::factory()->create(['slug' => 'keripik', 'is_active' => true]);
    $store = Seller::factory()->create(['slug' => 'ahmad-kerepek', 'status' => SellerStatus::Active]);
    $product = Product::factory()->create([
        'seller_id' => $store->id,
        'category_id' => $category->id,
        'slug' => 'keripik-pisang',
        'status' => ProductStatus::Active,
    ]);

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)->toContain(route('home'))
        ->and($xml)->toContain(route('products.index'))
        ->and($xml)->toContain(route('sellers.show', $store->slug))
        ->and($xml)->toContain(route('products.show', $product->slug))
        ->and($xml)->toContain('category=keripik');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('leaves out what shoppers cannot buy', function () {
    $draft = Product::factory()->create(['slug' => 'draft-product', 'status' => ProductStatus::Draft]);
    $suspended = Seller::factory()->create(['slug' => 'toko-suspend', 'status' => SellerStatus::Suspended]);
    $hidden = Product::factory()->create(['seller_id' => $suspended->id, 'slug' => 'hidden-product', 'status' => ProductStatus::Active]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->not->toContain($draft->slug)
        ->and($xml)->not->toContain($suspended->slug)
        ->and($xml)->not->toContain($hidden->slug);
});

it('points robots.txt at the sitemap and keeps private pages out', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain('Sitemap: '.route('sitemap'))
        ->and($robots)->toContain('Disallow: /admin')
        ->and($robots)->toContain('Disallow: /checkout');
});

it('closes the door to crawlers during maintenance', function () {
    $settings = app(SettingsService::class);
    $settings->set('general.maintenance_mode', true);
    $settings->forget();

    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain('Disallow: /')
        ->and($robots)->not->toContain('Sitemap:');
});
