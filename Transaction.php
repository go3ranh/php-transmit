<?php

/*
 * this class is meant for creating transactions to be synced to another mysql server
 * it can currently parse SQL INSERT and UPDATE statements
 */

class Transaction
{
    /*
     * start off with an empty transaction
     */
    private $transactionArray = array();

    /*
     * commit the created transaction to the database
     * expects to be given the database connection object
     * returns either true or false, depending on the success of the write to the database
     */
    public function commit($pdo): bool
    {
        if ($this->transactionArray != array()) {
            $json = $this->createTransactionJSON();
            var_dump($json);
            die();
            $stmt = $pdo->prepare("INSERT INTO " . $_SERVER['shopdb'] . ".transactions(transaction) VALUES (?)");
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
     * this function expects an sql statement designed for pdo prepared statements and the associated values in an array, in the correct order
     * this function can be run as many trimes as needed and will then combine sql statements into one large transaction
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

        if (strpos($sql, "WHERE") !== false) {
            if (count($whereFields) != 0 and count($whereFields) != 0) {
                if (count($whereFields) == count($whereValues))
                $final['data']['where']['fields'] = $whereFields;
                $final['data']['where']['values'] = $whereValues;
            }else{
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
    private function createTransactionJSON(): string
    {
        return json_encode($this->transactionArray);
    }
}