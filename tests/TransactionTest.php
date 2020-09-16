<?php
namespace goeranh\Transmit;

use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertNotEquals;

class TransactionTest extends TestCase
{
    public function testAddTransaction()
    {
        $transaction = new Transaction(null);
        $sql = "UPDATE shop.`produkte` SET `bezeichnung` = ? and hersteller = ? WHERE `produkte`.`barcode` = ?;";
        $transaction->addTransaction($sql, array('', ''), array('barcode'), array(1));
        //todo test insert
    }

    public function testCreateTransactionJSON()
    {
        $transaction = new Transaction(null);
        $sql = "UPDATE shop.`produkte` SET `bezeichnung` = ? and hersteller = ? WHERE `produkte`.`barcode` = ?;";
        $transaction->addTransaction($sql, array('', ''), array('barcode'), array(1));
        $test = $transaction->createTransactionJSON();
        $this->assertEquals('[{"action":"update","data":{"database-name":"shop","table-name":"produkte ","fields":["bezeichnung","hersteller"],"values":["",""],"where":{"fields":["barcode"],"values":[1]}}}]', $transaction->createTransactionJSON());
    }

    public function testGetErrorMessages()
    {
        $transaction = new Transaction(null);
        $sql = "UPDATE shop.`produkte` SET `bezeichnung` = ? and hersteller = ? WHERE `produkte`.`barcode` = ?;";
        $this->expectException("The number of fields for your where clause does not match the number of values you gave");
        $transaction->addTransaction($sql, array('', ''));
        self::assertEquals($transaction->getErrorCode(), 1);
        assertNotEquals($transaction->getErrorMessages(), '');
    }
}
