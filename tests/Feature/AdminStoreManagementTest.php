<?php

use App\Enums\SellerStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function storeAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('searches, filters and counts stores', function () {
    $owner = User::factory()->create(['name' => 'Aisyah Rahman', 'email' => 'aisyah@example.com']);
    $store = Seller::factory()->create(['user_id' => $owner->id, 'store_name' => 'Kerepek Mak Cik', 'status' => SellerStatus::Active]);
    Product::factory()->count(2)->create(['seller_id' => $store->id]);
    Seller::factory()->create(['status' => SellerStatus::Pending]);
    Seller::factory()->create(['status' => SellerStatus::Suspended]);

    $admin = storeAdmin();
    $get = fn (string $query) => $this->actingAs($admin)->getJson('/api/v1/admin/sellers?'.$query)->assertOk();

    $get('search=aisyah')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.store_name', 'Kerepek Mak Cik')
        ->assertJsonPath('data.0.products_count', 2)
        ->assertJsonPath('data.0.owner.email', 'aisyah@example.com');

    $get('')
        ->assertJsonPath('status_counts.active', 1)
        ->assertJsonPath('status_counts.pending', 1)
        ->assertJsonPath('status_counts.suspended', 1)
        ->assertJsonPath('status_counts_total', 3);

    $get('status=suspended')->assertJsonCount(1, 'data');
});

it('shows store stats on the detail page', function () {
    $store = Seller::factory()->create(['status' => SellerStatus::Active]);
    Product::factory()->create(['seller_id' => $store->id]);
    $store->followers()->attach(User::factory()->create());

    $this->withoutVite()->actingAs(storeAdmin())
        ->get(route('admin.sellers.show', $store))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sellers/Show')
            ->where('public_url', route('sellers.show', $store->slug))
            ->where('stats.followers', 1)
            ->where('stats.orders', 0)
            ->has('seller.products', 1)
            ->has('recent_orders', 0));
});

it('lets admins suspend and reactivate a store', function () {
    $store = Seller::factory()->create(['status' => SellerStatus::Active]);
    $admin = storeAdmin();

    $this->actingAs($admin)->putJson("/api/v1/admin/sellers/{$store->id}", ['status' => 'suspended'])->assertOk();
    expect($store->refresh()->status)->toBe(SellerStatus::Suspended);

    $this->actingAs($admin)->putJson("/api/v1/admin/sellers/{$store->id}", ['status' => 'active'])->assertOk();
    expect($store->refresh()->status)->toBe(SellerStatus::Active);
});

it('hides the products of suspended stores from the storefront', function () {
    $store = Seller::factory()->create(['status' => SellerStatus::Suspended]);
    $product = Product::factory()->create(['seller_id' => $store->id, 'slug' => 'hidden-keripik']);

    expect(Product::query()->active()->whereKey($product->id)->exists())->toBeFalse()
        ->and($product->isAvailable())->toBeFalse();

    $this->get(route('products.show', $product->slug))->assertNotFound();

    $store->update(['status' => SellerStatus::Active]);

    expect(Product::query()->active()->whereKey($product->id)->exists())->toBeTrue()
        ->and($product->fresh()->isAvailable())->toBeTrue();
});

it('lets admins upload and remove a store banner with the owner banner as fallback', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $owner->forceFill(['banner_path' => 'users/banners/owner.jpg'])->save();
    $store = Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);
    $admin = storeAdmin();

    $this->actingAs($admin)
        ->post('/api/v1/admin/sellers/'.$store->id, [
            '_method' => 'PUT',
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 300),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.banner_url', fn (string $url) => str_contains($url, 'sellers/banners/'));

    $path = $store->fresh()->banner_path;
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)
        ->post('/api/v1/admin/sellers/'.$store->id, ['_method' => 'PUT', 'remove_banner' => '1'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.banner_url', fn (string $url) => str_contains($url, 'users/banners/owner.jpg'));

    Storage::disk('public')->assertMissing($path);
    expect($store->fresh()->banner_path)->toBeNull();
});
