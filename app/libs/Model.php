<?php
/**
 * All phpBurn models should extend this class
 * It is the main responsable for the Magic.
 * 
 * @package PhpBURN
 * 
 * @author Kléderson Bueno <klederson@klederson.com>
 * @version 0.4a
 * @abstract
 */

PhpBURN::load('ConnectionManager', 'DialectManager', 'Mapping','IPhpBurn');

abstract class PhpBURN_Core implements IPhpBurn {
	/* The structure of the constants follow the concept
	 * The two first numbers identify the TYPE of constant for example:
	 * 100001, 10 means that integer corresponds to a SQL DATABASE constant, 00 means it corresponds to an QUERY and 01 at the end corresponds to the SELECT query
	 * For more information see the detailed documentation with all constants indexes.
	 * 
	 * TABLE OF REFERENCE:
	 * 10XXXX = SQL DATABASE
	 * 1000XX = QUERY TYPE
	 * 1001XX = QUERY TYPE RELATIONSHIP
	 * 1002XX = DATABASE CONNECTION
	 * 
	 * It has been made to make easier to identify an number in debugs and other stuffs.
	 */
	
	//Relationship types
	const ONE_TO_ONE 						= 100101;
	const ONE_TO_MANY 						= 100102;
	const MANY_TO_ONE 						= 100103;
	const MANY_TO_MANY 					= 100104;
	
	//Query types
	//@TODO We do not use the term SQL because in the future we want to expand phpBURN to NON-SQL databases and/or even possibles new kinds of database such as CouchDB
	const QUERY_SELECT						= 100001;
	const QUERY_SELECT_COUNT			= 100002;
	const QUERY_UPDATE						= 100003;
	const QUERY_INSERT						= 100004;
	const QUERY_DELETE						= 100005;
	const QUERY_MULTI_INSERT			= 100006;
	
	//Internal objects
	public $_connObj							= null;
	public $_mapObj							= null;
	public $_dialectObj							= null;
	
	//Fields mapping
	public $_fields								= array();
	
	//Persistent methods storage
	public $_where								= array();
	public $_orderBy							= array();
	public $_limit									= null;
	public $_select								= array();
	public $_join									= array();
	
	/**
	 * This is an automatic configuration when a model inherit another PhpBURN Model
	 * than the model will use two or more mapItens. 
	 * @example class MyNewModel extends ParentModel {
	 * @example	
	 * @example }
	 * 
	 * @example class ParentModel extends PhpBURN_Core {
	 * @example	
	 * @example }
	 * 
	 * @var Boolean
	 */
	public $_multiMap							= false;
	
	/**
	 * PHP magic method that automaticaly executes when a new instance of this class is been created
	 * Also here we configure the basics for the well work of PhpBURN Models
	 */
	public function __construct() {
		if(!isset($this->_tablename) || !isset($this->_package)) {
			throw new PhpBURN_Exeption(PhpBURN_Message::EMPTY_PACKAGEORTABLE);
		}
		
		//Internal objects
		$this->_connObj								= null;
		$this->_mapObj								= null;
		$this->_dialectObj							= null;
		
		//Fields mapping
		$this->_fields									= array();
		
		//Persistent methods storage
		$this->_where								= array();
		$this->_orderBy								= null;
		$this->_limit									= null;
		$this->_select								= array();
		$this->_join									= array();
		
		//Mapping the object ( _mapObj )
		PhpBURN_Mapping::create($this);
		
		//Setting Up the connection object ( _connObj )
		//$this->_connObj = &PhpBURN_ConnectionManager::create(PhpBURN_Configuration::getConfig($this->_package));
		
		//Setting Up the dialect object ( _dialectObj )
		$this->_dialectObj = clone PhpBURN_DialectManager::create(PhpBURN_Configuration::getConfig($this->_package),$this);
		
		//Then now we have all set... let's rock!
		$this->_initialize();
	}
	
	/**
	 * PHP Magic method that starts when you initialize a class/model
	 * It also starts the conection between the model and the database
	 */
	final private function _initialize() {
		//Opening the database connection for this object
		$this->getConnection()->connect();
	}
	
	/**
	 * Cleaning up the memory
	 */
	public function __destruct() {
		//Cleaning memory and activating __destruct triggers
		unset($this->_connObj, $this->_mapObj, $this->_dialectObj);
	}

