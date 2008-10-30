<?php
/**
 * All phpBurn classes should extend this
 */
abstract class PhpBURN_Core implements IPhpBurn {
	
	//Relationship types
	const ONE_TO_ONE = 1;
	const ONE_TO_MANY = 2;
	const MANY_TO_MANY = 3;
	
	protected $connection = null;
	protected $dialect = null;
	
	public function PhpBURN_Core() {
		if(!isset($this->tablename) || !isset($this->package)) {
			throw new PhpBURN_Exeption(PhpBURN_Message::EMPTY_PACKAGEORTABLE);
		}
		
		//Mapping the object
		$mappingManager = new PhpBURN_Mapping();
		$mappingManager->create($this);

		//Setting Up the connection Obj
		$this->connection = PhpBURN_Connection::create(PhpBURN_Configuration::getConfig($this->package));
		
		//Setting Up the dialect Obj
		//TODO Organizar a seleção do dialeto
		$dialectManager = new PhpBURN_Dialect();
		$this->dialect = clone $dialectManager->create(PhpBURN_Configuration::getConfig($this->package),$this);
	}
	
	public function __destruct() {
		unset($this->connObj);
	}
	
	public function getConnectionObj() {
		return $this->connObj;
	}

	public function find($sql) {
	}
	
	public function fetch() {
		$result = $this->dialect->fetch();
		if ($result) {
			foreach ($result as $key => $value) {
				$this->$key = $value;
			}
		}
		return $result;
	}
		
	public function get() {
		
	}
	
	public function save() {
		
	}
	
	public function delete() {
		
	}
	
	public function getMap() {
		return PhpBURN_Mapping::getMapping(get_class($this));
	}
#	addField($name, $column, $type, $length, array $options) {
	public function hasAttribute($attribute, $type, $length = null) {
		switch($type) {
			case 'string':
				if (!isset($length))
					$length = 255;
				PhpBURN_Mapping::getMapping(get_class($this))->addField($attribute, $attribute, 'varchar', $length, array());
				break;
		}
	}
}
?>
