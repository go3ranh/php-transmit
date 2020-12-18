<?php
/*
 * Copyright (c) 2020, go3ranh
 * All rights reserved.
 */

/**
 * this file contains the transaction class
 */

namespace goeranh\Transmit;

use Exception;
use mysqli;
use PDO;

/**
 * this class is meant for creating transactions to be synced to another mysql server
 * it can currently parse SQL INSERT and UPDATE statements
 */
class Transaction
{
    /**
     * @var array $transactionArray Array soring all added transactions
     */
    private $transactionArray = array();
    /**
     * @var String $errorMessage string storing all errormessages occured in the life of this object
     */
    private $errorMessage = '';
    /**
     * @var int $transactionError indicates wether or not any errors have occured
     */
    private $transactionError = 0;
    /**
     * @var array $replaceDBName sores which database name should be changed to what other name
     */
    private $replaceDBName = array();
    /**
     * @var PDO $pdo the PDO database connection
     */
    private $pdo;

    /**
     * @var int $lastInsertId
     */
    private ?int $lastInsertId = null;

    /**
     * pass database object via dependency injection
     * @param PDO $pdo PDO database connection
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * this function converts the transaction array to json and writes it into its database as pending
     * @return bool
     * @throws Exception
     */
    public function commit(): bool
    {
        if ($this->transactionArray != array()) {
            $json = $this->createTransactionJSON();
            $stmt = $this->pdo->prepare("INSERT INTO transactions(transaction) VALUES (?)");
            $stmt->bindParam(1, $json);
            if ($stmt->execute()) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new Exception('Can not commit empty transaction');
        }
    }

    /**
     * this function takes a transaction json, decodes it and performs the actions in it
     * @param String $json JSON String containing the transaction array
     * @param mysqli $mysqli mysqli connection for real_escape_string
     * @return bool
     * @throws Exception
     */
    public function runTransaction($json, $mysqli): int
    {
        //decode as array, not object
        $this->transactionArray = json_decode($json, 1);
        foreach ($this->transactionArray as $transaction) {
            $data = $transaction['data'];
            switch ($transaction['action']) {
                case 'insert':
                    if ($this->replaceDBName != array()) {
                        $database = str_replace($this->replaceDBName['from'], $this->replaceDBName['to'], $data['database-name']) . '.' . $data['table-name'];
                    } else {
                        $database = $data['database-name'] . '.' . $data['table-name'];
                    }
                    $sql = "INSERT INTO " . $mysqli->real_escape_string($database) . "(";
                    for ($i = 0; $i < sizeof($data['fields']); $i++) {
                        if ($i == 0) {
                            $sql .= $mysqli->real_escape_string($data['fields'][$i]);
                        } else {
                            $sql .= ", " . $mysqli->real_escape_string($data['fields'][$i]);
                        }
                    }
                    $sql .= ") VALUES (";
                    for ($i = 0; $i < sizeof($data['values']); $i++) {
                        if ($i == 0) {
                            $sql .= "?";
                        } else {
                            $sql .= ", ?";
                        }
                    }
                    $sql .= ")";

                    $stmt = $this->pdo->prepare($sql);

                    for ($i = 1; $i <= sizeof($data['values']); $i++) {
                        if ($data['values'][$i - 1] == '$$last-insert-id$$') {
                            $bind = $this->getLastInsertID();
                            $stmt->bindParam($i, $bind);
                        } else {
                            $stmt->bindParam($i, $data['values'][$i - 1]);
                        }
                    }

                    if (!$stmt->execute()) {
                        $this->transactionError = 1;
                        $this->errorMessage .= implode(' ', $stmt->errorInfo()) . ' ';
                    }
                    break;
                case 'update':
                    $database = $data['database-name'] . '.' . $data['table-name'];
                    $sql = "UPDATE " . $mysqli->real_escape_string($database) . "SET ";
                    for ($i = 0; $i < count($data['fields']); $i++) {
                        if ($i == 0) {
                            $sql .= $mysqli->real_escape_string($data['fields'][$i]) . "=?";
                        } else {
                            $sql .= ", " . $mysqli->real_escape_string($data['fields'][$i]) . "=?";
                        }
                    }
                    $sql .= " WHERE ";
                    for ($i = 0; $i < count($data['where']['fields']); $i++) {
                        if ($i == 0) {
                            if (is_array($data['where']['fields'][$i])){
                                $sql .= $mysqli->real_escape_string($data['where']['fields'][$i][1])."=?";
                            }else{
                                $sql .= $mysqli->real_escape_string($data['where']['fields'][$i]) . "=?";
                            }
                        } else {
                            $sql .= " and " . $mysqli->real_escape_string($data['where']['fields'][$i]) . "=?";
                        }
                    }

                    $stmt = $this->pdo->prepare($sql);
                    //todo implement last insert id
                    for ($i = 0; $i < count($data['fields']) + count($data['where']['fields']); $i++) {
                        if ($i < count($data['fields'])) {
                            $stmt->bindParam($i + 1, $data['values'][$i]);
                        } else {
                            $var = $data['where']['values'][$i - count($data['fields'])];
                            $stmt->bindParam($i + 1, $var);
                        }
                    }
                    if (!$stmt->execute()) {
                        $this->transactionError = 1;
                        $this->errorMessage .= implode(' ', $stmt->errorInfo()) . '.';
                    }
                    break;

                case 'delete':

                    $database = $data['database-name'] . '.' . $data['table-name'];
                    $sql = "DELETE FROM " . $mysqli->real_escape_string($database) . " WHERE ";
                    for ($i = 0; $i < count($data['where']['fields']); $i++) {
                        if ($i == 0) {
                            if (is_array($data['where']['fields'][$i])){
                                $sql .= $mysqli->real_escape_string($data['where']['fields'][$i][1])."=?";
                            }else{
                                $sql .= $mysqli->real_escape_string($data['where']['fields'][$i]) . "=?";
                            }
                        } else {
                            $sql .= " and " . $mysqli->real_escape_string($data['where']['fields'][$i]) . "=?";
                        }
                    }

                    $stmt = $this->pdo->prepare($sql);
                    //todo implement last insert id
                    for ($i = 0; $i < count($data['where']['fields']); $i++) {
                        $var = $data['where']['values'][$i - count($data['fields'])];
                        $stmt->bindParam($i + 1, $var);
                    }


                    if (!$stmt->execute()) {
                        $this->transactionError = 1;
                        $this->errorMessage .= implode(' ', $stmt->errorInfo()) . '.';
                    }

                    break;
                default:
                    $this->transactionError = 1;
                    $this->errorMessage .= 'Unsupported transaction action ' . $this->transactionArray['action'] . '.';
                    throw new Exception('Unsupported transaction action');
            }
        }
        return $this->transactionError;
    }

