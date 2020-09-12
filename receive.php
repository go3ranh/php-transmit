<?php
include "Transaction.php";
include "../../config/db.php";
include "../../config/config.php";
include 'config.php';

if(isset($_POST['token']) and isset($_POST['transactions'])){
    if ($_POST['token'] == $token){
        $transaction = new \goeranh\Transmit\Transaction();
        $result = $transaction->runTransaction($_POST['transactions'], $pdo, $mysqli);
        if ($result == 0){
            echo 'success';
        }else{
            echo $transaction->getErrorMessages();
        }
    }
}else{
    echo 'you either did not submit a token, or a transaction';
}