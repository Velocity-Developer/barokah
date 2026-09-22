<?php

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function categoryAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('searches, filters and sorts categories with product counts', function () {
    $snacks = Category::factory()->create(['name' => 'Snacks', 'sort_order' => 2, 'is_active' => true]);
    Category::factory()->create(['name' => 'Scarves', 'sort_order' => 1, 'is_active' => true]);
    Category::factory()->create(['name' => 'Old stuff', 'sort_order' => 0, 'is_active' => false]);
    Product::factory()->count(2)->create(['category_id' => $snacks->id]);
    Product::factory()->create(['category_id' => $snacks->id, 'status' => ProductStatus::Draft]);

    $admin = categoryAdmin();
    $get = fn (string $query) => $this->actingAs($admin)->getJson('/api/v1/admin/categories?'.$query)->assertOk();
    $names = fn (string $query) => collect($get($query)->json('data'))->pluck('name')->all();

    expect($names(''))->toBe(['Old stuff', 'Scarves', 'Snacks'])
        ->and($names('status=active'))->toBe(['Scarves', 'Snacks'])
        ->and($names('search=snack'))->toBe(['Snacks'])
        ->and($names('sort=products_desc&status=active'))->toBe(['Snacks', 'Scarves']);

    $get('search=snack')
        ->assertJsonPath('data.0.products_count', 3)
        ->assertJsonPath('data.0.active_products_count', 2)
        ->assertJsonPath('data.0.is_active', true)
        ->assertJsonPath('status_counts.active', 1)
        ->assertJsonPath('status_counts.inactive', 0);
});

it('refuses to delete a category that still has products', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);
    $empty = Category::factory()->create();
    $admin = categoryAdmin();

    $this->actingAs($admin)
        ->deleteJson('/api/v1/admin/categories/'.$category->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, '1 product'));
    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();

    $this->actingAs($admin)->deleteJson('/api/v1/admin/categories/'.$empty->id)->assertNoContent();
    expect(Category::query()->whereKey($empty->id)->exists())->toBeFalse();
});

it('passes product counts to the category edit page', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $this->withoutVite()
        ->actingAs(categoryAdmin())
        ->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Edit')
            ->where('category.products_count', 1)
            ->where('category.active_products_count', 1));
});
