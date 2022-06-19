<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use Exception;
use RuntimeException;

/**
 * @copyright (c) Jorge Castro C. Dual Licence: LGPL and Commercial License  https://github.com/EFTEC/AuthOne
 * @version       0.87
 */
class ServiceAuthOneJWTlite implements IServiceAuthOne
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
            $this->parent->failCause[]='user or password incorrect';
            // user or password incorrect
            return null;
        }
        unset($userObj[$this->parent->fieldPassword]);
        // 1'447'434'235 time() is a 10 digit value.
        // the token consits of the serialized value and the last 9 values are the ttl.
        $userObjStr = is_string($userObj) ? $userObj : json_encode($userObj);
        $duration = $ttl === 0 ? 1000000000 : time() + $ttl;
        $newToken = $this->parent->hash($userObjStr . $duration);
        return ['body' => $userObj, 'token' => $duration . $newToken];
    }

    /**
     * @inheritDoc
     */
    public function validate(string $auth, ?string $passwordOrCRC = null)
    {
        try {
            if (strlen($passwordOrCRC) !== 74) {
                // incorrect password
                $this->parent->failCause[]='jwtlite: incorrect password';
                return null;
            }
            // structure of the crc:
            // 12345678901234567890123456789012345678901234567890123456789012345678901234
            // [TIME    ][CRC                                                            ]
            $time = (int)substr($passwordOrCRC, 0, 10); // 1000000000 is the minimum date (9 sept 2001)
            $crc = substr($passwordOrCRC, 10);
            if ($time !== 1000000000 && $time < time()) {
                // expired.
                $this->parent->failCause[]='jwtlite: expired';
                return null;
            }
            $checkToken = $this->parent->hash($auth . $time);
            // $passwordOrCRC = contains the token+timeout
            if($crc===$checkToken) {
                // ok
                $this->parent->failCause=[];
                return $auth;
            }
            $this->parent->failCause[]='jwtlite: crc error';
            return null;

        } catch (Exception $ex) {
            // some error.
            $this->parent->failCause[]='jwtlite: exception'.$ex->getMessage();
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function renew($auth, ?string $passwordOrCRC, int $ttl = 0): ?array
    {
        $auth2 = is_string($auth) ? $auth : json_encode($auth);
        $v = $this->validate($auth2, $passwordOrCRC);
        /** @noinspection DuplicatedCode */
        if ($v === null) {
            return null;
        }
        $duration = $ttl === 0 ? 1000000000 : time() + $ttl;
        $newToken = $this->parent->hash($auth2 . $duration);
        return ['body' => $auth, 'token' => $duration . $newToken];
    }

    /**
     * @inheritDoc
     */
    public function invalidate($auth): bool
    {
        throw new RuntimeException('AuthOne: this service doesn\' allows invalidation');
    }
}