	/**
	 * This method search a content based in many arguments like: where, order, primary key, etc.
	 * 
	 * @param String $sql
	 * @return Integer
	 */
	public function find($pk = null) {		
		return $this->getDialect()->find($pk);
	}
	
	public function _moveNext() {
		$truePointer = $this->getDialect()->getPointer() == 0 && !$this->getDialect()->dataExists(0) ? 0 : $this->getDialect()->getPointer()+1;//$this->getDialect()->moveNext();
		
		if($truePointer <= $this->getDialect()->getLast() && $truePointer !== false) {
			$this->_moveTo($truePointer);
		} else {
			PhpBURN_Message::output('[!Can not move to register!]: #'. $truePointer .  ' [!at!] '  . get_class($this) . ' | [!Current pointer!]: #' . $this->getDialect()->getPointer());
			return false;
		}
	}
	
	public function _movePrev() {
		$truePointer = $this->getDialect()->movePrev();
		
		if($truePointer >= 0 ) {
			$this->_moveTo($truePointer);
		} else {
			PhpBURN_Message::output('[!Can not move to register!]: #'. $truePointer .  ' [!at!] '  . get_class($this) . ' | [!Current pointer!]: #' . $this->getDialect()->getPointer());
			return false;
		}
	}
	
	public function _moveLast() {
		$truePointer = $this->getDialect()->getLast();
		$this->_moveTo($truePointer);
	}
	
	public function _moveFirst() {
		$this->_moveTo(0);
	}
	
	public function _moveTo($pointer) {
		$currentPosition = $pointer;//$this->getDialect()->getPointer();
		$lastPosition = $this->getDialect()->getLast();
		
		if($pointer <= $lastPosition && $pointer >= 0) {
			if(!$this->getDialect()->dataExists($pointer)) {
				for($i = $currentPosition; $i <= $pointer; $i++) {
					$this->getDialect()->setPointer($i);
					PhpBURN_Message::output('[!Moving to register!]: #'. $i .  ' [!at!] '  . get_class($this));
					$data = $this->getDialect()->fetch();
					if($data !== false) {
						$this->getMap()->fillModel($data);
					}
				}
				
			} else {
				PhpBURN_Message::output('[!Moving to register!]: #'. $pointer .  ' [!at!] '  . get_class($this));
				$this->getMap()->fillModel($this->getDialect()->dataSet[$pointer]);
				
				$this->getDialect()->setPointer($pointer);
			}
		} else {
			PhpBURN_Message::output('[!You can not move to!]: #'. $pointer .  ' [!at!] '  . get_class($this) . ' | [!Current pointer!]: #' . $this->getDialect()->getPointer(), PhpBURN_Message::ERROR);
			return false;
		}
	}
	
	/**
	 * This function is going to retrive you the prepared QUERY for execution based on your dialect (MySQL, PostgreeSQL, Oracle, SQLite, etc )
	 * 
	 * The original idea is from Hugo Ferreira da Silva in the Lumine Base code we just take and re-design it to our needs.
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 * 
	 * @param Integer $type
	 * @return String
	 */
	public function _getQUERY( $type = self::QUERY_SELECT)
	{
		switch($type)
		{
			case self::QUERY_SELECT:
				return $this->getDialect()->prepareSelect();
			
			case self::QUERY_SELECT_COUNT:
				return null;
				
			case self::QUERY_UPDATE:
				return $this->getDialect()->prepareUpdate();
				
			case self::QUERY_DELETE:
				return $this->getDialect()->prepareDelete();
			
			case self::QUERY_INSERT:
				return $this->getDialect()->prepareInsert();

			case self::QUERY_MULTI_INSERT;
				return null;
				
			default:
				return $this->getDialect()->getCurrentQuery();
		}
		
		//Outputs the error message
		$msg = "[!Unsuported SQL type!]: $type";
		PhpBURN_Message::output($msg, PhpBURN_Message::ERROR);
	}
	
