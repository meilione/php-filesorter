<?php


class DBConnector {
	

	private $db;

	public $db_path = './db/yel-objects.db';

	public function __construct() {
		$this->connect();
	}


	public function connect() {
		$this->db = new SQLite3($this->db_path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	}


	public function query($qry) {
		$result = $this->db->query($qry);
		$out = array();
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		    $out[] = $row;
		}
		return $out;
	}


	public function getColumn($qry,$column = '') {
		$result = $this->db->query($qry);
		$out = array();

		$column = !empty($column) ? $column : 0;

		while ($row = $result->fetchArray(SQLITE3_BOTH)) {
		    $out[] = $row[$column];
		}
		return $out;
	}


	public function __destruct() {


	}

}

//$this->db->exec('CREATE TABLE foo (bar STRING)');