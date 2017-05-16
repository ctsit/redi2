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
$subjects = '';
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
global $Proj;
Kint::enabled($debug);
/**
 * INIT VARS
 */
$export_filename = 'SDV_COMPLETION_REPORT';
$plugin_title = fix_case($export_filename) . ' ' . "<span class='yellow'>DEVELOPMENT</span>";
$first_event = $Proj->firstEventId;
$table_csv = "";
$subject_event_locks = array();
$fields = array();
$excluded_forms = array('coding', 'derived_values', 'derived_values_baseline', 'post_monitoring_source_upload', 'protocol_deviations', 'cbc_standard', 'chemistry_standard', 'hcv_rna_standard');
$all_forms = array_diff(array_keys($Proj->forms), $excluded_forms);
foreach ($all_forms AS $form) {
	$fields[] = $form . '_complete';
}
$events_forms = $Proj->eventsForms;
/**
 * MAIN
 */
echo "<h3>$plugin_title</h3>";
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$fields = array_merge(array('dm_usubjid'), $fields);
$data = REDCap::getData('array', $subjects, $fields);
$form_event_lock_result = db_query("SELECT * FROM redcap_locking_data WHERE project_id = '$project_id'");
if ($form_event_lock_result) {
	while ($form_event_lock_row = db_fetch_assoc($form_event_lock_result)) {
		$subject_event_locks[$form_event_lock_row['record']][$form_event_lock_row['event_id']][] = $form_event_lock_row['form_name'];
	}
	db_free_result($form_event_lock_result);
}

foreach ($data AS $subject_id => $subject) {
	if ($subjects != '') {
		d($subject_id);
	}
	/**
	 * SUBJECT-LEVEL vars
	 */
	$form_scores = array();
	$data_row = array();
	$form_lock_counts = array();
	$form_complete_counts = array();
	/**
	 * MAIN EVENT LOOP
	 */
	$data_row['Record'] = $subject_id; // get record
	$data_row[$Proj->metadata['dm_usubjid']['element_label']] = $subject[$first_event]['dm_usubjid']; // get Subject ID
	foreach ($subject AS $event_id => $event) {
		/**
		 * aggregate number of each form that is locked vs. all forms by event
		 */
		foreach ($events_forms[$event_id] AS $form) {
			if ($event[$form . '_complete'] == '2') {
				$form_complete_counts[$form] = $form_complete_counts[$form] + 1;
				if (array_search($form, $subject_event_locks[$subject_id][$event_id]) !== false) {
					$form_lock_counts[$form] = $form_lock_counts[$form] + 1;
				}
			}
		}
	}
	d($form_complete_counts);
	d($form_lock_counts);
	foreach ($all_forms AS $this_form) {
		if (is_numeric($form_complete_counts[$this_form])) {
			$this_subject_form_score = round(($form_lock_counts[$this_form] / $form_complete_counts[$this_form]) * 100);
			$form_scores[$this_form][] = $this_subject_form_score;
		} else {
			$this_subject_form_score = '--';
		}
		$data_row[$Proj->forms[$this_form]['menu']] = $this_subject_form_score;
	}
	$row_csv = implode(',', $data_row) . "\n";
	$table_csv .= $row_csv;
}
/**
 * final row
 */
$final_row['Record'] = 'Completed forms monitored (%)'; // get record
$final_row['Subject ID'] = '';
foreach ($all_forms AS $this_form) {
	$final_row[$Proj->forms[$this_form]['menu']] = round(array_sum($form_scores[$this_form]) / count($form_scores[$this_form]));
}
$table_csv .= implode(',', $final_row) . "\n";
/**
 * build download
 */
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