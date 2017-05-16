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
/**
 * INIT VARS
 */
global $Proj;
$table_csv = "";
$export_filename = 'REPORT_RESOURCE';
$header_array = array();
Kint::enabled($debug);
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$fields = array('dm_usubjid', 'dm_rfstdtc', 'eot_dsstdtc', 'fund_nxenrsc', 'fund_nxltfrsc', 'fund_nxsrmrsc', 'fund_nxsrmfail', 'fund_nxdnarsc', 'hcv_suppfa_fudue', 'dis_suppds_funcmprsn', 'hcv_suppfa_nlgblrsn', 'dm_actarmcd');
foreach ($fields AS $field) {
	$header_array[] = quote_wrap($Proj->metadata[$field]['element_label']);
}
$data = REDCap::getData('array', '', $fields, $Proj->firstEventId);
$treatment_exp_fields = array('pegifn_mhoccur', 'pegifn_suppmh_response', 'triple_mhoccur', 'triple_suppmh_cmdaa', 'triple_suppmh_response', 'nopegifn_mhoccur', 'daa_mhoccur', 'daa_suppmh_failtype', 'daa_oth_suppmh_failtype');
foreach ($treatment_exp_fields AS $field) {
	$header_array[] = quote_wrap($Proj->metadata[$field]['element_label']);
}
$treatment_exp_data = REDCap::getData('array', '', $treatment_exp_fields, $Proj->firstEventId);
$misc_fields = array('hcvgt_lborres', 'hcvgt_s_lborres', 'hcv_suppfa_hcvout', 'cirr_suppfa_cirrstat', 'dcp_mhoccur', 'livr_mhoccur');
foreach ($misc_fields AS $field) {
	if ($field != 'hcvgt_s_lborres') {
		$header_array[] = quote_wrap($Proj->metadata[$field]['element_label']);
	}
}
$misc_data = REDCap::getData('array', '', $misc_fields, $Proj->firstEventId);
/**
 * MAIN
 */
