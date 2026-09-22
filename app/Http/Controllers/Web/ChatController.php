<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SendChatMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Buyer ↔ store chat. Pages poll these JSON endpoints; there is no
 * websocket server on the hosting.
 */
class ChatController extends Controller
{
    private const PAGE_SIZE = 50;

    /**
     * Open (or create) the thread with a store and jump to it in the profile.
     */
    public function start(Request $request, Seller $seller): RedirectResponse
    {
        abort_unless($seller->status === SellerStatus::Active, 404);

        $user = $request->user();

        if ($seller->user_id === $user->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot chat with your own store.')]);

            return back();
        }

        $conversation = Conversation::query()->firstOrCreate(['buyer_id' => $user->id, 'seller_id' => $seller->id]);

        $product = $request->filled('product')
            ? Product::query()->active()->where('seller_id', $seller->id)->where('slug', $request->string('product'))->first()
            : null;

        return to_route('profile.show', array_filter([
            'tab' => 'messages',
            'conversation' => $conversation->id,
            'product' => $product?->slug,
        ]));
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forParticipant($user)
            // Threads with messages, plus the one just opened from a store/product page.
            ->where(fn ($query) => $query
                ->whereNotNull('last_message_at')
                ->when($request->integer('include'), fn ($query, int $id) => $query->orWhere('conversations.id', $id)))
            ->with(['seller.user', 'buyer', 'latestMessage'])
            ->withUnreadCountFor($user)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $conversations->map(fn (Conversation $conversation) => $this->conversationPayload($conversation, $user))->values(),
            'unread_total' => Conversation::unreadTotalFor($user),
        ]);
    }

    /**
     * Latest messages, newer ones (`after`) for polling, or older ones (`before`).
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $after = $request->integer('after');
        $before = $request->integer('before');

        $query = $conversation->messages()->with('product.images');

        if ($after > 0) {
            $messages = $query->where('id', '>', $after)->orderBy('id')->limit(self::PAGE_SIZE)->get();
        } else {
            $messages = $query
                ->when($before > 0, fn ($query) => $query->where('id', '<', $before))
                ->orderByDesc('id')
                ->limit(self::PAGE_SIZE + 1)
                ->get();
            $hasOlder = $messages->count() > self::PAGE_SIZE;
            $messages = $messages->take(self::PAGE_SIZE)->reverse()->values();
        }

        $conversation->markReadBy($user);

        return response()->json([
            'data' => $messages->map(fn (ConversationMessage $message) => $this->messagePayload($message, $user))->values(),
            'has_older' => $hasOlder ?? false,
        ]);
    }

    public function send(SendChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $productId = $request->integer('product_id') ?: null;

        if ($productId !== null) {
            $productId = Product::query()
                ->whereKey($productId)
                ->where('seller_id', $conversation->seller_id)
                ->where('status', ProductStatus::Active)
                ->value('id');
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $request->validated('body'),
            'product_id' => $productId,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
        $conversation->markReadBy($user);

        return response()->json(['data' => $this->messagePayload($message->load('product.images'), $user)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationPayload(Conversation $conversation, User $user): array
    {
        $asBuyer = $conversation->isBuyer($user);
        $seller = $conversation->seller;
        $latest = $conversation->latestMessage;
        $whatsapp = preg_replace('/\D/', '', (string) ($seller?->whatsapp ?: $seller?->phone));

        return [
            'id' => $conversation->id,
            'role' => $asBuyer ? 'buyer' : 'seller',
            'counterpart' => $asBuyer
                ? [
                    'name' => $seller?->store_name,
                    'photo_url' => $seller?->profile_photo_url ?? $seller?->user?->profile_photo_url,
                    'store_url' => $seller !== null ? route('sellers.show', $seller->slug) : null,
                    'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp : null,
                ]
                : [
                    'name' => $conversation->buyer?->name,
                    'photo_url' => $conversation->buyer?->profile_photo_url,
                    'store_url' => null,
                    'whatsapp_url' => null,
                ],
            'store_name' => $seller?->store_name,
            'last_message' => $latest === null ? null : [
                'body' => $latest->body,
                'mine' => $latest->sender_id === $user->id,
                'created_at' => $latest->created_at?->toIso8601String(),
            ],
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(ConversationMessage $message, User $user): array
    {
        $product = $message->product;
        $images = $product?->images;

        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->sender_id === $user->id,
            'created_at' => $message->created_at?->toIso8601String(),
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'image' => $images?->firstWhere('is_primary', true)?->url ?? $images?->first()?->url,
            ],
        ];
    }
}
