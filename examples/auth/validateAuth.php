<?php

use eftec\authone\AuthOne;

include '../../vendor/autoload.php';

//  $database, string $server, string $user, string $pwd, string $db = ''

$pdoConfig=['databaseType'=>'mysql','server'=>'127.0.0.1','user'=>'root','pwd'=>'abc.123','db'=>'sakila'];

//$auth=new AuthOne('session','pdo','pdo',null,$pdoConfig);
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
//$sesid=$auth->createAuth('admin','abc.123');

$sesid='[{"myuser":"admin","mypassword":"3e1c1d71a28fbaf388d40326462e48be6b1bdbb7ca5712f123802e80ebf6e4ba","mydisable":"1"},0]';
$token='a03ba5dcda964b275579a21455e42334e1992a5be3703a225e7719b5a720a647';

//$sesid=$_GET['sesid'];
var_dump($auth->validateAuth(json_decode($sesid,true),$token));
var_dump("sessid: ".$sesid);
//$sesid='fbf9fe4e493f9fcca7538956551bf6bb';



