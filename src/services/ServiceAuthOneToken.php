<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use RuntimeException;

/**
 * @copyright (c) Jorge Castro C. Dual Licence: LGPL and Commercial License  https://github.com/EFTEC/AuthOne
 * @version       0.86
 */
class ServiceAuthOneToken implements IServiceAuthOne
{
    /** @var AuthOne */
    protected $parent;

    public function __construct($parent)
    {
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
        $newToken = $this->parent->genToken($user, $password);
        $this->parent->serviceTokenStore->set('authone', $newToken, $userObj, $ttl);
        return $newToken;
    }

    /**
     * @inheritDoc
     */
    public function validate(string $auth, ?string $passwordOrCRC = null)
    {
        if ($this->parent->serviceTokenStore === null) {
            throw new RuntimeException('Service token not set');
        }
        return $this->parent->serviceTokenStore->getValue($auth, null);
    }

    /**
     * @param string|null $passwordOrCRC
     * @inheritDoc
     */
    public function renew($auth, ?string $passwordOrCRC, int $ttl = 0): ?array
    {
        if ($this->parent->serviceTokenStore === null) {
            throw new RuntimeException('Service token not set');
        }
        return $this->parent->serviceTokenStore->getRenew($auth, $ttl, null);
    }

    /**
     * @inheritDoc
     */
    public function invalidate($auth): bool
    {
        if ($this->parent->serviceTokenStore === null) {
            throw new RuntimeException('Service token not set');
        }
        return $this->parent->serviceTokenStore->invalidate('', $auth);
    }
}
