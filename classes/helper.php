<?php

class Helper {
	
	
	public function __construct() {


	}


	public function is_valid_path($path, $check_file_readable = true) {
		if (!isset($path)) {
			return 'No "filelistpath" set in request';
		}

		if (empty($path)) {
			return '"filelistpath" variable contains an empty string';
		}

		if (!is_readable($path) && $check_file_readable) {
			return 'Provided filelist is not readable';
		}

		return true;
	}


}