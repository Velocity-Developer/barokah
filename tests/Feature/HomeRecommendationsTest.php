<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

it('paginates recommendations 24 at a time', function () {
    Product::factory()->count(30)->create();
    Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('latestProducts', 24)
            ->where('latestProductsHasMore', true));

    $this->get(route('home', ['recommendations_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('latestProducts', 6)
            ->where('latestProductsHasMore', false));
});

it('loads the next page with a partial reload of only the recommendations', function () {
    Product::factory()->count(26)->create();

    $this->get(route('home', ['recommendations_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly(['latestProducts', 'latestProductsHasMore'], fn (Assert $reload) => $reload
                ->has('latestProducts', 2)
                ->where('latestProductsHasMore', false)
                ->missing('categories')
                ->missing('bestSellerProducts')));
});

it('does not repeat products across pages', function () {
    Product::factory()->count(30)->create(['created_at' => now()]);

    $first = $this->get(route('home'))->viewData('page')['props']['latestProducts'];
    $second = $this->get(route('home', ['recommendations_page' => 2]))->viewData('page')['props']['latestProducts'];

    $ids = collect($first)->merge($second)->pluck('id');

    expect($ids)->toHaveCount(30)->and($ids->unique())->toHaveCount(30);
});
