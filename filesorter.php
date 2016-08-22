<?php

/*
* PHP (virtual) File Sorter
* Author: Yves Meili
* Date: 2016-08-01
* 
* This script takes a text file with a list of file paths and tries to reorganize the paths into a common structure
* that will be reused time and time again. It matches the path parts to known objects in the sqlite database and 
* outputs the new recommended file path as well as potential keywords for a file.
*
*
* -- Generate input file --
*
* On Windows
*
* Simply type in a cmd prompt: 
*
*    dir /A:-D /S /b > filelist.txt
*
* On Linux (or mac)
*
*    find /path/to/folder/ -type f -exec file {} \\; | awk -F: '{if ($2 ~/image/) print $1}'
* 
* The linux variant is a bit more clever and selects only images
*
*
* -- Script Input --
*
* The following information should be passed to this script
*
*    --filelistpath=<path to file with the file list>
*    --outputfile=<path where to save the output>
*    --ignorepath=<string with a path part to ignore>    for example /home/user/somefolder/
*    --pathseperator=<input file path delimiter / or \>
*    --delimiter= not in use
*
*
* -- Script output --
*
* This script saves a JSON file to disc, which can be further processed by another script and/or used to actually move
* the files from the source to the destination.
*
* 
*
*/


header('Content-type: application/json');


define('LPS_BASEPATH', '/home/yvesmeili/Sites/zivi/local-photo-station/php-filesorter/');

include_once(LPS_BASEPATH.'classes/helper.php');
include_once(LPS_BASEPATH.'classes/sort-files.php');
include_once(LPS_BASEPATH.'classes/knowntags.php');
include_once(LPS_BASEPATH.'classes/db-connector.php'); 
include_once(LPS_BASEPATH.'classes/stemmer.php');

set_time_limit(720);


/*
* Input vars processing, configuring the file sorter
*/
$helper = new Helper();

$shortopts  = "";
$shortopts .= "h";

$longopts  = array(
    "filelistpath:",    // Required value
    "outputfile:",      // Required value
    "ignorepath::",     // Optional value
    "pathsep::",  // Optional value
);
$args = getopt($shortopts, $longopts);

//print_r($args);

if (isset($args['h'])) {
	echo '
	Filesorter

	Given an input file list the script will reorganize the folder structure and save the
	proposed structure to file.

	Usage: 
		--filelistpath 		Path to file with the file list
		--outputfile		Path where to save the output
		--ignorepath 		String with a path part to ignore>    for example /home/user/somefolder/
		--pathsep			Input file path delimiter / or \\
		--tagallfileparts	Add all filepath parts to nomatches


';
	die();
}


//Input file processing
$error = $helper->is_valid_path($args['filelistpath']);
if ($error !== true) {
	echo "Error: " . $error;
	die();
}

//output path processing
$error = $helper->is_valid_path($args['outputfile'], false);
if ($error !== true) {
	echo "Error: " . $error;
	die();
}

//ignorePath processing
$ignorePath = isset($args['ignorepath']) && !empty($args['ignorepath']) ? $args['ignorepath'] : '';
$pathsep    = isset($args['pathsep']) && !empty($args['pathsep']) ? $args['pathsep'] : '/';
$delimiter  = isset($args['delimiter']) && !empty($args['delimiter']) ? $args['delimiter'] : '';
$tagallfileparts  = isset($args['tagallfileparts']) ? true : false;

//echo 'using delimiter: ' . $pathsep;

$options = array(
		"string" => array(
				"pathsep" => $pathsep,
				"ignorePath"    => $ignorePath,
				'tagallfileparts' => $tagallfileparts
			),
		"output" => array(
				"file" => $args['outputfile'],
				'delimiter' => $delimiter,
				'type' => 'json'
			),
		"debug" => array(
				"limitListAt" => 0,
				"limitListTo" => 0,
				"limitListRandom" => 1000000,
			)
	);

$SORT = new SortFiles($options);
$SORT->debug = false;
$SORT->set_file_list_path($args['filelistpath']);
$SORT->start();

//"S:\\shared_files_internal_network_(save_here)\\_Internal_Files\\Multimedia\\Digital Asset Management\\Imported unsorted\\"