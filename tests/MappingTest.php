<?php
require_once 'PHPUnit/Framework.php';
require_once '../app/libs/Configuration.php';
require_once '../app/libs/Mapping.php';
require_once '../example_application/model/webinsys/Teste.php';
class MappingTest extends PHPUnit_Framework_TestCase {
	function setUp() {
		$this->configuration = array(
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
					'webinsys',
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
	function testIfCanCreateAPhpburnTestObject() {
		$configuration = new PhpBURN_Configuration($this->configuration);
		$teste = new Teste();
		$this->assertType('PhpBURN_Core', $teste);
	}
	function testIfExistsInPhpburnObjectOneMappingObject() {
		$configuration = new PhpBURN_Configuration($this->configuration);
		$teste = new Teste();
		$this->assertType('PhpBURN_Map', $teste->getMap());
	}
	function testIfCanAddANewAttribute() {
		$configuration = new PhpBURN_Configuration($this->configuration);
		$teste = new Teste();
		$teste->hasAttribute('login', 'string');
		$this->assertEquals(1, count($teste->getMap()->getAllMaps()));
	}
	function testIfCanAddANewAttributeOfStringAndYourLengthIsFifty() {
		$configuration = new PhpBURN_Configuration($this->configuration);
		$teste = new Teste();
		$teste->hasAttribute('teste', 'string', 50);
		$field = $teste->getMap()->getFieldInfo('teste');
		$this->assertEquals(50, $field['field']['length']); 
	}
	function testIf
}
?>
