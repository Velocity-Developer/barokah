<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Rules\CityInState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Admin customer/buyer management (spec §17): list/search customers,
 * view their orders, update profile fields, toggle admin capability
 * and seller activation.
 */
class AdminUserController extends Controller
{
    use ProfileValidationRules;

    /**
     * Orders that count as money spent (paid onwards).
     *
     * @var list<OrderStatus>
     */
    private const PAID_STATUSES = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    /** Segment tabs: who the customer is on the marketplace. */
    private const SEGMENTS = ['buyers', 'sellers', 'admins'];

    /** @var array<string, array{0: string, 1: string}> */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'name_asc' => ['name', 'asc'],
        'orders_desc' => ['orders_count', 'desc'],
        'spend_desc' => ['total_spent', 'desc'],
    ];

    /**
     * Paginated customer list with search, segment tabs, sorting and counts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'segment' => ['nullable', Rule::in(self::SEGMENTS)],
            'search' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhereHas('seller', fn (Builder $seller) => $seller->where('store_name', 'like', $like)));
            })
            ->when($validated['state'] ?? null, fn (Builder $query, string $state) => $query->where('state', $state));

        $segmentCounts = collect(self::SEGMENTS)
            ->mapWithKeys(fn (string $segment): array => [
                $segment => (clone $filtered)->tap(fn (Builder $query) => $this->applySegment($query, $segment))->count(),
            ]);

        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'newest'];

        $users = $filtered
            ->when($validated['segment'] ?? null, fn (Builder $query, string $segment) => $this->applySegment($query, $segment))
            ->with('seller')
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn (Builder $query) => $query->whereIn('status', self::PAID_STATUSES)], 'total')
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return UserResource::collection($users)->additional([
            'segment_counts' => $segmentCounts,
            'segment_counts_total' => (clone $filtered)->count(),
        ]);
    }

    /**
     * Admins are anyone with the admin flag, sellers own a store, and
     * buyers are the rest.
     */
    private function applySegment(Builder $query, string $segment): Builder
    {
        return match ($segment) {
            'admins' => $query->where('is_admin', true),
            'sellers' => $query->where('is_admin', false)->whereHas('seller'),
            'buyers' => $query->where('is_admin', false)->whereDoesntHave('seller'),
            default => $query,
        };
    }

    /**
     * Show one customer with their seller profile.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('seller'));
    }

    /**
     * Update profile fields plus the admin capability and seller activation flag.
     */
    public function update(Request $request, User $user): UserResource
    {
        $validated = $request->validate([
            'name' => array_merge(['sometimes'], $this->nameRules()),
            'email' => array_merge(['sometimes'], $this->emailRules($user->id)),
            // Phone/postcode formats are TBC (spec §24 item 5); only length is enforced.
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'state' => ['sometimes', 'nullable', 'string', Rule::in(config('malaysia.states', []))],
            'city' => ['sometimes', 'nullable', 'string', 'max:100', new CityInState('state')],
            'post_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_admin' => ['sometimes', 'required', 'boolean'],
            'is_active_as_seller' => ['sometimes', 'required', 'boolean'],
        ]);

        $user->forceFill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return new UserResource($user->refresh()->load('seller'));
    }
}
