<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 10/6/2014
 * Time: 11:52 AM
 */
$debug = $_GET['debug'] ? (bool)$_GET['debug'] : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict use of this plugin to the appropriate project
 */
$allowed_pid = '26';
REDCap::allowProjects($allowed_pid);
Kint::enabled($enable_kint);
/**
 * project metadata
 */
global $Proj;
$baseline_event_id = $Proj->firstEventId;
$plugin_title = "Derive HCV RNA TX Outcomes";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * HCV RNA Outcome
 */
$fieldsets = array(
	'abstracted' => array(
		array('date_field' => 'hcv_lbdtc'),
		array('value_field' => 'hcv_lbstresn'),
		array('detect_field' => 'hcv_supplb_hcvdtct')
	),
	'imported' => array(
		array('date_field' => 'hcv_im_lbdtc'),
		array('value_field' => 'hcv_im_lbstresn'),
		array('detect_field' => 'hcv_im_supplb_hcvdtct'),
		array('trust' => 'hcv_im_nxtrust')
	)
);
$data = array();
$field_translate = array();
$reverse_translate = array();
foreach ($fieldsets as $formtype => $fieldset) {
	$fields = array();
	foreach ($fieldset AS $field) {
		foreach ($field as $key => $value) {
			$fields[] = $value;
			$field_translate[$formtype][$key] = $value;
			$reverse_translate[$formtype][$value] = $key;
		}
	}
	$data[$formtype] = REDCap::getData('array', $subjects, $fields);
}
$timer['start_main'] = microtime(true);
/**
 * Main
 */
$ie_fields = array('ie_ietestcd');
$ie_data = REDCap::getData('array', $subjects, $ie_fields);
$date_fields = array('dm_usubjid', 'dm_rfstdtc', 'dis_suppfa_txendt', 'eot_dsterm', 'dis_dsstdy', 'hcv_suppfa_fuelgbl', 'hcv_suppfa_nlgblrsn', 'hcv_suppfa_hcvout', 'hcv_suppfa_wk10rna', 'hcv_suppfa_lastbloq', 'dis_suppds_funcmprsn', 'hcv_suppfa_fudue', 'dm_suppdm_hcvt2id', 'dm_actarmcd', 'dm_suppdm_rtrtsdtc');
$date_data = REDCap::getData('array', $subjects, $date_fields, $baseline_event_id);
$blip_threshold = 500;

