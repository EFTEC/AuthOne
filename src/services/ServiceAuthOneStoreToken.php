<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use eftec\CacheOne;
use Exception;
use RuntimeException;

class ServiceAuthOneStoreToken implements IServiceAuthOneStore
{
    /** @var AuthOne */
    protected $parent;
    /** @var CacheOne */
    protected $cache;

    public function getInstance(): object
    {
        return $this->cache;
    }

    public function __construct($parent, $config)
    {
        @session_start();
        $this->parent = $parent;
        $instance = null;
        if ($instance === null) {
            if (PHP_MAJOR_VERSION >= 8) {
                $this->cache = new CacheOne(...$config);
            } else {
                $this->cache = new CacheOne(...array_values($config));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function addUser(array $userObj): ?array
    {
        try {
            $r = $userObj;
            $r[$this->parent->fieldPassword] = $this->parent->hash($userObj[$this->parent->fieldPassword]);
            $idDoc = $userObj[$this->parent->fieldUser];
            $result = $this->cache->set('', $idDoc, $r, 0);
            return ($result === false) ? null : $r;
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteUser(string $id): bool
    {
        try {
            $result = $this->cache->invalidate('', $id);
            return !(($result === false));
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function disableUser(array $userObj, $disablevalue = null): ?array
    {
        try {
            $idDoc = $userObj[$this->parent->fieldUser];
            $currentUser = $this->getUser($idDoc);
            $currentUser[$this->parent->fieldEnable] = $disablevalue;
            $this->cache->set('', $idDoc, $currentUser, 0);
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $userObj = $this->cache->get('', $idDoc);
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: Error disabling model ' . $ex->getMessage());
        }
        return $userObj;
    }

    /**
     * @inheritDoc
     */
    public function getUser(string $id): ?array
    {
        try {
            $userObj = $this->cache->getValue($id, null);
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
        return $userObj === false ? null : $userObj;
    }

    /**
     * @inheritDoc
     */
    public function updateUser(array $userObj, $encryptPassword = true): ?array
    {
        try {
            $idDoc = $userObj[$this->parent->fieldUser];
            if ($encryptPassword) {
                $userObj[$this->parent->fieldPassword] = $this->parent->hash($userObj[$this->parent->fieldPassword]);
            }
            $this->cache->set('', $idDoc, $userObj, 0);
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
        return $userObj;
    }

    /**
     * @inheritDoc
     */
    public function listUsers(?string $orderColumn = null, bool $ascending = true, $includeDisable = false): ?array
    {
        throw new RuntimeException('AuthOne: service doesn\' allow list users ');
    }

    public function validateUser(string $user, ?string $passwordNotEncrypted = null): ?array
    {
        try {
            $doc = $this->cache->get('', $user, null);
            if ($doc[$this->parent->fieldPassword] !== $this->parent->hash($passwordNotEncrypted)) {
                // password incrrect
                return null;
            }
            if ($this->parent->fieldEnable && $doc[$this->parent->fieldEnable] !== $this->parent->fieldEnableValues[0]) {
                // user not enable
                return null;
            }
            $userObj = $doc;
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
        return $userObj === false ? null : $userObj;
    }
}