    /**
     * this function returns all the combined error mssages that occured in the life ot the transaction object
     * @return string
     */
    public function getErrorMessages(): string
    {
        return $this->errorMessage;
    }

    /**
     * returns wether or not any errors have occured yet
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->transactionError;
    }

    /**
     * returns all pending transactions from the database
     * @return array PDO::ASSOC
     */
    public function getPendingTransactions(): array
    {
        $stmt = $this->pdo->query("SELECT id, transaction FROM transactions WHERE bearbeitet=0");
        $return = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $return;
    }

    /**
     * updates any given transaction as sent
     * @param $id int
     * @return bool
     */
    public function markAsSent($id): bool
    {
        $sql = "UPDATE shop.transactions SET bearbeitet=1 WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $id);
        if (!$stmt->execute()) {
            return false;
        }
        return true;
    }

    //todo substitute database names for shared hosting

    /**
     * set a database name, that needs to be changed on the server
     * @param String $from the original database name on the sending machine
     * @param String $to the database name to be inserted instead
     */
    public function substituteDatabaseName($from, $to)
    {
        $this->replaceDBName = array('from' => $from, 'to' => $to);
    }

    /**
     * returns the pdo last insert id
     * @return int
     */
    public function getLastInsertID(): int
    {
        if ($this->lastInsertId == null) {
            $this->lastInsertId = $this->pdo->lastInsertId();
        }
        return $this->lastInsertId;
    }

    /**
     * use curl to send transaction json to server
     * @param String $transaction the json string containing the transaction instructions
     * @param String $token the token, both parties have agreed upon
     * @param String $url The complete url to the location of the receiving script
     * @return string
     */
    public function sendTransaction($transaction, $token, $url): string
    {
        $submit = array();
        $submit['token'] = $token;
        $submit['transactions'] = $transaction;


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
        return curl_exec($ch);
    }

    public function getTransactions($token, $url): String
    {
        $submit = array();
        $submit['token'] = $token;
        $submit['get'] = true;


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
        return curl_exec($ch);
    }

    public function sendReults($results, $token, $url){
        $submit = array();
        $submit['token'] = $token;
        $submit['results'] = $results;


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
        return curl_exec($ch);
    }

