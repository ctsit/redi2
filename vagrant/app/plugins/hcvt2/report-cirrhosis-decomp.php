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
 * project metadata
 */
global $Proj;
/**
 * prettify field names
 */
$fields = array();
$pretty_field_name_result = db_query("SELECT field_name FROM redcap_metadata WHERE project_id = '$project_id' AND element_type != 'descriptive' AND form_name = 'fibrosis_staging' AND field_name NOT IN ('fibrosis_staging_complete')");
if ($pretty_field_name_result) {
	while ($field_name_row = db_fetch_assoc($pretty_field_name_result)) {
		$fields[] = $field_name_row['field_name'];
	}
}
$fields = array_merge($fields, array('cirr_suppfa_cirrstat'));
$addl_fields = array('meld_lborres', 'meld_lbblfl', 'plat_lbstresn', 'plat_lbblfl');
$addl_data = REDCap::getData('array', '', $addl_fields);
$pt_result = db_query("SELECT DISTINCT record FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'dcp_mhoccur' AND value = 'Y' ORDER BY abs(record) ASC");
if ($pt_result) {
	while ($pt_row = db_fetch_assoc($pt_result)) {
		$pts[] = $pt_row['record'];
	}
}
/**
 * WORKING DATA
 */
$table_csv = "";
$data = REDCap::getData('array', $pts, $fields);
foreach ($data AS $subject_id => $subject) {
	$data_row = array();
	$data_row['subjid'] = $subject_id;
	foreach ($subject AS $event_id => $event) {
		foreach ($event AS $key => $value) {
			$data_row[$Proj->metadata[$key]['element_label']] = quote_wrap($value);
		}
	}
	foreach ($addl_data[$subject_id] AS $addl_event) {
		if ($debug) {
			show_var($addl_event);
		}
		if ($addl_event['meld_lbblfl'] == 'Y') {
			$data_row[$Proj->metadata['meld_lborres']['element_label']] = $addl_event['meld_lborres'];
		}
		if ($addl_event['meld_lbblfl'] == 'Y') {
			$data_row[$Proj->metadata['plat_lbstresn']['element_label']] = $addl_event['plat_lbstresn'];
		}
	}
	if (!isset($data_row[$Proj->metadata['meld_lborres']['element_label']])) {
		$data_row[$Proj->metadata['meld_lborres']['element_label']] = 'NA';
	}
	if (!isset($data_row[$Proj->metadata['plat_lbstresn']['element_label']])) {
		$data_row[$Proj->metadata['plat_lbstresn']['element_label']] = 'NA';
	}
	$row_csv = implode(',', $data_row) . "\n";
	$table_csv .= $row_csv;
}
$headers = implode(',', array_keys($data_row)) . "\n";
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, "CIRRHOSIS_DECOMP_FIBROSIS", $debug);
} else {
	show_var($headers);
	show_var($table_csv);
}