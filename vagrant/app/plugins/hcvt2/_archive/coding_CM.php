<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 2/12/14
 * Time: 11:47 AM
 * Project: HCV-TARGET 2.0
 * Purpose: Provide an application for coding AEs and ConMeds
 */
$debug = true;
/**
 * includes
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
 * query to find ATC name for conmed
 */
$atc_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
(SELECT drug_name, med_prod_id FROM _whodrug_mp_us) drug
LEFT JOIN
(SELECT med_prod_id, atc_code FROM _whodrug_thg) mp_atc ON TRIM(LEADING '0' FROM drug.med_prod_id) = mp_atc.med_prod_id
LEFT JOIN
(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
LEFT JOIN
(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
/**
 * $fields = array("dm_subjid", "ifn_oth_suppcm_cmncmpae", "rib_oth_suppcm_cmncmpae", "boc_oth_suppcm_cmncmpae", "tvr_oth_suppcm_cmncmpae", "sim_oth_suppcm_cmncmpae", "sof_oth_suppcm_cmncmpae", "eot_oth_suppds_ncmpae", "cm_cmindc");
 */
$fields = array("dm_subjid", "cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod", "cm_suppcm_indcsys", "cm_suppcm_atcname", "cm_suppcm_atc2name");
$data = REDCap::getData('array', null, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		/**
		 * CM_CMDECOD
		 */
		$med = array();
		$med_result = db_query("SELECT DISTINCT drug_name FROM _whodrug_mp_us WHERE drug_name = '{$event['cm_cmtrt']}'");
		if ($med_result) {
			$med = db_fetch_assoc($med_result);
			if ($event['cm_cmdecod'] == '' && isset($med['drug_name']) && $med['drug_name'] != '') {
				update_field_compare($event['dm_subjid'], $project_id, $event_id, $med['drug_name'], $event['cm_cmdecod'], 'cm_cmdecod', $debug);
			}
		}
		/**
		 * CM_SUPPCM_INDCOD
		 */
		code_llt($project_id, $event['dm_subjid'], $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug);
		/**
		 * CM_SUPPCM_INDCSYS
		 */
		code_bodsys($project_id, $event['dm_subjid'], $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug);
		/**
		 * CM_SUPPCM_ATCNAME, CM_SUPPCM_ATC2NAME
		 */
		$atcname = array();
		$atcname_result = db_query($atc_query . " WHERE drug.drug_name = '{$event['cm_cmdecod']}'");
		if ($atcname_result) {
			$atcname = db_fetch_assoc($atcname_result);
			if ($event['cm_suppcm_atcname'] == '' && isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
				update_field_compare($event['dm_subjid'], $project_id, $event_id, $atcname['atc_name'], $event['cm_suppcm_atcname'], 'cm_suppcm_atcname', $debug);
			}
			if ($event['cm_suppcm_atc2name'] == '' && isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
				update_field_compare($event['dm_subjid'], $project_id, $event_id, $atcname['atc2_name'], $event['cm_suppcm_atc2name'], 'cm_suppcm_atc2name', $debug);
			}
		}
	}
}