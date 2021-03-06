<?php /** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection EncryptionInitializationVectorRandomnessInspection */
/** @noinspection CryptographicallySecureRandomnessInspection */

namespace eftec\authone;

use eftec\authone\services\IServiceAuthOne;
use eftec\authone\services\IServiceAuthOneStore;
use eftec\authone\services\ServiceAuthOneJWTlite;
use eftec\authone\services\ServiceAuthOneSession;
use eftec\authone\services\ServiceAuthOneStoreToken;
use eftec\authone\services\ServiceAuthOneStoreDocument;
use eftec\authone\services\ServiceAuthOneStorePdo;
use eftec\authone\services\ServiceAuthOneToken;
use eftec\authone\services\ServiceAuthOneUserPwd;
use eftec\CacheOne;
use Exception;
use RuntimeException;

/**
 * Class AuthOne
 * This class works with different kind of authentication.
 *
 * @see           https://github.com/EFTEC/AuthOne
 * @package       eftec
 * @author        Jorge Castro Castillo
 * @copyright (c) Jorge Castro C. Dual Licence: LGPL and Commercial License  https://github.com/EFTEC/AuthOne
 * @version       1.3
 */
class AuthOne
{
    public const VERSION = "1.3";
    /** @var array It stores the last cause of error, or empty if not error */
    public $failCause = [];
    /**
     * @var int The max lenght of the user, password, token (no token bearer), it helps to avoid overflow<br>
     *          The field "enabled" is always up to 32 characters.
     */
    public $MAXLENGHT = 128;
    /** @var int the max lenght of a complete user object and the token bearr */
    public $MAXLENGHTOBJECT = 8192; // 8kb.
    /** @var IServiceAuthOne */
    protected $serviceAuth;
    /** @var IServiceAuthOneStore where the tokens (if any) will be stored */
    public $serviceUserStore;
    /** @var CacheOne */
    public $serviceTokenStore;
    public $table = 'users';
    /** @var string it is the table/foolder used for tokens when the tokens are store using PDO or DocumentStoreOne */
    public $tableToken = 'tokens';
    public $fieldUser = 'id';
    public $fieldPassword = 'password';
    /** @var ?string It set the field to be used to enable or disable a user */
    public $fieldEnable;
    /** @var array It indicated the possible enable or disable values [enable value,disable value] */
    public $fieldEnableValues = [1, 0];
    /** @var string[] it contains the definitions of the other columns of the UserObject */
    public $fieldOthers = [];
    public $hashType = 'sha256';
    public $encSalt = '123'; // note: you must change this value
    /** @var string=['session','token','userpwd','jwtlite'][$i] */
    public $authType;
    /** @var string=['pdo','document','token'][$i] */
    public $storeType;
    /**
     * @var string[] The configuration to fetch values [userfield=>type,passwordfield=>type,bodyfield=>type<br>
     *               The allowed types are: get,post,cookie,request,body,header or all (it tries all the methods).
     */
    public $fetchConfig = ['user' => 'all', 'password' => 'all', 'token' => 'all', 'tokencrc' => 'all'];

    public $configUserStore;
    public $configTokenStore;
    /** @var bool */
    public $encEnabled = true;
    /**
     * @var string Encryption password.<br>
     * If the method is INTEGER, then the password must be an integer
     */
    public $encPassword = '';
    /**
     * @var bool If iv is true then it is generated randomly, otherwise is it generated via md5<br>
     * If true, then the encrypted value is always different (but the decryption yields the same value).<br>
     * If false, then the value encrypted is the same for the same value.<br>
     * Set to false if you want a deterministic value (it always returns the same value)
     */
    public $iv = true;
    /**
     * @var string<p> Encryption method, example AES-256-CTR (two ways).</p>
     * @see http://php.net/manual/en/function.openssl-get-cipher-methods.php
     */
    public $encMethod = 'AES-256-CTR';

