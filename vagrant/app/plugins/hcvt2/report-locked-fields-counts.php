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
$my_branching_logic = new BranchingLogic();
/**
 * forms with locked records
 */
$table_csv = "";
$final_row = array();
$locked_forms_total = 0;
$data_fields_total = 0;
$data_queries_total = 0;
$complete_forms_total = 0;
$locked_forms = db_query("SELECT DISTINCT form_name FROM redcap_locking_data WHERE project_id = '$project_id' ORDER BY form_name ASC");
if ($locked_forms) {
	while ($locked_forms_array = db_fetch_assoc($locked_forms)) {
		$data_row = array();
		$locked_event_ids_array = array();
		$locked_records_array = array();
		$fields = REDCap::getFieldNames($locked_forms_array['form_name']);
		$form_complete_field = $locked_forms_array['form_name'] . '_complete';
		unset($fields[array_search($form_complete_field, $fields)]);
		$fields_string = "'" . implode("', '", $fields) . "'";
		/**
		 * pretty form names
		 */
		$pretty_form_names = array();
		$pretty_form_name_result = db_query("SELECT form_menu_description FROM redcap_metadata WHERE project_id = '$project_id' AND form_name = '{$locked_forms_array['form_name']}' AND form_menu_description IS NOT NULL");
		if ($pretty_form_name_result) {
			$form_name_row = db_fetch_assoc($pretty_form_name_result);
			db_free_result($pretty_form_name_result);
		}
		/**
		 * subjects with locked forms
		 */
		$subject_lock_result = db_query("SELECT count(1) FROM `redcap_locking_data` WHERE project_id = '$project_id' AND form_name = '{$locked_forms_array['form_name']}'");
		if ($subject_lock_result) {
			$locked_forms_count = db_result($subject_lock_result, 0);
			$locked_forms_total = $locked_forms_total + $locked_forms_count;
		}
		/**
		 * fields on those forms
		 */
		$subjects_locked_forms_result = db_query("SELECT record, event_id FROM `redcap_locking_data` WHERE project_id = '$project_id' AND form_name = '{$locked_forms_array['form_name']}'");
		if ($subjects_locked_forms_result) {
			while ($subjects_locked_forms = db_fetch_assoc($subjects_locked_forms_result)) {
				$locked_event_ids_array[] = $subjects_locked_forms['event_id'];
				$locked_records_array[] = $subjects_locked_forms['record'];
			}
			$locked_event_ids = "'" . implode("', '", $locked_event_ids_array) . "'";
			$locked_records = "'" . implode("', '", $locked_records_array) . "'";
			$forms_fields_result = db_query("SELECT count(1) FROM redcap_data WHERE project_id = '$project_id' AND record IN ($locked_records) AND event_id IN ($locked_event_ids) AND field_name IN ($fields_string)");
			if ($forms_fields_result) {
				$data_fields_count = db_result($forms_fields_result, 0);
				$data_fields_total = $data_fields_total + $data_fields_count;
			}
			$forms_queries_result = db_query("SELECT count(1) FROM redcap_data_quality_status WHERE project_id = '$project_id' AND record IN ($locked_records) AND event_id IN ($locked_event_ids) AND field_name IN ($fields_string)");
			if ($forms_queries_result) {
				$data_queries_count = db_result($forms_queries_result, 0);
				$data_queries_total = $data_queries_total + $data_queries_count;
			}
		}
		/**
		 * subjects with complete forms
		 */
		$subject_complete_result = db_query("SELECT count(1) FROM redcap_data WHERE project_id = '$project_id' AND field_name = '$form_complete_field' AND value = '2'");
		if ($subject_complete_result) {
			$complete_forms_count = db_result($subject_complete_result, 0);
			$complete_forms_total = $complete_forms_total + $complete_forms_count;
		}
		/**
		 * output row to table
		 */
		$data_row['Form Name'] = '"' . $form_name_row['form_menu_description'] . '"';
		$data_row['Forms monitored'] = '"' . $locked_forms_count . '"';
		$data_row['Fields monitored'] = '"' . $data_fields_count . '"';
		$data_row['Queries issued'] = '"' . $data_queries_count . '"';
		$data_row['Forms Completed'] = '"' . $complete_forms_count . '"';
		$data_row['Percent monitored'] = '"' . round($locked_forms_count / $complete_forms_count * 100, 2) . '"';
		$row_csv = implode(',', $data_row) . "\n";
		$table_csv .= $row_csv;
	}
	/**
	 * final row
	 */
	$final_row['Form Name'] = 'Totals';
	$final_row['Forms monitored'] = '"' . $locked_forms_total . '"';
	$final_row['Fields monitored'] = '"' . $data_fields_total . '"';
	$final_row['Queries issued'] = '"' . $data_queries_total . '"';
	$final_row['Forms Completed'] = '"' . $complete_forms_total . '"';
	$final_row['Percent monitored'] = '"' . round($locked_forms_total / $complete_forms_total * 100, 2) . '"';
	$table_csv .= implode(',', $final_row) . "\n";
}
$headers = implode(',', array_keys($data_row)) . "\n";
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, "SOURCE_MONITORING_REPORT", $debug);
} else {
	show_var($table_csv, 'TABLE', 'red');
}