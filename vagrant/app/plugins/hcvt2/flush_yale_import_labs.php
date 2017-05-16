<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = true;
$subjects = array(); // = ALL
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
/**
 * find imported fields in non-imported forms
 */
function is_imported($var)
{
	if (strpos($var, '_im_') !== false) {
		return true;
	} else {
		return false;
	}
}

$subjects_result = db_query("SELECT DISTINCT record FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'dm_usubjid' AND left(value, 3) = '017'");
if ($subjects_result) {
	while ($subjects_row = db_fetch_array($subjects_result)) {
		$subjects[] = $subjects_row['record'];
	}
}
d($subjects);
//$subjects = '42';
/**
 * labs field names
 */
$change_message = 'Purging imported labs, standardizations and related derivations for site 017 (Yale) prior to REDI refresh for this site';
$forms = array('cbc_imported', 'cbc_im_standard', 'chemistry_imported', 'chemistry_im_standard', 'inr_imported', 'hcv_rna_imported', 'hcv_rna_im_standard', 'derived_values_baseline', 'derived_values');
//$forms = array('derived_values_baseline', 'derived_values');
foreach ($forms AS $form) {
	$fields = REDCap::getFieldNames($form);
	if (in_array($form, array('derived_values_baseline', 'derived_values'))) {
		$fields = array_filter($fields, "is_imported");
	}
	d($form, $fields);
	$data = REDCap::getData('array', $subjects, $fields);
	foreach ($data AS $subject_id => $subject) {
		foreach ($subject AS $event_id => $event) {
			foreach ($event AS $field => $value) {
				if ($value != '' && strpos($field, 'complete') === false) {
					update_field_compare($subject_id, $project_id, $event_id, '', $value, $field, $debug, $change_message);
				}
			}
		}
	}
}