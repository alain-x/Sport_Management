<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// The sports app runs on MySQL and ships with demo seed data in
// database/schema.sql (imported into sport_management_test). Tests execute
// inside a transaction so the seeded rows stay intact for every test.
pest()->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Fetch a seeded demo user by email (see database/schema.sql).
 */
function demoUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

function admin(): User
{
    return demoUser('admin@sport.test');
}

function manager(): User
{
    return demoUser('manager@sport.test');
}

function teamStaff(): User
{
    return demoUser('irl.staff@sport.test');
}

function fitnessUser(): User
{
    return demoUser('fitness@sport.test');
}

function playerUser(): User
{
    return demoUser('james@sport.test');
}

/**
 * A raw 1x1 PNG upload. We avoid Illuminate\Http\Testing\File::image()
 * because it requires the GD extension, which is disabled here (the Upload
 * support class falls back to a raw copy when GD is absent).
 */
function pngUpload(): UploadedFile
{
    $bytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    $path = tempnam(sys_get_temp_dir(), 'smoke').'.png';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'smoke.png', 'image/png', null, true);
}
