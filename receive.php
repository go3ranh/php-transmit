<?php
/*
 * Copyright (c) 2020, go3ranh
 * All rights reserved.
 */
/**
 * file for receiving transactions sent by a script like send.php
 */

/**
 * @author Göran Heinemann
 */

include "Transaction.php";
include 'config.php';

if(isset($_POST['token']) and isset($_POST['transactions'])){
    if ($_POST['token'] == $token){
        $transaction = new \goeranh\Transmit\Transaction($pdo);
        $result = $transaction->runTransaction($_POST['transactions'], $mysqli);
        if ($result == 0){
            echo 'success';
        }else{
            echo $transaction->getErrorMessages();
        }
    }else{
        echo 'Wrong token';
    }
}else{
    if (isset($_POST['token']) and isset($_POST['get'])){
        $transaction = new \goeranh\Transmit\Transaction($pdo);
        $transactions = $transaction->getPendingTransactions();
        echo json_encode($transactions);
    }else{
        if (isset($_POST['token']) and isset($_POST['results'])){
            foreach ($_POST['results'] as $result){
                if ($result['status'] == 'success'){
                    $transaction = new \goeranh\Transmit\Transaction($pdo);
                    $transaction->markAsSent($result['id']);
                }
            }
        }else{
            echo 'you either did not submit a token, or a transaction';
        }
    }
}
