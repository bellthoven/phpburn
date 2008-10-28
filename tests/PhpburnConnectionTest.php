<?php
require_once 'PHPUnit/Framework.php';
require_once '../app/phpBurn.php';
require_once '../app/libs/Connection.php';
class PhpburnConnectionTest extends PHPUnit_Framework_TestCase {
	function setUp() {
		$this->config = array(
		'dialect' => 'MySQL',
		'database' => 'phpburn_test',
		'user' => 'phpburn',
		'password' => 'phpburn',
		'port' => '3306',
		'host' => 'localhost',
		'class_path' => SYS_BASE_PATH . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR,
		'database_options' => array(),
		'options' => array(),
		'packages' => array(
			'default',
			'phpburn' => array(
				'host' => 'uol.com.br',
				'dialect' => 'SQLITE',
				'database' => 'phpburn',
				'class_path' => '/home/models/phpburn/',
				'port' => '3000'
			),
		)
		);
	}
	function testIfExistsStaticAttributeCaledConecctionsInConnectionClass() {
		$this->assertClassHasStaticAttribute('connections', 'PhpBURN_Connection');
	}
	function testIfExistsOnlyPhpburnConnectionInStaticAttributesConnections() {
		$configObj = new PhpBURN_Configuration($this->config);
		PhpBURN_Connection::create(PhpBURN_Configuration::getConfig('first'));
		$this->assertContainsOnly('PhpBURN_Connection_MySQL', PhpBURN_Connection::getAllConnections());
	}
	function testIfCanGetPhpburnMysqlConnection() {
		$configObj = new PhpBURN_Configuration($this->config);
		$conn = PhpBURN_Connection::create(PhpBURN_Configuration::getConfig('first'));
		$this->assertType('PhpBURN_Connection_MySQL', PhpBURN_Connection::getConnection());
	}
}
?>
