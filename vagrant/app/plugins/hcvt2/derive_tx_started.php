<?php
/**
 * Created by HCV-TARGET for HCV-TARGET studies v2.0 and above.
 * User: kbergqui
 * Date: 2014-07-01
 * Purpose: Derive 'Y' values for field trt_suppcm_txstat
 */
/**
 * TESTING
 */
$debug = false;
$subjects = ''; // '' = ALL
/**
 * includes
 * adjust $base_path dirname depth to suit location
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict access to one or more pids
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * project metadata
 */
global $Proj;
/**
 * initialize variables
 */
$plugin_title = "Derive Treatment Started (trt_suppcm_txstat)";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN LOOP
 */
$fields = array("dm_rfstdtc", "trt_suppcm_txstat");
$data = REDCap::getData('array', $subjects, $fields, $Proj->firstEventId);
if ($debug && $subjects != '') {
	show_var($data);
}
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		$started = $event['dm_rfstdtc'] == '' ? 'N' : 'Y';
		update_field_compare($subject_id, $project_id, $event_id, $started, $event['trt_suppcm_txstat'], 'trt_suppcm_txstat', $debug);
	}
}