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
$plugin_title = "Derive INDCMODF, INDCOD and INDCSYS for all CMINDC";
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
$fields = array('cm_cmindc', 'cm_oth_cmindc', 'cm_suppcm_indcmodf', 'cm_suppcm_indcod', 'cm_suppcm_indcsys');
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
    foreach ($subject AS $event_id => $event) {
	    if ($event['cm_cmindc'] != '') {
		    code_llt($project_id, $subject_id, $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
		    code_pt($project_id, $subject_id, $event_id, get_single_field($subject_id, $project_id, $event_id, "cm_suppcm_indcmodf", ''), $event["cm_suppcm_indcod"], "cm_suppcm_indcod", $debug, $recode_pt);
		    code_bodsys($project_id, $subject_id, $event_id, get_single_field($subject_id, $project_id, $event_id, "cm_suppcm_indcod", ''), $event["cm_suppcm_indcsys"], "cm_suppcm_indcsys", $debug, $recode_soc);
	    }
    }
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}