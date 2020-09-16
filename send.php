<?php
/**
 * file to send pending transactions from one server to another as specified in config.php
 */
include "Transaction.php";

use goeranh\Transmit\Transaction;

include "../../config/config.php";
include "../../config/db.php";
include 'config.php';

$tr = new Transaction($pdo);
$transactions = $tr->getPendingTransactions();

if (count($transactions) == 0){
    echo "nothing to do\n";
    die();
}

foreach ($transactions as $transaction) {
    $result = $tr->sendTransaction($transaction['transaction'], $token, $url);
    if ($result == 'success') {
        $tr->markAsSent($transaction['id']);
    } else {
        echo $result . "\n";
    }
}