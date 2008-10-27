<?php
require_once 'PHPUnit/Framework.php';
require_once '../app/phpBurn.php';
require_once '../app/libs/Connection.php';
class PHPBurnConnectionTest extends PHPUnit_Framework_TestCase {
	function testIfExistsStaticAttributeCaledConecctionsInConnectionClass() {
		$this->assertClassHasStaticAttribute('connections', 'PhpBURN_Connection');
	}
	function testIfCanGetPhpburnConnection() {
		$this->assertType('PhpBURN_Connection', PhpBURN_Connection::getConnection());
	}
}
?>
