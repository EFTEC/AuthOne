<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use Exception;

interface IServiceAuthOne
{
    /**
     * @param AuthOne $parent
     */
    public function __construct($parent);

    /**
     * @param string  $user
     * @param ?string $password
     * @param int     $ttl ttl in seconds.
     * @return mixed
     * @throws Exception
     */
    public function createAuth(string $user, ?string $password = null, int $ttl = 0);

    /**
     * It validates if the user is valid or not.<br>
     * jwt, it validates using the crc.<br>
     * session, it validates if the session is active or not.<br>
     * token, it validates if the token is still active (it uses registry)<br>
     * userpassword, it validates the user and password (it uses persistence)<br>
     *
     * @param string      $auth
     * @param string|null $passwordOrCRC
     * @return mixed|null If success, it returns the auth. If fails, it returns null.
     * @throws Exception
     */
    public function validate(string $auth, ?string $passwordOrCRC = null);

    /**
     * It renews an authentication.
     * @param mixed       $auth
     * @param string|null $passwordOrCRC
     * @param int         $ttl ttl in seconds.
     * @return array|null  it could return a new authentication or simply the same.<br>
     *                         it returns null if error.
     * @throws Exception
     */
    public function renew($auth, ?string $passwordOrCRC, int $ttl = 0): ?array;

    /**
     * Invalidates a specific authentication<br>
     * jwt can't be invalidated.
     * @param mixed $auth
     * @return bool
     * @throws Exception
     */
    public function invalidate($auth): bool;


}
