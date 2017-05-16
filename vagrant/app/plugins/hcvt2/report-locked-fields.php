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
$locked_forms = db_query("SELECT DISTINCT form_name FROM redcap_locking_data WHERE project_id = '$project_id'");
if ($locked_forms) {
	while ($locked_forms_array = db_fetch_assoc($locked_forms)) {
		$field_count = 0;
		$available_field_count = 0;
		$total_field_count = 0;
		$data_row = array();
		/**
		 * pretty form names
		 */
		$pretty_form_names = array();
		$pretty_form_name_result = db_query("SELECT form_menu_description FROM redcap_metadata WHERE project_id = '$project_id' AND form_name = '{$locked_forms_array['form_name']}' AND form_menu_description IS NOT NULL");
		if ($pretty_form_name_result) {
			$form_name_row = db_fetch_assoc($pretty_form_name_result);
		}
		/**
		 * subjects with locked forms
		 */
		$subject_lock_result = db_query("SELECT record, event_id FROM `redcap_locking_data` WHERE project_id = '$project_id' AND form_name = '{$locked_forms_array['form_name']}'");
		if ($subject_lock_result) {
			while ($subject_lock_row = db_fetch_assoc($subject_lock_result)) {
				$fields = REDCap::getFieldNames($locked_forms_array['form_name']);
				$data = REDCap::getData('array', $subject_lock_row['record'], $fields, $subject_lock_row['event_id']);
				foreach ($data AS $subject) {
					foreach ($subject AS $event) {
						foreach ($event AS $field => $value) {
							$available_field_count++;
							if ($value != '') {
								$field_count++;
							}
						}
					}
				}
			}
		}
		/**
		 * data in all fields, monitored or not
		 */
		$fields = REDCap::getFieldNames($locked_forms_array['form_name']);
		$fields_string = "'" . implode("', '", $fields) . "'";
		if ($debug) {
			show_var($fields_string);
		}
		$subject_all_result = db_query("SELECT * FROM `redcap_data` WHERE project_id = '$project_id' AND field_name IN ($fields_string)");
		if ($subject_all_result) {
			while ($subject_all_row = db_fetch_assoc($subject_all_result)) {
				if ($subject_all_row['value'] != '') {
					$total_field_count++;
				}
			}
		}
		$data_row['Form Name'] = '"' . $form_name_row['form_menu_description'] . '"';
		$data_row['Monitored Fields'] = '"' . $field_count . '"';
		$data_row['Total Fields'] = '"' . $total_field_count . '"';
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