	/**
	 * join Function inserts a JOIN clause in the get()/find() method and than returns the join result in a array into the object
	 * Ex. $obj->join('users');
	 * returns $obj->_users->name and $obj->_users->login (but only as object not a PhpBURN model if you want methods in user use _getLink())
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 * 
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 * @param String $joinType
	 */
	public function join($tableRight, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'JOIN', $tableLeft = null) {
		$this->_join[$tableRight]['fieldLeft'] = $fieldLeft;
		$this->_join[$tableRight]['fieldRight'] = $fieldRight;
		$this->_join[$tableRight]['operator'] = $operator;
		$this->_join[$tableRight]['type'] = $joinType;
		$this->_join[$tableRight]['tableLeft'] = $tableLeft;
	}
	
	/**
	 * Just a hook for join() method, it automaticaly creates a LEFT JOIN into the SELECT query of your model
	 * 
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinLeft($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=') {
		$this->join($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'LEFT JOIN');
	}
	
	/**
	 * Just a hook for join() method, it automaticaly creates a RIGHT JOIN into the SELECT query of your model
	 * 
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinRight($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=') {
		$this->join($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'RIGHT JOIN');
	}
	
	/**
	 * Just a hook for join() method, it automaticaly creates a INNER JOIN into the SELECT query of your model
	 * 
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinInner($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=') {
		$this->join($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'INNER JOIN');
	}
	
	/**
	 * Just a hook for join() method, it automaticaly creates a OUTTER JOIN into the SELECT query of your model
	 * 
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinOutter($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=') {
		$this->join($tableName, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'OUTTER JOIN');
	}
	
	/**
	 * Validate Field(s) value(s) based on mapping instructions and dialect rules
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 * 
	 * @param String $fieldName
	 * @return Boolean
	 */
	public function validateFields($fieldName = null) {
		if( $fieldName == null ) {
			//Validate all fields
			foreach($this->getMap()->fields as $fieldIndex => $fieldContent) {
				$this->getMap()->validateField($fieldIndex);
			}
		} else {
			//Validate an specific field
			$this->getMap()->validateField($fieldName);
		}
		
		return true;
	}
	
	/**
	 * This defines WHERE clauses to your model if override is true it cleanup all old wheres
	 * 
	 * @param String $conditions
	 * @param Boolean $override
	 */
	public function where($conditions, $override = false) {
		if($override == true) {
			unset($this->_where);
			$this->_where = array();
		}
		array_push($this->_where, $conditions);
	}
	
	/**
	 * This method create SELECT method for your call
	 * 
	 * @param String $field
	 * @param String $alias
	 */
	public function select($field, $alias = null) {
		$alias = $alias == null ? $field : $alias;
		array_push($this->_select, array('value'=>$field, 'alias'=>$alias));
	}
		
	/**
	 * SuperWhere (swhere)
	 * 
	 * This method allow your model to add various WHERE conditions before your get, search or find call.
	 * However it uses a new way of define your conditions and keep ALL compatibility when database change.
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 * 
	 * @param String $condition_start
	 * @param String $stringOperator
	 * @param String/Integer $conditon_end
	 * @param Boolean $override
	 */
	public function swhere($condition_start, $stringOperator, $conditon_end, $condition = "AND", $override = false) {
		
		//$this->_exceptionObj->log('teste');
		
		$conditions = array();
		$conditions['start'] = $condition_start;
		$conditions['end'] = $conditon_end;
		$conditions['operator'] = $this->convertWhereOperators($stringOperator);
		$conditions['condition'] = $condition;
		
		if($override == true) {
			unset($this->_where);
			$this->_where = array();
		}
		
		array_push($this->_where, $conditions);

	}
	
	/**
	 * Here we setup the operators table, that translate the spoken language into a programatic operator. It will be used in database queries.
	 * @var Array
	 */
	private $operatorsTable = array(
		">" => array('>', 'major', 'maior'),
		"<" => array('<','minor', 'menor'),
		"!=" => array('!=','diff', 'different', 'diferente'),
		"=" => array('=','==','===','equal','eq','igual'),
		">=" => array('>=','major_equal', 'major_eq', 'maior_igual'),
		"<=" => array('<=','minor_equal', 'minor_eq', 'menor_igual')
	);
	
	/**
	 * Searchs the native spoken language operator and converts into a programatic operator based on $this->operatorsTable .
	 * FIXME Discover another ( and more inteligent ) solution for this case and also move the operatorsTable too.
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 * 
	 * @param String $operator
	 * @return String
	 */
	private function convertWhereOperators($operator) {
		$operator = strtolower($operator);
		
		foreach($this->operatorsTable as $operatorIndex => $content) {
			foreach($content as $value) {
				if($value == $operator) {
					return $operatorIndex;
				}
			}
		}
		
		return $operator;
	}
	
