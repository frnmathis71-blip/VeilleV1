<?php

use App\Models\User;

test('public registration is unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Intrus', 'email' => 'intrus@example.com', 'password' => 'Password123!'])->assertNotFound();
    expect(User::count())->toBe(0);
});

test('login screen does not offer registration', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('href="http://localhost/register"', false)->assertSee('Espace privé');
});
