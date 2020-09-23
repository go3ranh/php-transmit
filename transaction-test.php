<?php
/*
 * Copyright (c) 2020, go3ranh
 * All rights reserved.
 */
include "config.php";
include "../helper.php";
include "Transaction.php";

//$aprikose = 'Aprikose';
$aprikose = 'Mandarine';
//$hersteller = 'Naturkost Erfurt GmbH';
$hersteller = 'Naturkost Erfurt GmbH';

$action = 'test';
$log_str = 'test';
$uid = '42';
$ip = 'test';

$sql = "UPDATE shop.`produkte` SET `bezeichnung` = ? and hersteller = ? WHERE `produkte`.`barcode` = 6;";
//$sql = "INSERT INTO shop.`log`(`action`, `description`, `user`, `ip`) VALUES (?,?,?,?)";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $action, PDO::PARAM_STR, 100);
$stmt->bindParam(2, $log_str, PDO::PARAM_STR, 1000);
$stmt->bindParam(3, $uid, PDO::PARAM_INT, 11);
$stmt->bindParam(4, $ip);
//$stmt->execute();

$transaction = new \goeranh\Transmit\Transaction($pdo);
$transaction->addTransaction($sql, array($aprikose, $hersteller), array('barcode'), array('6'));
$transaction->commit();

//$mysqli = new mysqli($dbhost,$dbuser, $dbpass, $dbname);
//$transaction->runTransaction($transaction->createTransactionJSON(), $pdo, $mysqli);