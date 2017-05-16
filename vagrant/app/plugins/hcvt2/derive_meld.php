<?php
/**
 * Created by HCV-TARGET for HCV-TARGET studies v2.0 and above.
 * User: kbergqui
 * Date: 3-5-14
 * Purpose: Calculate MELD score for HCV-TARGET 2.0 patients
 */
/**
 * TESTING
 */
$getdebug = $_GET['debug'] ? $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
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
Kint::enabled($debug);
/**
 * initialize variables
 */
$lab_date_array = array();
$initial_event = $Proj->firstEventId;
/**
 * plugin title
 */
echo "<h3>Derive MELD Scores for HCVT2 subjects</h3>";
/**
 * MAIN
 */
$timer['start_data'] = microtime(true);
$chem_fields = array("chem_lbdtc", "creat_lbstresn", "tbil_lbstresn", "meld_lborres", "meld_lbblfl");
$chem_data = REDCap::getData('array', $subjects, $chem_fields);
$chem_fields_import = array("chem_im_lbdtc", "creat_im_lbstresn", 'creat_im_nxtrust', "tbil_im_lbstresn", 'tbil_im_nxtrust', "meld_im_lborres", "meld_im_lbblfl");
$chem_data_import = REDCap::getData('array', $subjects, $chem_fields_import);
$inr_fields = array("inr_lbdtc", "inr_lbstat", "inr_lborres");
$inr_data = REDCap::getData('array', $subjects, $inr_fields);
$inr_fields_import = array("inr_im_lbdtc", "inr_im_lbstat", "inr_im_lborres");
$inr_data_import = REDCap::getData('array', $subjects, $inr_fields_import);
$cirr_fields = array("cirr_suppfa_cirrstat", "dm_rfstdtc", "meld_supplb_lbdtbl", "meld_im_supplb_lbdtbl");
$cirr_data = REDCap::getData('array', $subjects, $cirr_fields, $initial_event);
$timer['start_main'] = microtime(true);
d($cirr_data);
foreach ($cirr_data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		if ($event['cirr_suppfa_cirrstat'] == 'Y') {
			$rfstdtc = $event['dm_rfstdtc'];
			/**
			 * ABSTRACTED DATA
			 */
			$lab_subject = array();
			d($subject_id);
			foreach ($chem_data[$subject_id] AS $chem_event_id => $chem_event) {
				$meld = '';
				foreach ($inr_data[$subject_id] AS $inr_event_id => $inr_event) {
					if (array_search($chem_event['chem_lbdtc'], $inr_event) !== false) {
						if ($inr_event['inr_lborres'] != '' && $chem_event['creat_lbstresn'] != '' && $chem_event['tbil_lbstresn'] != '') {
							d('ABSTRACTED', $chem_event);
							d($inr_event);
							/**
							 * let's have fun with math
							 * make sure we don't have any zeros. PHP doesn't like log(0). Don't pass values less than 1.
							 */
							$inr_lborres = $inr_event['inr_lborres'] >= '1' ? $inr_event['inr_lborres'] : '1';
							$creat_lbstresn = $chem_event['creat_lbstresn'] >= '1' ? $chem_event['creat_lbstresn'] : '1';
							$tbil_lbstresn = $chem_event['tbil_lbstresn'] >= '1' ? $chem_event['tbil_lbstresn'] : '1';
							$meld = round((3.78 * log($tbil_lbstresn)) + (11.2 * log($inr_lborres)) + (9.57 * log($creat_lbstresn)) + 6.43);
						}
					}
				}
				/**
				 * set MELD for this event
				 */
				d($meld);
				update_field_compare($subject_id, $project_id, $chem_event_id, $meld, $chem_event['meld_lborres'], 'meld_lborres', $debug);
				/**
				 * set baseline date and flag
				 */
				if (isset($rfstdtc) || $rfstdtc != '') {
					/**
					 * if we have a value for the orres, then add to candidate events array
					 */
					$this_meld = $chem_event["meld_lborres"] != '' ? $chem_event["meld_lborres"] : $meld;
					if ($this_meld != '') {
						$lab_subject[$chem_event_id] = $chem_event;
					}
				}
			}
			/**
			 * if we have candidate events
			 * iterate the candidate array, find the baseline date and flag
			 */
			if (count($lab_subject) > 0) {
				/**
				 * fetch the baseline date and set baseline / flag pair
				 */
				$baseline_date = '';
				$baseline_flag = '';
				$this_data = get_baseline_date($lab_subject, 'chem', $rfstdtc);
				/**
				 * if the nearest date is prior or equal to rfstdtc, it's a baseline date
				 */
				d($lab_subject);
				d($this_data);
				d($rfstdtc);
				if ($this_data['chem_lbdtc'] != '' && $this_data['chem_lbdtc'] <= $rfstdtc) {
					$baseline_date = $this_data['chem_lbdtc'];
					$baseline_flag = 'Y';
					/**
					 * Baseline date belongs in Baseline event
					 */
					update_field_compare($subject_id, $project_id, $initial_event, $baseline_date, $event['meld_supplb_lbdtbl'], "meld_supplb_lbdtbl", $debug);
					d($baseline_date);
					/**
					 * Now reset all other flags that have changed
					 */
					$flag_reset_data = REDCap::getData('array', $subject_id, "meld_lbblfl");
					foreach ($flag_reset_data AS $reset) {
						d($reset);
						foreach ($reset AS $reset_event_id => $reset_event) {
							foreach ($reset_event as $reset_field => $reset_val) {
								if ($reset_event_id != $this_data['event_id']) {
									update_field_compare($subject_id, $project_id, $reset_event_id, '', $reset_val, "meld_lbblfl", $debug);
								}
							}
						}
					}
				}
				/**
				 * Baseline flag belongs in the event where the date occurs
				 */
				update_field_compare($subject_id, $project_id, $this_data['event_id'], $baseline_flag, $this_data['meld_lbblfl'], "meld_lbblfl", $debug);
			}
			/**
			 * IMPORTED LABS
			 */
			$lab_subject = array();
			foreach ($chem_data_import[$subject_id] AS $chem_event_id => $chem_event) {
				$meld = '';
				foreach ($inr_data_import[$subject_id] AS $inr_event_id => $inr_event) {
					if (array_search($chem_event['chem_im_lbdtc'], $inr_event) !== false) {
						if ($inr_event['inr_im_lborres'] != '' && is_numeric($chem_event['creat_im_lbstresn']) && ($chem_event['creat_im_lbstresn'] != '' && $chem_event['creat_im_nxtrust'] != 'N') && ($chem_event['tbil_im_lbstresn'] != '' && $chem_event['tbil_im_nxtrust'] != 'N')) {
							d('IMPORTED', $chem_event);
							d($inr_event);
							$inr_lborres = $inr_event['inr_im_lborres'] >= '1' ? $inr_event['inr_im_lborres'] : '1';
							$creat_lbstresn = $chem_event['creat_im_lbstresn'] >= '1' ? $chem_event['creat_im_lbstresn'] : '1';
							$tbil_lbstresn = $chem_event['tbil_im_lbstresn'] >= '1' ? $chem_event['tbil_im_lbstresn'] : '1';
							$meld = round((3.78 * log($tbil_lbstresn)) + (11.2 * log($inr_lborres)) + (9.57 * log($creat_lbstresn)) + 6.43);
						}
					}
				}
				d($meld);
				update_field_compare($subject_id, $project_id, $chem_event_id, $meld, $chem_event['meld_im_lborres'], 'meld_im_lborres', $debug);
				/**
				 * set baseline date and flag
				 */
				if (isset($rfstdtc) || $rfstdtc != '') {
					/**
					 * if we have a value for the orres, then add to candidate events array
					 */
					$this_meld = $chem_event["meld_im_lborres"] != '' ? $chem_event["meld_im_lborres"] : $meld;
					if ($this_meld != '') {
						$lab_subject[$chem_event_id] = $chem_event;
					}
				}
			}
			/**
			 * if we have candidate events
			 * iterate the candidate array, find the baseline date and flag
			 */
			if (count($lab_subject) > 0) {
				/**
				 * fetch the baseline date and set baseline / flag pair
				 */
				$baseline_date = '';
				$baseline_flag = '';
				$this_data = get_baseline_date($lab_subject, 'chem_im', $rfstdtc);
				/**
				 * if the nearest date is prior or equal to rfstdtc, it's a baseline date
				 */
				d($lab_subject);
				d($this_data);
				d($rfstdtc);
				if ($this_data['chem_im_lbdtc'] != '' && $this_data['chem_im_lbdtc'] <= $rfstdtc) {
					$baseline_date = $this_data['chem_im_lbdtc'];
					$baseline_flag = 'Y';
					/**
					 * Baseline date belongs in Baseline event
					 */
					update_field_compare($subject_id, $project_id, $initial_event, $baseline_date, $event['meld_im_supplb_lbdtbl'], "meld_im_supplb_lbdtbl", $debug);
					d($baseline_date);
					/**
					 * Now reset all other flags that have changed
					 */
					$flag_reset_data = REDCap::getData('array', $subject_id, "meld_im_lbblfl");
					foreach ($flag_reset_data AS $reset) {
						d($reset);
						foreach ($reset AS $reset_event_id => $reset_event) {
							foreach ($reset_event as $reset_field => $reset_val) {
								if ($reset_event_id != $this_data['event_id']) {
									update_field_compare($subject_id, $project_id, $reset_event_id, '', $reset_val, "meld_im_lbblfl", $debug);
								}
							}
						}
					}
				}
				/**
				 * Baseline flag belongs in the event where the date occurs
				 */
				update_field_compare($subject_id, $project_id, $this_data['event_id'], $baseline_flag, $this_data['meld_im_lbblfl'], "meld_im_lbblfl", $debug);
			}
		}
	}
}
$timer['end_main'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;