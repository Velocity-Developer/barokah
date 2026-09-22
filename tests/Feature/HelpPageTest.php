<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows the public help page', function () {
    $this->withoutVite()->get(route('help'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Help/Index'));
});
