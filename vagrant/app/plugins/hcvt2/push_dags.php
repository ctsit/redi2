<?php
//define("NOAUTH", true);
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
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict use of this plugin to the appropriate project
 */
$allowed_pid = '26';
REDCap::allowProjects($allowed_pid);
Kint::enabled($debug);
global $Proj;
$baseline_event_id = $Proj->firstEventId;
/**
 * let's bring this into the 21st century
 */
$fields = array('dm_subjid', 'dm_usubjid');
$data = REDCap::getData('array', $subjects, $fields, $baseline_event_id);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		if ($event['dm_usubjid'] != '') {
			/**
			 * find which DAG this subject belongs to
			 */
			$site_prefix = substr($event['dm_usubjid'], 0, 3) . '%';
			$dag_query = "SELECT group_id, group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_name LIKE '$site_prefix'";
			$dag_result = db_query($dag_query);
			if ($dag_result) {
				$dag = db_fetch_assoc($dag_result);
				if (isset($dag['group_id'])) {
					/**
					 * For each event in project for this subject, determine if this subject_id has been added to its appropriate DAG. If it hasn't, make it so.
					 * First, we need a list of events for which this subject has data
					 */
					$subject_events_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '{$event['dm_subjid']}' AND field_name LIKE '%_complete'";
					$subject_events_result = db_query($subject_events_query);
					if ($subject_events_result) {
						while ($subject_events_row = db_fetch_assoc($subject_events_result)) {
							if (isset($subject_events_row['event_id'])) {
								$_GET['event_id'] = $subject_events_row['event_id']; // for logging
								/**
								 * The subject has data in this event_id
								 * does the subject have corresponding DAG assignment?
								 */
								$has_event_data_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '{$event['dm_subjid']}' AND event_id = '" . $subject_events_row['event_id'] . "' AND field_name = '__GROUPID__'";
								$has_event_data_result = db_query($has_event_data_query);
								if ($has_event_data_result) {
									$has_event_data = db_fetch_assoc($has_event_data_result);
									if (!isset($has_event_data['event_id'])) {
										/**
										 * Subject does not have a matching DAG assignment for this data
										 * construct proper matching __GROUPID__ record and insert
										 */
										$insert_dag_query = "INSERT INTO redcap_data SET record = '{$event['dm_subjid']}', event_id = '" . $subject_events_row['event_id'] . "', value = '" . $dag['group_id'] . "', project_id = '$project_id', field_name = '__GROUPID__'";
										if (!$debug) {
											if (db_query($insert_dag_query)) {
												target_log_event($insert_dag_query, 'redcap_data', 'insert', $event['dm_subjid'], $dag['group_name'], 'Assign record to Data Access Group (' . $dag['group_name'] . ')');
												show_var($insert_dag_query, '', 'green');
											} else {
												error_log("SQL INSERT FAILED: " . db_error() . "\n");
												echo db_error() . "\n";
											}
										} else {
											show_var($insert_dag_query);
										}
									}
									db_free_result($has_event_data_result);
								}
							}
						}
						db_free_result($subject_events_result);
					}
				}
				db_free_result($dag_result);
			}
		}
	}
}