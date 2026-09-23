<?php

use App\Enums\ProductStatus;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function imageAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('uploads and removes a category picture', function () {
    Storage::fake('public');

    $category = Category::factory()->create();
    $admin = imageAdmin();

    $this->actingAs($admin)
        ->post('/api/v1/admin/categories/'.$category->id, [
            '_method' => 'PUT',
            'name' => $category->name,
            'image' => UploadedFile::fake()->image('keripik.jpg', 600, 600),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.own_image_url', fn (?string $url) => str_contains((string) $url, 'categories/'));

    $path = $category->fresh()->image_path;
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)
        ->post('/api/v1/admin/categories/'.$category->id, ['_method' => 'PUT', 'remove_image' => '1'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.own_image_url', null);

    Storage::disk('public')->assertMissing($path);
    expect($category->fresh()->image_path)->toBeNull();
});

it('gives a new category its picture straight away', function () {
    Storage::fake('public');

    $this->actingAs(imageAdmin())
        ->post('/api/v1/admin/categories', [
            'name' => 'Kerudung',
            'image' => UploadedFile::fake()->image('kerudung.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $category = Category::query()->where('name', 'Kerudung')->firstOrFail();

    expect($category->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($category->image_path);
});

it('prefers the category picture over a product photo', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Active]);
    ProductImage::factory()->create(['product_id' => $product->id, 'is_primary' => true]);

    $shown = fn (): string => (string) (new CategoryResource(
        Category::query()->whereKey($category->id)->with(['products' => fn ($query) => $query->with('images')])->first()
    ))->toArray(request())['image_url'];

    // With no picture of its own, a product photo stands in.
    expect(str_contains($shown(), 'products/'))->toBeTrue();

    $category->forceFill(['image_path' => 'categories/keripik.jpg'])->save();

    expect(str_contains($shown(), 'categories/keripik.jpg'))->toBeTrue();
});
