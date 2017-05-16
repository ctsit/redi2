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
 * adjust $base_path dirname depth to suit location
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict access to one or more pids
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * project metadata
 */
global $Proj;
Kint::enabled($enable_kint);
/**
 * initialize variables
 */
$initial_event = $Proj->firstEventId;
/**
 * plugin title
 */
echo "<h3>Derive Baseline dates and flags at both form and lab levels</h3>";
/**
 * get Lab dates for each subject
 */
$fields_array = array(
	"cbc" => 'cbc',
	"chem" => 'chemistry',
	"inr" => 'inr',
	"hcv" => 'hcv_rna_results'
);
$lab_fields = array();
$control_fields = array();
foreach ($fields_array AS $fragment => $form) {
	$import_string = strpos($fragment, '_im') !== false ? '_im' : '';
	$timer['start_fields_' . $fragment] = microtime(true);
	$lab_fields[] = $fragment . "_lbdtc";
	$lab_fields[] = $fragment . "_im_lbdtc";
	$lab_fields_query = "SELECT DISTINCT LEFT(field_name, INSTR(field_name, '_')-1) AS prefix FROM redcap_metadata WHERE field_name NOT LIKE '%complete' AND project_id = '$project_id' AND form_name = '$form'";
	$lab_fields_result = db_query($lab_fields_query);
	if ($lab_fields_result) {
		while ($lab_prefix = db_fetch_assoc($lab_fields_result)) {
			/**
			 * FIELD-level baselines
			 */
			$lab_fields[] = $lab_prefix['prefix'] . "_supplb_lbdtbl";
			$lab_fields[] = $lab_prefix['prefix'] . "_lbblfl";
			$lab_fields[] = $lab_prefix['prefix'] . "_im_lbblfl";
			$lab_fields[] = $lab_prefix['prefix'] . "_lbstresn";
			$lab_fields[] = $lab_prefix['prefix'] . "_im_lbstresn";
			if (in_array($fragment, array('hcv', 'hcv_im'))) {
				$lab_fields[] = $lab_prefix['prefix'] . "_supplb_hcvdtct";
				$lab_fields[] = $lab_prefix['prefix'] . "_im_supplb_hcvdtct";
			}

			$control_fields[$fragment][$lab_prefix['prefix']]['lbdtc'] = $fragment . "_lbdtc";
			$control_fields[$fragment][$lab_prefix['prefix']]['im_lbdtc'] = $fragment . "_im_lbdtc";
			$control_fields[$fragment][$lab_prefix['prefix']]['lbdtbl'] = $lab_prefix['prefix'] . "_supplb_lbdtbl";
			$control_fields[$fragment][$lab_prefix['prefix']]['lbblfl'] = $lab_prefix['prefix'] . "_lbblfl";
			$control_fields[$fragment][$lab_prefix['prefix']]['im_lbblfl'] = $lab_prefix['prefix'] . "_im_lbblfl";
			$control_fields[$fragment][$lab_prefix['prefix']]['lborres'] = $lab_prefix['prefix'] . "_lbstresn";
			$control_fields[$fragment][$lab_prefix['prefix']]['im_lborres'] = $lab_prefix['prefix'] . "_im_lbstresn";
			if (in_array($fragment, array('hcv', 'hcv_im'))) {
				$control_fields[$fragment][$lab_prefix['prefix']]['hcvdtct'] = $lab_prefix['prefix'] . "_supplb_hcvdtct";
				$control_fields[$fragment][$lab_prefix['prefix']]['im_hcvdtct'] = $lab_prefix['prefix'] . "_im_supplb_hcvdtct";
			}
		}
		db_free_result($lab_fields_result);
	}
	$timer['end_fields_' . $fragment] = microtime(true);
}
d($lab_fields);
d($control_fields);
/**
 * find baselines for each lab test
 */
