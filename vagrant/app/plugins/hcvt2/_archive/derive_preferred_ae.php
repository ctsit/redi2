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
$timer_start = microtime(true);
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
 * MAIN
 */
$fields = array('ae_aedecod', 'ae_aebodsys', 'cm_suppcm_indcod', 'cm_suppcm_indcsys');
$data = REDCap::getData('array', '', $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		if ($event['ae_aedecod'] != '') {
			/**
			 * preferred llt query
			 */
			$pref_llt_result = db_query("SELECT llt_pref_name FROM _target_xlate_llt WHERE llt_name = '{$event['ae_aedecod']}'");
			if ($pref_llt_result) {
				while ($pref_llt_row = db_fetch_assoc($pref_llt_result)) {
					code_llt($project_id, $subject_id, $event_id, fix_case($pref_llt_row['llt_pref_name']), null, $event['ae_aedecod'], 'ae_aedecod', $debug, true);
					code_bodsys($project_id, $subject_id, $event_id, fix_case($pref_llt_row['llt_pref_name']), $event['ae_aebodsys'], 'ae_aebodsys', $debug, true);
					if ($debug) {
						error_log("INFO (TESTING): Recoded preferred AEDECOD {$pref_llt_row['llt_pref_name']}: subject=$subject_id, event=$event_id for AEDECOD {$event['ae_aedecod']}");
					}
				}
				db_free_result($pref_llt_result);
			}
		}
		if ($event['cm_suppcm_indcod'] != '') {
			/**
			 * preferred llt query
			 */
			$pref_llt_result = db_query("SELECT llt_pref_name FROM _target_xlate_llt WHERE llt_name = '{$event['cm_suppcm_indcod']}'");
			if ($pref_llt_result) {
				while ($pref_llt_row = db_fetch_assoc($pref_llt_result)) {
					code_llt($project_id, $subject_id, $event_id, fix_case($pref_llt_row['llt_pref_name']), null, $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, true);
					code_bodsys($project_id, $subject_id, $event_id, fix_case($pref_llt_row['llt_pref_name']), $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, true);
					if ($debug) {
						error_log("INFO (TESTING): Recoded preferred SUPPCM_INDCOD {$pref_llt_row['llt_pref_name']}: subject=$subject_id, event=$event_id for SUPPCM_INDCOD {$event['cm_suppcm_indcod']}");
					}
				}
				db_free_result($pref_llt_result);
			}
		}
	}
}
$timer_stop = microtime(true);
$timer_time = number_format(($timer_stop - $timer_start), 2);
echo 'This page loaded in ', $timer_time / 60, ' minutes';