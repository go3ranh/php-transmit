<?php
include "Transaction.php";

use goeranh\Transmit\Transaction;

include "../../config/config.php";
include "../../config/db.php";
include 'config.php';

$transaction = new Transaction();
$transactions = $transaction->getPendingTransactions($pdo);

foreach ($transactions as $transaction) {
    $submit = array();
    $submit['token'] = $token;
    $submit['transactions'] = $transaction['transaction'];


    //url-ify the data for the POST
    $fields_string = http_build_query($submit);

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

    //So that curl_exec returns the contents of the cURL; rather than echoing it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //execute post
    $result = curl_exec($ch);

    if ($result == 'success') {
        $sql = "UPDATE shop.transactions SET bearbeitet=1 WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(1, $transaction['id']);
        $stmt->execute();
    } else {
        echo $result . "\n";
    }
}