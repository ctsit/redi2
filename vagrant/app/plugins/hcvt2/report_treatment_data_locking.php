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
//$subjects = array("1645", "2620", "3512", "3890", "619", "902", "1579", "3916", "4702", "2537", "3554", "352", "782", "398", "178", "1462", "293", "2099", "3868", "1595", "4443", "1776", "2904", "936", "2476", "3437", "3802", "913", "3522", "122-49", "2207", "2208", "2790");
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
Kint::enabled($debug);
/**
 * INIT VARS
 */
global $Proj;
$table_csv = "";
$export_filename = 'REPORT_TREATMENT_STATUS';
$header_array = array();
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$header_array[] = get_element_label('dm_subjid');
$fields = array('dm_usubjid', 'dm_rfstdtc', 'eot_dsstdtc', 'dis_dsstdy', 'eot_dsterm', 'fund_nxenrsc', 'fund_nxltfrsc', 'fund_nxsrmrsc', 'fund_nxsrmfail', 'fund_nxdnarsc', 'hcv_suppfa_fudue', 'dis_suppds_funcmprsn', 'hcv_suppfa_nlgblrsn', 'dm_actarmcd');
foreach ($fields AS $field) {
	$header_array[] = quote_wrap(get_element_label($field));
}
$data = REDCap::getData('array', $subjects, $fields, $Proj->firstEventId);
$header_array[] = quote_wrap('Fibrosis locked?');
$header_array[] = quote_wrap('AE locked?');
$treatment_exp_fields = array('pegifn_mhoccur', 'pegifn_suppmh_response', 'triple_mhoccur', 'triple_suppmh_cmdaa', 'triple_suppmh_response', 'nopegifn_mhoccur', 'daa_mhoccur', 'daa_suppmh_failtype', 'daa_oth_suppmh_failtype');
foreach ($treatment_exp_fields AS $field) {
	$header_array[] = quote_wrap(get_element_label($field));
}
$treatment_exp_data = REDCap::getData('array', $subjects, $treatment_exp_fields, $Proj->firstEventId);
$misc_fields = array('hcvgt_lborres', 'hcvgt_s_lborres', 'hcv_suppfa_hcvout', 'cirr_suppfa_cirrstat', 'cirr_suppfa_decomp', 'dcp_mhoccur', 'livr_mhoccur');
foreach ($misc_fields AS $field) {
	if ($field != 'hcvgt_s_lborres') {
		$header_array[] = quote_wrap(get_element_label($field));
	}
}
$misc_data = REDCap::getData('array', $subjects, $misc_fields, $Proj->firstEventId);
$egfr_fields = array('egfr_lborres', 'egfr_im_lborres', 'egfr_lbblfl', 'egfr_im_lbblfl');
$egfr_data = REDCap::getData('array', $subjects, $egfr_fields);
foreach ($egfr_fields AS $field) {
	if ($field == 'egfr_lborres') {
		$header_array[] = quote_wrap('Baseline ' . get_element_label($field));
	}
}
$hcvrna_fields = array('hcv_lbblfl', 'hcv_lbstresn', 'hcv_im_lbblfl', 'hcv_im_lbstresn');
$hcvrna_data = REDCap::getData('array', $subjects, $hcvrna_fields);
foreach ($hcvrna_fields AS $field) {
	if ($field == 'hcv_lbstresn') {
		$header_array[] = quote_wrap('Baseline ' . get_element_label($field));
	}
}
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
		$data_row[get_element_label('dm_subjid')] = $subject_id != '' ? quote_wrap($subject_id) : blanks();
		$data_row[get_element_label('dm_usubjid')] = $event['dm_usubjid'] != '' ? quote_wrap($event['dm_usubjid']) : blanks();
		$data_row[get_element_label('dm_rfstdtc')] = $event['dm_rfstdtc'] != '' ? quote_wrap($event['dm_rfstdtc']) : blanks();
		$data_row[get_element_label('eot_dsstdtc')] = $event['eot_dsstdtc'] != '' ? quote_wrap($event['eot_dsstdtc']) : blanks();
		$data_row[get_element_label('dis_dsstdy')] = $event['dis_dsstdy'] != '' ? quote_wrap($event['dis_dsstdy']) : blanks();
		$data_row[get_element_label('eot_dsterm')] = $event['eot_dsterm'] != '' ? quote_wrap($event['eot_dsterm']) : blanks();
		$data_row[get_element_label('fund_nxenrsc')] = $event['fund_nxenrsc'] != '' ? quote_wrap($event['fund_nxenrsc']) : blanks();
		$data_row[get_element_label('fund_nxltfrsc')] = $event['fund_nxltfrsc'] != '' ? quote_wrap($event['fund_nxltfrsc']) : blanks();
		$data_row[get_element_label('fund_nxsrmrsc')] = $event['fund_nxsrmrsc'] != '' ? quote_wrap($event['fund_nxsrmrsc']) : blanks();
		$data_row[get_element_label('fund_nxsrmfail')] = $event['fund_nxsrmfail'] != '' ? quote_wrap($event['fund_nxsrmfail']) : blanks();
		$data_row[get_element_label('fund_nxdnarsc')] = $event['fund_nxdnarsc'] != '' ? quote_wrap($event['fund_nxdnarsc']) : blanks();
		$data_row[get_element_label('hcv_suppfa_fudue')] = $event['hcv_suppfa_fudue'] != '' ? quote_wrap($event['hcv_suppfa_fudue']) : blanks();
		$data_row[get_element_label('dis_suppds_funcmprsn')] = $event['dis_suppds_funcmprsn'] != '' ? quote_wrap($event['dis_suppds_funcmprsn']) : blanks();
		$data_row[get_element_label('hcv_suppfa_nlgblrsn')] = $event['hcv_suppfa_nlgblrsn'] != '' ? quote_wrap($event['hcv_suppfa_nlgblrsn']) : blanks();
		$data_row[get_element_label('dm_actarmcd')] = $event['dm_actarmcd'] != '' ? quote_wrap($event['dm_actarmcd']) : blanks();
	}
	/**
	 * fibrosis form locked?
	 */
	$fibro_result = db_query("SELECT IF (username IS NULL, 'N', 'Y') AS fibro_locked FROM `redcap_locking_data` WHERE form_name = 'fibrosis_staging' AND project_id = '$project_id' AND record = '$subject_id'");
	if ($fibro_result) {
		$fibro_locked = db_result($fibro_result, 0, 'fibro_locked');
		if (!isset($fibro_locked) || $fibro_locked == '') {
			$data_row['Fibrosis locked?'] = quote_wrap('N');
		} else {
			$data_row['Fibrosis locked?'] = quote_wrap($fibro_locked);
		}
	}
	/**
	 * adverse_events form locked?
	 */
	$have_ae_lock = false;
	$ae_result = db_query("SELECT IF (username IS NULL, 'N', 'Y') AS ae_locked FROM `redcap_locking_data` WHERE form_name = 'adverse_events' AND project_id = '$project_id' AND record = '$subject_id'");
	if ($ae_result) {
		while ($ae_locked_row = db_fetch_assoc($ae_result)) {
			if ($ae_locked_row['ae_locked'] == 'Y') {
				$have_ae_lock = true;
			}
		}
		if (!$have_ae_lock) {
			$data_row['AE locked?'] = quote_wrap('N');
		} else {
			$data_row['AE locked?'] = quote_wrap('Y');
		}
	}
	/**
	 * treatment exp
	 */
	if (isset($treatment_exp_data[$subject_id])) {
		foreach ($treatment_exp_data[$subject_id] AS $treatment_exp_event) {
			d($treatment_exp_event);
			$data_row[get_element_label('pegifn_mhoccur')] = $treatment_exp_event['pegifn_mhoccur'] != '' ? quote_wrap($treatment_exp_event['pegifn_mhoccur']) : blanks();
			$data_row[get_element_label('pegifn_suppmh_response')] = $treatment_exp_event['pegifn_suppmh_response'] != '' ? quote_wrap($treatment_exp_event['pegifn_suppmh_response']) : blanks();
			$data_row[get_element_label('triple_mhoccur')] = $treatment_exp_event['triple_mhoccur'] != '' ? quote_wrap($treatment_exp_event['triple_mhoccur']) : blanks();
			$data_row[get_element_label('triple_suppmh_cmdaa')] = $treatment_exp_event['triple_suppmh_cmdaa'] != '' ? quote_wrap($treatment_exp_event['triple_suppmh_cmdaa']) : blanks();
			$data_row[get_element_label('triple_suppmh_response')] = $treatment_exp_event['triple_suppmh_response'] != '' ? quote_wrap($treatment_exp_event['triple_suppmh_response']) : blanks();
			$data_row[get_element_label('nopegifn_mhoccur')] = $treatment_exp_event['nopegifn_mhoccur'] != '' ? quote_wrap($treatment_exp_event['nopegifn_mhoccur']) : blanks();
			$data_row[get_element_label('daa_mhoccur')] = $treatment_exp_event['daa_mhoccur'] != '' ? quote_wrap($treatment_exp_event['daa_mhoccur']) : blanks();
			$data_row[get_element_label('daa_suppmh_failtype')] = !empty($treatment_exp_event['daa_suppmh_failtype']) ? quote_wrap(implode(', ', array_keys($treatment_exp_event['daa_suppmh_failtype'], '1'))) : blanks();
			$data_row[get_element_label('daa_oth_suppmh_failtype')] = $treatment_exp_event['daa_oth_suppmh_failtype'] != '' ? quote_wrap($treatment_exp_event['daa_oth_suppmh_failtype']) : blanks();
		}
	} else {
		$data_row[get_element_label('pegifn_mhoccur')] = blanks();
		$data_row[get_element_label('pegifn_suppmh_response')] = blanks();
		$data_row[get_element_label('triple_mhoccur')] = blanks();
		$data_row[get_element_label('triple_suppmh_cmdaa')] = blanks();
		$data_row[get_element_label('triple_suppmh_response')] = blanks();
		$data_row[get_element_label('nopegifn_mhoccur')] = blanks();
		$data_row[get_element_label('daa_mhoccur')] = blanks();
		$data_row[get_element_label('daa_suppmh_failtype')] = blanks();
		$data_row[get_element_label('daa_oth_suppmh_failtype')] = blanks();
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
			$data_row[get_element_label('hcvgt_lborres')] = $misc_event['hcvgt_lborres'] != '' ? quote_wrap($misc_event['hcvgt_lborres']) : blanks();
			$data_row[get_element_label('hcv_suppfa_hcvout')] = $misc_event['hcv_suppfa_hcvout'] != '' ? quote_wrap($misc_event['hcv_suppfa_hcvout']) : blanks();
			$data_row[get_element_label('cirr_suppfa_cirrstat')] = $misc_event['cirr_suppfa_cirrstat'] != '' ? quote_wrap($misc_event['cirr_suppfa_cirrstat']) : blanks();
			$data_row[get_element_label('cirr_suppfa_decomp')] = $event['cirr_suppfa_decomp'] != '' ? quote_wrap($event['cirr_suppfa_decomp']) : blanks();
			$data_row[get_element_label('dcp_mhoccur')] = $misc_event['dcp_mhoccur'] != '' ? quote_wrap($misc_event['dcp_mhoccur']) : blanks();
			$data_row[get_element_label('livr_mhoccur')] = $misc_event['livr_mhoccur'] != '' ? quote_wrap($misc_event['livr_mhoccur']) : blanks();
		}
	} else {
		$data_row[get_element_label('hcvgt_lborres')] = blanks();
		$data_row[get_element_label('hcv_suppfa_hcvout')] = blanks();
		$data_row[get_element_label('cirr_suppfa_cirrstat')] = blanks();
		$data_row[get_element_label('cirr_suppfa_decomp')] = blanks();
		$data_row[get_element_label('dcp_mhoccur')] = blanks();
		$data_row[get_element_label('livr_mhoccur')] = blanks();
	}
	/**
	 * add baseline eGFR
	 */
	if (isset($egfr_data[$subject_id])) {
		foreach ($egfr_data[$subject_id] AS $egfr_event) {
			if ($egfr_event['egfr_lbblfl'] == 'Y') {
				d($egfr_event);
				$data_row['Baseline ' . get_element_label('egfr_lborres')] = $egfr_event['egfr_lborres'] != '' ? quote_wrap($egfr_event['egfr_lborres']) : blanks();
			} elseif ($egfr_event['egfr_im_lbblfl'] == 'Y') {
				d($egfr_event);
				$data_row['Baseline ' . get_element_label('egfr_lborres')] = $egfr_event['egfr_im_lborres'] != '' ? quote_wrap($egfr_event['egfr_im_lborres']) : blanks();
			} else {
				$data_row['Baseline ' . get_element_label('egfr_lborres')] = blanks();
			}
		}
	} else {
		$data_row['Baseline ' . get_element_label('egfr_lborres')] = blanks();
	}
	/**
	 * add baseline HCV RNA
	 */
	if (isset($hcvrna_data[$subject_id])) {
		foreach ($hcvrna_data[$subject_id] AS $hcvrna_event) {
			if ($hcvrna_event['hcv_lbblfl'] == 'Y') {
				d($hcvrna_event);
				$data_row['Baseline ' . get_element_label('hcv_lbstresn')] = $hcvrna_event['hcv_lbstresn'] != '' ? quote_wrap($hcvrna_event['hcv_lbstresn']) : blanks();
			} elseif ($hcvrna_event['hcv_im_lbblfl'] == 'Y') {
				d($hcvrna_event);
				$data_row['Baseline ' . get_element_label('hcv_lbstresn')] = $hcvrna_event['hcv_im_lbstresn'] != '' ? quote_wrap($hcvrna_event['hcv_im_lbstresn']) : blanks();
			}
		}
	} else {
		$data_row['Baseline ' . get_element_label('hcv_lbstresn')] = blanks();
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