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
 * query redcap_locking_data for relevant information about which forms are locked.
 */
$data_row = array();
$table_csv = "";
$locking_result = db_query("SELECT ld.record, ld.event_id, meta.form_menu_description as form_name, ld.username, ld.timestamp FROM
(SELECT * FROM `redcap_locking_data`) ld
LEFT JOIN
(SELECT project_id, form_name, form_menu_description FROM redcap_metadata WHERE form_menu_description IS NOT NULL) meta
ON ld.project_id = meta.project_id AND ld.form_name = meta.form_name
WHERE ld.project_id = '$project_id'
ORDER BY abs(ld.record) ASC, ld.event_id ASC, ld.timestamp ASC");
if ($locking_result) {
	while ($locking_row = db_fetch_assoc($locking_result)) {
		foreach ($locking_row AS $export_key => $export_value) {
			$data_row[$export_key] = '"' . $export_value . '"';
		}
		$row_csv = implode(',', $data_row) . "\n";
		$table_csv .= $row_csv;
	}
	db_free_result($locking_result);
}
$headers = implode(',', array_keys($data_row)) . "\n";
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, "LOCKING_STATUS", $debug);
}