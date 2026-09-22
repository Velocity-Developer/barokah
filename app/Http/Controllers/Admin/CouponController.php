<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Seller;
use App\Services\CurrencyFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    private const STATUSES = ['active', 'scheduled', 'used_up', 'expired', 'disabled'];

    public function index(Request $request, CurrencyFormatter $currency): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'owner' => ['nullable', Rule::in(['global', 'seller'])],
            'sort' => ['nullable', Rule::in(['newest', 'ends_asc', 'most_used', 'code_asc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = Coupon::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhereHas('seller', fn (Builder $seller) => $seller->where('store_name', 'like', $like)));
            })
            ->when($validated['owner'] ?? null, fn (Builder $query, string $owner) => $query->where('owner_type', $owner));

        $statusCounts = collect(self::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => $this->whereStatus(clone $filtered, $status)->count()]);

        $coupons = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $this->whereStatus($query, $status))
            ->with('seller:id,store_name')
            ->when($validated['sort'] ?? 'newest', fn (Builder $query, string $sort) => match ($sort) {
                'ends_asc' => $query->orderBy('ends_at'),
                'most_used' => $query->orderByDesc('usage_count'),
                'code_asc' => $query->orderBy('code'),
                default => $query->latest(),
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Coupon $coupon): array => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'owner' => $coupon->owner_type === 'seller' ? ($coupon->seller?->store_name ?? 'Store') : null,
                'discount_label' => $this->discountLabel($coupon, $currency),
                'minimum_spend_formatted' => (float) $coupon->minimum_spend > 0 ? $currency->format((float) $coupon->minimum_spend) : null,
                'usage_count' => $coupon->usage_count,
                'usage_limit' => $coupon->usage_limit,
                'starts_at' => $coupon->starts_at?->toIso8601String(),
                'ends_at' => $coupon->ends_at?->toIso8601String(),
                'enabled' => (bool) $coupon->status,
                'status' => $this->statusOf($coupon),
            ]);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'filters' => [
                'search' => $search,
                'status' => $validated['status'] ?? '',
                'owner' => $validated['owner'] ?? '',
                'sort' => $validated['sort'] ?? 'newest',
            ],
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupons/Create', ['sellers' => $this->sellerOptions()]);
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('Admin/Coupons/Edit', [
            'coupon' => [
                ...$coupon->only(['id', 'code', 'name', 'description', 'owner_type', 'seller_id', 'discount_type', 'usage_limit', 'usage_count', 'per_user_limit', 'allow_flash_sale']),
                'discount_value' => (float) $coupon->discount_value,
                'maximum_discount' => $coupon->maximum_discount !== null ? (float) $coupon->maximum_discount : null,
                'minimum_spend' => (float) $coupon->minimum_spend,
                'status' => (bool) $coupon->status,
                'starts_at' => $coupon->starts_at?->toIso8601String(),
                'ends_at' => $coupon->ends_at?->toIso8601String(),
            ],
            'sellers' => $this->sellerOptions(),
        ]);
    }

    /**
     * @return list<array{id: int, store_name: string}>
     */
    private function sellerOptions(): array
    {
        return Seller::query()->orderBy('store_name')->get(['id', 'store_name'])->toArray();
    }

    /**
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    private function whereStatus(Builder $query, string $status): Builder
    {
        $now = now();
        $enabled = fn (Builder $inner) => $inner->where('status', true);
        $notUsedUp = fn (Builder $inner) => $inner->where(fn (Builder $limit) => $limit->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'));

        return match ($status) {
            'disabled' => $query->where('status', false),
            'scheduled' => $enabled($query)->where('starts_at', '>', $now),
            'expired' => $enabled($query)->where('ends_at', '<', $now),
            'used_up' => $enabled($query)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->whereNotNull('usage_limit')->whereColumn('usage_count', '>=', 'usage_limit'),
            default => $notUsedUp($enabled($query)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)),
        };
    }

    private function statusOf(Coupon $coupon): string
    {
        return match (true) {
            ! $coupon->status => 'disabled',
            $coupon->starts_at?->isFuture() => 'scheduled',
            $coupon->ends_at?->isPast() => 'expired',
            $coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit => 'used_up',
            default => 'active',
        };
    }

    private function discountLabel(Coupon $coupon, CurrencyFormatter $currency): string
    {
        return match ($coupon->discount_type) {
            'percentage' => rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.').'% off'
                .($coupon->maximum_discount !== null && (float) $coupon->maximum_discount > 0 ? ' (max '.$currency->format((float) $coupon->maximum_discount).')' : ''),
            'fixed' => $currency->format((float) $coupon->discount_value).' off',
            'free_shipping' => 'Free shipping',
            default => $coupon->discount_type,
        };
    }
}
