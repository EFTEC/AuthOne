<?php

namespace eftec\authone\services;

/**
 * @copyright (c) Jorge Castro C. Dual Licence: LGPL and Commercial License  https://github.com/EFTEC/AuthOne
 * @version       1.0
 */
interface IServiceAuthOneStore
{
    public function getInstance() : object;

    /**
     * It adds a new object to the persistence layer.
     * @param array $userObj
     * @return array|null it returns the array if the operation was successfull<br>
     *                    it returns null if error.
     */
    public function addUser(array $userObj): ?array;

    /**
     * @param string $id
     * @return bool
     */
    public function deleteUser(string $id): bool;

    /**
     * @param array $userObj
     * @param mixed $disablevalue
     * @return array|null
     */
    public function disableUser(array $userObj, $disablevalue = null): ?array;

    /**
     * @param string $id
     * @return array|null
     */
    public function getUser(string $id): ?array;

    /**
     * @param array $userObj
     * @param bool  $encryptPassword
     * @return array|null
     */
    public function updateUser(array $userObj, $encryptPassword = true): ?array;

    /**
     * @return array|null
     */
    public function listUsers(): ?array;

    /**
     * It validates a user.
     * @param string      $user The user to validate
     * @param string|null $passwordNotEncrypted The password to validate.<br>
     *                                          This password must not be encrypted
     * @return array|null
     */
    public function validateUser(string $user, ?string $passwordNotEncrypted = null): ?array;
}
