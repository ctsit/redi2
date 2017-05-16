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
 * forms with locked records
 */
$table_csv = "";
$locked_forms = db_query("SELECT DISTINCT form_name FROM redcap_locking_data WHERE project_id = '$project_id' ORDER BY form_name ASC");
if ($locked_forms) {
	while ($locked_forms_array = db_fetch_assoc($locked_forms)) {
		$filled_locked = 0;
		$avail_locked = 0;
		$filled_complete = 0;
		$avail_complete = 0;
		$filled_incomplete = 0;
		$avail_incomplete = 0;
		$filled_all = 0;
		$avail_all = 0;
		$data_row = array();
		$fields = REDCap::getFieldNames($locked_forms_array['form_name']);
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
		$subject_lock_result = db_query("SELECT record, event_id FROM `redcap_locking_data` WHERE project_id = '$project_id' AND form_name = '{$locked_forms_array['form_name']}'");
		if ($subject_lock_result) {
			while ($subject_lock_row = db_fetch_assoc($subject_lock_result)) {
				$subject_lock_fields_result = db_query("SELECT value FROM `redcap_data` WHERE project_id = '$project_id' AND record = '{$subject_lock_row['record']}' AND event_id = '{$subject_lock_row['event_id']}' AND field_name IN ($fields_string)");
				if ($subject_lock_fields_result) {
					while ($subject_lock_fields_row = db_fetch_assoc($subject_lock_fields_result)) {
						$avail_locked++;
						if (isset($subject_lock_fields_row['value']) && $subject_lock_fields_row['value'] != '') {
							$filled_locked++;
						}
					}
					db_free_result($subject_lock_fields_result);
				}
			}
		}
		/**
		 * data in fields on all monitored forms
		 */
		$data = REDCap::getData('array', '', $fields);
		foreach ($data AS $subject) {
			foreach ($subject AS $event) {
				foreach ($event AS $field => $value) {
					/**
					 * All fields
					 */
					$avail_all++;
					if ($value != '') {
						$filled_all++;
					}
					/**
					 * complete / incomplete
					 */
					if ($event[$locked_forms_array['form_name'] . '_complete'] == '2') {
						$avail_complete++;
						if ($value != '') {
							$filled_complete++;
						}
					} else {
						$avail_incomplete++;
						if ($value != '') {
							$filled_incomplete++;
						}
					}
				}
			}
		}
		/**
		 * output row to table
		 */
		$data_row['Form Name'] = '"' . $form_name_row['form_menu_description'] . '"';
		$data_row['Filled Fields on Locked Forms'] = '"' . $filled_locked . '"';
		$data_row['Avail Fields on Locked Forms'] = '"' . $avail_locked . '"';
		$data_row['Filled Fields on Completed Forms'] = '"' . $filled_complete . '"';
		$data_row['Avail Fields on Completed Forms'] = '"' . $avail_complete . '"';
		$data_row['Filled Fields on Incomplete Forms'] = '"' . $filled_incomplete . '"';
		$data_row['Avail Fields on Incomplete Forms'] = '"' . $avail_incomplete . '"';
		$data_row['Filled Fields on All Forms'] = '"' . $filled_all . '"';
		$data_row['Avail Fields on All Forms'] = '"' . $avail_all . '"';
		$row_csv = implode(',', $data_row) . "\n";
		$table_csv .= $row_csv;
	}
}
$headers = implode(',', array_keys($data_row)) . "\n";
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, "LOCKED_FORMS", $debug);
} else {
	show_var($table_csv, 'TABLE', 'red');
}