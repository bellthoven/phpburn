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
	const MANY_TO_MANY                                              = 100104;

	//Query types
	//@TODO We do not use the term SQL because in the future we want to expand phpBURN to NON-SQL databases and/or even possibles new kinds of database such as CouchDB
	const QUERY_SELECT						= 100001;
	const QUERY_SELECT_COUNT                                        = 100002;
	const QUERY_UPDATE						= 100003;
	const QUERY_INSERT						= 100004;
	const QUERY_DELETE						= 100005;
	const QUERY_MULTI_INSERT                                        = 100006;

	//Internal objects
	public $_connObj                                                = null;
	public $_mapObj							= null;
	public $_dialectObj						= null;

	//Fields mapping
	public $_fields							= array();

	//Persistent methods storage
	public $_where							= array();
	public $_orderBy						= array();
        public $_groupBy                                                = array();
	public $_limit							= null;
	public $_select							= array();
	public $_join							= array();

//      Utils
        public $_amount                                                 = 0;

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
	public function __construct($tablename = null, $package = null) {

                $this->_tablename = isset($this->_tablename) ? $this->_tablename : $tablename;
                $this->_package = isset($this->_package) ? $this->_package : $package;

		if(empty($this->_tablename) || empty($this->_package)) {
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

        public function __clone() {
            $this->__cloneSubObjects();
        }

        public function __cloneSubObjects() {
            foreach($this->getMap()->fields as $index => $field) {
                if($this->$index instanceof PhpBURN_Core && $this->getMap()->isRelationship($index)) {
                    $this->$index = clone ($this->$index);
//                  I Still don't know why, but it works!
                    $this->$index = null;
                }
            }
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
         * @param Mixed $pk
         * @param Boolean $fluid
         * @return Integer/PhpBURN_Core
         */
	public function find($pk = null, $fluid = false) {
		// find(true|false) and find(null, true|false); are the same since $pk should not be boolean
		if (is_bool($pk)) {
			$fluid = $pk;
			$pk = null;
		}
		$amount = $this->getDialect()->find($pk);
		$this->_amount = $amount;
		return $fluid == false ? $amount : $this;
	}

        /**
         * Returns the amount of registers finded by find() or get() or related
         * @return Integer
         */
        public function getAmount() {
            return $this->_amount;
        }

	/**
	 * This method resets the fields at the model ( CLEAR ALL FIELDS )
	 */
	public function reset() {
//          Reset MapFields values
            $this->getMap()->reset();

//          Reset Model Conditions
            $this->_orderBy = $this->_select = $this->_where = $this->_limit = $this->_groupBy = array();

//          Reset Dialect values
            $this->getDialect()->reset();
	}

	public function _moveNext() {
		$truePointer = $this->getDialect()->getPointer() == 0 && !$this->getDialect()->dataExists(0) ? 0 : $this->getDialect()->getPointer()+1;//$this->getDialect()->moveNext();

		if($truePointer <= $this->getDialect()->getLast() && $truePointer !== false) {
			return $this->_moveTo($truePointer);
		} else {
			PhpBURN_Message::output('[!Can not move to register!]: #'. $truePointer .  ' [!at!] '  . get_class($this) . ' | [!Current pointer!]: #' . $this->getDialect()->getPointer());
			return false;
		}
	}

	public function _movePrev() {
		$truePointer = $this->getDialect()->movePrev();

		if($truePointer >= 0 ) {
			return $this->_moveTo($truePointer);
		} else {
			PhpBURN_Message::output('[!Can not move to register!]: #'. $truePointer .  ' [!at!] '  . get_class($this) . ' | [!Current pointer!]: #' . $this->getDialect()->getPointer());
			return false;
		}
	}

	public function _moveLast() {
		$truePointer = $this->getDialect()->getLast();
		return $this->_moveTo($truePointer);
	}

	public function _moveFirst() {
		return $this->_moveTo(0);
	}



        /**
         * Moves the resultSet pointer to a determined pointer if the index
         * does not exists it will return a Boolean FALSE
         *
         * @param Integer $pointer
         * @return Boolean/PhpBURN_Core
         */
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
                        return $this;
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
	 * $obj->_users->name and $obj->_users->login (but only as object not a PhpBURN model if you want methods in user use _getLink())
	 *
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 *
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 * @param String $joinType
         * @param String $tableRight
         * @return PhpBURN_Core
	 */
	public function join($tableLeft, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'JOIN', $tableRight = null) {
//		$this->_join[$tableLeft][] 										= array();

//		$index = count($this->_join[$tableLeft])-1;

		$this->_join[$tableLeft]['tableLeft'] 				= $tableLeft;
		$this->_join[$tableLeft]['fieldLeft'] 				= $fieldLeft;
		$this->_join[$tableLeft]['fieldRight'] 			= $fieldRight;
		$this->_join[$tableLeft]['operator'] 				= $operator;
		$this->_join[$tableLeft]['type']						= $joinType;
		$this->_join[$tableLeft]['tableRight'] 			= $tableRight;

                return $this;
	}

	/**
	 * Just a hook for join() method, it automaticaly creates a LEFT JOIN into the SELECT query of your model
	 *
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinLeft($tableName, $fieldLeft = null, $fieldRight = null, $tableRight = null,$operator = '=') {
		return $this->join($tableName, $fieldLeft, $fieldRight, $operator,'LEFT JOIN', $tableRight);
	}

	/**
	 * Just a hook for join() method, it automaticaly creates a RIGHT JOIN into the SELECT query of your model
	 *
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinRight($tableName, $fieldLeft = null, $fieldRight = null, $tableRight = null, $operator = '=') {
		return $this->join($tableName, $fieldLeft, $fieldRight, $operator,'RIGHT JOIN', $tableRight);
	}

	/**
	 * Just a hook for join() method, it automaticaly creates a INNER JOIN into the SELECT query of your model
	 *
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinInner($tableName, $fieldLeft = null, $fieldRight = null, $tableRight = null, $operator = '=') {
		return $this->join($tableName, $fieldLeft, $fieldRight, $operator,'INNTER JOIN', $tableRight);
	}

	/**
	 * Just a hook for join() method, it automaticaly creates a OUTTER JOIN into the SELECT query of your model
	 *
	 * @param String $tableName
	 * @param String $fieldLeft
	 * @param String $fieldRight
	 * @param String $operator
	 */
	public function joinOutter($tableName, $fieldLeft = null, $fieldRight = null, $tableRight = null, $operator = '=') {
		return $this->join($tableName, $fieldLeft, $fieldRight, $operator,'OUTTER JOIN', $tableRight);
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



	public function from($conditions, $override = false) {
		if($override == true) {
			unset($this->_from);
			$this->_from = array();
		}
		array_push($this->_from, $conditions);

                return $this;
	}

	/**
         * This method create customized element alias for you SELECT ( find, get ) queries
         *
         * @param String $condition
         * @param String $alias
         * @param Boolean $only ( DEPRECATED )
         * @param Boolean $override
         *
         * @return PhpBURN_Core
         */
	public function select($condition, $alias = null, $only = true, $override = false) {
		$alias = $alias == null ? $condition : $alias;
                if($override == true) {
			unset($this->_select);
			$this->_select = array();
		}
		array_push($this->_select, array('value'=>$condition, 'alias'=>$alias, 'only' => $only));

                return $this;
	}

	/**
	 * This method allow your model to add various WHERE conditions before your get, search or find call.
	 * However it uses a new way of define your conditions and keep ALL compatibility when database change.
	 *
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1a
	 *
	 * @param String $condition_start
	 * @param String $stringOperator
	 * @param String/Integer $conditon_end
         * @param String $condition
	 * @param Boolean $override
         *
         * @return PhpBURN_Core
	 */
	public function where($condition_start, $stringOperator, $conditon_end = null, $condition = "AND", $override = false) {
            if($stringOperator != null && $conditon_end != null) {
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
            } else {
                $this->mwhere($condition_start, $override);
            }

            return $this;
	}

        /**
	 * This defines MANUAL WHERE clauses to your model if override is true it cleanup all old wheres
	 *
	 * @param String $conditions
	 * @param Boolean $override
         * @return PhpBURN_Core
	 */
	public function mwhere($conditions, $override = false) {
		if($override == true) {
			unset($this->_where);
			$this->_where = array();
		}

		array_push($this->_where, $conditions);

                return $this;
	}

        /**
	 * SuperWhere (swhere) - DEPRECATED USE WHERE INSTEAD
         *
	 * @param String $condition_start
	 * @param String $stringOperator
	 * @param String/Integer $conditon_end
	 * @param Boolean $override
         *
         * @return PhpBURN_Core
         */
        public function swhere($condition_start, $stringOperator, $conditon_end = null, $condition = "AND", $override = false) {
            return $this->where($condition_start, $stringOperator, $conditon_end, $condition, $override);
        }

	/**
         * Add a LIKE condition to your select query
         *
         * @param String $field
         * @param String $content
         * @param String $condition
         * @return PhpBURN_Core
         */
	public function like($field, $content, $condition = 'AND') {


		$conditions = array();
		$conditions['start'] = $field;
		$conditions['end'] = sprintf("LIKE ('%s')", $content);
		$conditions['operator'] = null; //$this->convertWhereOperators($stringOperator);
		$conditions['condition'] = $condition;

//		print $strLike = sprintf("%s LIKE ('%s')", $field, $content);

		array_push($this->_where, $conditions);
                return $this;
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
		$result = $this->getDialect()->fetch();

                if ($result) {
//			Clean old data
			$this->getMap()->reset();
			foreach ($result as $key => $value) {
				$this->getMap()->setFieldValue($key,$value);
			}

                        $this->getDialect()->moveNext();

                        return $this;
		}
		return $result;
	}

	/**
         * This find and fetch a registry from the database and is always used with a Primary Key to identify the searched object.
         * @param Mixed $pk
         * @return PhpBURN_Core
         */
	public function get($pk = null) {
		$amount = $this->find($pk);
		if($amount >= 1) {
			if($amount > 1 && $pk != null) {
				PhpBURN_Message::output('[!There are more than one results for primary key!]: '. $pk, PhpBURN_Message::WARNING);
			}
			return $this->fetch();
		} else {
			return false;
		}
    }

	/**
         * Saves all changes into the Model ( including relationships )
         * @return Boolean
         */
	public function save() {
		return $this->getDialect()->save();
	}

	/**
         * Delete an entry
         * @param Mixed $pk
         * @return Mixed ( false for errors )
         */
	public function delete($pk = null) {
		return $this->getDialect()->delete($pk);
	}

	/**
         * It will order your results based on given params
         *
         * @param String $field
         * @param String $orderType
         * @param Boolean $override
         * @return PhpBURN_Core
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

                return $this;
	}

        /**
         * Groups a set of results based in one common param
         *
         * @param String $field
         * @param Boolean $override
         * @return PhpBURN_Core
         */
        public function groupBy($field, $override = false) {
                if($override == true) {
			unset($this->_groupBy);
			$this->_groupBy = array();
		}

                if(!is_array($this->_groupBy)) {
			$this->_groupBy = array();
		}

		$_tmpGroup['field'] = $field;

		array_push($this->_groupBy, $_tmpGroup);

                return $this;
        }

	/**
         * Limits and/or Paginate your results by changing the query to bring
         * only the correct amount or the range.
         *
         * @param Integer $offset
         * @param Integer $limit
         * @return PhpBURN_Core
         */
	public function limit($offset = null, $limit = null) {
		$this->_limit = $limit == null ? $offset : $offset . ',' . $limit;

                return $this;
	}

//	Relationships functions

	/**
         * Works like $model->find() but instead it search ofr a mapped relationship.
         * Generate a SELECT into the relationship.
         *
         * @param String $name
         * @param Boolean $fluid
         * @param Array $options
         * @return Mixed ( Integer/PhpBURN_Core )
         */
	public function getRelationship($name, $fluid = false, array $options = array()) {
                //Cheking if the link existis

                $defaultOptions = array(
                    "offset"    => null,
                    "limit"     => null
                );

                $options = array_merge($defaultOptions, $options);

		$fieldInfo = $this->getMap()->getRelationShip($name, true);

		if($fieldInfo == false) {
			$modelName = get_class($this);
			PhpBURN_Message::output("<b>$modelName</b> [!has no such relationship!]", PhpBURN_Message::ERROR);
			return false;
		}

//		All good let's start rock'n'roll

//		Instance object
		if( !($this->$fieldInfo['alias'] instanceof $fieldInfo['foreignClass']) && $this->modelExist($fieldInfo['foreignClass'])) {
			$this->$fieldInfo['alias'] = new $fieldInfo['foreignClass'];
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

				return $fluid == false ? $amount : $this->$fieldInfo['alias'];
			break;

			case self::ONE_TO_MANY:
//				Looking for ONE TO MANY relationship

//				Define WHERE based on relationship fields
				$this->$fieldInfo['alias']->swhere($fieldInfo['relKey'],'=',$this->$fieldInfo['thisKey']);

				return $this->$fieldInfo['alias']->find(null, $fluid);
			break;

			case self::MANY_TO_MANY:
//				Looking for MANY TO MANY relationship

//				Table Reference
				$this->$fieldInfo['alias']->join($fieldInfo['relTable'],$fieldInfo['outKey'],$fieldInfo['relOutKey'],'=','JOIN',$this->$fieldInfo['alias']->_tablename);

//				Current Model table
				$this->$fieldInfo['alias']->join($this->_tablename,$fieldInfo['thisKey'],$fieldInfo['relKey'],'=', 'JOIN',$fieldInfo['relTable']);


//				Define HOW TO FIND
				$conditionString = count($this->$fieldInfo['alias']->_where) > 0 ? ' AND ' : '';
				$this->$fieldInfo['thisKey'] = !is_numeric($this->$fieldInfo['thisKey']) ? sprintf("'%s'",$this->$fieldInfo['thisKey']) : $this->$fieldInfo['thisKey'];

				$whereString = sprintf('%s %s.%s = %s',$conditionString, $this->_tablename,$fieldInfo['thisKey'],$this->$fieldInfo['thisKey']);
				$this->$fieldInfo['alias']->mwhere($whereString);

				return $this->$fieldInfo['alias']->find(null, $fluid);

			break;
		}
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
	public function _getLink($name, $fluid = false, array $options = array()) {
		return self::getRelationship($name, $fluid, $options);
	}


	/**
	 * It puts a WHERE clause when you want to get a relationship with specific caracteristics.
	 * If override is true then cleanup old stuff.
	 *
	 * FOR RELATIONSHIPS ONLY
	 *
	 * @example
	 * <code>
	 * $model->relationshipWhere('pictures','date','>', '10/10/2010','AND',true);
	 * $model->getRelationship('albums');
	 * </code>
	 *
	 * @author Kléderson Bueno <klederson@klederson.com>
	 * @version 0.1b
	 *
	 * @param String $linkName
	 * @param String $condition_start
	 * @param String $stringOperator
	 * @param String $conditon_end
	 * @param String $condition
	 * @param Boolean $override
	 *
	 * @return PhpBURN_Core
	 */
        public function relationshipWhere($linkName, $condition_start, $stringOperator = null, $conditon_end = null, $condition = "AND", $override = false) {
            $existis = $this->getMap()->getRelationShip($linkName);

            if($existis) {
                $infos = $this->getMap()->fields[$linkName];

                if( !($this->$linkName instanceof $infos['isRelationship']['foreignClass']) && $this->modelExist($infos['isRelationship']['foreignClass'])) {
                    $this->$linkName = new $infos['isRelationship']['foreignClass'];
                }
            }

            if($existis == true && $stringOperator == null && $condition_start != null) {
                $this->$linkName->mwhere($condition_start);
                return $this;
            } else if( $existis == true && $stringOperator != null ) {
                $this->$linkName->where($condition_start, $stringOperator, $conditon_end, $condition, $override);

                return $this;
            } else {
                PhpBURN_Message::output($linkName . ' [!is not a valid relationship or is missing parameters!]',PhpBURN_Message::ERROR);
                return false;
            }
        }

	public function _linkWhere($linkName, $condition_start, $stringOperator = null, $conditon_end = null, $condition = "AND", $override = false) {
            return $this->relationshipWhere($linkName, $condition_start, $stringOperator, $conditon_end, $condition, $override);
	}

        /**
         * Works like select() but for getRelationship() calls
         * @param String $linkName
         * @param String $field
         * @param String $alias
         * @param Boolean $only
         * @param Boolean $override
         *
         * @return PhpBURN_Core
         */
        public function relationshipSelect($linkName, $field, $alias = null, $only = true, $override = false) {
            $existis = $this->getMap()->getRelationShip($linkName);

            if($existis) {
                $infos = $this->getMap()->fields[$linkName];

                if( !($this->$linkName instanceof $infos['isRelationship']['foreignClass']) && $this->modelExist($infos['isRelationship']['foreignClass'])) {
                    $this->$linkName = new $infos['isRelationship']['foreignClass'];
                }
            }

            if($existis == true && $field != null && $alias != null) {
                $this->$linkName->select($field, $alias, $only, $override);
                return $this;
            } else {
                PhpBURN_Message::output($linkName . ' [!is not a valid relationship or is missing parameters!]',PhpBURN_Message::ERROR);
                return false;
            }
        }

        public function _linkSelect($linkName, $field, $alias = null, $only = true, $override = false) {
            return $this->relationshipSelect($linkName, $field, $alias, $only, $override);
	}

        /**
         * Works like join() but for getRelationship() call         *
         *
         * @param String $linkName
         * @param String $tableLeft
         * @param String $fieldLeft
         * @param String $fieldRight
         * @param String $operator
         * @param String $joinType
         * @param String $tableRight
         *
         * @return PhpBURN_Core
         */
        public function relationshipJoin($linkName, $tableLeft, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'JOIN', $tableRight = null) {
            $existis = $this->getMap()->getRelationShip($linkName);

            if($existis) {
                $infos = $this->getMap()->fields[$linkName];

                if( !($this->$linkName instanceof $infos['isRelationship']['foreignClass']) && $this->modelExist($infos['isRelationship']['foreignClass'])) {
                    $this->$linkName = new $infos['isRelationship']['foreignClass'];
                }
            }

            if($existis == true && $tableLeft != null) {
                $this->$linkName->join($tableLeft, $fieldLeft, $fieldRight, $operator, $joinType, $tableRight);
                return $this;
            } else {
                PhpBURN_Message::output($linkName . ' [!is not a valid relationship or is missing parameters!]',PhpBURN_Message::ERROR);
                return false;
            }
        }

        public function _linkJoin($linkName, $tableLeft, $fieldLeft = null, $fieldRight = null, $operator = '=', $joinType = 'JOIN', $tableRight = null) {
            return $this->relationshipJoin($linkName, $tableLeft, $fieldLeft, $fieldRight, $operator, $joinType, $tableRight);
        }

	/**
	 * Just checks if a model exists based on the configured packages you just have to know the name
	 *
	 * @param String $modelName
	 * @return Boolean
	 */
	private function modelExist($modelName) {
            if ( PhpBURN::import($modelName) != 'error' ) {
                return true;
            } else {
                return false;
            }
	}

        /**
         * Works like limit() but for getRelationship() call
         *
         * @param String $linkName
         * @param Integer $offset
         * @param Integer $limit
         *
         * @return PhpBURN_Core
         */
        public function relationshipLimit($linkName, $offset = null, $limit = null) {
            if( $this->getMap()->getRelationShip($linkName) == true ) {
                $infos = $this->getMap()->fields[$linkName];

                if( !($this->$linkName instanceof $infos['isRelationship']['foreignClass']) && $this->modelExist($infos['isRelationship']['foreignClass'])) {
                                $this->$linkName = new $infos['isRelationship']['foreignClass'];
                }

                $this->$linkName->limit($offset, $limit);
                return $this;
            } else {
                PhpBURN_Message::output($linkName . ' [!is not a valid relationship of!] ' . get_class($this),PhpBURN_Message::ERROR);

                return false;
            }
        }

	public function _linkLimit($linkName, $offset = null, $limit = null) {
            return $this->relationshipLimit($linkName, $offset, $limit);
	}

        /**
         * Works like like() but for getRelationship() call
         *
         * @param String $linkName
         * @param String $field
         * @param Mixed $content
         * @param String $condition
         *
         * @return PhpBURN_Core
         */
        public function relationshipLike($linkName, $field, $content, $condition = 'AND') {
            if( $this->getMap()->getRelationShip($linkName) == true ) {
                $infos = $this->getMap()->fields[$linkName];

                if( !($this->$linkName instanceof $infos['isRelationship']['foreignClass']) && $this->modelExist($infos['isRelationship']['foreignClass'])) {
                                $this->$linkName = new $infos['isRelationship']['foreignClass'];
                }

                $this->$linkName->like($field, $content, $condition);
                return $this;
            } else {
                PhpBURN_Message::output($linkName . ' [!is not a valid relationship of!] ' . get_class($this),PhpBURN_Message::ERROR);

                return false;
            }
        }

	public function _linkLike($linkName, $field, $content, $condition = 'AND') {
            return $this->relationshipLike($linkName, $field, $content, $condition);
	}

        /**
         * Works like order() but for getRelationship() calls
         *
         * @param String $linkName
         * @param String $field
         * @param String $orderType
         * @param Boolean $override
         *
         * @return PhpBURN_Core
         */
        public function relationshipOrder($linkName, $field, $orderType = "ASC", $override = false) {
            if( $this->getMap()->getRelationShip($linkName) == true ) {
                $infos = $this->getMap()->fields[$linkName];

                if( !($this->$linkName instanceof $infos['isRelationship']['foreignClass']) && $this->modelExist($infos['isRelationship']['foreignClass'])) {
                                $this->$linkName = new $infos['isRelationship']['foreignClass'];
                }

                $this->$linkName->order($field, $orderType, $override);
                return $this;
            } else {
                PhpBURN_Message::output($linkName . ' [!is not a valid relationship of!] ' . get_class($this),PhpBURN_Message::ERROR);

                return false;
            }
        }

	public function _linkOrder($linkName, $field, $orderType = "ASC", $override = false) {
            return $this->relationshipOrder($linkName, $field, $orderType, $override);
	}


        /**
         * Begins a Transaction
         *
         * @return PhpBURN_Core
         */
	public function begin() {
		$this->getConnection()->begin();

                return $this;
	}

	/**
         * Commit a Transaction
         * @return PhpBURN_Core
         */
	public function commit() {
		$this->getConnection()->commit();

                return $this;
	}

	/**
	 * Auxiliar Method : Rolls back a Transaction
	 */
	public function rollback() {
		$this->getConnection()->rollback();

                return $this;
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
         * @param Boolean (DEPRECATED) $insane
         * @return Array
         */
	public function toArray($recursive = true, $full = false, $insane = false) {
		$return = array();
		foreach($this->getMap()->fields as $fieldName => $info) {
			if($this->getMap()->getRelationShip($fieldName) == true && $recursive == true) {
				if(get_parent_class($this->$fieldName) == 'PhpBURN_Core') {
					if(count($this->$fieldName->getDialect()->dataSet) > 0)
					foreach($this->$fieldName->getDialect()->dataSet as $index => $value) {
						$return[$fieldName][] = $this->$fieldName->toArray();
					}
				}
//
//                            if($full == true) {
//                                if( ($this->$fieldName instanceof PhpBURN_Core) && $insane == false) {
//                                    $return[$fieldName][] = $this->$fieldName->toArray($recursive, $full);
//                                } else if($insane==true){
//                                    if($this->$fieldName instanceof PhpBURN_Core) {
//                                        $this->$fieldName->find();
//                                        $this->$fieldName->getDialect()->moveFirst();
//                                    } else {
//                                        $this->getRelationship($fieldName);
//                                    }
//
//                                    $return[$fieldName][] = $this->$fieldName->toArray($recursive, $full, $insane);
//                                }
//                            }

			} else {
				$return[$fieldName] = $this->getMap()->getFieldValue($fieldName);
			}
		}

		return $return;
	}

	/**
	 * This method convert all mapped informationg (including cascating relatioinships) into a JSON format to better manage it into views or anything you want to.
         * By default recursive is true and full recursive is false.
         * Recursive means it will take all relationships ( currently or not ) and convert in a zero level to JSON too.
	 *
	 * <code>
	 * $user = new Users();
         *
         * $user->get(1);
         *
         * var_dump($user->toJSON());
	 * </code>
	 *
	 * @param Boolean $recursive
	 * @param Boolean $full
	 * @return String
	 */
	public function toJSON($recursive = true, $full = false) {
		return json_encode(self::toArray($recursive, $full));
	}

        /**
         * This method convert all mapped informationg (including cascating relatioinships) into a stdClass Objecto to better manage it into views or anything you want to.
         * By default recursive is true and full recursive is false.
         * Recursive means it will take all relationships ( currently or not ) and convert in a zero level to stdClass Object too.
	 *
	 * <code>
	 * $user = new Users();
         *
         * $user->get(1);
         *
         * var_dump($user->toStdClass());
	 * </code>
         *
         * @param Boolean $recursive
         * @return stdClass
         */
        public function toStdClass($recursive = true) {
            return $this->arrayToObject($this->toArray($recursive));
        }

        /**
         * This returns all model finded/fetched entries into an Array
         * 
         * @return Array
         */
        public function transformArray() {
            $return = array();

            $currentPosition = $this->getDialect()->getPointer();
            
            if($this->getAmount() > 0) {
                while($this->fetch()) {
                    $return[] = $this->toArray();
                }
            }

            $this->_moveTo($currentPosition);

            return $return;
        }

        /**
         * Transform all model finded/fetched entries into an JSON String
         *
         * @return String
         */
        public function transformJSON() {
            return json_encode($this->transformArray());
        }

        /**
         * Transform all model finded/fetched entries into an stdClass Object
         *
         * @return stdClass
         */
        public function transformStdClass() {
            return $this->arrayToObject($this->transformArray());
        }        

        /**
         * Transform recursively an array into an stdClass Object
         * 
         * @param Array $array
         * @return stdClass
         */
        public function arrayToObject(array $array) {
            $object = new stdClass();

            foreach ($array as $index => $value) {
                if ( !is_null($index) ) {
                    $object->$index = is_array($value) ? $this->arrayToObject($value) : $value;
                } else {
                    $object->$index = new stdClass();
                }
            }
            
            return $object;
            
        }
}
?>
