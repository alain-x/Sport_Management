<?php

namespace App\Session;

use Illuminate\Session\Store as BaseStore;

/**
 * Session store that mirrors the legacy CSRF token format.
 *
 * The original app generated its token with bin2hex(random_bytes(32)), which
 * produces a 64-character hex string. Laravel's default Store uses a 40-char
 * Str::random() value; several views and the smoke suites assert the legacy
 * 64-character shape, so we regenerate the token the same way.
 */
class Store extends BaseStore
{
    /**
     * Regenerate the CSRF token value (legacy 64-char hex form).
     */
    public function regenerateToken()
    {
        $this->put('_token', bin2hex(random_bytes(32)));
    }
}
