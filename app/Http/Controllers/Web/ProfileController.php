<?php

namespace App\Http\Controllers\Web;

use App\Actions\Seller\SubmitSellerApplication;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ActivateSellerRequest;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Web\ProfileMediaUpdateRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\SellerResource;
use App\Models\Product;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Suspended sellers / unpublished products stay followed in the DB and
        // reappear once they are active again.
        $followedSellers = $user->followedSellers()
            ->where('status', SellerStatus::Active)
            ->with('user')
            ->withCount('followers')
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->each(fn ($seller) => $seller->setAttribute('is_followed', true));

        $favoriteProducts = $user->favoriteProducts()
            ->active()
            ->with(['seller', 'category', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->each(fn (Product $product) => $product->setAttribute('is_favorited', true));

        $seller = $user->seller;

        return Inertia::render('Profile/Show', [
            'sellerApplication' => $seller === null ? null : [
                'store_name' => $seller->store_name,
                'slug' => $seller->slug,
                'status' => $seller->status->value,
                'submitted_at' => $seller->created_at?->toIso8601String(),
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'followedSellers' => SellerResource::collection($followedSellers),
            'favoriteProducts' => ProductResource::collection($favoriteProducts),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        \Log::info('Profile update request received', [
            'validated' => $request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        \Log::info('Profile updated successfully', ['user_id' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.show');
    }

    public function applyAsSeller(ActivateSellerRequest $request, SubmitSellerApplication $submit): RedirectResponse
    {
        abort_if($request->user()->can('admin'), 403);

        if ($request->user()->seller()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You already have a store or a pending application.')]);

            return to_route('profile.show', ['tab' => 'seller']);
        }

        $submit->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Seller application submitted. Waiting for admin approval.')]);

        return to_route('profile.show', ['tab' => 'seller']);
    }

    public function updateMedia(ProfileMediaUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $media = [
            'profile_photo_path' => ['file' => 'profile_photo', 'remove' => 'remove_profile_photo', 'folder' => 'users/photos'],
            'banner_path' => ['file' => 'banner', 'remove' => 'remove_banner', 'folder' => 'users/banners'],
        ];

        foreach ($media as $column => $input) {
            $upload = $request->file($input['file']);

            if ($upload === null && ! $request->boolean($input['remove'])) {
                continue;
            }

            if ($user->{$column} !== null && $user->{$column} !== '') {
                Storage::disk('public')->delete($user->{$column});
            }

            $user->{$column} = $upload?->store($input['folder'], 'public');
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo and banner updated.')]);

        return back();
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
