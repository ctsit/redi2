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
$subjects = array('740'); // '' = ALL
//$subjects = array('740', '775', '802', '1252', '1323', '1633', '2000', '2487', '2688', '3507', '3604', '3707'); // '' = ALL
$timer_start = microtime(true);
/**
 * includes
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
 * init vars
 */
$recode_llt = true;
$recode_pt = true;
$recode_soc = true;
$recode_msg = "Recoding Adverse events";
/**
 * $fields
 */
$fields = array("dm_subjid", "ae_aeterm", "ae_oth_aeterm", "ae_aemodify", "ae_aedecod", "ae_aebodsys");
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		/**
		 * AE_AEDECOD
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['ae_aeterm']), fix_case($event['ae_oth_aeterm']), $event['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt, $recode_msg);
		if ($debug) {
			error_log("DEBUG: Coded AE_AEMODIFY {$event['ae_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['ae_aeterm']} - {$event['ae_oth_aeterm']}");
		}
		/**
		 * AE_AEDECOD
		 */
		$aemodify = get_single_field($subject_id, $project_id, $event_id, 'ae_aemodify', '');
		code_pt($project_id, $subject_id, $event_id, fix_case($aemodify), $event['ae_aedecod'], 'ae_aedecod', $debug, $recode_pt, $recode_msg);
		if ($debug) {
			error_log("DEBUG: Coded AE_AEDECOD {$event['ae_aedecod']}: subject=$subject_id, event=$event_id for AE {$aemodify}");
		}
		/**
		 * AE_AEBODSYS
		 */
		$aedecod = get_single_field($subject_id, $project_id, $event_id, 'ae_aedecod', '');
		code_bodsys($project_id, $subject_id, $event_id, $aedecod, $event['ae_aebodsys'], 'ae_aebodsys', $debug, $recode_soc, $recode_msg);
		if ($debug) {
			error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event['ae_aedecod']}");
		}
		$timer_stop = microtime(true);
		$timer_time = number_format(($timer_stop - $timer_start), 2);
		if ($debug) {
			error_log("DEBUG: This DET action (Code AE) took $timer_time seconds");
		}
	}
}
$timer_stop = microtime(true);
$timer_time = number_format(($timer_stop - $timer_start), 2);
echo 'This page loaded in ', $timer_time / 60, ' minutes';