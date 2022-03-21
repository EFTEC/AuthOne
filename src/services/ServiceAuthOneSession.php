<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use RuntimeException;

class ServiceAuthOneSession implements IServiceAuthOne
{
    /** @var AuthOne */
    protected $parent;

    public function __construct($parent)
    {
        @ini_set("session.gc_maxlifetime", 31622400); // 1 year.
        $this->parent = $parent;
    }


    /**
     * @inheritDoc
     */
    public function createAuth(string $user, ?string $password = null, int $ttl = 0)
    {
        $userObj = $this->parent->validateUser($user, $password);
        if ($userObj === null) {
            // user or password incorrect
            return null;
        }
        @session_write_close();
        $id = session_create_id();
        @session_id($id);
        @session_start();
        $_SESSION['AuthOne'] = $userObj;
        $_SESSION['AuthOne_ttl'] = $ttl === 0 ? 0 : time() + $ttl;
        session_write_close();
        return $id;
    }

    /**
     * @inheritDoc
     */
    public function validate(string $auth, ?string $passwordOrCRC = null)
    {
        @session_write_close();
        @session_id($auth);
        @session_start();
        $valid = $_SESSION['AuthOne'] ?? null;
        // if $_SESSION['AuthOne_ttl'] is 0 (never expires) then it keeps the validity.
        if (!isset($_SESSION['AuthOne_ttl'])) {
            $valid = false;
        } elseif ($_SESSION['AuthOne_ttl'] > 100 && $_SESSION['AuthOne_ttl'] < time()) {
            $valid = false;
        }
        return $valid === false ? null : $valid;
    }

    /**
     * @param string|null $passwordOrCRC
     * @inheritDoc
     */
    public function renew($auth, ?string $passwordOrCRC, int $ttl = 0): ?array
    {
        @session_write_close();
        @session_id($auth);
        @session_start();
        $_SESSION['AuthOne_ttl'] = $ttl === 0 ? 0 : time() + $ttl;
        return $_SESSION['AuthOne'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function invalidate($auth): bool
    {
        @session_write_close();
        @session_id($auth);
        @session_unset();
        @session_destroy();
        return true;
    }


}
