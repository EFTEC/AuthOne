<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

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
 * @version       0.92
 */
class AuthOne
{
    public const VERSION = "0.92";
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
    /** @var bool */
    public $encEnabled = true;
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
    public $fetchConfig = ['user' => 'all', 'password' => 'all', 'token' => 'all','tokencrc'=>'all'];

    public $configUserStore;
    public $configTokenStore;

    /**
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
     * @param array|null $configTokenStore The configuration of the token store (used by "token")<br>
     *                                     If null, then it will try to inject an instance<br>
     * @noinspection PhpFullyQualifiedNameUsageInspection
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
                throw new RuntimeException('AuthOne: authType incorrect');
        }
        switch ($storeType) {
            case 'pdo':
                /** @noinspection ClassConstantCanBeUsedInspection */
                if (!class_exists('eftec\\PdoOne')) {
                    throw new RuntimeException('AuthOne: Library eftec\\PdoOne not found or not loaded.
                    Did you add it to the composer? composer add eftec\\pdoone');
                }
                if($this->configUserStore===null) {
                    // auto wire the instance
                    $this->serviceUserStore = \eftec\PdoOne::instance(true);
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
                if($this->configUserStore===null) {
                    // auto wire the instance
                    $this->serviceUserStore = \eftec\DocumentStoreOne\DocumentStoreOne::instance(true);
                } else {
                    $this->serviceUserStore = new ServiceAuthOneStoreDocument($this, $this->configUserStore);
                }
                break;
            case 'token':
                if($this->configUserStore===null) {
                    // auto wire the instance
                    $this->serviceUserStore =CacheOne::instance(true);
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
        if($userObj===null) {
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
     * It creates a new authentication. The result depends on the type of authentication.
     * @param string $user     the user
     * @param string $password the password
     * @param int    $ttl      the duration of the authentication (in seconds).
     * @return array|false|mixed|string|null
     * @throws Exception
     */
    public function createAuth(string $user, string $password, int $ttl = 0)
    {
        if (strlen($user) > $this->MAXLENGHT) {
            // user too big
            return null;
        }
        if (strlen($password) > $this->MAXLENGHT) {
            // password too big
            return null;
        }
        return $this->serviceAuth->createAuth($user, $password, $ttl);
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
        [$u, $p] = $this->fetch(null,true);
        if ($u === null || $p === null) {
            return null;
        }
        return $this->createAuth($u, $p, $ttl);
    }

    /**
     * It validates some authentication. It returns an associative array if succes or null if error.
     * @param mixed       $auth          the authentication
     * @param string|null $PasswordOrCrc the password or crc to validate the authentication<br>
     *                                   it is only required for some type of authentication
     * @return array|null                The userobject or null if the validation fails
     * @throws Exception
     */
    public function validateAuth($auth, ?string $PasswordOrCrc = null): ?array
    {
        $authString = is_string($auth) ? $auth : json_encode($auth);
        if ($this->authType === 'jwtlite' && strlen($authString) > $this->MAXLENGHTOBJECT) {
            // auth too big
            return null;
        }
        if ($this->authType !== 'jwtlite' && strlen($authString) > $this->MAXLENGHT) {
            // auth too big
            return null;
        }
        $r = $this->serviceAuth->validate($authString, $PasswordOrCrc);
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
     * @param array|null $config see fetConfig();
     * @param bool       $alwaysUserPwd if true then it always fetches the user and passowrd.
     * @return array [user/token,password/validator,body]
     * @see \eftec\authone\AuthOne::fetchConfig
     */
    public function fetch($config = null, $alwaysUserPwd=false): array
    {
        $this->fetchConfig = $config ?? $this->fetchConfig;
        $fc = array_keys($this->fetchConfig);
        $fv = array_values($this->fetchConfig);
        if($this->authType==='userpwd' || $alwaysUserPwd) {
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
                throw new RuntimeException('unknown fetch type [' . $type.']');
        }
    }
}
