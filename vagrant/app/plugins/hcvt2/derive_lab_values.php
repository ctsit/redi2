<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 8/27/13
 * Time: 4:06 PM
 */
/**
 * debug
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
$plugin_title = "Derive Creatinine Clearance values";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * VARS
 */
$uln = 40;
$dm_array = array('dm_rfstdtc', 'dm_brthyr', 'dm_race', 'dm_sex', 'age_suppvs_age', 'dis_suppfa_txendt', 'dis_dsstdy', 'hcv_suppfa_svr12dt', 'hcv_suppfa_svr24dt');
$bmi_array = array('height_vsorresu', 'height_vsorres', 'height_suppvs_htcm', 'weight_vsorresu', 'weight_vsorres', 'weight_suppvs_wtkg', 'bmi_suppvs_bmi');
$chem_fields = array('chem_lbdtc', 'chem_im_lbdtc', 'creat_lbstresn', 'creat_im_lbstresn', 'crcl_lborres', 'crcl_im_lborres', 'creat_lbblfl', 'creat_im_lbblfl', 'ast_lbstresn', 'ast_im_lbstresn');
$cbc_fields = array('cbc_lbdtc', 'plat_lbstresn', 'apri_lborres', 'cbc_im_lbdtc', 'plat_im_lbstresn', 'apri_im_lborres');
$fields = array_merge($dm_array, $bmi_array, $chem_fields, $cbc_fields);
/**
 * MAIN
 */
$timer['start_data'] = microtime(true);
$data = REDCap::getData('array', $subjects, $fields);
$timer['start_data_loop'] = microtime(true);
foreach ($data AS $subject_id => $subject) {
	d($subject_id);
	$chem_values = array();
	$cbc_values = array();
	$sex = isset($subject[$baseline_event_id]['dm_sex']) ? $subject[$baseline_event_id]['dm_sex'] : 'F';
	$sex_factor = $sex == 'F' ? .85 : 1;
	/**
	 * EVENT LEVEL ACTIONS
	 */
	foreach ($subject AS $event_id => $event) {
		/**
		 * build $chem_values
		 */
		foreach ($chem_fields AS $chem_field_name) {
			$chem_values[$event_id][$chem_field_name] = $event[$chem_field_name];
		}
		/**
		 * build $cbc_values
		 */
		foreach ($cbc_fields AS $cbc_field_name) {
			$cbc_values[$event_id][$cbc_field_name] = $event[$cbc_field_name];
		}
	}
	d($chem_values);
	d($cbc_values);
	/**
	 * Creatinine Clearance (Cockcroft-Gault Equation)
	 * IF([chem_lbdtc] = "", null, round(((140 - ([chem_lbdtc].substring(0,4) - [brthyr])) * [weight_suppvs_wtkg] * (IF([dm_sex] = "0", .85, 1)) / (72 * [creat_lbstresn])), 0))
	 */
	if ($subject[$baseline_event_id]['dm_brthyr'] != '' && $subject[$baseline_event_id]['weight_suppvs_wtkg'] != '') {
		foreach ($chem_values as $chem_event => $values) {
			unset($creatinine_clearance);
			$chem_age = (substr($values['chem_lbdtc'], 0, 4)) - $subject[$baseline_event_id]['dm_brthyr'];
			$creatinine_clearance = round(((140 - $chem_age) * $subject[$baseline_event_id]['weight_suppvs_wtkg'] * $sex_factor) / (72 * $values['creat_lbstresn']));
			update_field_compare($subject_id, $project_id, $chem_event, $creatinine_clearance, $values['crcl_lborres'], 'crcl_lborres', $debug);
			$chem_im_age = (substr($values['chem_im_lbdtc'], 0, 4)) - $subject[$baseline_event_id]['dm_brthyr'];
			$creatinine_im_clearance = round(((140 - $chem_im_age) * $subject[$baseline_event_id]['weight_suppvs_wtkg'] * $sex_factor) / (72 * $values['creat_im_lbstresn']));
			update_field_compare($subject_id, $project_id, $chem_event, $creatinine_im_clearance, $values['crcl_im_lborres'], 'crcl_im_lborres', $debug);
		}
	}
	/**
	 * APRI
	 */
//	$chem_fields = array('chem_lbdtc', 'ast_lbstresn');
//	$cbc_data = REDCap::getData('array', '', $cbc_fields);
//	foreach ($cbc_values AS $cbc_event => $values) {
//		$apri_score = '';
//		if ($event['cbc_lbdtc'] != '' && $event['plat_lbstresn'] != '' && is_numeric($event['plat_lbstresn'])) {
//			foreach ($chem_data AS $chem_subject) {
//				foreach ($chem_subject AS $chem_event) {
//					if ($chem_event['chem_lbdtc'] != '' && $chem_event['ast_lbstresn'] != '' && $chem_event['chem_lbdtc'] == $event['cbc_lbdtc'] && is_numeric($chem_event['ast_lbstresn'])) {
//						$apri_score = (string)round(((($chem_event['ast_lbstresn'] / $uln) / $event['plat_lbstresn']) * 100), 2);
//					}
//				}
//			}
//		}
//		update_field_compare($subject_id, $project_id, $event_id, $apri_score, $event['apri_lborres'], 'apri_lborres', $debug);
//
//	}
//	foreach ($cbc_data AS $subject_id => $subject) {
//		$chem_events = array();
//		$chem_data = REDCap::getData('array', $subject_id, $chem_fields);
//		foreach ($subject AS $event_id => $event) {
//		}
//	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;