<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A buyer ↔ store chat thread. The store side is answered by the seller's
 * owner (sellers.user_id).
 *
 * @property int $id
 * @property int $buyer_id
 * @property int $seller_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $buyer_read_at
 * @property Carbon|null $seller_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['buyer_id', 'seller_id', 'last_message_at', 'buyer_read_at', 'seller_read_at'])]
class Conversation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'buyer_read_at' => 'datetime',
            'seller_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    /**
     * Conversations the user takes part in, as buyer or as store owner.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeForParticipant(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('buyer_id', $user->id)
            ->orWhereHas('seller', fn (Builder $seller) => $seller->where('user_id', $user->id)));
    }

    public function isBuyer(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    public function isStoreOwner(User $user): bool
    {
        return $this->seller?->user_id === $user->id;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->isBuyer($user) || $this->isStoreOwner($user);
    }

    /**
     * When this user last read the thread (their own side).
     */
    public function readAtFor(User $user): ?Carbon
    {
        return $this->isBuyer($user) ? $this->buyer_read_at : $this->seller_read_at;
    }

    public function markReadBy(User $user): void
    {
        $this->forceFill([$this->isBuyer($user) ? 'buyer_read_at' : 'seller_read_at' => now()])->save();
    }

    /**
     * Adds `unread_count`: messages from the other side newer than this
     * user's read marker, computed in the same query as the list.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeWithUnreadCountFor(Builder $query, User $user): Builder
    {
        return $query->withCount(['messages as unread_count' => fn (Builder $messages) => self::unreadMessagesFor($messages, $user)]);
    }

    /**
     * Total unread messages across all of the user's conversations.
     */
    public static function unreadTotalFor(User $user): int
    {
        return self::unreadMessagesFor(
            ConversationMessage::query()
                ->join('conversations', 'conversations.id', '=', 'conversation_messages.conversation_id')
                ->whereIn('conversation_messages.conversation_id', self::query()->forParticipant($user)->select('id')),
            $user,
        )->count();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $messages
     * @return Builder<TModel>
     */
    private static function unreadMessagesFor(Builder $messages, User $user): Builder
    {
        return $messages
            ->where('conversation_messages.sender_id', '!=', $user->id)
            ->whereRaw(
                'conversation_messages.created_at > COALESCE(CASE WHEN conversations.buyer_id = ? THEN conversations.buyer_read_at ELSE conversations.seller_read_at END, ?)',
                [$user->id, '1970-01-01 00:00:00'],
            );
    }
}
