<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$getdebug = $_GET['debug'] ? $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
/**
 * timing
 */
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($enable_kint);
/**
 * project metadata
 */
global $Proj;
$baseline_event_id = $Proj->firstEventId;
$plugin_title = "Derive stuff";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$fields = array('dm_usubjid');
$data = REDCap::getData('array', $subjects, $fields, $baseline_event_id);
d($data);

foreach ($data AS $subject_id => $subject) {
	/**
	 * SUBJECT-LEVEL vars
	 */
	$var = array();
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		foreach ($event AS $key => $value) {
			/**
			 * do stuff
			 */
		}
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}