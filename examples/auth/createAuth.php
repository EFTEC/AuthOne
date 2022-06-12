<?php

use eftec\authone\AuthOne;

include '../../vendor/autoload.php';



//  $database, string $server, string $user, string $pwd, string $db = ''

$pdoConfig=['databaseType'=>'mysql','server'=>'127.0.0.1','user'=>'root','pwd'=>'abc.123','db'=>'sakila'];

//$auth=new AuthOne('session','pdo',null,$pdoConfig);
$auth=new AuthOne('jwtlite', 'pdo', $pdoConfig);

$auth->fieldUser='myuser';
$auth->fieldPassword='mypassword';
$auth->fieldEnable='mydisable';
$auth->table='mytable';

$newUser=['myuser'=>'admin','mypassword'=>'abc.123','mydisable'=>1];

$auth->initialize();

try {
    $auth->addUser($newUser);
} catch(Exception $ex) {
    echo "This error is normal:<br>";
    var_dump($ex->getMessage());
    echo "----<br>";
}



//var_dump($auth->serviceStore->getUser('admin'));
$sesid=$auth->createAuth('admin','abc.123');
echo "body: ".json_encode($sesid['body'])."<br>";
echo "token: ".json_encode($sesid['token'])."<br>";
//echo json_encode(['sessid'=>$sesid]);

//$sesid='fbf9fe4e493f9fcca7538956551bf6bb';

//var_dump($auth->serviceAuth->validate($sesid));


