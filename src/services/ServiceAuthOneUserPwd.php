<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use RuntimeException;

class ServiceAuthOneUserPwd implements IServiceAuthOne
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
        return $userObj ?? null;
    }

    /**
     * @inheritDoc
     */
    public function validate(string $auth, ?string $passwordOrCRC = null)
    {
        return $this->createAuth($auth, $passwordOrCRC);
    }

    /**
     * @param string|null $passwordOrCRC
     * @inheritDoc
     */
    public function renew($auth, ?string $passwordOrCRC, int $ttl = 0): ?array
    {
        throw new RuntimeException('AuthOne: you can\'t renew this type of authentication');
    }

    /**
     * @inheritDoc
     */
    public function invalidate($auth): bool
    {
        throw new RuntimeException('AuthOne: you can\'t invalidate this type of authentication');
    }
}