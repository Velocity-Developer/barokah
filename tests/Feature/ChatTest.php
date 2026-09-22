<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function activeStore(): Seller
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);

    return Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);
}

it('sends guests to login when starting a chat', function () {
    $this->post(route('sellers.chat', activeStore()))->assertRedirect(route('login'));
});

it('opens one conversation per buyer and store', function () {
    $store = activeStore();
    $product = Product::factory()->create(['seller_id' => $store->id]);
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->post(route('sellers.chat', ['seller' => $store, 'product' => $product->slug]))
        ->assertRedirect(route('profile.show', [
            'tab' => 'messages',
            'conversation' => Conversation::query()->value('id'),
            'product' => $product->slug,
        ]));

    $this->actingAs($buyer)->post(route('sellers.chat', $store));

    expect(Conversation::query()->count())->toBe(1);
});

it('does not open chats with your own or inactive stores', function () {
    $store = activeStore();
    $pending = Seller::factory()->create(['status' => SellerStatus::Pending]);

    $this->actingAs($store->user)->post(route('sellers.chat', $store))->assertRedirect();
    $this->actingAs(User::factory()->create())->post(route('sellers.chat', $pending))->assertNotFound();

    expect(Conversation::query()->count())->toBe(0);
});

it('lets buyer and store owner exchange messages and tracks unread counts', function () {
    $store = activeStore();
    $buyer = User::factory()->create();
    $conversation = Conversation::query()->create(['buyer_id' => $buyer->id, 'seller_id' => $store->id]);

    $this->actingAs($buyer)
        ->postJson(route('chat.messages.store', $conversation), ['body' => '  Is this in stock?  '])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Is this in stock?')
        ->assertJsonPath('data.mine', true);

    // The store owner sees one unread message, in the list and in shared props.
    $this->actingAs($store->user)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->assertJsonPath('data.0.role', 'seller')
        ->assertJsonPath('data.0.counterpart.name', $buyer->name)
        ->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('unread_total', 1);

    $this->actingAs($store->user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.unread_messages', 1));

    // Reading the thread clears it; the reply is unread for the buyer.
    $this->actingAs($store->user)
        ->getJson(route('chat.messages.index', $conversation))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.mine', false);

    $this->travel(1)->seconds();

    $reply = $this->actingAs($store->user)
        ->postJson(route('chat.messages.store', $conversation), ['body' => 'Yes, ready to ship.'])
        ->json('data.id');

    expect(Conversation::unreadTotalFor($store->user))->toBe(0)
        ->and(Conversation::unreadTotalFor($buyer))->toBe(1);

    // Polling with `after` only returns newer messages.
    $this->actingAs($buyer)
        ->getJson(route('chat.messages.index', ['conversation' => $conversation, 'after' => $reply]))
        ->assertJsonCount(0, 'data');
});

it('keeps conversations private to their participants', function () {
    $conversation = Conversation::query()->create(['buyer_id' => User::factory()->create()->id, 'seller_id' => activeStore()->id]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->getJson(route('chat.messages.index', $conversation))->assertForbidden();
    $this->actingAs($stranger)->postJson(route('chat.messages.store', $conversation), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($stranger)->getJson(route('chat.conversations.index'))->assertJsonCount(0, 'data');
});

it('validates messages and only attaches the store\'s active products', function () {
    $store = activeStore();
    $buyer = User::factory()->create();
    $conversation = Conversation::query()->create(['buyer_id' => $buyer->id, 'seller_id' => $store->id]);
    $own = Product::factory()->create(['seller_id' => $store->id]);
    $other = Product::factory()->create();
    $draft = Product::factory()->create(['seller_id' => $store->id, 'status' => ProductStatus::Draft]);

    $this->actingAs($buyer)->postJson(route('chat.messages.store', $conversation), ['body' => '   '])->assertUnprocessable();

    $this->actingAs($buyer)
        ->postJson(route('chat.messages.store', $conversation), ['body' => 'About this', 'product_id' => $own->id])
        ->assertJsonPath('data.product.slug', $own->slug);

    foreach ([$other, $draft] as $product) {
        $this->actingAs($buyer)
            ->postJson(route('chat.messages.store', $conversation), ['body' => 'And this?', 'product_id' => $product->id])
            ->assertJsonPath('data.product', null);
    }
});
