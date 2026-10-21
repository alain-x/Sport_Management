<?php

test('tests run against the dedicated test database', function () {
    $database = DB::connection()->getDatabaseName();

    expect($database)->toBe('sport_management_test');
});