	/**
	 * fetch() moves the cursor to the next result into the dataset
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 * 
	 * @return PhpBURN_Core
	 */
	public function fetch() {
		
		if($this->getDialect()->getPointer() == 0 && !$this->getDialect()->dataExists($this->getDialect()->getPointer())) {
				
		} else {
			$this->getDialect()->moveNext();
		}
		
		$result = $this->getDialect()->fetch();
		if ($result) {
//			Clean old data
			$this->getMap()->reset();
			foreach ($result as $key => $value) {
				$this->getMap()->setFieldValue($key,$value);
			}
			//$this->getDialect()->moveNext();
			
			/* Parent Referecences
//			@TODO Klederson's note: I`m thinking about a way to generalize and use left join at same time with same cascading effect we have using relationShip
			$parentClass = get_parent_class($this);
			
			if($parentClass != 'PhpBURN_Core') {
				$linkName = '__PhpBURN_Extended_'.$parentClass;
				$this->_getLink($linkName);
				$this->$linkName->fetch();

				$subResult = $this->$linkName->getMap()->fields;
				
				foreach ($subResult as $key => $value) {
					$this->getMap()->setFieldValue($key,$value['#value']);
				}
			}
			*/
		}
		
		return $result;
	}

	/**
	 * (non-PHPdoc)
	 * @see app/libs/IPhpBurn#get()
	 */
	public function get($pk = null) {
		$amount = $this->find($pk);
		if($amount >= 1) {
			if($amount > 1 && $pk != null) {
				PhpBURN_Message::output('[!There are more than one results for primary key!]: '. $pk, PhpBURN_Message::WARNING);
			}
			$this->fetch();
			return true;
		} else {
			return false;
		}
    }
	
