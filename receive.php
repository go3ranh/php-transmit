<?php
include "Transaction.php";
include "../../config/db.php";
include "../../config/config.php";
include 'config.php';

var_dump($_POST['transactions']);

if(isset($_POST['token']) and isset($_POST['transactions'])){
    if ($_POST['token'] == $token){
        $transactions = json_decode($_POST['transactions'], 1);
        foreach ($transactions as $transaction){
            var_dump($transaction);
        }
    }
}else{
    echo 'you either did not submit a token, or a transaction';
}