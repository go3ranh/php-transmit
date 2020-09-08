# php-transmit
PHP-Scripts/Classes for mirroring database calls onto another server

## Motivation / Inspiration
I personally needed to mirror changes from one MySQL server to another in a shared hosting envoironment without beeing abled to leverage the built in MySQL-replication functionality

## How it works
This class needs to have one database table 'transactions' in which it can store all database changes to be transmitted to the other server. It creates an array containing information about the kind of change (INSERT, UPDATE), the table name, the fields to be used and the corresponding values. This is PHP-Array is then converted to JSON for storrage and transmission. The receiving server then takes this array and performs actions depending on the contents of this array.

## Roadmap / TODO
I have already written tooling to send and receive these transactions, but I still have to modify them slightly, in order for them to be useable in this general version.
