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

		print_r($this->metadata);

	}


	private function build_folder_structure() {
		if (empty($this->metadata)) {
			return false;
		}

		foreach ($this->metadata as $meta) {
			$new_file_path = $this->compile_path_from_keywords($meta['keywords']);
			$meta['destination'] = $new_file_path;
		}

		print_r($this->metadata);

	}


	private function compile_path_from_keywords($keywords) {
		if (empty($keywords)) {
			return false;
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


}


$options = array(
		"string" => array(
				"pathSeparator" => "\\",
				"ignorePath"    => "S:\\shared_files_internal_network_(save_here)\\_Internal_Files\\Multimedia\\Digital Asset Management\\Imported unsorted\\"
			),
		"debug" => array(
				"limitListAt" => 13738,
				"limitListTo" => 1,
				"limitListRandom" => 10000,
			)
	);


$SORT = new SortFiles($options);
$SORT->debug = true;

$file_list_path = '/home/yvesmeili/Sites/zivi/razuna-api/sandbox/filesorter/filelist.txt';
$SORT->set_file_list_path($file_list_path);

$SORT->start();

