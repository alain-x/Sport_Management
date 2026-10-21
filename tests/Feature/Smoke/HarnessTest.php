<?php

use App\Models\User;

test('the seeded demo users are available to the test database', function () {
    expect(User::where('email', 'admin@sport.test')->exists())->toBeTrue();
    expect(User::count())->toBe(5);
});
