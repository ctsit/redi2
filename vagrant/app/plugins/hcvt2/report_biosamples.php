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
$subjects = array(); // '' =  ALL
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
$export_filename = 'REPORT_BIOSPECIMENS';
$header_array = array();
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$header_array[] = get_element_label('dm_subjid');
$fields = array('dm_usubjid', 'dm_rfstdtc', 'eot_dsstdtc', 'hcv_suppfa_fudue', 'dis_suppds_funcmprsn', 'hcv_suppfa_nlgblrsn', 'dm_actarmcd');
foreach ($fields AS $field) {
	$header_array[] = quote_wrap($Proj->metadata[$field]['element_label']);
}
$data = REDCap::getData('array', $subjects, $fields, $Proj->firstEventId);
/*$regimen_data = REDCap::getData('array', $subjects, $regimen_fields);
d($regimen_data);*/
//$header_array[] = quote_wrap('Regimen');
/**
 * biospecimens
 */
$sample_fields = array('bs_bsstdtc');
$base_fields_array = array('sample_code' => 'bs_%_bscode', 'sample_type' => 'bs_%_bstype', 'sample_onhand' => 'bs_%_bsonhand');
for ($i = 1; $i <= 20; $i++) {
	foreach ($base_fields_array AS $base_key => $base_field) {
		$sample_fields[] = str_replace('%', $i, $base_field);
	}
}
$samples_data = REDCap::getData('array', $subjects, $sample_fields);
$sample_field_count = (count($sample_fields) - 1) / 3;
$aliquot_array = array();
foreach ($samples_data AS $sample_subject_id => $sample_subject) {
	foreach ($sample_subject AS $sample_event_id => $sample_event) {
		if ($sample_event['bs_bsstdtc'] != '') {
			for ($i = 1; $i <= $sample_field_count; $i++) {
				foreach ($base_fields_array AS $field_type => $field_name) {
					$$field_type = str_replace('%', $i, $field_name);
				}
				if (array_search('1', $sample_event[$sample_onhand]) !== false) {
					$aliquot_array[$sample_subject_id][$sample_event['bs_bsstdtc']][$sample_event[$sample_type]][] = $sample_type;
				}
			}
		}
	}
}
/**
 * get the greatest number of sample dates and types to set up $header_array
 */
$max_sample_row_count = 0;
$max_type_row_count = 0;
foreach ($aliquot_array AS $aliquot_subject_id => $aliquot_event) {
	d($aliquot_event);
	if (count($aliquot_event) >= $max_sample_row_count) {
		$max_sample_row_count = count($aliquot_event);
	}
	foreach ($aliquot_event AS $sample_type) {
		if (count($sample_type) >= $max_type_row_count) {
			$max_type_row_count = count($sample_type);
		}
	}
}
for ($i = 1; $i <= $max_sample_row_count; $i++) {
	$header_array[] = quote_wrap('Aliquot date ' . $i);
	$header_array[] = quote_wrap('SERUM ' . $i);
	$header_array[] = quote_wrap('WHOLE_BLOOD ' . $i);
	$header_array[] = quote_wrap('DNA ' . $i);
}
/**
 * treatment experience
 */
$treatment_exp_fields = array('pegifn_mhoccur', 'pegifn_suppmh_response', 'triple_mhoccur', 'triple_suppmh_cmdaa', 'triple_suppmh_response', 'nopegifn_mhoccur', 'daa_mhoccur', 'daa_suppmh_failtype', 'daa_oth_suppmh_failtype');
foreach ($treatment_exp_fields AS $field) {
	$header_array[] = quote_wrap($Proj->metadata[$field]['element_label']);
}
$treatment_exp_data = REDCap::getData('array', $subjects, $treatment_exp_fields, $Proj->firstEventId);
d($samples_data);
/**
 * subject characteristics
 */
$misc_fields = array('hcvgt_lborres', 'hcvgt_s_lborres', 'hcv_suppfa_hcvout', 'cirr_suppfa_cirrstat', 'cirr_suppfa_decomp', 'dcp_mhoccur', 'livr_mhoccur');
foreach ($misc_fields AS $field) {
	if ($field != 'hcvgt_s_lborres') {
		$header_array[] = quote_wrap($Proj->metadata[$field]['element_label']);
	}
}
$misc_data = REDCap::getData('array', $subjects, $misc_fields, $Proj->firstEventId);
$egfr_fields = array('egfr_lborres', 'egfr_im_lborres', 'egfr_lbblfl', 'egfr_im_lbblfl');
$egfr_data = REDCap::getData('array', $subjects, $egfr_fields);
foreach ($egfr_fields AS $field) {
	if ($field == 'egfr_lborres') {
		$header_array[] = quote_wrap('Baseline ' . $Proj->metadata[$field]['element_label']);
	}
}
/**
 * MAIN
 */