$timer['start_data'] = microtime(true);
$data = REDCap::getData('array', $subjects, $lab_fields);
$timer['end_data'] = microtime(true);
foreach ($data AS $subject_id => $subject) {
	d($subject_id);
	/**
	 * get rfstdtc for this subject
	 */
	$tx_start_data = REDCap::getData('array', $subject_id, array("dm_rfstdtc"));
	$rfstdtc = $tx_start_data[$subject_id][$initial_event]['dm_rfstdtc'];
	/**
	 * if treatment has started
	 */
	if (isset($rfstdtc) || $rfstdtc != '') {
		d($rfstdtc);
		/**
		 * iterate the lab events for this subject
		 */
		foreach ($control_fields as $form_prefix => $labs) {
			foreach ($labs as $lab_prefix => $fields) {
				$lab_subject = array();
				$reset = array();
				foreach ($fields AS $field) {
					foreach ($subject AS $lab_event_id => $lab_event) {
						if ($lab_event[$fields['lborres']] != '' || $lab_event[$fields['hcvdtct']] != '' || $lab_event[$fields['im_lborres']] != '' || $lab_event[$fields['im_hcvdtct']] != '') {
							$lab_subject[$lab_event_id][$field] = $lab_event[$field];
						}
					}
				}
				d($lab_subject);
				/**
				 * if we have candidate events
				 */
				if (count($lab_subject) > 0) {
					/**
					 * fetch the baseline date and set baseline / flag pair
					 */
					$this_data = get_baseline_date($lab_subject, $form_prefix, $rfstdtc);
					d($form_prefix, $lab_prefix, $this_data);
					/**
					 * ABSTRACTED
					 * if the nearest date is prior or equal to rfstdtc, it's a baseline date
					 */
					$baseline_date = '';
					$this_baseline_flag = '';
					if ($this_data[$form_prefix . '_lbdtc'] != '' && $this_data[$form_prefix . '_lbdtc'] <= $rfstdtc) {
						$baseline_date = $this_data[$form_prefix . '_lbdtc'];
						$this_baseline_flag = 'Y';
					} else {
						/**
						 * Now reset all other flags that have changed
						 */
						foreach ($data[$subject_id] AS $flag_event_id => $flag_event) {
							$reset[$flag_event_id] = $flag_event[$lab_prefix . '_lbblfl'];
						}
						foreach ($reset AS $reset_event_id => $reset_event) {
							foreach ($reset_event as $reset_field => $reset_val) {
								update_field_compare($subject_id, $project_id, $reset_event_id, '', $reset_val, $lab_prefix . "_lbblfl", $debug);
							}
						}
					}
					/**
					 * Baseline flag belongs in the event where the baseline occurs
					 */
					update_field_compare($subject_id, $project_id, $this_data['event_id'], $this_baseline_flag, $data[$subject_id][$this_data['event_id']][$lab_prefix . "_lbblfl"], $lab_prefix . "_lbblfl", $debug);
					/**
					 * Baseline date belongs in Baseline event - one date for both abstracted and imported
					 */
					//update_field_compare($subject_id, $project_id, $initial_event, $baseline_date, get_single_field($subject_id, $project_id, $initial_event, $lab_prefix . "_supplb_lbdtbl", ''), $lab_prefix . "_supplb_lbdtbl", $debug);
					/**
					 * IMPORTED
					 */
					//$baseline_date = '';
					$this_baseline_flag = '';
					if ($this_data[$form_prefix . '_im_lbdtc'] != '' && $this_data[$form_prefix . '_im_lbdtc'] <= $rfstdtc) {
						$baseline_date = $this_data[$form_prefix . '_im_lbdtc'];
						$this_baseline_flag = 'Y';
					} else {
						/**
						 * Now reset all other flags that have changed
						 */
						foreach ($data[$subject_id] AS $flag_event_id => $flag_event) {
							$reset[$flag_event_id] = $flag_event[$lab_prefix . '_im_lbblfl'];
						}
						foreach ($reset AS $reset_event_id => $reset_event) {
							foreach ($reset_event as $reset_field => $reset_val) {
								update_field_compare($subject_id, $project_id, $reset_event_id, '', $reset_val, $lab_prefix . "_im_lbblfl", $debug);
							}
						}
					}
					/**
					 * Baseline flag belongs in the event where the date occurs
					 */
					update_field_compare($subject_id, $project_id, $this_data['event_id'], $this_baseline_flag, $data[$subject_id][$this_data['event_id']][$lab_prefix . "_im_lbblfl"], $lab_prefix . "_im_lbblfl", $debug);
					/**
					 * Baseline date belongs in Baseline event - one date for both abstracted and imported
					 */
					d($baseline_date);
					update_field_compare($subject_id, $project_id, $initial_event, $baseline_date, get_single_field($subject_id, $project_id, $initial_event, $lab_prefix . "_supplb_lbdtbl", ''), $lab_prefix . "_supplb_lbdtbl", $debug);
				}
			}
		}
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;