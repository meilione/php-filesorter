<?php


/*
* Sort files
*/

header('Content-type: text/plain');

include_once('knowntags.php');

set_time_limit(720);

class SortFiles {
	
	private $file_list_path = '';
	private $file_list = array();

	private $settings = array();

	private $known_tags = null;

	private $metadata = array();

	public $debug = false;


	public function __construct($_settings_in = array()) {
		$_settings = array(
			"string" => array(
				"ignorePath" => ""
			)
		);
		$_settings = array_replace_recursive($_settings, $_settings_in);
		$this->settings = $_settings;

		$this->known_tags = new KnownTags();
	}


	public function start() {
		$this->known_tags->findOrangUtanNames($this->file_list_path);
		$this->read_file_list();
		$this->process_file_list();
		$this->build_folder_structure();
		if ($this->debug) {
			$this->debug_output();
		}
	}


	public function set_file_list_path($_path) {
		$this->file_list_path = $_path;
	}


	private function read_file_list() {
		if (!file_exists($this->file_list_path)) {
			return 'File list does not exist';
		}

		$this->file_list = file($this->file_list_path, FILE_SKIP_EMPTY_LINES);

		if ($this->debug) { 
			if (isset($this->settings['debug']['limitListRandom']) && !empty($this->settings['debug']['limitListRandom'])) {
				shuffle($this->file_list);
				$this->file_list = array_slice($this->file_list, 0, $this->settings['debug']['limitListRandom']);
			} else {
				$this->file_list = array_slice($this->file_list, $this->settings['debug']['limitListAt'], $this->settings['debug']['limitListTo']);
			}
		}
	}


	private function process_file_list() {
		if (empty($this->file_list)) {
			return 'File list empty';
		}

		foreach ($this->file_list as $file_path) {
			$file_path = $this->clean_string($file_path);
			$file_path_parts = explode($this->settings['string']['pathSeparator'],$file_path);

			$keywords = array();

			$parts_count = count($file_path_parts);
			for ($i=0;$i<$parts_count;$i++) {
				$file_path_part = $this->clean_part($file_path_parts[$i]);

				if (empty($file_path_part)) {
					continue;
				}

				$match = $this->known_tags->searchObject($file_path_part, true);

				//echo "Match type: ". $match[0] . "\n";

				switch ($match[0]) {
					case 'nomatch':
						$keywords[] = array(
									'part'    => $file_path_parts[$i],
									'keyword' => '',
									'multiple'=> array(),
									'type'    => '',
									'score'   => 0,
									'status'  => $match[0]
									);
						break;

					case 'multiple-all':
					case 'multiple-parts':
						//calculate score
						if ($match[0] == 'multiple-all') {
							$score = 100;
						} else {
							$score = 0;
						}

						$keywords[] = array(
									'part'     => $file_path_part,
									'keyword'  => '',
									'multiple' => $match[1],
									'type'     => '',
									'score'    => $score,
									'status'   => $match[0]
									);
						break;

					case 'exact':
					case 'like':
					default:
						$keywords[] = array(
									'part'    => $file_path_part,
									'keyword' => $match[1]['object'],
									'multiple'=> array(),
									'type'    => $this->known_tags->object_types[$match[1]['type']],
									'score'   => $match[1]['score'],
									'status'  => $match[0]
									);
						break;
				}

			}

			$this->metadata[] = array(
								'filepath' => $file_path,
								'keywords' => $keywords
								);

		}

		//print_r($this->metadata);

	}


	private function build_folder_structure() {
		if (empty($this->metadata)) {
			return false;
		}

		foreach ($this->metadata as $key => $meta) {
			$filename = basename(str_replace('\\','/',$meta['filepath']));
			$new_file_path = $this->compile_path_from_keywords($meta['keywords']) . $filename;
			$this->metadata[$key]['destination'] = $new_file_path;
		}

		//print_r($this->metadata);

	}