foreach ($data AS $subject_id => $subject) {
	if ($subjects != '') {
		d("SUBJECT #$subject_id");
	}
	/**
	 * SUBJECT-LEVEL vars
	 */
	$data_row = array();
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		$data_row[get_element_label('dm_subjid')] = $subject_id != '' ? quote_wrap($subject_id) : blanks();
		$data_row[get_element_label('dm_usubjid')] = $event['dm_usubjid'] != '' ? quote_wrap($event['dm_usubjid']) : blanks();
		$data_row[get_element_label('dm_rfstdtc')] = $event['dm_rfstdtc'] != '' ? quote_wrap($event['dm_rfstdtc']) : blanks();
		$data_row[get_element_label('eot_dsstdtc')] = $event['eot_dsstdtc'] != '' ? quote_wrap($event['eot_dsstdtc']) : blanks();
		$data_row[get_element_label('hcv_suppfa_fudue')] = $event['hcv_suppfa_fudue'] != '' ? quote_wrap($event['hcv_suppfa_fudue']) : blanks();
		$data_row[get_element_label('dis_suppds_funcmprsn')] = $event['dis_suppds_funcmprsn'] != '' ? quote_wrap($event['dis_suppds_funcmprsn']) : blanks();
		$data_row[get_element_label('hcv_suppfa_nlgblrsn')] = $event['hcv_suppfa_nlgblrsn'] != '' ? quote_wrap($event['hcv_suppfa_nlgblrsn']) : blanks();
		$data_row[get_element_label('dm_actarmcd')] = $event['dm_actarmcd'] != '' ? quote_wrap($event['dm_actarmcd']) : blanks();
	}
	/**
	 * treatment regimen
	 */
	/*$regimen = get_regimen($regimen_data[$subject_id]);
	if (!empty($regimen)) {
		$data_row['Regimen'] = quote_wrap($regimen['actarmcd']);
	} else {
		$data_row['Regimen'] = blanks();
	}*/
	/**
	 * biospecimens
	 * first, front-fill the fields for this subject with blanks
	 */
	for ($i = 1; $i <= $max_sample_row_count; $i++) {
		$data_row['Aliquot date ' . $i] = blanks();
		$data_row['SERUM ' . $i] = blanks();
		$data_row['WHOLE_BLOOD ' . $i] = blanks();
		$data_row['DNA ' . $i] = blanks();
	}
	$aliquots = array();
	/**
	 * get aliquot counts into an array for this subject
	 */
	if (isset($aliquot_array[$subject_id])) {
		foreach ($aliquot_array[$subject_id] AS $sample_date => $sample_event) {
			if ($subjects != '') {
				d($sample_event);
			}
			foreach ($sample_event AS $type => $barcode_fields) {
				$aliquots[$sample_date][$type] = count($barcode_fields);
			}
		}
		if ($subjects != '') {
			d($aliquots);
		}
	}
	$aliquot_count = 1;
	foreach ($aliquots AS $aliquot_date => $types) {
		$data_row['Aliquot date ' . $aliquot_count] = quote_wrap($aliquot_date);
		foreach ($types AS $aliquot_type => $sample_count) {
			$data_row[$aliquot_type . ' ' . $aliquot_count] = quote_wrap($sample_count);
		}
		$aliquot_count++;
	}
	/*if ($aliquots != '') {
		$data_row['Aliquots'] = quote_wrap($aliquots);
	} else {
		$data_row['Aliquots'] = blanks();
	}*/
	/**
	 * treatment exp
	 */
	if (isset($treatment_exp_data[$subject_id])) {
		foreach ($treatment_exp_data[$subject_id] AS $treatment_exp_event) {
			if ($subjects != '') {
				d($treatment_exp_event);
			}
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
			if ($subjects != '') {
				d($misc_event);
			}
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
				if ($subjects != '') {
					d($egfr_event);
				}
				$data_row['Baseline ' . get_element_label('egfr_lborres')] = $egfr_event['egfr_lborres'] != '' ? quote_wrap($egfr_event['egfr_lborres']) : blanks();
			} elseif ($egfr_event['egfr_im_lbblfl'] == 'Y') {
				if ($subjects != '') {
					d($egfr_event);
				}
				$data_row['Baseline ' . get_element_label('egfr_lborres')] = $egfr_event['egfr_im_lborres'] != '' ? quote_wrap($egfr_event['egfr_im_lborres']) : blanks();
			}
		}
	} else {
		$data_row['Baseline ' . get_element_label('egfr_lborres')] = blanks();
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