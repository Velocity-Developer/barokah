<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('requires login to update profile media', function () {
    $this->post(route('my.profile.media.update'), [
        'profile_photo' => UploadedFile::fake()->image('me.jpg'),
    ])->assertRedirect(route('login'));
});

it('uploads a profile photo and banner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.show'))
        ->post(route('my.profile.media.update'), [
            'profile_photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
            'banner' => UploadedFile::fake()->image('cover.png', 1600, 400),
        ])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->profile_photo_path)->toStartWith('users/photos/')
        ->and($user->banner_path)->toStartWith('users/banners/')
        ->and($user->profile_photo_url)->not->toBeNull()
        ->and($user->banner_url)->not->toBeNull();
    Storage::disk('public')->assertExists([$user->profile_photo_path, $user->banner_path]);
});

it('replaces the old file and keeps the other image untouched', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('my.profile.media.update'), [
        'profile_photo' => UploadedFile::fake()->image('old.jpg'),
        'banner' => UploadedFile::fake()->image('cover.jpg'),
    ]);
    $user->refresh();
    [$oldPhoto, $banner] = [$user->profile_photo_path, $user->banner_path];

    $this->actingAs($user)->post(route('my.profile.media.update'), [
        'profile_photo' => UploadedFile::fake()->image('new.jpg'),
    ]);
    $user->refresh();

    Storage::disk('public')->assertMissing($oldPhoto);
    Storage::disk('public')->assertExists($user->profile_photo_path);
    expect($user->profile_photo_path)->not->toBe($oldPhoto)
        ->and($user->banner_path)->toBe($banner);
});

it('removes the profile photo and banner on request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('my.profile.media.update'), [
        'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        'banner' => UploadedFile::fake()->image('cover.jpg'),
    ]);
    $user->refresh();
    $paths = [$user->profile_photo_path, $user->banner_path];

    $this->actingAs($user)->post(route('my.profile.media.update'), [
        'remove_profile_photo' => true,
        'remove_banner' => true,
    ]);
    $user->refresh();

    expect($user->profile_photo_path)->toBeNull()->and($user->banner_path)->toBeNull();
    Storage::disk('public')->assertMissing($paths);
});

it('rejects files that are not images or too large', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('my.profile.media.update'), [
        'profile_photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        'banner' => UploadedFile::fake()->image('huge.jpg')->size(5000),
    ])->assertSessionHasErrors(['profile_photo', 'banner']);

    expect($user->refresh()->profile_photo_path)->toBeNull();
});
