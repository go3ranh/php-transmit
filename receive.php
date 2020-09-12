<?php
include "Transaction.php";
include "../../config/db.php";
include "../../config/config.php";
include 'config.php';

if(isset($_POST['token']) and isset($_POST['transactions'])){
    if ($_POST['token'] == $token){
        $transaction = new \goeranh\Transmit\Transaction();
        $transaction->runTransaction($_POST['transactions'], $pdo, $mysqli);
    }
}else{
    echo 'you either did not submit a token, or a transaction';
}