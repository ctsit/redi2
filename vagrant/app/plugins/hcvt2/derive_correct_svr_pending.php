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
$subjects = ''; // '' = ALL
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
global $Proj;
$first_event_id = $Proj->firstEventId;
$plugin_title = "Reset SVR DATA PENDING flag when hidden by branching logic";
$my_branching_logic = new BranchingLogic();
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
$fields = array('dis_suppds_funcmprsn', 'hcv_suppfa_svr12dt', 'hcv_suppfa_svr24dt', 'hcv_suppfa_fuelgbl');
$data = REDCap::getData('array', $subjects, $fields, $first_event_id);
foreach ($data AS $subject_id => $subject) {
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		$field_is_hidden = $my_branching_logic->allFieldsHidden($subject_id, $event_id, array('dis_suppds_funcmprsn'));
		if ($field_is_hidden /*&& $event['dis_suppds_funcmprsn'] == 'SVR_DATA_PENDING' && ($event['hcv_suppfa_svr12dt'] != '' || $event['hcv_suppfa_fuelgbl'] == 'N')*/) {
			update_field_compare($subject_id, $project_id, $event_id, '', $event['dis_suppds_funcmprsn'], 'dis_suppds_funcmprsn', $debug, $plugin_title);
		}
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}