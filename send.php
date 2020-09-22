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
$data = $tr->getTransactions($token, $url);

var_dump($data);