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

$bear="Nzc2NGY0ODE2OWQwMGUzMGUwOGE5YzEyZjZkZTlkNDVLM3RGd3dMcXJaUFpzeXlQc0ZaN0ZXVHFldXFMWDMxWE9zRmZBbUk5MVllK3VhNUppZ0Ixck4wald0czBpWWVpd0tVQ3B4elhhTk1vbThodE1jZi9iL1cvajFrNWUvSkdSa1lEdTMrUGIzbUdQVURjNzhPZ2V5ekQ3LysyamVOY21YbXdCRXp3SklOa3dxbWJRSFcvaWdVc0FJMURZVXpPY3Q3TlpxQkpxaHdnaUtsR21WS3llOEtLWGIzTlRlejgvWnhhUlpickNZOHpaem8yUzVDVmlFRG1US3dKOHdJaA==";

echo "<h1>If the validations is correct, then it will return an userobject, otherwise null:</h1>";
var_dump($auth->validateAuth($bear,null,true));
var_dump($auth->failCause);


