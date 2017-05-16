<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 2/12/14
 * Time: 11:47 AM
 * Project: HCV-TARGET 2.0
 * Purpose: Provide an application for coding AEs and ConMeds
 */
$debug = false;
$recode_llt = false;
$recode_soc = false;
$recode_atc = false;
$timer_start = microtime(true);
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
 * $fields = array("dm_subjid", "ifn_oth_suppcm_cmncmpae", "rib_oth_suppcm_cmncmpae", "boc_oth_suppcm_cmncmpae", "tvr_oth_suppcm_cmncmpae", "sim_oth_suppcm_cmncmpae", "sof_oth_suppcm_cmncmpae", "eot_oth_suppds_ncmpae", "cm_cmindc");
 */
$fields = array("dm_subjid", "xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod", "xfsn_suppcm_indcsys", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
//$run_count = 1;
//while ($run_count <= 2) {
//  do stuff
//	$run_count++;
//}
$data = REDCap::getData('array', '', $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		/**
		 * CM_CMDECOD
		 */
		if (isset($event['xfsn_cmtrt']) && $event['xfsn_cmtrt'] != '') {
			$med = array();
			$med_result = db_query("SELECT DISTINCT drug_coded FROM _target_xfsn_coding WHERE drug_name = '" . prep($event['xfsn_cmtrt']) . "'");
			if ($med_result) {
				$med = db_fetch_assoc($med_result);
				if (isset($med['drug_coded']) && $med['drug_coded'] != '') {
					update_field_compare($subject_id, $project_id, $event_id, $med['drug_coded'], $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
					if ($debug) {
						error_log("INFO (TESTING): Coded Transfusion: subject=$subject_id, event=$event_id for CMTRT {$event['xfsn_cmtrt']}");
					}
					/**
					 * XFSN_SUPPCM_ATCNAME
					 * XFSN_SUPPCM_ATC2NAME
					 */
					code_atc_xfsn($project_id, $subject_id, $event_id, $med['drug_coded'], $event['xfsn_suppcm_atcname'], $event['xfsn_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("INFO (TESTING): Coded XFSN ATCs: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
					/**
					 * XFSN_SUPPCM_INDCOD
					 */
					code_llt($project_id, $subject_id, $event_id, fix_case($event['xfsn_cmindc']), '', $event['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
					if ($debug) {
						error_log("INFO (TESTING): Coded XFSN INDC: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
					/**
					 * XFSN_SUPPCM_INDCSYS
					 */
					code_bodsys($project_id, $subject_id, $event_id, get_single_field($subject_id, $project_id, $event_id, 'xfsn_suppcm_indcod', null), $event['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("INFO (TESTING): Coded XFSN INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['xfsn_suppcm_indcod']}");
					}
				}
			}
		} else {
			update_field_compare($subject_id, $project_id, $event_id, '', $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
		}
	}
}
$timer_stop = microtime(true);
$timer_time = number_format(($timer_stop - $timer_start), 2);
echo 'This page loaded in ', $timer_time / 60, ' minutes';