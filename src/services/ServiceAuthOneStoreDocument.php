<?php /** @noinspection DuplicatedCode */

namespace eftec\authone\services;

use eftec\authone\AuthOne;
use eftec\DocumentStoreOne\DocumentStoreOne;
use Exception;
use RuntimeException;

/**
 * @copyright (c) Jorge Castro C. Dual Licence: LGPL and Commercial License  https://github.com/EFTEC/AuthOne
 * @version       0.86
 */
class ServiceAuthOneStoreDocument implements IServiceAuthOneStore
{
    /** @var AuthOne */
    protected $parent;
    /** @var DocumentStoreOne */
    protected $document;

    public function getInstance(): object
    {
        return $this->document;
    }

    public function __construct($parent, $config)
    {
        @session_start();
        $this->parent = $parent;
        $instance = null;
        if ($instance === null) {
            if (PHP_MAJOR_VERSION >= 8) {
                $this->document = new DocumentStoreOne(...$config);
            } else {
                $this->document = new DocumentStoreOne(...array_values($config));
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
            $result = $this->document->insertOrUpdate($idDoc, $r);
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
            $result = $this->document->delete($id);
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
            $idDoc = $userObj[$this->parent->fieldUser];
            $currentUser = $this->getUser($idDoc);
            $currentUser[$this->parent->fieldEnable] = $disablevalue;
            $this->document->update($idDoc, $currentUser);
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: Error disabling model ' . $ex->getMessage());
        }
        return $currentUser;
    }

    /**
     * @inheritDoc
     */
    public function getUser(string $id): ?array
    {
        try {
            $userObj = $this->document->get($id);
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
            $this->document->update($idDoc, $userObj);
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $userObj = $this->getUser($idDoc);
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
        $results = [];
        try {
            $listIds = $this->document->select();
            if (!$includeDisable) {
                $enabledValue = $this->parent->fieldEnableValues[0];
                foreach ($listIds as $docId) {
                    $doc = $this->document->get($docId);
                    if ($doc[$this->parent->fieldEnable] === $enabledValue) {
                        $results[] = $doc;
                    }
                }
            }
        } catch (Exception $ex) {
            throw new RuntimeException('AuthOne: ' . $ex->getMessage());
        }
        return $results;
    }

    public function validateUser(string $user, ?string $passwordNotEncrypted = null): ?array
    {
        try {
            $doc = $this->document->get($user);
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