    /**
     * It creates and configures the instance<br>
     * <b>Examples of configurations:</b><br>
     * <pre>
     * $this->redisConfig = [
     *           'type' => 'redis', // it will use redis to store the temporary tokens.
     *                              // Values allowed: auto (automatic),redis (redis) ,
     *                              //memcache (memcache),apcu (PHP APCU),pdoone (database) and documentone (file
     *                              system)
     *           'server' => '127.0.0.1',  // the server of REDIS or PDO. For documentone it is the startup folder.
     *           'schema' => '', // (optional), the schema or folder.
     *           'port' => 0, // (optional) the port, used by redis memcache or pdo
     *           'user' => '', // (optional) the user used by pdo
     *           'password' => '' // (optional) the password used by pdo
     *           ];
     * $this->pdoConfig = [ // See PdoOne for more information.
     *           'databaseType' => 'mysql',  // mysql,sqlsrv,oci,test
     *           'server' => '127.0.0.1',  // the server of REDIS or PDO. For documentone it is the startup folder.
     *           'user' => 'root', // (optional) the user used by pdo
     *           'password' => 'abc123' // (optional) the password used by pdo
     *           'db' => 'sakila', // (optional), the schema or folder.
     *           ];
     * </pre>
     * @param string     $authType         =['session','token','userpwd','jwtlite'][$i] The type of authentication<br>
     *                                     <b>session</b>: PHP session<br>
     *                                     <b>token</b>: Token<br>
     *                                     <b>userpwd</b>: User and password<br>
     *                                     <b>jwt</b>: JWT like token bearer<br>
     * @param string     $storeType        =['pdo','document','token'][$i] The type of storage (where the users will be
     *                                     stored)<br>
     *                                     <b>pdo</b>: It uses PDO (database)<br>
     *                                     <b>document</b>: It uses the file-system<br>
     *                                     <b>token</b>: it uses a cache-library that it could use
     *                                     redis,pdo,document,memcached and apcu<br>
     * @param array|null $configUserToken  The configuration of the user store<br>
     *                                     If null, then it will try to inject an instance<br>
     * @param array|null $configTokenStore The configuration of the token store (only used by "token")<br>
     *                                     If null, then it will try to inject an instance<br>
     */
    public function __construct(string $authType,
                                string $storeType,
                                ?array $configUserToken = null,
                                ?array $configTokenStore = null)
    {
        $this->authType = $authType;
        $this->storeType = $storeType;
        $this->configUserStore = $configUserToken;
        $this->configTokenStore = $configTokenStore;
        switch ($authType) {
            case 'session':
                $this->serviceAuth = new ServiceAuthOneSession($this);
                break;
            case 'token':
                $this->serviceAuth = new ServiceAuthOneToken($this);
                break;
            case 'userpwd':
                $this->serviceAuth = new ServiceAuthOneUserPwd($this);
                break;
            case 'jwtlite':
                $this->serviceAuth = new ServiceAuthOneJWTlite($this);
                break;
            default:
                throw new RuntimeException("AuthOne: authType [$authType] incorrect");
        }
        switch ($storeType) {
            case 'pdo':
                /** @noinspection ClassConstantCanBeUsedInspection */
                if (!class_exists('eftec\\PdoOne')) {
                    throw new RuntimeException('AuthOne: Library eftec\\PdoOne not found or not loaded.
                    Did you add it to the composer? composer add eftec\\pdoone');
                }
                if ($this->configUserStore === null) {
                    // auto wire the instance
                    $this->serviceUserStore = new ServiceAuthOneStorePdo($this, null);
                } else {
                    $this->serviceUserStore = new ServiceAuthOneStorePdo($this, $this->configUserStore);
                }
                break;
            case 'document':
                /** @noinspection ClassConstantCanBeUsedInspection */
                if (!class_exists('eftec\\DocumentStoreOne\\DocumentStoreOne')) {
                    throw new RuntimeException('AuthOne: Library eftec\\DocumentStoreOne not found or not loaded.
                     Did you add it to the composer? composer add eftec\\documentstoreone');
                }
                if ($this->configUserStore === null) {
                    // auto wire the instance
                    $this->serviceUserStore = new ServiceAuthOneStoreDocument($this, null);
                } else {
                    $this->serviceUserStore = new ServiceAuthOneStoreDocument($this, $this->configUserStore);
                }
                break;
            case 'token':
                if ($this->configUserStore === null) {
                    // auto wire the instance
                    $this->serviceUserStore = new ServiceAuthOneStoreToken($this, null);
                } else {
                    $this->serviceUserStore = new ServiceAuthOneStoreToken($this, $this->configUserStore);
                }
                break;
            default:
                throw new RuntimeException("AuthOne: storeType [$storeType] incorrect");
        }
        if ($this->configTokenStore) {
            if (PHP_MAJOR_VERSION >= 8) {
                // php8 allows named argumentes
                $this->serviceTokenStore = new CacheOne(...$configTokenStore);
            } else {
                $this->serviceTokenStore = new CacheOne(...array_values($configTokenStore));
            }
        } else {
            $this->serviceTokenStore = CacheOne::instance(false);
        }
    }

    /**
     * It sets the parameters of encryption
     * @param string $encPassword The password to encrypt/decrypt the information. Example "passw0rd"
     * @param string $encSalt     The salt used to ensure the safety of the information. Example "92fdkdsfdgdsffsd"
     * @param bool   $encEnable   If true (default) then the encryption is enabled. Otherwise, the values are not
     *                            encrypted/decrypted
     * @param string $encMethod   The method of encryption.
     * @param bool   $iv          true if you want to use IV
     * @return void
     * @noinspection PhpUnused
     */
    public function setEncryptConfig($encPassword, $encSalt, $encEnable = true, $encMethod = 'AES-256-CTR', $iv = true): void
    {
        $this->iv = $iv;
        $this->encEnabled = $encEnable;
        $this->encSalt = $encSalt;
        $this->encMethod = $encMethod;
        $this->encPassword = $encPassword;
    }

    /**
     * It sets the parameters of encryption using parameteres used in the instance of serviceUserStore<br>
     * @return bool returns true if the parameters were set. False if not. ServiceUserStore must be an instance of
     *              PdoOne
     * @noinspection PhpUnused
     */
    public function setEncryptConfigUsingPDO(): bool
    {
        /** @var \eftec\PdoOne $instance */
        $instance = $this->serviceUserStore->getInstance();
        if ($instance instanceof \eftec\PdoOne) {
            $this->iv = $instance->encryption->iv;
            $this->encEnabled = $instance->encryption->encEnabled;
            $this->encSalt = $instance->encryption->encSalt;
            $this->encMethod = $instance->encryption->encMethod;
            $this->encPassword = $instance->encryption->encPassword;
            return true;
        }
        return false;
    }

    /**
     * It specifies the values of persistence and token.
     * @param string      $table         the table to be used in the store
     * @param string      $fieldUser     the field (column) for the user.
     * @param string      $fieldPassword the field (column) for the password. The password must contain at least 64
     *                                   characters.
     * @param string|null $fieldEnable   (optional), the field (column) to detemine if the user is active or not.
     * @param array       $enableValues  The values to determine if fieldenable is enabled or not.<br>
     *                                   <b>examples:</b> [enable value,disable value] [1,0] ['e','d']
     * @param array|null  $fieldOthers   (optional), it specifies other fields.<br>
     *                                   It is used if you want to create the table directly and if you want to
     *                                   validate each field<br>
     *                                   ['column type(l) null autonumeric']<br>
     *                                   <b>example:</b> ['col1 string(50) null','col2 int','col3 datetime']<br>
     *                                   <b>types allowed</b>: string, int, long, decimal, date, datetime, timestamp
     *                                   and bool<br>
     *                                   <b>null values</b>: "null" for null, or " " for not null.<br>
     *                                   <b>automeric</b>: "autonumeric" for autonumeric (identity) or " " for nothing.
     *
     * @return void
     */
    public function setUserStoreConfig(string  $table = 'users',
                                       string  $fieldUser = 'id',
                                       string  $fieldPassword = 'password',
                                       ?string $fieldEnable = null,
                                       array   $enableValues = [1, 0],
                                       ?array  $fieldOthers = null): void
    {
        $this->fieldUser = $fieldUser;
        $this->fieldPassword = $fieldPassword;
        $this->fieldEnable = $fieldEnable;
        $this->fieldEnableValues = $enableValues;
        $this->fieldOthers = $fieldOthers ?? [];
        $this->table = $table;
    }

    /**
     * @param string|null $tableToken the table/folder used to store tokens. It is only used for the type "token".
     * @return void
     */
    public function setTokenStoreConfig(string $tableToken = 'tokens'): void
    {
        $this->tableToken = $tableToken;
    }


    /**
     * Initialize the tables/folders.<br>
     * It must be called only once<br>
     * @return string[] returns a list of errors, or an empty array if not error.
     * @throws Exception
     */
    public function initialize(): array
    {
        $result = [];
        try {
            if ($this->storeType === 'pdo') {
                /** @var \eftec\PdoOne $instance */
                $instance = $this->serviceUserStore->getInstance();
                $definitions = [];
                $definitions[] = $this->fieldUser . ' string(' . $this->MAXLENGHT . ')';
                $definitions[] = $this->fieldPassword . ' string(' . $this->MAXLENGHT . ')';
                if ($this->fieldEnable !== null) {
                    $definitions[] = $this->fieldEnable . ' string(24)';
                }
                foreach ($this->fieldOthers as $f) {
                    $definitions[] = $f;
                }
                if ($instance->objectExist($this->table)) {
                    $result[] = 'table ' . $this->table . ' already exist';
                } else {
                    $instance->createTable($this->table, $definitions, $this->fieldUser, '', '', true);
                }
            }
        } catch (Exception $ex) {
            $result[] = $ex->getMessage();
        }
        if ($this->serviceTokenStore !== null) {
            if ($this->serviceTokenStore->type === 'pdoone') {
                /** @var \eftec\PdoOne $instance */
                $instance = $this->serviceTokenStore->service->getInstance();
                $instance->setKvDefaultTable($this->tableToken);
                try {
                    $r = $instance->createTableKV();
                } catch (Exception $ex) {
                    $r = false;
                }
                if ($r === false) {
                    $result[] = 'unable to create token table ' . $this->tableToken;
                }
            }
            if ($this->serviceTokenStore->type === 'documentone') {
                /** @var \eftec\DocumentStoreOne\DocumentStoreOne $instance */
                $instance = $this->serviceTokenStore->service->getInstance();
                try {
                    $r = $instance->createCollection($this->tableToken);
                } catch (Exception $ex) {
                    $r = false;
                }
                if ($r === false) {
                    $result[] = 'unable to create folder ' . $this->tableToken;
                }
            }
        }
        return $result;
    }

    /**
     * It validates if the object contains the basic fields or not.<br>
     * It doesn't validate other fields.
     * @param array|null $userObj
     * @return bool
     */
    public function basicValidateObject(?array $userObj): bool
    {
        $valid = true;
        if ($userObj === null) {
            // no object
            return false;
        }
        if (!isset($userObj[$this->fieldUser]) || strlen($userObj[$this->fieldUser]) > $this->MAXLENGHT) {
            // user missing or too long
            $valid = false;
        }
        if (!isset($userObj[$this->fieldPassword]) || strlen($userObj[$this->fieldPassword]) > $this->MAXLENGHT) {
            // password missing or too long
            $valid = false;
        }
        if ($this->fieldEnable !== null) {
            if (!isset($userObj[$this->fieldEnable])) {
                // enable is missing
                $valid = false;
            }
            if (strlen($userObj[$this->fieldEnable]) > 32) {
                // enable is too long
                $valid = false;
            }
        }
        return $valid;
    }

    /**
     * It adds a new user.  If the user exists, it will throw an exception.
     * <b>Example:</b><br>
     * <pre>
     * $this->setUserStoreConfig('tableusr','usr','pwd','enabled',[]);
     * $this->adduser(['usr'=>'john','pwd'=>'123','enabled'='1']);
     * </pre>
     * @param array $userObj
     * @return array|null
     * @see \eftec\authone\AuthOne::setUserStoreConfig
     */
    public function addUser(array $userObj): ?array
    {
        if (!$this->basicValidateObject($userObj)) {
            return null;
        }
        return $this->serviceUserStore->addUser($userObj);
    }

    public function getUser(string $id): ?array
    {
        return $this->serviceUserStore->getUser($id);
    }

    public function updateUser(array $userObj, $encryptPassword = true): ?array
    {
        if (!$this->basicValidateObject($userObj)) {
            return null;
        }
        return $this->serviceUserStore->updateUser($userObj, $encryptPassword);
    }

    public function listUsers(): ?array
    {
        return $this->serviceUserStore->listUsers();
    }

    public function deleteUser(string $id): bool
    {
        return $this->serviceUserStore->deleteUser($id);
    }

    /**
     * Enable or disable a user.<br>
     * This function only works if the model has disableField.
     * @param array     $userObj
     * @param bool|null $disable (optional) if the user must be enabled or disable<br>
     *                           <b>true</b>: enabled<br>
     *                           <b>false</b>: disabled<br>
     *                           <b>null</b>: it sets the value using the value of $userObj.
     * @return array|null
     */
    public function setDisableUser(array $userObj, ?bool $disable = null): ?array
    {
        if ($this->fieldEnable === null) {
            throw new RuntimeException('AuthOne: You can\'t disable this model');
        }
        if (!$this->basicValidateObject($userObj)) {
            return null;
        }
        if ($disable === true) {
            $disablevalue = $this->fieldEnableValues[1];
        } elseif ($disable === null) {
            $disablevalue = $userObj[$this->fieldEnable];
        } else {
            $disablevalue = $this->fieldEnableValues[0];
        }
        return $this->serviceUserStore->disableUser($userObj, $disablevalue);
    }

    public function validateUser(string $user, ?string $passwordNotEncrypted = null): ?array
    {
        if ($user === '' ||
            strlen($user) > $this->MAXLENGHT ||
            strlen($passwordNotEncrypted) > $this->MAXLENGHT) {
            // no user to validate, or the username/password is too long
            return null;
        }
        return $this->serviceUserStore->validateUser($user, $passwordNotEncrypted);
    }

    /**
     * It creates a new authentication. The result depends on the type of authentication.<br>
     * <b>Example:</b><br>
     * <pre>
     * $auth=$this->createAuth('john','abc123');
     * </pre>
     * <ul>
     * <li><b>jwtlite:</b> (auth) It returns an array of the type ['body'=>'..','token'=>'..']</li>
     * <li><b>session:</b> (auth) It returns the session id (string)</li>
     * <li><b>token:</b> (auth) It returns the token generated (string)</li>
     * <li><b>userpwd:</b> (auth) It returns the database user (associative array)</li>
     * </ul>
     * @param string|null $user        The username
     * @param string|null $password    the password
     * @param int         $ttl         the duration of the authentication (in seconds).<br>
     *                                 <b>userpwd</b> doesn't use this value
     * @param string      $returnValue =['auth','bear','both'][$i]<br>
     *                                 <b>auth:</b> (default) it returns the authentication.<br>
     *                                 <b>bear:</b> it returns the bearer (the auth encrypted)<br>
     *                                 <b>both:</b> It returns the auth and bearer in a array [auth,bear]
     * @return array|false|mixed|string|null
     * @throws Exception
     */
    public function createAuth(?string $user, ?string $password, int $ttl = 0, string $returnValue = 'auth')
    {
        $this->failCause=[];
        if($user===null || $password===null) {
            // no user or password.
            $this->failCause[]='No username or password';
            return null;
        }
        if (strlen($user) > $this->MAXLENGHT) {
            // user too big
            $this->failCause[]='Username too big';
            return null;
        }
        if (strlen($password) > $this->MAXLENGHT) {
            // password too big
            $this->failCause[]='Password too big';
            return null;
        }
        $r = $this->serviceAuth->createAuth($user, $password, $ttl);
        if (!$r) {
            return $r;
        }

        if ($returnValue === 'bear') {
            return $this->encrypt($r);
        }
        if ($returnValue === 'both') {
            return [$r, $this->encrypt($r)];
        }
        return $r;
    }

    /**
     * It creates a new authentication. The result depends on the type of authentication.<br>
     * <b>Example:</b><br>
     * <pre>
     * $auth=$this->createAuth(['usr=>'john','pwd'=>'abc123']);
     * </pre>
     * <ul>
     * <li><b>jwtlite:</b> (auth) It returns an array of the type ['body'=>'..','token'=>'..']</li>
     * <li><b>session:</b> (auth) It returns the session id (string)</li>
     * <li><b>token:</b> (auth) It returns the token generated (string)</li>
     * <li><b>userpwd:</b> (auth) It returns the database user (associative array)</li>
     * </ul>
     * @param array|null $userobj      The user-object to validate.<br>
     *                                 The fields are read from $this->fieldUser and $this->fieldPassword<br>
     *                                 You can set those fields using the method $this->setUserStoreConfig()
     * @param int         $ttl         the duration of the authentication (in seconds).<br>
     *                                 <b>userpwd</b> doesn't use this value
     * @param string      $returnValue =['auth','bear','both'][$i]<br>
     *                                 <b>auth:</b> (default) it returns the authentication.<br>
     *                                 <b>bear:</b> it returns the bearer (the auth encrypted)<br>
     *                                 <b>both:</b> It returns the auth and bearer in a array [auth,bear]
     * @return array|false|mixed|string|null
     * @throws Exception
     */
    public function createAuthObj(?array $userobj, int $ttl = 0, string $returnValue = 'auth') {
        $user=$userobj[$this->fieldUser]??null;
        $password=$userobj[$this->fieldPassword]??null;
        return $this->createAuth($user,$password,$ttl,$returnValue);
    }

    /**
     * It creates an authentication fetching the values using the values of fetchConfig
     * @param int $ttl the duration of the authentication (in seconds)
     * @return array|false|mixed|string|null
     * @throws Exception
     * @noinspection PhpUnusedLocalVariableInspection
     * @see          \eftec\authone\AuthOne::fetchConfig
     */
    public function createAuthFetch(int $ttl = 0)
    {
        [$u, $p] = $this->fetch(null, true);
        if ($u === null || $p === null) {
            return null;
        }
        return $this->createAuth($u, $p, $ttl);
    }

    /**
     * It validates some authentication. It returns an associative array if success or null if error.<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->validateAuth($sesid['body'],$sesid['token']); // jwtlite, where the body contains the payload
     * $this->validateAuth($user,$password); // userpwd, where user is the username, and password is the password
     * $this->validateAuth($token); // token, where token is a string.
     * $this->validateAuth($session); // session, where session is a string.
     * $this->validateAuth($token,null,true); // use the token bearer as authentication.
     * </pre>
     * @param mixed       $auth          the authentication, if $asBear=true, then it is the token bearer.
     * @param string|null $passwordOrCrc the password or crc to validate the authentication.<br>
     *                                   It is only required for some type of authentication
     * @param bool        $asBear        If true, then $auth is an encrypted token bearer.
     * @return array|null                The "userobject" or null if the validation fails
     * @throws Exception
     */
    public function validateAuth($auth, ?string $passwordOrCrc = null, bool $asBear = false): ?array
    {
        $this->failCause=[];
        if ($asBear) {
            $bear = $this->decrypt($auth);
            if (!is_array($bear)) {
                $this->failCause[] = 'AuthOne: unable to decrypt bearer';
                return null;
            }
            if ($this->authType === 'jwtlite') {
                $auth = $bear['body']??null;
                $passwordOrCrc = $bear['token']??null;
                if($auth===null || $passwordOrCrc===null) {
                    $this->failCause[] = 'AuthOne: no body or token stored in the bearer';
                    return null;
                }
            }
        }
        $authString = is_string($auth) ? $auth : json_encode($auth);
        if ($this->authType === 'jwtlite' && strlen($authString) > $this->MAXLENGHTOBJECT) {
            // auth too big
            $this->failCause[] = 'AuthOne: auth is too big';
            return null;
        }
        if ($this->authType !== 'jwtlite' && strlen($authString) > $this->MAXLENGHT) {
            // auth too big
            $this->failCause[] = 'AuthOne: auth is too big';
            return null;
        }
        $r = $this->serviceAuth->validate($authString, $passwordOrCrc);
        if (is_string($r)) {
            return json_decode($r, true);
        }
        return $r;
    }

    /**
     * It validates the authentication by fetching the values using the values of fetchConfig
     * @throws Exception
     * @noinspection PhpUnusedLocalVariableInspection
     * @see          \eftec\authone\AuthOne::fetchConfig
     */
    public function validateAuthFetch(): ?array
    {
        [$u, $p] = $this->fetch();
        if ($u === null) {
            return null;
        }
        return $this->validateAuth($u, $p);
    }

    /**
     * @param mixed       $auth the authentication
     * @param string|null $PasswordOrCrc
     * @param int         $ttl  duration in seconds
     * @return array|null
     * @throws Exception
     */
    public function renewAuth($auth, ?string $PasswordOrCrc = null, int $ttl = 0): ?array
    {
        $this->failCause=[];
        $authString = is_string($auth) ? $auth : json_encode($auth);
        if ($this->authType === 'jwtlite' && strlen($authString) > $this->MAXLENGHTOBJECT) {
            // auth too big
            return null;
        }
        if ($this->authType !== 'jwtlite' && strlen($authString) > $this->MAXLENGHT) {
            // auth too big
            return null;
        }
        return $this->serviceAuth->renew($auth, $PasswordOrCrc, $ttl);
    }

    /**
     * @param int $ttl duration in seconds
     * @return array|null
     * @throws Exception
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function renewAuthFetch(int $ttl = 0): ?array
    {
        [$u, $p] = $this->fetch();
        if ($u === null) {
            return null;
        }
        return $this->renewAuth($u, $p, $ttl);
    }

    /**
     * @throws Exception
     */
    public function invalidateAuth(string $auth): bool
    {
        $this->failCause=[];
        if ($this->authType === 'jwtlite' && strlen($auth) > $this->MAXLENGHTOBJECT) {
            // auth too big
            return false;
        }
        if ($this->authType !== 'jwtlite' && strlen($auth) > $this->MAXLENGHT) {
            // auth too big
            return false;
        }
        return $this->serviceAuth->invalidate($auth);
    }

    /**
     * @return bool
     * @throws Exception
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function invalidateAuthFetch(): bool
    {
        [$u, $p] = $this->fetch();
        if ($u === null) {
            return false;
        }
        return $this->invalidateAuth($u);
    }

    /**
     * It generates a hash based in the hash type ($this->hashType), the data used and the SALT.
     *
     * @param mixed $data It could be any type of serializable data.
     * @return false|string If the serialization is not set, then it returns the same value.
     */
    public function hash($data)
    {
        if (!is_string($data)) {
            $data = serialize($data);
        }
        if (!$this->encEnabled) {
            return $data;
        }
        return hash($this->hashType, $this->encSalt . $data);
    }

    /**
     * It is a two-way decryption
     * @param mixed $data
     * @return bool|string
     */
    public function decrypt($data)
    {
        if (!$this->encEnabled || $data === null) {
            return $data;
        } // no encryption
        $data = base64_decode(str_replace(array('-', '_'), array('+', '/'), $data));
        $iv_strlen = 2 * openssl_cipher_iv_length($this->encMethod);
        if (preg_match('/^(.{' . $iv_strlen . '})(.+)$/', $data, $regs)) {
            try {
                [, $iv, $crypted_string] = $regs;
                $decrypted_string = openssl_decrypt($crypted_string, $this->encMethod, $this->encPassword, 0, hex2bin($iv));
                $result = substr($decrypted_string, strlen($this->encSalt));
                if (strlen($result) > 2 && $result[1] === ':') {
                    /** @noinspection UnserializeExploitsInspection */
                    $resultfinal = @unserialize($result); // we try to unserialize, if fails, then we keep the current value
                    $result = $resultfinal === false ? $result : $resultfinal;
                }
                return $result;
            } catch (Exception $ex) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * It is a two-way encryption. The result is htlml/link friendly.
     * @param mixed $data For the method simple, it could be a simple value (string,int,etc.)<br>
     *                    For the method integer, it must be an integer<br>
     *                    For other methods, it could be any value. If it is an object or array, then it is
     *                    serialized<br>
     * @return string|int|false     Returns a string with the value encrypted
     */
    public function encrypt($data)
    {
        if (!$this->encEnabled) {
            return $data;
        } // no encryption
        if (is_array($data) || is_object($data)) {
            $data = serialize($data);
        }
        if ($this->iv) {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->encMethod));
        } else {
            $iv = substr(md5($data, true), 0, openssl_cipher_iv_length($this->encMethod));
        }
        $encrypted_string = bin2hex($iv) . openssl_encrypt($this->encSalt . $data, $this->encMethod
                , $this->encPassword, 0, $iv);
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($encrypted_string));
    }


    /**
     * It generates a token based in the user, password and the time<br>
     * This token is "hashed" to it uses the method hash(), that includes the salt.
     * @param string      $user
     * @param string|null $password
     * @return false|string
     */
    public function genToken(string $user, ?string $password)
    {
        return $this->hash($user . $password . time());
    }


    /**
     * It configures how to fetch the values, by assigning the field and how the values will be fetched.<br>
     * <b>Example:</b>
     * <pre>
     * // localhost/page.php?field1=root,field2=123456 it reads the user and password from get.
     * $this->fetchConfig('field1','get','field2','get');
     * // it reads the user from get and the password (called mypwd) from post.
     * $this->fetchConfig('field1','get','mypwd','post');
     * </pre>
     * <b>type of fetch values:</b>'get','post','cookie','request','body','header','all'
     * @param string      $userField         the field user to read the value of the user authentication
     * @param string      $userFieldType     =['get','post','cookie','request','body','header','all'][$i]
     * @param string      $passwordField     the field to read the password of the authentication
     * @param string      $passwordFieldType =['get','post','cookie','request','body','header','all'][$i]
     * @param string|null $tokenField        the name of the field to fetch the values of the token
     * @param string|null $tokenFieldType    =['get','post','cookie','request','body','header','all'][$i]
     * @param string|null $tokenCRCField     the name of the field to fetch the values of the validation of token
     * @param string|null $tokenCRCFieldType =['get','post','cookie','request','body','header','all'][$i]
     * @return void
     */
    public function fetchConfig(string  $userField,
                                string  $userFieldType,
                                string  $passwordField,
                                string  $passwordFieldType,
                                ?string $tokenField = null,
                                ?string $tokenFieldType = null,
                                ?string $tokenCRCField = null,
                                ?string $tokenCRCFieldType = null
    ): void
    {
        $this->fetchConfig = [
            $userField => $userFieldType,
            $passwordField => $passwordFieldType,
            $tokenField => $tokenFieldType,
            $tokenCRCField => $tokenCRCFieldType];
    }

    /**
     * Fetch and returns the user, password, token and token crc (if any) using the configuration in $this->fetchConfig
     * @param array|null $config        see fetConfig();
     * @param bool       $alwaysUserPwd if true then it always fetches the user and passowrd.
     * @return array [user/token,password/validator,body]
     * @see \eftec\authone\AuthOne::fetchConfig
     */
    public function fetch($config = null, $alwaysUserPwd = false): array
    {
        $this->fetchConfig = $config ?? $this->fetchConfig;
        $fc = array_keys($this->fetchConfig);
        $fv = array_values($this->fetchConfig);
        if ($this->authType === 'userpwd' || $alwaysUserPwd) {
            return [
                $this->fetchValue($fc[0], $fv[0]), // user
                $this->fetchValue($fc[1], $fv[1]) // password
            ];
        }
        return [
            $this->fetchValue($fc[2], $fv[2]), // token
            $this->fetchValue($fc[3], $fv[3]) // token crc
        ];
    }

    protected function fetchValue($key, $type)
    {
        switch ($type) {
            case 'get':
                return @$_GET[$key];
            case 'post':
                return @$_POST[$key];
            case 'cookie':
                return @$_COOKIE($key);
            case 'request':
                return @$_REQUEST[$key];
            case 'body':
                $r = file_get_contents('php://input');
                if ($key === '') {
                    return $r;
                }
                try {
                    $json = @json_decode($r, true);
                } catch (Exception $ex) {
                    $json = [];
                }
                return @$json[$key];
            case 'header':
                return @$_SERVER['HTTP_' . strtoupper($key)];
            case 'all':
                $c = $this->fetchValue($key, 'request');
                if ($c === null) {
                    $c = $this->fetchValue($key, 'header');
                    if ($c === null) {
                        $c = $this->fetchValue($key, 'body');
                    }
                }
                return $c;
            default:
                throw new RuntimeException('unknown fetch type [' . $type . ']');
        }
    }
}
