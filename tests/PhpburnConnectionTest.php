<?php
require_once 'PHPUnit/Framework.php';
require_once '../app/phpBurn.php';
require_once '../app/libs/Connection.php';
class PhpburnConnectionTest extends PHPUnit_Framework_TestCase {
	function setUp() {
		$config = array(
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
			'newmodel',
		)
		);
		$this->configObj = new PhpBURN_Configuration($config);
	}
	function testIfExistsStaticAttributeCaledConecctionsInConnectionClass() {
		$this->assertClassHasStaticAttribute('connections', 'PhpBURN_Connection');
	}
	function testIfCanGetPhpburnConnection() {
		$this->assertType('PhpBURN_Connection', PhpBURN_Connection::getConnection());
	}
}
?>