$timer['have_data'] = microtime(true);
foreach ($date_data AS $subject_id => $subject) {
	$all_events = array();
	$post_tx_dates = array();
	$post_tx_bloq_dates = array();
	$re_treat_possible = false;
	$re_treat_dates = array();
	foreach ($subject AS $date_event_id => $date_event) {
		/**
		 * HCV RNA Outcome
		 */
		$hcvrna_improved = false;
		$on_tx_scores = array();
		$hcvrna_previous_score = '';
		$post_tx_scores = array();
		$post_tx_plus10w_scores = array();
		$post_tx_plus10d_scores = array();
		$last_hcvrna_bloq = false;
		$stop_date_plus_10w = null;
		$stop_date_plus_10d = null;
		$tx_stopped_10_wks_ago = false;
		$started_tx = false;
		$stopped_tx = false;
		$hcv_fu_eligible = true;
		$hcv_fu_ineligible_reason = array();
		$lost_to_followup = false;
		$hcv_data_due = false;
		$tx_start_date = isset($date_event['dm_rfstdtc']) && $date_event['dm_rfstdtc'] != '' ? $date_event['dm_rfstdtc'] : null;
		$stop_date = isset($date_event['dis_suppfa_txendt']) && $date_event['dis_suppfa_txendt'] != '' ? $date_event['dis_suppfa_txendt'] : null;
		$dis_dsstdy = isset($date_event['dis_dsstdy']) && $date_event['dis_dsstdy'] != '' ? $date_event['dis_dsstdy'] : null;
		/**
		 * look for this dm_usubjid in dm_suppdm_hcvt2id. This is a foreign key between TARGET 2 and TARGET 3 patients.
		 * Get the start date of the TARGET 3 patient if dm_suppdm_hcvt2id is not empty.
		 */
		$t3_fk_result = db_query("SELECT record FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'dm_suppdm_hcvt2id' AND value = '{$date_event['dm_usubjid']}'");
		if ($t3_fk_result) {
			$t3_fk = db_fetch_assoc($t3_fk_result);
			$t3_start_date_value = get_single_field($t3_fk['record'], $project_id, $baseline_event_id, 'dm_rfstdtc', '');
		}
		$t3_start_date = isset($t3_start_date_value) ? $t3_start_date_value : '';
		/**
		 * where are we in treatment?
		 */
		if (isset($tx_start_date)) { // started treatment
			$started_tx = true;
			/**
			 * treatment must have started to stop
			 */
			if (isset($stop_date)) { // completed treatment
				$stopped_tx = true;
				$stop_date_plus_10d = add_date($stop_date, 10, 0, 0);
				$stop_date_plus_10w = add_date($stop_date, 64, 0, 0);
				if (date("Y-m-d") >= $stop_date_plus_10w && isset($stop_date_plus_10w)) {
					$tx_stopped_10_wks_ago = true;
				}
			} else { // not completed treatment
				$stopped_tx = false;
				$hcv_fu_eligible = false;
				$hcv_fu_ineligible_reason[] = 'TX Not Completed';
			}
		} else { // not started treatment
			$started_tx = false;
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = 'TX Not Started';
		}
		/**
		 * get fields for both abstracted (standardized) and imported HCV RNA forms
		 */
		foreach ($fieldsets as $formtype => $fieldset) {
			foreach ($data[$formtype][$subject_id] as $event_id => $event) {
				/**
				 * standardize array keys
				 */
				foreach ($event AS $event_key => $event_value) {
					unset($event[$event_key]);
					$event[array_search($event_key, $field_translate[$formtype])] = $event_value;
				}
				/**
				 * merge into all_events array
				 */
				if ($event['date_field'] != '') {
					$all_events[$event['date_field']][] = $event;
				}
			}
		}
		ksort($all_events);
		d($all_events);
		/**
		 * get outcomes
		 */
		foreach ($all_events AS $event_date => $event_set) {
			foreach ($event_set as $event) {
				/**
				 * if we have a date, and the HCV RNA isn't an 'untrusted blip'...
				 * (blips are sudden, small increases in viral load following EOT)
				 */
				if ((($event['date_field'] != '' && $t3_start_date == '') || ($event['date_field'] != '' && $t3_start_date != '' && $event['date_field'] <= $t3_start_date)) && $event['trust'] != 'N') {
					$is_bloq = (in_array($event['detect_field'], array('BLOQ', 'NOT_SPECIFIED', 'DETECTED'))) ? true : false;
					$score = $is_bloq ? '0' : '1';
					/**
					 * if treatment has started, and $event['date_field'] is after start date (is baseline or later)
					 */
					if ($started_tx && $tx_start_date <= $event['date_field']) {
						/**
						 * and is...
						 */
						if (!$stopped_tx || ($stopped_tx && $event['date_field'] <= $stop_date)) { // on treatment
							$on_tx_scores[] = $score;
							if ($score >= $hcvrna_previous_score) {
								$hcvrna_improved = false;
							} elseif ($score < $hcvrna_previous_score) {
								$hcvrna_improved = true;
							}
							$hcvrna_previous_score = $score;
						} else { // post-treatment
							/**
							 * RE-TREAT handling
							 * If this HCVRNA is quantifiable, add the date to an array
							 * if this HCVRNA is bloq and we have quantified post-TX HCVRNA, it's a re-treat and we don't want it in $post_tx_scores
							 */
							if ($is_bloq && !in_array('1', $post_tx_scores)) {
								$post_tx_bloq_dates[] = $event['date_field'];
								$post_tx_scores[] = $score;
								/**
								 * capture scores that are after EOT plus 10 weeks
								 */
								if (isset($stop_date_plus_10w) && $event['date_field'] >= $stop_date_plus_10w) {
									$post_tx_plus10w_scores[] = $score;
								}
								/**
								 * capture scores that are between EOT and EOT plus 10 days
								 */
								if (isset($stop_date_plus_10d) && $event['date_field'] <= $stop_date_plus_10d) {
									$post_tx_plus10d_scores[] = $score;
								}
							}
							if (!$is_bloq && !in_array('1', $post_tx_scores) && !$re_treat_possible) {
								$post_tx_dates[] = $event['date_field'];
								$post_tx_scores[] = $score;
								/**
								 * capture scores that are after EOT plus 10 weeks
								 */
								if (isset($stop_date_plus_10w) && $event['date_field'] >= $stop_date_plus_10w) {
									$post_tx_plus10w_scores[] = $score;
								}
								/**
								 * capture scores that are between EOT and EOT plus 10 days
								 */
								if (isset($stop_date_plus_10d) && $event['date_field'] <= $stop_date_plus_10d) {
									$post_tx_plus10d_scores[] = $score;
								}
							}
							if ($is_bloq && in_array('1', $post_tx_scores)) {
								$re_treat_possible = true;
							}
						}
					}
				}
			}
		}
		/**
		 * we have all our score candidates
		 */
		$all_scores = array_merge($on_tx_scores, $post_tx_scores);
		$last_hcvrna_bloq = count($all_scores) > 0 && get_end_of_array($all_scores) == '0' ? true : false;
		/**
		 * get candidates for re-treat cutoff date
		 */
		$re_treat_dates = array_diff(array_unique($post_tx_dates), array_unique($post_tx_bloq_dates));
		/**
		 * HCVRNA Followup Eligibility
		 * subjects are ineligible for followup if:
		 */
		foreach ($ie_data[$subject_id] as $ie_event) {
			if ($ie_event['ie_ietestcd'] != '') { // failed i/e criteria
				$hcv_fu_eligible = false;
				$hcv_fu_ineligible_reason[] = $ie_criteria_labels[$ie_event['ie_ietestcd']];
			}
		}
		/**
		 * disposition-related ineligibility
		 */
		if (in_array($date_event['eot_dsterm'], array('LOST_TO_FOLLOWUP', 'LACK_OF_EFFICACY'))) { // disposition is lost to followup
			$lost_to_followup = true;
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = fix_case($date_event['eot_dsterm']);
		}
		/**
		 * Quantified HCVRNA after EOT
		 */
		if (count($post_tx_scores) > 1 && !$hcvrna_improved) {
			if (in_array('1', $post_tx_scores)) { // had quantified HCV RNA after EOT
				$hcv_fu_eligible = false;
				$hcv_fu_ineligible_reason[] = 'Quantified post-TX HCVRNA';
			}
		} else {
			if (in_array('1', $post_tx_scores)) { // had quantified HCV RNA after EOT
				$hcv_fu_eligible = false;
				$hcv_fu_ineligible_reason[] = 'Quantified post-TX HCVRNA';
			}
		}
		/**
		 * lost to post-treatment follow up
		 */
		$post_tx_followup_eligible = $date_event['dis_suppds_funcmprsn'] == 'LOST_TO_FOLLOWUP' ? false : true;
		if (!$post_tx_followup_eligible) {
			$lost_to_followup = true;
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = 'Lost to post-TX followup';
		}
		/**
		 * derive outcome now as it's needed below
		 */
		$eot_dsterm = $date_event['eot_dsterm'];
		$outcome = get_outcome();
		/**
		 * IS FOLLOWUP DATA FOR THIS SUBJECT DUE?
		 * if followup eligible and treatment duration greater than 4 weeks...
		 */
		if (($hcv_fu_eligible && $post_tx_followup_eligible) && isset($dis_dsstdy) && $dis_dsstdy >= 29) {
			/**
			 * AND today is TX stop date + 14 weeks ago, and no final outcome, data is due
			 */
			if (date("Y-m-d") >= (add_date($stop_date, 98, 0, 0)) && !in_array($outcome, array('SVR', 'VIRAL BREAKTHROUGH', 'RELAPSE', 'NON-RESPONDER', 'LOST TO FOLLOWUP'))) {
				$hcv_data_due = true;
			}
		}
		/**
		 * if not followup eligible (and no TX stop - implied by ineligible)...
		 */
		if ((!$hcv_fu_eligible || !$post_tx_followup_eligible) && $started_tx && !$stopped_tx) {
			/**
			 * is regimen SOF + RBV?
			 */
			$due_fields = array('sof_cmstdtc', 'rib_cmstdtc');
			$due_data = REDCap::getData('array', $subject_id, $due_fields);
			$sof_rbv_regimen = false;
			$sof = array();
			$rbv = array();
			foreach ($due_data[$subject_id] AS $event_id => $event) {
				if ($event['sof_cmstdtc'] != '') {
					$sof[] = true;
				}
				if ($event['rib_cmstdtc'] != '') {
					$rbv[] = true;
				}
			}
			$sof_rbv_regimen = eval("return ((" . implode(' || ', $sof) . ") && (" . implode(' || ', $rbv) . "));");
			/**
			 * get genotype
			 */
			$genotype = get_single_field($subject_id, $project_id, $baseline_event_id, 'hcvgt_lborres', '');
			/**
			 * if regimen is SOF + RBV and Genotype 1 or 3
			 */
			if ($sof_rbv_regimen && ($genotype == '1' || $genotype == '3')) {
				/**
				 * AND if TX start is 168 days ago, data is due
				 */
				if (date("Y-m-d") >= (add_date($tx_start_date, 168, 0, 0))) {
					$hcv_data_due = true;
				}
				/**
				 * if regimen is SOF + RBV and Genotype 2
				 */
			} elseif ($sof_rbv_regimen && ($genotype == '2')) {
				/**
				 * if TX start is 84 days ago, data is due
				 */
				if (date("Y-m-d") >= (add_date($tx_start_date, 84, 0, 0))) {
					$hcv_data_due = true;
				}
				/**
				 * if any other regimen or genotype
				 */
			} else {
				/**
				 * if TX start is 84 days ago, data is due
				 */
				if (date("Y-m-d") >= (add_date($tx_start_date, 84, 0, 0))) {
					$hcv_data_due = true;
				}
			}
		}
		/**
		 * get values
		 */
		$last_bloq = $last_hcvrna_bloq ? 'Y' : 'N';
		$eligible = !$hcv_fu_eligible ? 'N' : 'Y';
		$reason = implode('; ', array_unique($hcv_fu_ineligible_reason));
		$data_due = $hcv_data_due ? 'Y' : 'N';
		$wk10_rna = count($post_tx_plus10w_scores) > 0 ? 'Y' : 'N';
		rsort($re_treat_dates);
		$re_treat_date = $re_treat_possible ? get_end_of_array($re_treat_dates) : null;
		/**
		 * debug
		 */
		if ($debug) {
			d($all_scores);
			if ($started_tx) {
				d($tx_start_date);
				d($on_tx_scores);
				if ($stopped_tx) {
					d($stop_date);
					d($post_tx_scores);
					d($post_tx_plus10d_scores);
					d($post_tx_plus10w_scores);
					d($last_hcvrna_bloq);
					d($lost_to_followup);
					d($post_tx_followup_eligible);
					d($hcv_fu_eligible);
					d($post_tx_bloq_dates);
					d($post_tx_dates);
					d($t3_start_date);
					d($re_treat_possible);
					d($tx_stopped_10_wks_ago);
					d($hcv_data_due);
					d($outcome);
				} else {
					d('NO TX STOP');
				}
			} else {
				d('NO TX START');
			}
		}
		/**
		 * set overall hcvrna followup eligibility and reason if ineligible
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $eligible, $date_event['hcv_suppfa_fuelgbl'], 'hcv_suppfa_fuelgbl', $debug);
		update_field_compare($subject_id, $project_id, $baseline_event_id, $reason, $date_event['hcv_suppfa_nlgblrsn'], 'hcv_suppfa_nlgblrsn', $debug);
		/**
		 * set follow up timing - is it due?
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $data_due, $date_event['hcv_suppfa_fudue'], 'hcv_suppfa_fudue', $debug);
		/**
		 * set outcome
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $outcome, $date_event['hcv_suppfa_hcvout'], 'hcv_suppfa_hcvout', $debug);
		/**
		 * set 10 HCV RNA?
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $wk10_rna, $date_event['hcv_suppfa_wk10rna'], 'hcv_suppfa_wk10rna', $debug);
		/**
		 * set HCV RNA BLOQ?
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $last_bloq, $date_event['hcv_suppfa_lastbloq'], 'hcv_suppfa_lastbloq', $debug);
		/**
		 * set re-treat window start date
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $re_treat_date, $date_event['dm_suppdm_rtrtsdtc'], 'dm_suppdm_rtrtsdtc', $debug);
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;