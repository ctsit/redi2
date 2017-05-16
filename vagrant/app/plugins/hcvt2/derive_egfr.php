<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = $_GET['debug'] ? (bool)$_GET['debug'] : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * project metadata
 */
global $Proj;
Kint::enabled($enable_kint);
$baseline_event_id = $Proj->firstEventId;
$plugin_title = "Derive stuff";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$dm_array = array('dm_rfstdtc', 'dm_race', 'dm_sex', 'age_suppvs_age', 'dis_dsstdy');
$egfr_fields = array('chem_lbdtc', 'egfr_lborres', 'creat_lbstresn', 'egfr_lbblfl', 'creat_lbblfl', 'egfr_im_lborres', 'egfr_im_lbblfl', 'chem_im_lbdtc', 'creat_im_lbstresn', 'creat_im_nxtrust');
$fields = array_merge($dm_array, $egfr_fields);
$data = REDCap::getData('array', $subjects, $fields);
$fragment = 'chem';
foreach ($data AS $subject_id => $subject) {
	d($subject_id);
	/**
	 * SUBJECT-LEVEL vars
	 */
	$creat_array = array();
	$tx_start_date = $subject[$baseline_event_id]['dm_rfstdtc'];
	$race = $subject[$baseline_event_id]['dm_race'];
	$sex = $subject[$baseline_event_id]['dm_sex'];
	$age = $subject[$baseline_event_id]['age_suppvs_age'];
	$race_factor = $race == 'BLACK_OR_AFRICAN_AMERICAN' ? 1.212 : 1;
	$sex_factor = $sex == 'F' ? 0.742 : 1;
	/**
	 * EVENT LEVEL ACTIONS
	 */
	if (isset($tx_start_date) && $tx_start_date != '') {
		foreach ($subject AS $event_id => $event) {
			if ($event['creat_lbstresn'] != '') {
				if ($race != '' && $sex != '' && $age != '') {
					$egfr = round((175 * pow($event['creat_lbstresn'], -1.154) * pow($age, -.203) * $sex_factor * $race_factor), 2);
				} else {
					$egfr = '';
				}
				update_field_compare($subject_id, $project_id, $event_id, $egfr, $event['egfr_lborres'], 'egfr_lborres', $debug);
				$is_baseline = ($event['creat_lbblfl'] == 'Y' && $egfr != '') ? 'Y' : '';
				update_field_compare($subject_id, $project_id, $event_id, $is_baseline, $event['egfr_lbblfl'], 'egfr_lbblfl', $debug);
				/**
				 * for egfr from imported, we need a standardized creat, it must be numeric and the trust field must not be 'N'
				 */
			} elseif ($event['creat_im_lbstresn'] != '' && is_numeric($event['creat_im_lbstresn']) && $event['creat_im_nxtrust'] != 'N') {
				if ($race != '' && $sex != '' && $age != '') {
					$egfr = round((175 * pow($event['creat_im_lbstresn'], -1.154) * pow($age, -.203) * $sex_factor * $race_factor), 2);
				} else {
					$egfr = '';
				}
				update_field_compare($subject_id, $project_id, $event_id, $egfr, $event['egfr_im_lborres'], 'egfr_im_lborres', $debug);
				$is_baseline = ($event['creat_lbblfl'] == 'Y' && $egfr != '') ? 'Y' : '';
				update_field_compare($subject_id, $project_id, $event_id, $is_baseline, $event['egfr_im_lbblfl'], 'egfr_im_lbblfl', $debug);
			} elseif ($event['creat_lbstresn'] == '') {
				$egfr = '';
				update_field_compare($subject_id, $project_id, $event_id, $egfr, $event['egfr_lborres'], 'egfr_lborres', $debug);
				$is_baseline = ($event['creat_lbblfl'] == 'Y' && $egfr != '') ? 'Y' : '';
				update_field_compare($subject_id, $project_id, $event_id, $is_baseline, $event['egfr_lbblfl'], 'egfr_lbblfl', $debug);
			} elseif ($event['creat_im_lbstresn'] == '') {
				$egfr = '';
				update_field_compare($subject_id, $project_id, $event_id, $egfr, $event['egfr_im_lborres'], 'egfr_im_lborres', $debug);
				$is_baseline = ($event['creat_lbblfl'] == 'Y' && $egfr != '') ? 'Y' : '';
				update_field_compare($subject_id, $project_id, $event_id, $is_baseline, $event['egfr_im_lbblfl'], 'egfr_im_lbblfl', $debug);
			}
		}
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}