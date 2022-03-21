<?php

use eftec\authone\AuthOne;

include '../../vendor/autoload.php';

//  $database, string $server, string $user, string $pwd, string $db = ''

$pdoConfig=['databaseType'=>'mysql','server'=>'127.0.0.1','user'=>'root','pwd'=>'abc.123','db'=>'sakila'];

//$auth=new AuthOne('session','pdo',null,$pdoConfig);
$auth=new AuthOne('jwt', 'pdo', $pdoConfig);

$auth->fieldUser='myuser';
$auth->fieldPassword='mypassword';
$auth->fieldEnable='mydisable';
$auth->table='mytable';

$newUser=['myuser'=>'admin','mypassword'=>'abc.123','mydisable'=>1];

try {
    $auth->addUser($newUser);
} catch(Exception $ex) {
  //  var_dump($ex->getMessage());
}



//var_dump($auth->serviceStore->getUser('admin'));
$sesid=$auth->createAuth('admin','abc.123');
echo json_encode(['sessid'=>$sesid]);

//$sesid='fbf9fe4e493f9fcca7538956551bf6bb';

//var_dump($auth->serviceAuth->validate($sesid));


