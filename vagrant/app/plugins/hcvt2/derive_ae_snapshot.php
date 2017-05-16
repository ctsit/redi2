<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = true;
$subjects = '500'; // '' = ALL
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
}
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
Kint::enabled($debug);
/**
 * project metadata
 */
global $Proj;
$baseline_event_id = $Proj->firstEventId;
$plugin_title = "Derive stuff";
$today = date('Y-m-d');
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
$fields = array('dm_usubjid', 'ae_aestdtc', 'ae_aedecod', 'ae_aeser');
$data = REDCap::getData('array', $subjects, $fields);
d($data);


foreach ($data AS $subject_id => $subject) {
	/**
	 * SUBJECT-LEVEL vars
	 */
	$history_array = array();
	$dm_usubjid = $subject[$baseline_event_id]['dm_usubjid'];
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		foreach ($event AS $key => $value) {
			if ($key != 'dm_usubjid') {
				$history_array[$key][] = Form::getDataHistoryLog($subject_id, $event_id, $key);
				/**
				 * do stuff
				 */
			}
		}
	}
	d($history_array);
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}