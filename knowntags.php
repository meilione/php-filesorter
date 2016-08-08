<?php

include_once('db-connector.php'); 
include_once('stemmer.php');

class KnownTags {
	

	private $db;

	private $maxMatchesConfidentResult = 1;
	private $min_string_part_length = 1;

	public $object_types = array();
	public $stop_words = array();

	public function __construct() {
		$this->db = new DBConnector();
		$this->loadObjectTypes();
		$this->loadStopWords();
	}


	private function loadObjectTypes() {
		$query = "SELECT * FROM types";
		$result = $this->db->query($query);

		if (!empty($result)) {
			foreach ($result as $key => $row) {
				$this->object_types[$row['ID']] = $row['type'];
			}
		}
	}


	private function loadStopWords() {
		$query = "SELECT * FROM stopwords";
		$result = $this->db->getColumn($query);
		if (!empty($result)) {
			$this->stop_words = $result;
		}
	}


	public function searchObject($text, $single = false) {

		//TODO implement caching
		//echo "Searching for $text \n";

		/*
		* Match mechanism
		*/
		$match_type = 'nomatch';

		//exact
		$result = $this->getObject($text, '', false, true, true, false); //TODO maybe try stemmed as true
		
		//echo "exact style results\n";
		//print_r($result);
		
		if (!empty($result) && count($result) == 1) {
			$result[0]['score'] = 100;
			$match_type = 'exact';
		}

		//Like
		//TODO consider not searching for "like" style matches as this can be very inaccurate
		if (empty($result)) {
			$result = $this->getObject($text, '', false, true, false, false);
			
			//echo "like style results\n";
			//print_r($result);
			
			if (!empty($result) && count($result) == 1) {
				//calculate similarity
				foreach ($result as $key => $row) {
					$string1 = trim(strtolower($row['object']));
					$string2 = trim(strtolower($text));
					if ($string1 !== $string2) {
						$result[$key]['score'] = levenshtein($text, $row['object']);
					} else {
						$result[$key]['score'] = 100;
					}
				}
				$match_type = 'like';
			}
		}

		//multiple
		if (empty($result)) {
			$matches = array();
			//try splitting by spaces
			if (strpos($text, ' ') !== false) {
				$string_parts = explode(' ', $text);

				//echo 'String parts'."\n";
				//print_r($string_parts);

				foreach ($string_parts as $part) {
					if (strlen($part) < $this->min_string_part_length) {
						continue;
					}

					//see if it is a year number
					if ($this->isYear($part)) {
						$matches[] = array(
								"ID" => 0,
					            "object" => $part,
					            "parent" => 0,
					            "type" => 6,
					            "score" => 100
							);
					} else {
						$part_result = $this->getObject($part, '', false, true, true, true);
						foreach ($part_result as $res_row) {
							$res_row['score'] = 100;
							$matches[] = $res_row;
						}
					}

				}

				//all parts matched
				if (count($string_parts) === count($matches)) {
					$match_type = 'multiple-all';
				} else if (!empty($matches)) {
					$match_type = 'multiple-parts';
				}

			}

			if (!empty($matches)) {
				$result = $matches;
			}
			
		} 

		/*
		* Clean up
		*/
		
		//echo 'Results before clean up'."\n";
		//print_r($result);

		if ($match_type == 'exact') {
			$result = $result[0];
		} else if (in_array($match_type, array('multiple-all','multiple-parts'))) {
			foreach ($result as $rowid => $match) {
				$result[$rowid]['type'] = $this->object_types[$match['type']];
			}
		}

		return array($match_type, $result);

	}


	private function getObject($search, $type = '', $onlyMain = false, $outputParent = false, $exact = false, $stemmed = true) {
		if (empty($type)) {
			$type = '';
		} else {
			$type = ' AND type = ' . $type;
		}

		$onlyMain = $onlyMain ? " AND parent = 0" : "";

		if ($stemmed) {
			$search = PorterStemmer::Stem($search);
		}

		if ($exact) {
			$where = "object = '". $search ."' COLLATE NOCASE";
		} else {
			$where = "object LIKE '%" . $search . "%' COLLATE NOCASE";
		}

		$query = "SELECT * FROM objects WHERE ". $where . $type . $onlyMain;

		//echo $query;

		$result = $this->db->query($query);

		if (empty($result)) {
			return array();
		} else if ($outputParent) {
			return $this->mergeParentObjects($result);
		}

		return $result;
	}


	private function mergeParentObjects($rows) {
		if (empty($rows)) {
			return array();
		}

		foreach ($rows as $key => $row) {
			if ($row['parent'] !== 0) {
				$parent = $this->getParentObject($row['parent']);
				if (!empty($parent)) {
					$score = isset($row['score']) ? $row['score'] : 0;
					$rows[$key] = array_merge( $parent[0], array('score'=>$score));
				}
			}
		}

		return $rows;
	}


	private function getParentObject($id) {
		$query = "SELECT * FROM objects WHERE ID = " . $id;
		$result = $this->db->query($query);

		if (empty($result)) {
			return false;
		}

		return $result;
	}


	private function isYear($text) {
		if (!is_numeric($text)) {
			return false;
		}

		if ($text > 1900 && $text < 2100) {
			return true;
		}
	}


	public function findOrangUtanNames($file_list_path) {
		$file_path_string = file_get_contents($file_list_path);
		preg_match_all('/ou ([0-9]{0,3}) (.*?)[\\\s]/mi', $file_path_string, $matches);
		$matches = array_unique($matches[2]);
		array_walk($matches, array($this, 'cleanUpOrangutanNames'));
		$matches = array_unique($matches);
		print_r($matches);

		//Insert Into DB
		$values = implode("',0,5),('",$matches);
		$values = substr($values, 7);
		$sql = "INSERT INTO objects (object, parent, type) VALUES $values',0,5)";
		echo $sql;
		$this->db->exec($sql);

	}

	private function cleanUpOrangutanNames(&$item, $key) {
		$item = str_ireplace($this->stop_words, '', $item);
		$item = str_replace(array('_relea','relea','_died'),'', $item);
		$item = ucfirst(strtolower($item));
		$item = trim($item);

		if (strlen($item) <= 2) {
			$item = '';
		}
	}

}
