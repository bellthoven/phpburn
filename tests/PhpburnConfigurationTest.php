<?php
require_once 'PHPUnit/Framework.php';
require_once '../app/phpBurn.php';
require_once '../app/libs/Configuration.php';
class PhpburnConfigurationTest extends PHPUnit_Framework_TestCase {
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
			'newmodel',
		)
		);
	}
	function testCreateANewConfiguration() {
		$config = new PhpBURN_Configuration($this->config);
		$this->assertType('PhpBURN_Configuration', $config);
	}
	function testIfExistsStaticAttributeCalledPackages() {
		$this->assertClassHasStaticAttribute('packages', 'PhpBURN_Configuration');
	}
	function testIfExistsOnlyPhpburnConfigurationObjetcsInPackagesArray() {
		$config = new PhpBURN_Configuration($this->config);
		$this->assertContainsOnly('PhpBURN_ConfigurationItem', $config->getPackages());
	}
	function testIfReturnAPhpburnConfigurationOfPackageDefault() {
		$config = new PhpBURN_Configuration($this->config);
		$this->assertType('PhpBURN_ConfigurationItem', $config->getConfig("default"));
	}
	function testIfPackageNotExists() {
		$config = new PhpBURN_Configuration($this->config);
		$this->assertFalse($config->getConfig("worry"));
	}
}
?>
