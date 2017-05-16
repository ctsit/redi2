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
/**
 * vars
 */
/**
 * labs field names
 */
$forms = array('cbc' => 'cbc', 'chem' => 'chemistry', 'inr' => 'inr', 'hcv' => 'hcv_rna_results');
$forms_std = array('cbc' => 'cbc_standard', 'chemistry' => 'chemistry_standard', 'hcv' => 'hcv_rna_standard');
/**
 * WORKING SET OF STUDY_IDs, limited to UF (site 001)
 */
$subject_sql = "SELECT DISTINCT record AS subjid, value AS usubjid
FROM redcap_data
WHERE project_id = '$project_id'
AND record != ''
AND field_name = 'dm_usubjid'
AND SUBSTRING(value,1,3) = '001'
ORDER BY abs(record) ASC";
$subject_result = db_query($subject_sql);
if ($subject_result) {
	while ($subject_row = db_fetch_assoc($subject_result)) {
		$subjid = $subject_row['subjid'];
		if ($debug) {
			show_var($subjid, 'SUBJ', 'green');
		}
		foreach ($forms as $prefix => $form) {
			$fields = REDCap::getFieldNames($form);
			$data = REDCap::getData('array', $subjid, $fields);
			foreach ($data[$subjid] AS $event_id => $event) {
				if ($event[$prefix . "_nximport"] == 'Y') {
					if ($debug) {
						//show_var($event);
					}
					/**
					 * delete each row and log it
					 */
					foreach ($event AS $field => $value) {
						update_field_compare($subjid, $project_id, $event_id, '', $value, $field, $debug);
					}
					foreach ($forms_std as $form_key => $form_std) {
						show_var($form_std);
						if ($form_key == $form) {
							$fields_std = REDCap::getFieldNames($form_std);
							$data_std = REDCap::getData('array', $subjid, $fields_std, $event_id);
							foreach ($data_std AS $subject_std) {
								foreach ($subject_std AS $event_std) {
									foreach ($event_std AS $field_std => $value_std) {
										update_field_compare($subjid, $project_id, $event_id, '', $value_std, $field_std, $debug);
									}
								}
							}
						}
					}
				}
			}
		}
	}
}