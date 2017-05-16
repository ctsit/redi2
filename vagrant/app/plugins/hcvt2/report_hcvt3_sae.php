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
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * INIT VARS
 */
global $Proj;
Kint::enabled($debug);
$table_csv = "";
$filter_date = '2014-10-10';
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$fields = array('dm_subjid', 'dm_usubjid', 'consent_dssstdtc', 'ae_aeser');
$data = REDCap::getData('array', '', $fields);
foreach ($data AS $subject_id => $subject) {
	d($subject);
	/**
	 * SUBJECT-LEVEL vars
	 */
	$data_row = array();
	/**
	 * MAIN EVENT LOOP
	 */
	/*if ($subject[$Proj->firstEventId]['consent_dssstdtc'] != '' && $subject[$Proj->firstEventId]['consent_dssstdtc'] >= $filter_date) {
		foreach ($subject AS $event_id => $event) {
			if ($event['ae_aeser'] == 'Y') {
				foreach ($event AS $key => $value) {
					$data_row[$Proj->metadata[$key]['element_label'] . " [$key]"] = $value;
				}
			}
		}
	}*/
	/*$row_csv = implode(',', $data_row) . "\n";
	$table_csv .= $row_csv;*/
}
$headers = implode(',', array_keys($data_row)) . "\n";
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, $export_filename, $debug);
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}