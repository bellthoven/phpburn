<?php
class PhpBURN_Connection {
	private static $connections = array();
	private function PhpBURN_Connection() {
		return false;
	}
	public static function create(PhpBURN_ConfigurationItem $config) {
		$conn = self::getConnection($config->dialect);
		if(!$conn) {
			//Create a new connection
			//Loads the interface for dialect uses
			PhpBURN::load('Connection.IConnection');
			if (PhpBURN::load("Connection.$config->dialect") != "error") {
				$className = self::getConnectionClass($config->dialect);
				$connectionClass = new $className;
				$connectionClass->setHost($config->host);
				$connectionClass->setPort($config->port);
				$connectionClass->setUser($config->user);
				$connectionClass->setPassword($config->password);
				$connectionClass->setDatabase($config->database);
				$conn = self::$connections[$config->package] = $connectionClass;
			}
		}
		return $conn;
	}
	public static function getAllConnections() {
		return self::$connections;
	}
	public function getConnection($package = null) {
		if (isset($package))
			return self::$connections[$package];
		else {
			$connections = self::$connections;
			return array_shift($connections);
		}
	}
	private function getConnectionClass($dialect = null) {
		$dialect = $dialect = null ? "MySQL" : $dialect;
		return "PhpBURN_Connection_$dialect";
	}
}
?>
