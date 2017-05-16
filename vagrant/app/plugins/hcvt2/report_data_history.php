<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * debug
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
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
global $Proj;
Kint::enabled($debug);
/**
 * INIT VARS
 */
$table_csv = "";
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$fields = array('dm_usubjid');
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
	d($subject_id);
	/**
	 * SUBJECT-LEVEL vars
	 */
	$data_row = array();
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		d(Form::getDataHistoryLog($subject_id, $event_id, 'cm_suppcm_atcname'));
		foreach ($event AS $key => $value) {
			$data_row[$Proj->metadata[$key]['element_label']] = $value;
		}
	}
	$row_csv = implode(',', $data_row) . "\n";
	$table_csv .= $row_csv;
}
$headers = implode(',', array_keys($data_row)) . "\n";
d($headers);
d($table_csv);
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, $export_filename, $debug);
} else {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}