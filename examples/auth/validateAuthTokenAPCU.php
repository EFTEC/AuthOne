<?php

use eftec\authone\AuthOne;
use eftec\CacheOne;

include '../../vendor/autoload.php';

//  $database, string $server, string $user, string $pwd, string $db = ''
var_dump('uno:');
$cache=new CacheOne('apcu','127.0.0.1','',0);
var_dump('dos:');

$tokenConfig = [
    'type' => 'apcu', // it will use redis to store the temporary tokens.
    // Values allowed: auto (automatic),redis (redis) ,
    //memcache (memcache),apcu (PHP APCU),pdoone (database) and documentone (file system)
    'server' => '',  // the server of REDIS or PDO. For documentone it is the startup folder.
    'schema' => '', // (optional), the schema or folder.
    'port' => 0, // (optional) the port, used by redis memcache or pdo
    'user' => '', // (optional) the user used by pdo
    'password' => '' // (optional) the password used by pdo
];

//$auth=new AuthOne('session','pdo','pdo',null,$pdoConfig);
$auth=new AuthOne('token','token', null,$tokenConfig);

$auth->fieldUser='myuser';
$auth->fieldPassword='mypassword';
$auth->fieldEnable='mydisable';
$auth->table='mytable';
/*
$newUser=['myuser'=>'admin','mypassword'=>'abc.123','mydisable'=>1];

try {
    $auth->addUser($newUser);
} catch(Exception $ex) {
  //  var_dump($ex->getMessage());
}
*/
// EDIT THOSE VALUES:

$token="d956003993ebbb7e5ca7b6ba99ad1211251183f9c8757958ac87e81342b35e39";
echo "<h1>If the validations is correct, then it will return an userobject, otherwise null:</h1>";
var_dump($auth->validateAuth($token));


