<?php

use App\Models\User;

test('home redirects visitors to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('home redirects authenticated users to dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertRedirect(route('dashboard'));
});
