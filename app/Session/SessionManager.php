<?php

namespace App\Session;

use Illuminate\Session\SessionManager as BaseSessionManager;

/**
 * Session manager that builds the legacy-compatible store (see App\Session\Store).
 */
class SessionManager extends BaseSessionManager
{
    /**
     * Build the session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Illuminate\Session\Store
     */
    protected function buildSession($handler)
    {
        if ($this->config->get('session.encrypt')) {
            return $this->buildEncryptedSession($handler);
        }

        return new Store(
            $this->config->get('session.cookie'),
            $handler,
            $id = null,
            $this->config->get('session.serialization', 'php')
        );
    }
}