	private function compile_path_from_keywords($keywords) {
		if (empty($keywords)) {
			return false;
		}

		$path_raw_items = array();
		$keyword_types  = $this->known_tags->object_types;
		$path_structure = 'Location/Event/Orangutan/Medium-Type/Year/Month';

		$path = '';

		//path
		foreach ($keywords as $keyword) {
			switch ($keyword['status']) {
				case 'multiple-all':
				case 'multiple-parts':
					foreach ($keyword['multiple'] as $parts) {
						if (in_array($parts['type'],$keyword_types)) {
							if (isset($path_raw_items[$parts['type']])) {
								if (!in_array($parts['object'],$path_raw_items[$parts['type']])) {
									$path_raw_items[$parts['type']][] = $parts['object'];
								}
							} else {
								$path_raw_items[$parts['type']] = array($parts['object']);
							}
						}
					}
					break;
				case 'exact':
				case 'like':
					if (in_array($keyword['type'],$keyword_types)) {
						if (isset($path_raw_items[$keyword['type']]) && !in_array($keyword['keyword'],$path_raw_items[$keyword['type']])) {
							$path_raw_items[$keyword['type']][] = $keyword['keyword'];
						} else {
							$path_raw_items[$keyword['type']] = array($keyword['keyword']);
						}
					}
					break;
				case 'nomatch':
					if (isset($path_raw_items['nomatch'])) {
						$path_raw_items['nomatch'][] = $keyword['part'];
					} else {
						$path_raw_items['nomatch'] = array($keyword['part']);
					}
					break;
			}
		}

		$path_structure = explode('/',$path_structure);
		$used_parts = 0;
		$categorized = false;
		foreach ($path_structure as $part) {
			$placed = false;
			if (isset($path_raw_items[$part]) && !empty($path_raw_items[$part])) {
				if (count($path_raw_items[$part]) == 1) {
					$path .= $path_raw_items[$part][0].'/';
					$placed = true;
				} else {
					//TODO how to deal with multiple occurances
					//deal with "orangutan" and <name of orangutan> occuring
					if (count($path_raw_items[$part]) == 2 && in_array('Orangutan',$path_raw_items[$part])) {
						foreach ($path_raw_items[$part] as $tmp_part) {
							if ($tmp_part != 'Orangutan') {
								$path .= $tmp_part.'/';
								$placed = true;
							}
						}
					}
				}
			} else if ($part == 'Location' && isset($path_raw_items['Organisation'])) {
				if (count($path_raw_items['Organisation']) == 1) {
					$path .= $path_raw_items['Organisation'][0].'/';
					$placed = true;
				}
			}
			if ($placed) {
				$used_parts++;
				if (in_array($part, array('Month','Orangutan'))) {
					$categorized = true;
				}
			}
		}

		if ($used_parts < count($path_structure) && !$categorized) {
			$path .= 'uncategorized/';
		}

		return $path;
	}


	private function clean_string($string) {
		$string = trim(str_replace($this->settings['string']['ignorePath'], '', $string));
		return $string;
	}


	private function clean_part($string) {
		//TODO remove stopwords
		$string = trim($string);
		$string = str_ireplace($this->known_tags->stop_words, '', $string);
		$string = str_replace('_', ' ', $string);
		return $string;
	}


	private function debug_output() {
		$new_files = array();

		header('Content-type: text/html');
		echo '<table>'."\n";
		foreach ($this->metadata as $meta) {
			echo '<tr>'."\n";
			echo '	<td style="padding-right: 20px;">'.$meta['filepath'].'</td>'."\n";
			echo '	<td>'.$meta['destination'].'</td>'."\n";
			echo '</tr>'."\n";

			$new_files[] = $meta['destination'];
		}
		echo '</table>'."\n";

		echo '<pre>';	
		//tree structure
		$new_files_tree = $this->file_paths_to_tree($new_files);
		print_r($new_files_tree);
		echo '</pre>';

	}

	//http://stackoverflow.com/questions/12295458/make-treeview-in-php
	private function file_paths_to_tree($files) {
		$newFiles = array();
		foreach($files as $file){
		    $one = explode('/', $file);       // explode '/' to get each value
		    $last = array_pop($one);          // pop the last item because it is the file
		    $rev = array_reverse($one);       // we reverse the array in order to append the last to previous
		    $mixArray = array();              // create a temporary array

		    //print_r($rev);

		    foreach($rev as $num => $dir){    // loop in reversed array to extract directories

		    	if (is_numeric($dir)) {
		    		$dir = $dir . 'k';
		    	}

		        $mixArray[$dir] = $last;      // append the last item to the current dir, the first loop puts the file to the last directory
		        $last = $mixArray;            // overwrite last variable with current created array

		        //print_r($mixArray);

		        if($num < count($rev)-1){ 
		            unset($mixArray);         // if the current directory is not the last in reversed array we unset it because we will have duplicates
		        }
		    }

		    $newFiles = array_merge_recursive($newFiles, $mixArray); // merge recursive the result to main array
		    //$newFiles = $newFiles + $mixArray; // merge recursive the result to main array

		}

		return $newFiles;
	}


}


$options = array(
		"string" => array(
				"pathSeparator" => "\\",
				"ignorePath"    => "S:\\shared_files_internal_network_(save_here)\\_Internal_Files\\Multimedia\\Digital Asset Management\\Imported unsorted\\"
			),
		"debug" => array(
				"limitListAt" => 32600,
				"limitListTo" => 1,
				"limitListRandom" => 1000,
			)
	);


$SORT = new SortFiles($options);
$SORT->debug = true;

$file_list_path = '/home/yvesmeili/Sites/zivi/razuna-api/sandbox/filesorter/filelist.txt';
$SORT->set_file_list_path($file_list_path);

$SORT->start();

