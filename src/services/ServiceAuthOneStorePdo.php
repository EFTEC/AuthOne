<?php

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use eftec\PdoOne;
use Exception;
use RuntimeException;

/**
 * @copyright (c) Jorge Castro C. Dual Licence: LGPL and Commercial License  https://github.com/EFTEC/AuthOne
 * @version       0.86
 */
class ServiceAuthOneStorePdo implements IServiceAuthOneStore
{
    /** @var AuthOne */
    protected $parent;
    /** @var PdoOne */
    protected $pdo;

    public function getInstance(): object
    {
        return $this->pdo;
    }

    public function __construct($parent, $config)
    {
        @session_start();
        $this->parent = $parent;
        if ($config === null) {
            $this->pdo = PdoOne::instance();
        } else {
            if (PHP_MAJOR_VERSION >= 8) {
                $this->pdo = new PdoOne(...$config);
            } else {
                $this->pdo = new PdoOne(...array_values($config));
            }
            $this->pdo->connect();
        }
        $this->pdo->logLevel = 2;
    }

    /**
     * @inheritDoc
     */
    public function addUser(array $userObj): ?array
    {
        try {
            $r = $userObj;
            $r[$this->parent->fieldPassword] = $this->parent->hash($userObj[$this->parent->fieldPassword]);
            $result = $this->pdo->insert($this->parent->table, $r);
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
            $r[$this->parent->fieldUser] = $id;
            $result = $this->pdo->delete($this->parent->table, $r);
            return !($result === false);
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
            $this->pdo
                ->set([$this->parent->fieldEnable => $disablevalue])
                ->where([$this->parent->fieldUser => $userObj[$this->parent->fieldUser]])
                ->update($this->parent->table);
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: Error disabling model: ' . $ex->getMessage());
        }
        return $this->getUser($userObj[$this->parent->fieldUser]);
    }

    /**
     * @inheritDoc
     */
    public function getUser(string $id): ?array
    {
        try {
            $userObj = $this->pdo->select('*')
                ->from($this->parent->table)
                ->where([$this->parent->fieldUser => $id])
                ->first();
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
            $set = $userObj;
            $filter[$this->parent->fieldUser] = $userObj[$this->parent->fieldUser];
            $set[$this->parent->fieldPassword] = $encryptPassword
                ? $this->parent->hash($set[$this->parent->fieldPassword])
                : $set[$this->parent->fieldPassword];
            unset($set[$this->parent->fieldUser]);
            $this->pdo->set($set)->where($filter)->update($this->parent->table);
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $userObj = $this->getUser($filter[$this->parent->fieldUser]);
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
        try {
            $query = $this->pdo->select('*')->table($this->parent->table);
            if ($orderColumn !== null) {
                $asc = $ascending ? ' ' : ' desc';
                $query = $query->order($orderColumn . $asc);
            }
            if (!$includeDisable) {
                $query->where([$this->parent->fieldEnable => $this->parent->fieldEnableValues[0]]);
            }
            $result = $query->toList();
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
        return $result;
    }

    public function validateUser(string $user, ?string $passwordNotEncrypted = null): ?array
    {
        try {
            $query = $this->pdo
                ->select('*')
                ->from($this->parent->table)
                ->where([
                    $this->parent->fieldUser => $user,
                    $this->parent->fieldPassword => $this->parent->hash($passwordNotEncrypted)
                ]);
            if ($this->parent->fieldEnable) {
                $query = $query->where([$this->parent->fieldEnable => $this->parent->fieldEnableValues[0]]);
            }
            $userObj = $query->first();
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
        return $userObj === false ? null : $userObj;
    }
}
