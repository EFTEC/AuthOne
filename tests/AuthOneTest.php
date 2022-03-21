<?php /** @noinspection ForgottenDebugOutputInspection */
/** @noinspection PhpArrayWriteIsNotUsedInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection SqlDialectInspection */

namespace eftec\tests;

use eftec\authone\AuthOne;
use eftec\PdoOne;
use Exception;
use PHPUnit\Framework\TestCase;

class AuthOneTest extends TestCase
{
    /** @var AuthOne */
    protected $auth;

    protected $userTemplate = ['myuser' => 'admin',
        'mypassword' => '3e1c1d71a28fbaf388d40326462e48be6b1bdbb7ca5712f123802e80ebf6e4ba',
        'myenable' => '1'];
    protected $userTemplate2 = ['myuser' => 'admin2',
        'mypassword' => '3e1c1d71a28fbaf388d40326462e48be6b1bdbb7ca5712f123802e80ebf6e4ba',
        'myenable' => 1];
    protected $userTemplate2Disable = ['myuser' => 'admin2',
        'mypassword' => '3e1c1d71a28fbaf388d40326462e48be6b1bdbb7ca5712f123802e80ebf6e4ba',
        'myenable' => 0];
    protected $tokenConfig = [];
    protected $tokenConfigPdo = [];
    protected $pdoConfig = [];
    protected $docConfig = [];
    protected $userObj2= ['myuser' => 'admin2', 'mypassword' => 'abc.123', 'myenable' => 1];
    protected $userObj= ['myuser' => 'admin', 'mypassword' => 'abc.123', 'myenable' => 1];

    public function setUp(): void
    {
        parent::setUp();
        $this->tokenConfig = [
            'type' => 'redis', // it will use redis to store the temporary tokens.
            // Values allowed: auto (automatic),redis (redis) ,
            //memcache (memcache),apcu (PHP APCU),pdoone (database) and documentone (file system)
            'server' => '127.0.0.1',  // the server of REDIS or PDO. For documentone it is the startup folder.
            'schema' => '', // (optional), the schema or folder.
            'port' => 0, // (optional) the port, used by redis memcache or pdo
            'user' => '', // (optional) the user used by pdo
            'password' => '' // (optional) the password used by pdo
        ];
        $this->tokenConfigPdo = [
            'type' => 'pdoone', // it will use redis to store the temporary tokens.
            // Values allowed: auto (automatic),redis (redis) ,
            //memcache (memcache),apcu (PHP APCU),pdoone (database) and documentone (file system)
            'server' => '127.0.0.1',  // the server of REDIS or PDO. For documentone it is the startup folder.
            'schema' => 'sakila', // (optional), the schema or folder.
            'port' => 0, // (optional) the port, used by redis memcache or pdo
            'user' => 'root', // (optional) the user used by pdo
            'password' => 'abc.123' // (optional) the password used by pdo
        ];
        $this->pdoConfig = [
            'databaseType' => 'mysql', // the type of database: mysql, sqlsrv or oci (oracle)
            'server' => '127.0.0.1', // the server of the database
            'user' => 'root', // the user
            'pwd' => 'abc.123', // the password
            'db' => 'sakila'
        ]; // the database or schema. In oracle, this value is ignored, and it uses the user.
        $this->docConfig = [
            'database' => __DIR__ . '/base', // the initial folder
            'collection' => '', // (optional) the sub-folder
            'strategy' => 'folder', // (optional )the lock strategy.
                                    // It is used to avoid that two users replace the same file at the same time.
            'server' => '', // used by REDIS, example: localhost:6379
            'serializeStrategy' => 'json_array' // (optional) the strategy to serialization
        ];
        $this->initialize();
    }
    public function initialize():void {
        $this->auth = new AuthOne('token', 'pdo', $this->pdoConfig, $this->tokenConfigPdo);
        /** @var PdoOne $pdo */
        $pdo=$this->auth->serviceUserStore->getInstance();
        try {
            // we delete the previous tables.
            $pdo->dropTable('mytable');

        } catch(Exception $ex) {
            // it could throw an exception if the tables does not exist.
            var_dump($ex->getMessage());
        }
        try {
        $pdo->setKvDefaultTable('mytokens');
        $pdo->dropTableKV();
        } catch(Exception $ex) {
            // it could throw an exception if the tables does not exist.
            var_dump($ex->getMessage());
        }
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->setTokenStoreConfig('mytokens');
        $this->assertEquals([],$this->auth->initialize());

        $this->auth->deleteUser($this->userObj2['myuser']);
        $this->assertEquals($this->userTemplate2, $this->auth->addUser($this->userObj2));
    }

