<?php
/**
 * file to send pending transactions from one server to another as specified in config.php
 */
include "Transaction.php";

use goeranh\Transmit\Transaction;
include 'config.php';

$tr = new Transaction($pdo);
$transactions = $tr->getPendingTransactions();

foreach ($transactions as $transaction) {
    $result = $tr->sendTransaction($transaction['transaction'], $token, $url);
    if ($result == 'success') {
        $tr->markAsSent($transaction['id']);
    } else {
        echo $result . "\n";
    }
}

$tr = new Transaction($pdo);
$data = json_decode($tr->getTransactions($token, $url), 1);

$results = array();

foreach ($data as $transaction){
    $tr = new Transaction($pdo);
    $tran = $transaction['transaction'];
    $result = $tr->runTransaction($tran, $mysqli);
    if ($result == 1){
        $status = array('id' => $transaction['id'], 'status' => 'failure');
        $results[] = $status;
    }else{
        $status = array('id' => $transaction['id'], 'status' => 'success');
        $results[] = $status;
    }
}

$tr = new Transaction($pdo);
$tr->sendReults($results, $token, $url);

if (count($transactions) == 0 and count($results) == 0){
    echo "nothing was changed\n";
}else{
    echo count($transactions) . " transactions were sent to the server\n";
    echo count($results) . " transactions were received from the server\n";
}