foreach ($data AS $subject_id => $subject) {
	d($subject[$Proj->firstEventId]['dm_usubjid']);
	/**
	 * SUBJECT-LEVEL vars
	 */
	$data_row = array();
	$regimens = array();
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		$data_row[get_element_label('dm_usubjid')] = $event['dm_usubjid'] != '' ? quote_wrap($event['dm_usubjid']) : quote_wrap('--');
		$data_row[get_element_label('dm_rfstdtc')] = $event['dm_rfstdtc'] != '' ? quote_wrap($event['dm_rfstdtc']) : quote_wrap('--');
		$data_row[get_element_label('eot_dsstdtc')] = $event['eot_dsstdtc'] != '' ? quote_wrap($event['eot_dsstdtc']) : quote_wrap('--');
		$data_row[get_element_label('fund_nxenrsc')] = $event['fund_nxenrsc'] != '' ? quote_wrap($event['fund_nxenrsc']) : quote_wrap('--');
		$data_row[get_element_label('fund_nxltfrsc')] = $event['fund_nxltfrsc'] != '' ? quote_wrap($event['fund_nxltfrsc']) : quote_wrap('--');
		$data_row[get_element_label('fund_nxsrmrsc')] = $event['fund_nxsrmrsc'] != '' ? quote_wrap($event['fund_nxsrmrsc']) : quote_wrap('--');
		$data_row[get_element_label('fund_nxsrmfail')] = $event['fund_nxsrmfail'] != '' ? quote_wrap($event['fund_nxsrmfail']) : quote_wrap('--');
		$data_row[get_element_label('fund_nxdnarsc')] = $event['fund_nxdnarsc'] != '' ? quote_wrap($event['fund_nxdnarsc']) : quote_wrap('--');
		$data_row[get_element_label('hcv_suppfa_fudue')] = $event['hcv_suppfa_fudue'] != '' ? quote_wrap($event['hcv_suppfa_fudue']) : quote_wrap('--');
		$data_row[get_element_label('dis_suppds_funcmprsn')] = $event['dis_suppds_funcmprsn'] != '' ? quote_wrap($event['dis_suppds_funcmprsn']) : quote_wrap('--');
		$data_row[get_element_label('hcv_suppfa_nlgblrsn')] = $event['hcv_suppfa_nlgblrsn'] != '' ? quote_wrap($event['hcv_suppfa_nlgblrsn']) : quote_wrap('--');
		$data_row[get_element_label('dm_actarmcd')] = $event['dm_actarmcd'] != '' ? quote_wrap($event['dm_actarmcd']) : quote_wrap('--');
	}
	/**
	 * treatment exp
	 */
	if (isset($treatment_exp_data[$subject_id])) {
		foreach ($treatment_exp_data[$subject_id] AS $treatment_exp_event) {
			d($treatment_exp_event);
			$data_row[get_element_label('pegifn_mhoccur')] = $treatment_exp_event['pegifn_mhoccur'] != '' ? quote_wrap($treatment_exp_event['pegifn_mhoccur']) : quote_wrap('--');
			$data_row[get_element_label('pegifn_suppmh_response')] = $treatment_exp_event['pegifn_suppmh_response'] != '' ? quote_wrap($treatment_exp_event['pegifn_suppmh_response']) : quote_wrap('--');
			$data_row[get_element_label('triple_mhoccur')] = $treatment_exp_event['triple_mhoccur'] != '' ? quote_wrap($treatment_exp_event['triple_mhoccur']) : quote_wrap('--');
			$data_row[get_element_label('triple_suppmh_cmdaa')] = $treatment_exp_event['triple_suppmh_cmdaa'] != '' ? quote_wrap($treatment_exp_event['triple_suppmh_cmdaa']) : quote_wrap('--');
			$data_row[get_element_label('triple_suppmh_response')] = $treatment_exp_event['triple_suppmh_response'] != '' ? quote_wrap($treatment_exp_event['triple_suppmh_response']) : quote_wrap('--');
			$data_row[get_element_label('nopegifn_mhoccur')] = $treatment_exp_event['nopegifn_mhoccur'] != '' ? quote_wrap($treatment_exp_event['nopegifn_mhoccur']) : quote_wrap('--');
			$data_row[get_element_label('daa_mhoccur')] = $treatment_exp_event['daa_mhoccur'] != '' ? quote_wrap($treatment_exp_event['daa_mhoccur']) : quote_wrap('--');
			$data_row[get_element_label('daa_suppmh_failtype')] = !empty($treatment_exp_event['daa_suppmh_failtype']) ? quote_wrap(implode(', ', array_keys($treatment_exp_event['daa_suppmh_failtype'], '1'))) : quote_wrap('--');
			$data_row[get_element_label('daa_oth_suppmh_failtype')] = $treatment_exp_event['daa_oth_suppmh_failtype'] != '' ? quote_wrap($treatment_exp_event['daa_oth_suppmh_failtype']) : quote_wrap('--');
		}
	} else {
		$data_row[get_element_label('pegifn_mhoccur')] = quote_wrap('--');
		$data_row[get_element_label('pegifn_suppmh_response')] = quote_wrap('--');
		$data_row[get_element_label('triple_mhoccur')] = quote_wrap('--');
		$data_row[get_element_label('triple_suppmh_cmdaa')] = quote_wrap('--');
		$data_row[get_element_label('triple_suppmh_response')] = quote_wrap('--');
		$data_row[get_element_label('nopegifn_mhoccur')] = quote_wrap('--');
		$data_row[get_element_label('daa_mhoccur')] = quote_wrap('--');
		$data_row[get_element_label('daa_suppmh_failtype')] = quote_wrap('--');
		$data_row[get_element_label('daa_oth_suppmh_failtype')] = quote_wrap('--');
	}
	/**
	 * misc data
	 */
	if (isset($misc_data[$subject_id])) {
		foreach ($misc_data[$subject_id] AS $misc_event) {
			d($misc_event);
			if ($misc_event['hcvgt_s_lborres'] != 'NOT_AVAILABLE' && $misc_event['hcvgt_s_lborres'] != '') {
				$misc_event['hcvgt_lborres'] = $misc_event['hcvgt_lborres'] . $misc_event['hcvgt_s_lborres'];
			}
			$data_row[get_element_label('hcvgt_lborres')] = $misc_event['hcvgt_lborres'] != '' ? quote_wrap($misc_event['hcvgt_lborres']) : quote_wrap('--');
			$data_row[get_element_label('hcv_suppfa_hcvout')] = $misc_event['hcv_suppfa_hcvout'] != '' ? quote_wrap($misc_event['hcv_suppfa_hcvout']) : quote_wrap('--');
			$data_row[get_element_label('cirr_suppfa_cirrstat')] = $misc_event['cirr_suppfa_cirrstat'] != '' ? quote_wrap($misc_event['cirr_suppfa_cirrstat']) : quote_wrap('--');
			$data_row[get_element_label('dcp_mhoccur')] = $misc_event['dcp_mhoccur'] != '' ? quote_wrap($misc_event['dcp_mhoccur']) : quote_wrap('--');
			$data_row[get_element_label('livr_mhoccur')] = $misc_event['livr_mhoccur'] != '' ? quote_wrap($misc_event['livr_mhoccur']) : quote_wrap('--');
		}
	} else {
		$data_row[get_element_label('hcvgt_lborres')] = quote_wrap('--');
		$data_row[get_element_label('hcv_suppfa_hcvout')] = quote_wrap('--');
		$data_row[get_element_label('cirr_suppfa_cirrstat')] = quote_wrap('--');
		$data_row[get_element_label('dcp_mhoccur')] = quote_wrap('--');
		$data_row[get_element_label('livr_mhoccur')] = quote_wrap('--');
	}
	/**
	 * create csv row from $data_row and add to $table_csv
	 */
	$table_csv .= implode(',', $data_row) . "\n";
}
$headers = implode(',', $header_array) . "\n";
d($headers);
d($table_csv);
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, $export_filename, $debug);
} else {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}