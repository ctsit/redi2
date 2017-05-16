<?php
/**
 * Created by HCV-TARGET for HCV-TARGET studies v2.0 and above.
 * User: kbergqui
 * Date: 3-5-14
 * Purpose: Derive baseline dates and flags
 */
/**
 * TESTING
 */
$debug = true;
$test_id = '';
/**
 * includes
 * adjust $base_path dirname depth to suit location
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict access to one or more pids
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * project metadata
 */
$project = new Project();
/**
 * initialize variables
 */
$initial_event = $project->firstEventId;
/**
 * plugin title
 */
echo "<h3>Derive Baseline dates and flags at both form and lab levels</h3>";
/**
 * get Lab dates for each subject
 */
$fields_array = array("cbc", "chem", "inr", "hcv");
foreach ($fields_array AS $fragment) {
	$date_field = array($fragment . "_lbdtc");
	$form_fields = array();
	$lab_fields = array();
	$lab_fields_query = "SELECT DISTINCT LEFT(field_name, INSTR(field_name, '_')-1) AS prefix FROM redcap_metadata WHERE field_name NOT LIKE '%complete' AND project_id = '$project_id' AND form_name LIKE '$fragment%'";
	$lab_fields_result = db_query($lab_fields_query);
	if ($lab_fields_result) {
		while ($lab_prefix = db_fetch_assoc($lab_fields_result)) {
			$form_fields = array();
			$lab_fields = array();
			if ($lab_prefix['prefix'] == $fragment) {
				/**
				 * FORM-level baselines
				 */
				$form_fields[] = $lab_prefix['prefix'] . "_supplb_lbdtbl";
				$form_fields[] = $lab_prefix['prefix'] . "_lbblfl";
				$form_fields = array_merge($date_field, $form_fields);
				/**
				 * get/set baseline date and flag for the form-level fields
				 */
				if (!$debug) {
					$data = REDCap::getData('array', '', $form_fields);
				} else {
					$data = REDCap::getData('array', $test_id, $form_fields);
				}
				foreach ($data AS $subjid => $subject) {
					if ($debug) {
						//show_var($subjid, 'FORM SUBJID', 'blue');
					}
					/**
					 * get refstdtc for this subject
					 */
					$tx_start_fields = array("dm_rfstdtc");
					$tx_start_data = REDCap::getData('array', $subjid, $tx_start_fields);
					$rfstdtc = $tx_start_data[$subjid][$initial_event]['dm_rfstdtc'];
					/**
					 * if we have candidate events in $subject and treatment has started
					 */
					if (count($subject) > 0 && (isset($rfstdtc) || $rfstdtc != '')) {
						/**
						 * fetch baseline date
						 */
						$baseline_date = '';
						$this_data = get_baseline_date($subject, $fragment, $rfstdtc);
						/**
						 * if the nearest date is prior or equal to rfstdtc, it's a baseline date
						 */
						if ($debug) {
							//show_var($subject, 'FORM SUBJECT DATA', 'blue');
							//show_var($this_data, 'FORM BASELINE DATA', 'red');
							//show_var($rfstdtc, 'TX start', 'yellow');
						}
						if ($this_data[$fragment . '_lbdtc'] != '' && $this_data[$fragment . '_lbdtc'] <= $rfstdtc) {
							$baseline_date = $this_data[$fragment . '_lbdtc'];
						}
						if ($debug) {
							//show_var($baseline_date, 'FORM BASELINE DATE', 'red');
						}
						/**
						 * Baseline date belongs in Baseline event
						 */
						update_field_compare($subjid, $project_id, $initial_event, $baseline_date, get_single_field($subjid, $project_id, $initial_event, $fragment . "_supplb_lbdtbl", null), $fragment . "_supplb_lbdtbl", $debug);
						/**
						 * Now reset all other flags that have been set previously
						 */
						$flag_reset_data = REDCap::getData('array', $subjid, $fragment . "_lbblfl");
						$this_baseline_flag = get_single_field($subjid, $project_id, $this_data['event_id'], $fragment . "_lbblfl", null);
						foreach ($flag_reset_data AS $reset) {
							if ($debug) {
								//show_var($reset, 'FORM RESET FLAGS', 'red');
							}
							foreach ($reset AS $reset_event_id => $reset_event) {
								foreach ($reset_event as $reset_field => $reset_val) {
									if ($reset_event_id != $this_data['event_id']) {
										update_field_compare($subjid, $project_id, $reset_event_id, '', $reset_val, $fragment . "_lbblfl", $debug);
									}
								}
							}
						}
						/**
						 * Baseline flag belongs in the event where the date occurs
						 */
						if ($baseline_date != '') {
							update_field_compare($subjid, $project_id, $this_data['event_id'], 'Y', get_single_field($subjid, $project_id, $this_data['event_id'], $fragment . "_lbblfl", null), $fragment . "_lbblfl", $debug);
						}
					}
				}
			} else {
//				/**
//				 * FIELD-level baselines
//				 */
//				$lab_fields[] = $lab_prefix['prefix'] . "_supplb_lbdtbl";
//				$lab_fields[] = $lab_prefix['prefix'] . "_lbblfl";
//				$lab_fields[] = $lab_prefix['prefix'] . "_lborres";
//				$lab_fields = array_merge($date_field, $lab_fields);
//				if ($debug) {
//					/*show_var($lab_fields);*/
//				}
//				/**
//				 * find baselines for each lab test
//				 */
//				if (!$debug) {
//					$data = REDCap::getData('array', '', $lab_fields);
//				} else {
//					$data = REDCap::getData('array', $test_id, $lab_fields);
//				}
//				foreach ($data AS $subjid => $subject) {
//					$lab_subject = array();
//					$orphan_subject = array();
//					if ($debug) {
//						show_var($subjid, 'FIELD SUBJID', 'blue');
//					}
//					/**
//					 * get refstdtc for this subject
//					 */
//					$tx_start_fields = array("dm_rfstdtc");
//					$tx_start_data = REDCap::getData('array', $subjid, $tx_start_fields);
//					$rfstdtc = $tx_start_data[$subjid][$initial_event]['dm_rfstdtc'];
//					if ($debug) {
//						//show_var($subject, 'BEFORE', 'red');
//					}
//					/**
//					 * if treatment has started
//					 */
//					if (isset($rfstdtc) || $rfstdtc != '') {
//						/**
//						 * iterate the lab events for this prefix
//						 */
//						foreach ($subject AS $lab_event_id => $lab_event) {
//							/**
//							 * if we have a value for the orres, then add to candidate events array
//							 */
//							if ($lab_event[$lab_prefix['prefix'] . "_lborres"] != '') {
//								$lab_subject[$lab_event_id] = $subject[$lab_event_id];
//							}
//						}
//						if ($debug) {
//							//show_var($lab_subject, "lab_subject {$lab_prefix['prefix']}", 'green');
//						}
//						/**
//						 * if we have candidate events
//						 */
//						if (count($lab_subject) > 0) {
//							/**
//							 * fetch the baseline date and set baseline / flag pair
//							 */
//							$baseline_date = '';
//							$this_data = get_baseline_date($lab_subject, $fragment, $rfstdtc);
//							/**
//							 * if the nearest date is prior or equal to rfstdtc, it's a baseline date
//							 */
//							if ($debug) {
//								show_var($lab_subject, 'FIELD SUBJECT DATA', 'blue');
//								show_var($this_data, 'FIELD BASELINE DATA', 'red');
//								show_var($rfstdtc, 'TX start', 'red');
//							}
//							if ($this_data[$fragment . '_lbdtc'] != '' && $this_data[$fragment . '_lbdtc'] <= $rfstdtc) {
//								$baseline_date = $this_data[$fragment . '_lbdtc'];
//								/**
//								 * Baseline date belongs in Baseline event
//								 */
//								update_field_compare($subjid, $project_id, $initial_event, $baseline_date, get_single_field($subjid, $project_id, $initial_event, $lab_prefix['prefix'] . "_supplb_lbdtbl", null), $lab_prefix['prefix'] . "_supplb_lbdtbl", $debug);
//								if ($debug) {
//									show_var($baseline_date, 'FIELD BASELINE DATE', 'red');
//								}
//								/**
//								 * Now reset all other flags that have changed
//								 */
//								$flag_reset_data = REDCap::getData('array', $subjid, $lab_prefix['prefix'] . "_lbblfl");
//								$this_baseline_flag = get_single_field($subjid, $project_id, $this_data['event_id'], $lab_prefix['prefix'] . "_lbblfl", null);
//								foreach ($flag_reset_data AS $reset) {
//									if ($debug) {
//										show_var($reset, 'FIELD RESET FLAGS', 'red');
//									}
//									foreach ($reset AS $reset_event_id => $reset_event) {
//										foreach ($reset_event as $reset_field => $reset_val) {
//											if ($reset_event_id != $this_data['event_id']) {
//												update_field_compare($subjid, $project_id, $reset_event_id, '', $reset_val, $lab_prefix['prefix'] . "_lbblfl", $debug);
//											}
//										}
//									}
//								}
//								/**
//								 * Baseline flag belongs in the event where the date occurs
//								 */
//								if ($baseline_date != '') {
//									update_field_compare($subjid, $project_id, $this_data['event_id'], 'Y', $this_baseline_flag, $lab_prefix['prefix'] . "_lbblfl", $debug);
//								}
//							}
//						}
//					}
//				}
			}
		}
		db_free_result($lab_fields_result);
	}
}