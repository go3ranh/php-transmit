
[//]: # (Copyright (c) 2020, go3ranh. All rights reserved.)

# php-transmit
PHP-Scripts/Classes for mirroring database calls onto another server

## Motivation / Inspiration
I personally needed to mirror changes from one MySQL server to another in a shared hosting envoironment without beeing abled to leverage the built in MySQL-replication functionality

## How it works
This class needs to have one database table 'transactions' in which it can store all database changes to be transmitted to the other server. It creates an array containing information about the kind of change (INSERT, UPDATE), the table name, the fields to be used and the corresponding values. This is PHP-Array is then converted to JSON for storrage and transmission. The receiving server then takes this array and performs actions depending on the contents of this array. The method ```createTransaction($sql)``` deconstructs the sql-statement and stores all nessecary parts (the action(INSERT or UPDATE), the affected fields, theis corresponding values and if needed the WHERE condition) in a JSON format. Each database transaction row has a field to indicate, wether or not the transaction has already been transmitted. This field is only changes on success to make sure unsuccessful transactions will be retried and successfull transactions are not executed again. To use the sending / receiving you could just use the send.php and receive.php scrips - you simply have to adapt the values in config.php to fit your needs. After changing the config values, you need to ad a cronjob to execute the send.php script on a regular basis.

## Roadmap / TODO
I have already written tooling to send and receive these transactions, but I still have to modify them slightly, in order for them to be useable in this general version.
