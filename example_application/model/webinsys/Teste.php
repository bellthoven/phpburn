<?php
class Teste extends PhpBURN_Core {
	public $package = 'webinsys';
	public $tablename = 'users';
	
	public $id;
	public $login;
	public $pass;
	public $status;
	public $created;
	
	function Teste() {
		parent::PhpBURN_Core();
	}
	
	/**
	 * Here we setup all mapping fields without user XML
	 * 
	 * IMPORTANT: This method will ONLY be called automaticaly if the model DOES NOT have a xml Map
	 * 
	 * @example $this->_mapObj->addField('name','column','sqlType','length',array('notnull' => true, 'autoincrement' => true);
	 * @example $this->_mapObj->addField('name','column','sqlType','length',array();
	 * @example $this->_mapObj->addField('id','user_id','int','10',array('notnull' => true, 'pk' => true, 'autoincrement' => true);
	 */
	public function _mapping() {
		
	}
}
?>
