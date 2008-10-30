<?php
PhpBURN::load('Mapping.Map');

class PhpBURN_Mapping {
	private static $mapping = array();
	
	/**
	 * Creates and return a PhpBURN_Map Object for the calling model
	 * If the map already exists it just return it ( caching )
	 *
	 * @param PhpBURN_Core $modelObj
	 * @return unknown
	 */
	public function create(PhpBURN_Core $model) {
		$map = $this->getMapping(get_class($model));
		if (!($map instanceof PhpBURN_Map)) {
			$map = new PhpBURN_Map($model);
			$this->addMap($model, $map);
		}
	}

	/**
	 * Add a new map to mapList
	 *
	 * @param PhpBURN_Core $modelObj
	 * @param PhpBURN_Map $mapObj
	 */
	public function addMap(PhpBURN_Core $model, PhpBURN_Map $map) {
		self::$mapping[get_class($model)] = clone $map;
	}
	
	/**
	 * This method add to our default method external maps and infos from inhirited models
	 *
	 * @param PhpBURN_Map $parentMaps
	 * @param PhpBURN_Core $modelObj
	 * @param PhpBURN_Map $mapObj
	 */
	public function addMultiMap($parentMaps,PhpBURN_Core $modelObj,PhpBURN_Map $mapObj) {
		
	}
	
	/**
	 * Get the correspondent map for the Model
	 *
	 * @param PhpBURN_Core $modelObj
	 * @return PhpBURN_Map
	 */
	public static function getMapping($className) {
		if(self::$mapping[$className] != null && self::$mapping[$className] != '') {
			return self::$mapping[$className];
		}
		return null;
	}
	
	/**
	 * Checks if the object is a child from another PhpBURN_Core Object(s)
	 *
	 * @param String $class
	 * @return PhpBURN_MappingItem
	 */
	public function cascadeMaps($class) {
		while($class = get_parent_class($class)) { 
			if($class != "PhpBURN_Core") {
				$_class = new $class;
				$maps[] = $this->create($_class);
				unset($_class);
			}
		}
		return $maps;
	}
}
?>
