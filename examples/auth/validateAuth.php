<?php

use eftec\authone\AuthOne;

include '../../vendor/autoload.php';

//  $database, string $server, string $user, string $pwd, string $db = ''

$pdoConfig=['databaseType'=>'mysql','server'=>'127.0.0.1','user'=>'root','pwd'=>'abc.123','db'=>'sakila'];

//$auth=new AuthOne('session','pdo','pdo',null,$pdoConfig);
$auth=new AuthOne('jwtlite', 'pdo', $pdoConfig);

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

$body='{"myuser":"admin","mypassword":"3e1c1d71a28fbaf388d40326462e48be6b1bdbb7ca5712f123802e80ebf6e4ba","mydisable":"1"}';
$token='1000000000ffb1f450f317a160ccafb534d6f4b46c46147b8e1a34752746a84f0390aab705';

echo "<h1>If the validations is correct, then it will return an userobject, otherwise null:</h1>";
var_dump($auth->validateAuth($body,$token));


