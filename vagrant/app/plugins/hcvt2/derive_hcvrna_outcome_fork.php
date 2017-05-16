<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 10/6/2014
 * Time: 11:52 AM
 */
$debug = true;
$subjects = '122-8' /*array('122-8', '190', '628', '1173', '1520', '331')*/
; // '' = ALL
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
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
/**
 * project metadata
 */
$project = new Project();
$baseline_event_id = $project->firstEventId;
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
		array('detect_field' => 'hcv_supplb_hcvdtct'),
		array('trust_blip' => 'hcv_supplb_blipfl')
	),
	'imported' => array(
		array('date_field' => 'hcv_im_lbdtc'),
		array('value_field' => 'hcv_im_lbstresn'),
		array('detect_field' => 'hcv_im_supplb_hcvdtct'),
		array('trust_blip' => 'hcv_im_supplb_blipfl')
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
$date_fields = array('dm_usubjid', 'dm_rfstdtc', 'dis_suppfa_txendt', 'eot_dsterm', 'dis_dsstdy', 'hcv_suppfa_fuelgbl', 'hcv_suppfa_nlgblrsn', 'hcv_suppfa_hcvout', 'hcv_suppfa_wk10rna', 'hcv_suppfa_lastbloq', 'dis_suppds_funcmprsn', 'hcv_suppfa_fudue', 'dm_suppdm_hcvt2id');
$date_data = REDCap::getData('array', $subjects, $date_fields, $baseline_event_id);

$timer['have_data'] = microtime(true);
foreach ($date_data AS $subject_id => $subject) {
	$all_events = array();
	if ($debug) {
		show_var($subject_id, 'Subject ID', 'blue');
	}
	foreach ($subject AS $date_event_id => $date_event) {
		/**
		 * HCV RNA Outcome
		 */
		$before_count = 0;
		$before_eot_plus10_count = 0;
		$hcvrna_improved = false;
		$have_cutoff = false;
		$hcvrna_unchanged_count = 0;
		$on_tx_scores = array();
		$hcvrna_last_before_score = '';
		$previous_post_tx_score = '';
		$previous_hcvrna_date = '';
		$hcvrna_before_zero_count = 0;
		$post_tx_scores = array();
		$hcvrna_breakthrough_possible = false;
		$post_tx_plus10w_scores = array();
		$post_tx_plus10d_scores = array();
		$last_hcvrna_bloq = false;
		unset($stop_date_plus_10w);
		$has_10week_results = false;
		$tx_stopped_10_wks_ago = false;
		$started_tx = false;
		$stopped_tx = false;
		$hcv_fu_eligible = true;
		$hcv_fu_ineligible_reason = array();
		$lost_to_followup = false;
		$tx_start_date = isset($date_event['dm_rfstdtc']) && $date_event['dm_rfstdtc'] != '' ? $date_event['dm_rfstdtc'] : null;
		$stop_date = isset($date_event['dis_suppfa_txendt']) && $date_event['dis_suppfa_txendt'] != '' ? $date_event['dis_suppfa_txendt'] : null;
		$dis_dsstdy = isset($date_event['dis_dsstdy']) && $date_event['dis_dsstdy'] != '' ? $date_event['dis_dsstdy'] : null;
		/**
		 * look for this dm_usubjid in dm_suppdm_hcvt2id. This is a foreign key between TARGET 2 and TARGET 3 patients.
		 * Get the start date of the TARGET 3 patient if dm_suppdm_hcvt2id is not empty.
		 */
		$t3_start_date = '';
		$t3_fk_result = db_query("SELECT record FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'dm_suppdm_hcvt2id' AND value = '{$date_event['dm_usubjid']}'");
		if ($t3_fk_result) {
			$t3_fk = db_fetch_assoc($t3_fk_result);
			$t3_start_date = get_single_field($t3_fk['record'], $project_id, $baseline_event_id, 'dm_rfstdtc', '');
		}
		/**
		 * where are we in treatment?
		 */
		if (!isset($tx_start_date)) { // never started treatment
			$started_tx = false;
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = 'TX Not Started';
		} else {
			$started_tx = true;
		}
		if (!isset($stop_date)) { // never completed treatment
			$stopped_tx = false;
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = 'TX Not Completed';
		} else {
			$stopped_tx = true;
			$stop_date_plus_10d = add_date($stop_date, 10, 0, 0);
			$stop_date_plus_10w = add_date($stop_date, 64, 0, 0);
			if (date("Y-m-d") >= $stop_date_plus_10w) {
				$tx_stopped_10_wks_ago = true;
			}
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
		if ($debug && $subjects != '') {
			show_var($all_events);
		}
		/**
		 * get outcomes
		 */
		foreach ($all_events AS $event_date => $event_set) {
			foreach ($event_set as $event) {
				/**
				 * if we have HCVRNA results on this event, and the subject is not re-treated on Target 3 OR
				 * if we have results on this event, and the subject IS re-treated on HCVT3, and the event date is on or before the HCVT3 treatment start
				 * then include this result in outcome derivation
				 */
				if (($event['date_field'] != '' && $t3_start_date == '') || ($event['date_field'] != '' && $t3_start_date != '' && $event['date_field'] <= $t3_start_date) /* && $event['trust_blip'] != 'N'*/) {
					/**
					 * if the event detect_field is BLOQ, NOT_SPECIFIED, or DETECTED, OR
					 * if the event value_field is zero, this result is a BLOQ
					 */
					$is_bloq = (in_array($event['detect_field'], array('BLOQ', 'NOT_SPECIFIED', 'DETECTED')) || ($event['value_field']) != '' AND !$event['value_field'] < 1000) ? true : false;
					/**
					 * Get the score:
					 * if we have a quantified result that's greater than zero, OR
					 * the quantified result is post-treatment and >= 1000, record a 1 score
					 */
					if (($event['value_field'] != '' && $event['value_field'] > 0) || (isset($stop_date) && $event['value_field'] != '' && $event['value_field'] >= 1000)) {
						$score = '1';
						$last_hcvrna_bloq = false;
						/**
						 * otherwise, if the result is BLOQ as defined above, OR
						 * we're post-treatment and the quantified result is < 1000 (BLIP), record a 0 score
						 */
					} elseif ($is_bloq || (isset($stop_date) && $event['value_field'] != '' && $event['value_field'] < 1000)) {
						$last_hcvrna_bloq = true;
						$score = '0';
					}
					/**
					 * before , after, or no EOT date?
					 */
					if (isset($stop_date)) {
						if ($stop_date < $event['date_field']) {
							$before_eot = false;
						} else {
							$before_eot = true;
							$before_count++;
						}
						if ($stop_date < $event['date_field'] && $event['date_field'] <= $stop_date_plus_10d) {
							$post_tx_plus10d_scores[] = $score;
							$before_eot_plus10 = true;
							$before_eot_plus10_count++;
						} else {
							$before_eot_plus10 = false;
						}
					} else {
						$before_eot = true;
					}
					if ($debug && $subjects != '') {
						$event_color = $before_eot ? 'green' : 'red';
						show_var($event, 'event', $event_color);
						show_var($score, 'score', $event_color);
					}
					/**
					 * if treatment has started,
					 */
					if ($tx_start_date <= $event['date_field']) {
						/**
						 * and has not yet stopped...
						 */
						if ($before_eot) { // on treatment
							$on_tx_scores[] = $score;
							if ($score == '0') {
								$hcvrna_before_zero_count++;
							}
							if (($score == $hcvrna_last_before_score) && $hcvrna_improved) {
								$hcvrna_unchanged_count++;
							} elseif ($score > $hcvrna_last_before_score) {
								$hcvrna_improved = false;
								$hcvrna_breakthrough_possible = true;
							} elseif ($score < $hcvrna_last_before_score) {
								$hcvrna_improved = true;
								$hcvrna_breakthrough_possible = false;
							} else {
								$hcvrna_unchanged_count = 0;
							}
							$hcvrna_last_before_score = $score;
							$previous_hcvrna_date = $event['date_field'];
						} else { // post-treatment
							/**
							 * otherwise, add this score to the array.
							 */
							/**
							 * if in post-TX a BLOQ occurs after 1 or more QUANT, exclude this score
							 * from outcome derivation in potential out-of-network re-treats
							 */
							/**
							 * @TODO: This does not yet work as expected.
							 */
							if (!$have_cutoff && $previous_post_tx_score != '' && $score < $previous_post_tx_score && $event['date_field'] > add_date($previous_hcvrna_date, 7)) {
								$have_cutoff = true;
							} else {
								/**
								 * otherwise, add this score to the array.
								 */
								$post_tx_scores[] = $score;
								if ($event['date_field'] >= $stop_date_plus_10w) {
									$has_10week_results = true;
									$post_tx_plus10w_scores[] = $score;
								}
							}
							$previous_post_tx_score = $score;
							$previous_hcvrna_date = $event['date_field'];
						}
					}
				}
			}
		}
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
		 * lost to follow-up any time
		 */
		if ($date_event['eot_dsterm'] == 'LOST_TO_FOLLOWUP') { // disposition is lost to followup
			$lost_to_followup = true;
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = 'Lost to Followup';
		}
		/**
		 * Quantified HCVRNA after EOT
		 */
		if (in_array('1', $post_tx_scores)) { // had quantified HCV RNA after EOT
			$hcv_fu_eligible = false;
			$hcv_fu_ineligible_reason[] = 'Quantified post-TX HCVRNA';
		}
		/**
		 * get outcome
		 */
		$eot_dsterm = $date_event['eot_dsterm'];
		$outcome = get_outcome();
		/**
		 * IS FOLLOWUP DATA FOR THIS SUBJECT DUE?
		 */
		$hcv_data_due = false;
		$post_tx_followup_eligible = $date_event['dis_suppds_funcmprsn'] == 'LOST_TO_FOLLOWUP' ? false : true;
		/**
		 * if followup eligible and treatment duration greater than 4 weeks...
		 */
		if (($hcv_fu_eligible && $post_tx_followup_eligible) && isset($dis_dsstdy) && $dis_dsstdy >= 29) {
			/**
			 * AND today is TX stop date + 14 weeks ago, and no final outcome, data is due
			 */
			if (date("Y-m-d") >= (add_date($stop_date, 98, 0, 0)) && !in_array($outcome, array('SVR', 'VIRAL BREAKTHROUGH', 'RELAPSE', 'NON-RESPONDER'))) {
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
		$eligible = !$hcv_fu_eligible ? 'N' : 'Y';
		$reason = implode('; ', array_unique($hcv_fu_ineligible_reason));
		$data_due = $hcv_data_due ? 'Y' : 'N';
		$wk10_rna = $has_10week_results ? 'Y' : 'N';
		$last_bloq = $last_hcvrna_bloq ? 'Y' : 'N';
		/**
		 * debug
		 */
		if ($debug) {
			if ($subjects != '') {
				show_var($hcv_fu_eligible, 'FU Eligible?', 'gray');
				show_var($post_tx_followup_eligible, 'POST TX FU', 'gray');
				show_var($genotype, 'GT');
				show_var($sof_rbv_regimen, 'SOF/RBV');
				show_var($hcv_data_due, 'FU DUE');
			}
			if ($started_tx) {
				show_var($on_tx_scores, 'ON TX', 'green');
				if ($stopped_tx) {
					show_var($post_tx_plus10d_scores, 'POST TX +10day', 'darkgreen');
					show_var($post_tx_scores, 'POST TX', 'yellow');
					if ($has_10week_results) {
						show_var($post_tx_plus10w_scores, 'POST TX +10week', 'red');
					} else {
						show_var('NO POST TX +10week HCVRNA', '', 'red');
					}
					if ($t3_start_date != '') {
						show_var('THIS PATIENT started HCVT3 TX on ' . $t3_start_date, '', 'red');
					}
				}
				show_var($hcvrna_before_zero_count, 'on tx BLOQ count', 'gray');
				show_var($last_hcvrna_bloq, 'last BLOQ?', 'gray');
				show_var($lost_to_followup, 'LTFU?', 'gray');
				show_var($tx_stopped_10_wks_ago, 'TX stop > 10wks ago', 'gray');
				show_var($outcome, 'Outcome', 'brown');
			} else {
				show_var('NO TX START', '', 'red');
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
		 * set week 10 HCV RNA
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $wk10_rna, $date_event['hcv_suppfa_wk10rna'], 'hcv_suppfa_wk10rna', $debug);
		/**
		 * set HCV RNA BLOQ
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $last_bloq, $date_event['hcv_suppfa_lastbloq'], 'hcv_suppfa_lastbloq', $debug);
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;