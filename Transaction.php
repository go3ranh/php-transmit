<?php

/*
 * this class is meant for creating transactions to be synced to another mysql server
 * it can currently parse SQL INSERT and UPDATE statements
 */

namespace goeranh\Transmit;

use Exception;
use PDO;

class Transaction
{
    /*
     * start off with an empty transaction
     */
    private $transactionArray = array();
    private $errorMessage = '';
    private $transactionError = 0;

    /*
     * commit the created transaction to the database
     * expects to be given the database connection object
     * returns either true or false, depending on the success of the write to the database
     */
    public function commit($pdo): bool
    {
        if ($this->transactionArray != array()) {
            $json = $this->createTransactionJSON();
            $stmt = $pdo->prepare("INSERT INTO transactions(transaction) VALUES (?)");
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

    /*
     * this function currently requires a mysqli database connection, wich is only used to run the real_escape_string function
     * the actual queries are run as pdo prepared statements
     */
    public function runTransaction($json, $pdo, $mysqli): bool
    {
        //decode as array, not object
        $this->transactionArray = json_decode($json, 1);
        foreach ($this->transactionArray as $transaction) {
            $data = $transaction['data'];
            switch ($transaction['action']) {
                case 'insert':
                    $database = $data['database-name'] . '.' . $data['table-name'];
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

                    $stmt = $pdo->prepare($sql);

                    for ($i = 1; $i <= sizeof($data['values']); $i++) {
                        if ($data['values'] == '$$last-insert-id$$'){
                            $bind = $this->getLastInsertID($pdo);
                            $stmt->bindParam($i, $bind);
                        }else {
                            $stmt->bindParam($i, $data['values'][$i - 1]);
                        }
                    }

                    if (!$stmt->execute()) {
                        $this->transactionError = 1;
                        $this->errorMessage .= $stmt->errorInfo() . ' ';
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
                            $sql .= $mysqli->real_escape_string($data['where']['fields'][$i]) . "=?";
                        } else {
                            $sql .= " and " . $mysqli->real_escape_string($data['where']['fields'][$i]) . "=?";
                        }
                    }

                    $stmt = $pdo->prepare($sql);
                    //todo implement last insert id
                    for ($i = 0; $i < count($data['fields']) + count($data['where']['fields']); $i++) {
                        if ($i < count($data['fields'])) {
                            $stmt->bindParam($i + 1, $data['values'][$i]);
                        } else {
                            $stmt->bindParam($i + 1, $data['where']['values'][$i - count($data['fields'])]);
                        }
                    }
                    if (!$stmt->execute()) {
                        $this->transactionError = 1;
                        $this->errorMessage .= $stmt->errorInfo() . '.';
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

    public function getErrorMessages(): string
    {
        return $this->errorMessage;
    }

    public function getErrorCode(): int
    {
        return $this->transactionError;
    }

    public function getPendingTransactions($pdo): array
    {
        $stmt = $pdo->query("SELECT id, transaction FROM transactions WHERE bearbeitet=0");
        $return = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $return;
    }

    public function markAsSent($id, $pdo): bool
    {
        $sql = "UPDATE shop.transactions SET bearbeitet=1 WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(1, $id);
        if (!$stmt->execute()) {
            return false;
        }
        return true;
    }

    //todo substitute database names for shared hosting
    public function substituteDatabaseName($from, $to){

    }

    public function getLastInsertID($pdo):int{
        return $pdo->lastInsertId();
    }

    public function sendTransaction($transaction, $token, $url): string
    {
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
        return curl_exec($ch);
    }

    /*
     * this function expects an sql statement designed for pdo prepared statements and the associated values in an array, in the correct order
     * this function can be run as many times as needed and will then combine sql statements into one large transaction
     * this function is responsible for creating the transaction array from the sql string
     */
    public function addTransation($sql, $values, $whereFields = array(), $whereValues = array())
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

        //TODO implement where with database names, not just fields (for example with nested arrays, first beeing database, second fieldname)
        if (strpos($sql, "WHERE") !== false) {
            if (count($whereFields) != 0 and count($whereValues) != 0) {
                if (count($whereFields) == count($whereValues))
                    $final['data']['where']['fields'] = $whereFields;
                $final['data']['where']['values'] = $whereValues;
            } else {
                throw new Exception('The number of fields for your where clause does not match the number of values you gave');
            }
        }

        $this->transactionArray[] = $final;
    }

    /*
     * defers the mysql table name from the given sql query, if supported
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
            default:
                throw new Exception('Unsupported SQL');
        }
    }

    /*
     * this function defers the database name from the sql statement
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
            default:
                throw new Exception('Unsupported SQL');
        }
    }

    /*
     * this function returns an array with all the fields, wich are used in the query
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
                //TODO implement updating
                //UPDATE `produkte` SET `bezeichnung` = 'Aprikose ' WHERE `produkte`.`barcode` = 6;
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
            default:
                throw new Exception("Invalid / unsupported SQL");
        }
    }

    /*
     * this function returns the transaction object as JSON
     */
    public function createTransactionJSON(): string
    {
        return json_encode($this->transactionArray);
    }
}