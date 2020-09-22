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
var_dump($tr->getTransactions($token, $url));