	/**
	 * (non-PHPdoc)
	 * @see app/libs/IPhpBurn#save()
	 */
	public function save() {
		return $this->getDialect()->save();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see app/libs/IPhpBurn#delete()
	 */
	public function delete($pk = null) {
		$this->getDialect()->delete($pk);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see app/libs/IPhpBurn#order()
	 */
	public function order($field, $orderType = "ASC", $override = false) {
		if($override == true) {
			unset($this->_orderBy);
			$this->_orderBy = array();
		}
		
		if(!is_array($this->_orderBy)) {
			$this->_orderBy = array();
		}
		
		$_tmpOrder['field'] = $field;
		$_tmpOrder['type'] = $orderType;
		
		array_push($this->_orderBy, $_tmpOrder);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see app/libs/IPhpBurn#limit()
	 */
	public function limit($offset = null, $limit = null) {
		$this->_limit = $limit == null ? $offset : $offset . ',' . $limit;
	}
	
//	Relationships functions

	/**
	 * This method gets a relationship of the model based on mapping informations
	 * 
	 * @param String $name
	 * @param String $linkWhere
	 * @param Integer $offset
	 * @param Integer $limit
	 * @return PhpBURN_Core
	 */
	public function getRelationship($name, $linkWhere = null, $offset = null, $limit = null) {
		return self::_getLink($name, $linkWhere, $offset, $limit);
	}
	
	/**
	 * This method gets a relationship of the model based on mapping informations
	 * 
	 * @param String $name
	 * @param String $linkWhere
	 * @param Integer $offset
	 * @param Integer $limit
	 * @return PhpBURN_Core
	 */
	public function _getLink($name, $linkWhere = null, $offset = null, $limit = null) {
		//Cheking if the link existis
		$fieldInfo = $this->getMap()->getRelationShip($name, true);
		
		if($fieldInfo == false) {
			$modelName = get_class($this);
			PhpBURN_Message::output("<b>$modelName</b> [!has no such relationship!]", PhpBURN_Message::EXCEPTION);
			return false;
		}
		
//		All good let's start rock'n'roll

//		Prepare the Where and LIMIT
		$parms = func_get_args();
		
//		Instance object
		PhpBURN::import($this->_package . '.' . $fieldInfo['foreignClass']);
		$this->$fieldInfo['alias'] = new $fieldInfo['foreignClass'];
		
		$linkWhere = $this->getWhereLink($name);
		$linkLimit = $this->getLimitLink($name);
		$linkOrder = $this->getOrderLink($name);
		
//		Setup Where Clause
		if($linkWhere != null && is_array($linkWhere)) {
			foreach($linkWhere as $index => $value) {
				foreach($value as $whereCondition) {
					$this->$fieldInfo['alias']->where($whereCondition);
				}
			}
		}
		
//		Setup Order by Clause
		if($linkOrder != null && is_array($linkOrder)) {
			foreach($linkOrder as $index => $value) {
				foreach($value as $orderCondition) {
					$this->$fieldInfo['alias']->order($orderCondition['field'],$orderCondition['type']);
				}
			}
		}
		
//		Setup Limit Clause
		if($linkLimit != null && !empty($linkLimit) ) {
			$this->$fieldInfo['alias']->limit($linkLimit);
		}
		
//		Setup Limit Clause
		if(isset($offset)) {		
			$this->_linkLimit($name,$offset,$limit);	
		}
		
		
//		Define rules to get it
		switch($fieldInfo['type']) {
				case self::ONE_TO_ONE: 
				case self::MANY_TO_ONE:
//				Looking for ONE TO ONE relationship				
				
//				Define WHERE based on relationship fields
				$this->$fieldInfo['alias']->swhere($fieldInfo['relKey'],'=',$this->$fieldInfo['thisKey']);
				
//				Verify database consistence if there's more then one than we have a database problem
				$amount = $this->$fieldInfo['alias']->find();
				if( $amount > 1 && $fieldInfo['type'] == self::ONE_TO_ONE) {
					$modelName = get_class($this);
					PhpBURN_Message::output("<b>$modelName</b> [!has an inconsistent relationship!] ONE_TO_ONE [!called!] <b>$name</b> [[!results!] ($amount)]", PhpBURN_Message::WARNING);
					return false;
					exit;
				}
				
				return $amount;
			break;
			case self::ONE_TO_MANY:
//				Looking for ONE TO MANY relationship
				
//				Define WHERE based on relationship fields
				$this->$fieldInfo['alias']->swhere($fieldInfo['relKey'],'=',$this->$fieldInfo['thisKey']);		
								
				return $this->$fieldInfo['alias']->find();			
			break;
			case self::MANY_TO_MANY:
//				Looking for MANY TO MANY relationship

				$this->$fieldInfo['alias']->join($fieldInfo['relTable'],$fieldInfo['thisKey'],$fieldInfo['relKey']);
				$this->$fieldInfo['alias']->join($fieldInfo['relTable'],$fieldInfo['outKey'],$fieldInfo['relOutKey']);
				
				return $this->$fieldInfo['alias']->find();
			break;
		}
	}
	
	
	/**
	 * It puts a WHERE clause when you want to get a relationship with specific caracteristics.
	 * If override is true then cleanup old stuff.
	 * 
	 * FOR RELATIONSHIPS ONLY
	 * 
	 * @example
	 * <code>
	 * $model->_linkWhere('album_id','> 10');
	 * $model->getRelationship('albums');
	 * </code>
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1b
	 * 
	 * @param String $linkName
	 * @param String $conditions
	 * @param Boolean $override
	 */
	public function _linkWhere($linkName, $conditions, $override = false) {
		if($override == true) {
			unset($this->_whereLink[$linkName]);
		}
		
		if(!is_array($this->_whereLink[$linkName])) {
			$this->_whereLink[$linkName] = array();
		}
		
		array_push($this->_whereLink[$linkName], $conditions);
	}
	
	public function getWhereLink($linkName) {
		return $this->_whereLink;
	}
	
	public function getLimitLink($linkName) {
		return $this->_linkLimit[$linkName];
	}
	
	/**
	 * It sets a limit or pagination in you link call
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 *
	 * @param String $linkName
	 * @param Integer $start
	 * @param Integer $end
	 */
	public function _linkLimit($linkName, $offset = null, $limit = null) {
		$this->_linkLimit[$linkName] = $limit == null ? $offset : $offset . ',' . $limit;
	}
	
	/**
	 * It creates a order into your link list
	 * 
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 *
	 * @param String $linkName
	 * @param String $field
	 * @param String $orderType
	 */
	public function _linkOrder($linkName, $field, $orderType = "ASC", $override = false) {
		if($override == true) {
			unset($this->_orderLink[$linkName]);
		}
		
		if(!is_array($this->_orderLink[$linkName])) {
			$this->_orderLink[$linkName] = array();
		}
		
		array_push($this->_orderLink[$linkName], $conditions);
	}
	
	public function getOrderLink($linkName) {
		return $this->_orderLink[$linkName];
	}
	
	/**
	 * Auxiliar Method : Begins a Transaction
	 */
	public function begin() {
		$this->getConnection()->begin();
	}
	
	/**
	 * Auxiliar Method : Begins a Transaction
	 */
	public function commit() {
		$this->getConnection()->commit();
	}
	
	/**
	 * Auxiliar Method : Begins a Transaction
	 */
	public function rollback() {
		$this->getConnection()->rollback();
	}
	
	/**
	 * Auxiliar Method : Gets the Map Object for the model
	 * @return PhpBURN_Map
	 */
	public function getMap() {
		return $this->_mapObj;
	}
	
	/**
	 * Auxiliar Method : Gets the Dialect Object for the model
	 * @return PhpBURN_Dialect_(DatabaseType)
	 * @see app/libs/Dialect(Folder)
	 */
	public function getDialect() {
		return $this->_dialectObj;
	}
	
	/**
	 * Auxiliar Method : Gets the Connection Object for the model
	 * @return PhpBURN_Connection_(DatabaseType)
	 * @see app/libs/Connection(Folder)
	 */
	public function getConnection() {
		//return $this->_connObj;
		return PhpBURN_ConnectionManager::create(PhpBURN_Configuration::getConfig($this->_package));
	}
	
	/**
	 * This method convert all mapped informationg (including cascating relatioinships) into a array to better manage it into views or anything you want to.
	 * By default recursive is true and full recursive is false.
	 * 
	 * Recursive means it will take all relationships ( currently or not ) and convert in a zero level to array too.
	 * <code>
	 * Array('name'=>'Klederson', 'albums'=>Array('name' = 'My First Album')
	 * </code>
	 * 
	 * Full means (PLEASE BE VERY CAREFUL WHEN USE IT OR YOU WILL PROBABLY CRASH YOUR APP) it will take ALL relationships (yes all dataset) and ALL sub-relationships ( infinite and alldataset ) and convert giving you for each relationship a array pretty much like:
	 * <code>
	 * Array('name'=>'Klederson', 'albums'=>Array(0 => 'name' = 'My First Album', 1 => 'name' => 'My Second Album')
	 * </code>
	 * 
	 * @param Boolean $recursive
	 * @param Boolean $full
	 * @return Array
	 */
	public function toArray($recursive = true, $full = false) {
		$return = array();
		foreach($this->getMap()->fields as $fieldName => $info) {
			if($this->getMap()->getRelationShip($fieldName) == true) {
				if(get_parent_class($this->$fieldName) == 'PhpBURN_Core') {
					if(count($this->$fieldName->getDialect()->dataSet) > 0)					
					foreach($this->$fieldName->getDialect()->dataSet as $index => $value) {
						$return[$fieldName][] = $this->$fieldName->toArray();
					}
				}
			} else {
				$return[$fieldName] = $this->getMap()->getFieldValue($fieldName);
			}
		}
		
		return $return;
	}
	
	/**
	 * This method convert all mapped informationg (including cascating relatioinships) into a JSON format to better manage it into views or anything you want to.
	 * By default recursive is true and full recursive is false.
	 * 
	 * Recursive means it will take all relationships ( currently or not ) and convert in a zero level to array too.
	 * <code>
	 * @TODO PUT AN EXAMPLE HERE
	 * </code>
	 * 
	 * Full means (PLEASE BE VERY CAREFUL WHEN USE IT OR YOU WILL PROBABLY CRASH YOUR APP) it will take ALL relationships (yes all dataset) and ALL sub-relationships ( infinite and alldataset ) and convert giving you for each relationship a array pretty much like:
	 * <code>
	 * @TODO PUT AN EXAMPLE HERE
	 * </code>
	 * 
	 * @param Boolean $recursive
	 * @param Boolean $full
	 * @return String
	 */
	public function toJSON($recursive = true, $full = false) {		
		return json_encode(self::toArray($recursive, $full));
	}
}
?>