    public function testStorePdo(): void
    {
        $this->auth = new AuthOne('token', 'pdo', $this->pdoConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->setTokenStoreConfig('mytokens');

        $this->auth->deleteUser($this->userObj2['myuser']);
        $this->assertEquals($this->userTemplate2, $this->auth->addUser($this->userObj2));
        $this->assertEquals($this->userTemplate2, $this->auth->getUser('admin2'));
        $this->assertEquals($this->userTemplate2, $this->auth->updateUser($this->userObj2));
        $this->assertEquals($this->userTemplate2Disable, $this->auth->setDisableUser($this->userObj2, true));
        $this->assertGreaterThanOrEqual(0, count($this->auth->listUsers()));
        $this->assertEquals($this->userTemplate2, $this->auth->setDisableUser($this->userObj2, false));
        $this->assertGreaterThanOrEqual(1, count($this->auth->listUsers()));

    }

    public function testStoreDocument(): void
    {
        $this->docConfig = ['database' => __DIR__ . '/base',
            'collection' => '',
            'strategy' => 'folder',
            'server' => '',
            'serializeStrategy' => 'json_array'];

        $this->auth = new AuthOne('token', 'document', $this->docConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');

        $this->auth->deleteUser($this->userObj2['myuser']);
        $this->assertEquals($this->userTemplate2, $this->auth->addUser($this->userObj2));
        $this->assertEquals($this->userTemplate2, $this->auth->getUser('admin2'));
        $this->assertEquals($this->userTemplate2, $this->auth->updateUser($this->userObj2));
        $this->assertEquals($this->userTemplate2Disable, $this->auth->setDisableUser($this->userObj2, true));
        $this->assertGreaterThanOrEqual(0, count($this->auth->listUsers()));
        $this->assertEquals($this->userTemplate2, $this->auth->setDisableUser($this->userObj2, false));
        $this->assertGreaterThanOrEqual(1, count($this->auth->listUsers()));

    }

    public function testStoreCache(): void
    {
        $this->tokenConfig = ['type' => 'auto', 'server' => '127.0.0.1', 'schema' => ''];
        $this->auth = new AuthOne('token', 'token', $this->tokenConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');

        $this->auth->deleteUser($this->userObj2['myuser']);
        $this->assertEquals($this->userTemplate2, $this->auth->addUser($this->userObj2));
        $this->assertEquals($this->userTemplate2, $this->auth->getUser('admin2'));
        $this->assertEquals($this->userTemplate2, $this->auth->updateUser($this->userObj2));
        $this->assertEquals($this->userTemplate2Disable, $this->auth->setDisableUser($this->userObj2, true));
        $this->assertEquals($this->userTemplate2, $this->auth->setDisableUser($this->userObj2, false));
        try {
            $this->auth->listUsers();
        } catch (Exception $exception) {
            $this->assertStringContainsString('AuthOne: service doesn\' allow list users', $exception->getMessage());
        }
    }

    public function testPdoToken(): void
    {
        $this->auth = new AuthOne('token', 'pdo', $this->pdoConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->deleteUser($this->userObj['myuser']);
        $this->assertEquals($this->userTemplate, $this->auth->addUser($this->userObj));
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        sleep(2);
        $this->assertEquals(null, $this->auth->validateAuth($auth)); // expired.
        $auth = $this->auth->createAuth('admin', 'abc.123');
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $this->assertEquals($this->userTemplate, $this->auth->renewAuth($auth, 3));
        sleep(2);
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
    }

    public function testPdoSession(): void
    {
        $this->auth = new AuthOne('session', 'pdo', $this->pdoConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->deleteUser($this->userObj['myuser']);
        $this->assertEquals($this->userTemplate, $this->auth->addUser($this->userObj));
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(32, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        sleep(2);
        $this->assertEquals(null, $this->auth->validateAuth($auth)); // expired.
        $auth = $this->auth->createAuth('admin', 'abc.123');
        $this->assertEquals(32, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(32, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $this->assertEquals($this->userTemplate, $this->auth->renewAuth($auth, 3));
        sleep(2);
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
    }

    public function testPdoJWT(): void
    {
        $this->auth = new AuthOne('jwt', 'pdo', $this->pdoConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->deleteUser($this->userObj['myuser']);
        $this->assertEquals($this->userTemplate, $this->auth->addUser($this->userObj));
        $auths = $this->auth->createAuth('admin', 'abc.123', 1);
        $auth = $auths['token'];
        $this->assertEquals(74, strlen($auth)); // 10 time + 64 sha256
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auths['body'], $auths['token']));
        sleep(2);
        $this->assertEquals(null, $this->auth->validateAuth($auths['body'], $auths['token'])); // expired.
        $auths = $this->auth->createAuth('admin', 'abc.123');
        $this->assertEquals(74, strlen($auths['token']));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auths['body'], $auths['token']));
        $auths = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals($this->userTemplate,$auths['body']);
        $this->assertEquals(74, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auths['body'], $auths['token']));
        $authOriginal=$auths;
        $auths = $this->auth->renewAuth($auths['body'], $auths['token'], 3);
        $this->assertEquals($authOriginal['body'],$auths['body']); // the body is the same.
        $this->assertNotEquals($authOriginal['token'],$auths['token']); // the token is different
        sleep(2);
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auths['body'], $auths['token']));
    }

    public function testPdoUserPwd(): void
    {
        $this->auth = new AuthOne('userpwd', 'pdo', $this->pdoConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->deleteUser($this->userObj['myuser']);
        $this->assertEquals($this->userTemplate, $this->auth->addUser($this->userObj));
        $auths = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals($this->userTemplate, $auths);
        $this->assertEquals(
            $this->userTemplate, $this->auth->validateAuth('admin', 'abc.123'));
        sleep(2);
        $this->assertNotNull($this->auth->validateAuth('admin', 'abc.123')); // expired.
        $auths = $this->auth->createAuth('admin', 'abc.123');
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth('admin', 'abc.123'));
        $auths = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth('admin', 'abc.123'));
        try {
            // user/pwd does not allow renewing (because it doesn't expires)
            $auths = $this->auth->renewAuth('admin', 'abc.123', 3);
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        sleep(2);
        $this->assertEquals(
            $this->userTemplate, $this->auth->validateAuth('admin', 'abc.123'));
    }

    public function testFetch(): void
    {
        $this->auth = new AuthOne('token', 'document', $this->docConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->addUser($this->userObj);
        $this->auth->fetchConfig('user','get','password','get','body','get','tokencrc','all');
        $_GET=['user'=>'admin','password'=>'abc.123'];
        $auth = $this->auth->createAuthFetch();
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));

        // test #2
        $this->auth->fetchConfig('user','all','password','all','token','all','tokencrc','all');
        $_GET=[];
        $_SERVER=['HTTP_USER'=>'admin','HTTP_PASSWORD'=>'abc.123'];
        $auth = $this->auth->createAuthFetch();
        $_SERVER['HTTP_TOKEN']=$auth;
        $this->assertEquals(64, strlen($auth));

        $this->assertEquals($this->userTemplate, $this->auth->validateAuthFetch());
        $this->auth->renewAuthFetch();
        $this->auth->invalidateAuthFetch();
        $this->assertEquals(null, $this->auth->validateAuthFetch());

    }

    public function testDocument(): void
    {
        $this->auth = new AuthOne('token', 'document', $this->docConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->addUser($this->userObj);
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        sleep(2);
        $this->assertEquals(null, $this->auth->validateAuth($auth)); // expired.
        $auth = $this->auth->createAuth('admin', 'abc.123');
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $this->assertEquals($this->userTemplate, $this->auth->renewAuth($auth, 3));
        sleep(2);
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
    }

    public function testTokenStorage(): void
    {
        $this->tokenConfig = ['type' => 'auto', 'server' => '127.0.0.1', 'schema' => ''];
        $this->auth = new AuthOne('token', 'token', $this->tokenConfig, $this->tokenConfig);
        $this->auth->setUserStoreConfig('mytable', 'myuser', 'mypassword', 'myenable');
        $this->auth->addUser($this->userObj);
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        sleep(2);
        $this->assertEquals(null, $this->auth->validateAuth($auth)); // expired.
        $auth = $this->auth->createAuth('admin', 'abc.123');
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $auth = $this->auth->createAuth('admin', 'abc.123', 1);
        $this->assertEquals(64, strlen($auth));
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
        $this->assertEquals($this->userTemplate, $this->auth->renewAuth($auth, 3));
        sleep(2);
        $this->assertEquals($this->userTemplate, $this->auth->validateAuth($auth));
    }
}
