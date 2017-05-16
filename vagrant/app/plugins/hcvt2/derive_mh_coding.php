<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = false;
$subjects = ''; // '' = ALL
$recode_llt = false;
$recode_pt = true;
$recode_soc = true;
$mh_prefixes = array('othpsy', 'othca');
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
/**
 * project metadata
 */
$project = new Project();
$baseline_event_id = $project->firstEventId;
$plugin_title = "Derive MHMODIFY, MHDECOD and MHBODSYS for all OTH_MHTERM";
/**
 * plugin
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$fields = array();
foreach ($mh_prefixes AS $prefix) {
	$fields[] = $prefix . "_oth_mhterm";
	$fields[] = $prefix . "_mhmodify";
	$fields[] = $prefix . "_mhdecod";
	$fields[] = $prefix . "_mhbodsys";
}
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
    foreach ($subject AS $event_id => $event) {
	    foreach ($mh_prefixes AS $prefix) {
		    code_llt($project_id, $subject_id, $event_id, fix_case($event[$prefix . '_oth_mhterm']), '', $event[$prefix . '_mhmodify'], $prefix . '_mhmodify', $debug, $recode_llt);
		    code_pt($project_id, $subject_id, $event_id, get_single_field($subject_id, $project_id, $event_id, $prefix . "_mhmodify", ''), $event[$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
		    code_bodsys($project_id, $subject_id, $event_id, get_single_field($subject_id, $project_id, $event_id, $prefix . "_mhdecod", ''), $event[$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
	    }
    }
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}