    /**
     * add another transaction to the transaction array - this can be executed once, or as many times as u need, in case you rely on some compound queries
     * @param String $sql SQL statement to be parsed
     * @param array $values values to be bound
     * @param array $whereFields
     * @param array $whereValues
     * @throws Exception
     */
    public function addTransaction($sql, $values, $whereFields = array(), $whereValues = array())
    {
        $type = explode(' ', $sql)[0];
        $fields = $this->getFields($type, $sql);
        $database = $this->getDatabase($type, $sql);
        $table = $this->getTable($type, $sql);

        if (count($values) != count($fields)) {
            throw new Exception('The number of values does not match the number of fields');
        }

        $final = array();
        $final['action'] = strtolower($type);
        $final['data'] = array();
        $final['data']['database-name'] = $database;
        $final['data']['table-name'] = $table;
        $final['data']['fields'] = $fields;
        $final['data']['values'] = $values;

        if (strpos($sql, "WHERE") !== false) {
            if (count($whereFields) != 0 and count($whereValues) != 0) {
                if (count($whereFields) == count($whereValues))
                    $final['data']['where']['fields'] = $whereFields;
                $final['data']['where']['values'] = $whereValues;
            } else {
                $this->errorMessage .= 'The number of fields for your where clause does not match the number of values you gave. ';
                $this->transactionError = 1;
                throw new Exception('The number of fields for your where clause does not match the number of values you gave');
            }
        }

        $this->transactionArray[] = $final;
    }

    /**
     * extracts the table name from the sql statement
     * @param String $type the sql method (INSERT / UPDATE)
     * @param String $sql The sql statement
     * @return string
     * @throws Exception
     */
    private function getTable($type, $sql): string
    {
        $type = strtoupper($type);
        switch ($type) {
            case 'INSERT':
                $parts = explode('INTO', $sql);
                $parts = explode('.', $parts[1]);
                $parts = explode('(', $parts[1]);
                $table = trim(str_replace('`', '', $parts[0]));
                return $table;
            case 'UPDATE':
                $parts = explode('SET', $sql);
                $parts = explode('.', $parts[0]);
                $table = str_replace('`', '', $parts[1]);
                return $table;

            case 'DELETE':
                $parts = explode('.', $sql);
                $parts = explode(' ', $parts[1]);
                $table = str_replace('`', '', $parts[0]);
                return $table;
            default:
                throw new Exception('Unsupported SQL');
        }
    }

    /**
     * extracts the database name from the sql statement
     * @param String $type the sql method (INSERT / UPDATE)
     * @param String $sql The sql statement
     * @return string
     * @throws Exception
     */
    private function getDatabase($type, $sql): string
    {
        $type = strtoupper($type);
        switch ($type) {
            case 'INSERT':
                $parts = explode('INTO', $sql);
                $parts = explode('.', $parts[1]);
                $parts = explode(' ', $parts[0]);
                $database = str_replace('`', '', $parts[1]);
                return $database;
            case 'UPDATE':
                $parts = explode('.', $sql);
                $parts = explode(' ', $parts[0]);
                $database = str_replace('`', '', $parts[1]);
                return $database;
            case 'DELETE':
                $parts = explode('.', $sql);
                $parts = explode('FROM ', $parts[0]);
                $database = str_replace('`', '', $parts[1]);
                return $database;
            default:
                throw new Exception('Unsupported SQL');
        }
    }

    /**
     * extracts the fields from the sql statement
     * @param String $type the sql method (INSERT / UPDATE)
     * @param String $sql The sql statement
     * @return array
     * @throws Exception
     */
    private function getFields($type, $sql): array
    {
        $type = strtoupper($type);
        switch ($type) {
            case 'INSERT':
                $parts = explode('(', $sql);
                $parts = explode(')', $parts[1])[0];
                $parts = explode(', ', $parts);
                for ($i = 0; $i < count($parts); $i++)
                    $parts[$i] = str_replace('`', '', $parts[$i]);
                return $parts;
            case 'UPDATE':
                $parts = explode('WHERE', $sql);
                $parts = explode('SET', $parts[0]);
                $parts = explode('=', $parts[1]);
                $fields = array();
                foreach ($parts as $part) {
                    $part = trim($part);
                    $part = explode(' ', $part);
                    $part = str_replace('`', '', $part[count($part) - 1]);
                    if ($part != '?') {
                        $fields[] = $part;
                    }
                }
                return $fields;

            case 'DELETE':
                return array();
            default:
                throw new Exception("Invalid / unsupported SQL");
        }
    }

    /**
     * converts the transaction array to json
     * @return string
     */
    public function createTransactionJSON(): string
    {
        return json_encode($this->transactionArray);
    }
}