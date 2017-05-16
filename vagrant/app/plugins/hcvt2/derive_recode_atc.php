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
$subjects = ''; // '' = ALL
$recode_atc = true;
$recode_pt = true;
$recode_soc = true;
$timer = array();
$timer['start'] = microtime(true);
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
 * project metadata
 */
$project = new Project();
$baseline_event_id = $project->firstEventId;
$plugin_title = "RECODE all ATCs and Indications.";
/**
 * plugin
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$atc_reset_val = '';
$delete_message = 'Flushing derived ATC data prior to refresh';
$flush_fields = array('cm_suppcm_atcname', 'cm_suppcm_atc2name');
$flush_data = REDCap::getData('array', $subjects, $flush_fields);
$update_message = 'Refresh ATC, Indication and Bodsys coding';
$fields = array('cm_cmoccur', 'cm_cmdecod', 'cm_cmindc', 'cm_oth_cmindc', 'cm_suppcm_indcmodf', 'cm_suppcm_indcod', 'cm_suppcm_indcsys', 'cm_suppcm_atcname', 'cm_suppcm_atc2name');
$data = REDCap::getData('array', $subjects, $fields);
foreach ($flush_data AS $subject_id => $flush_subject) {
	foreach ($flush_subject AS $event_id => $event) {
		foreach ($flush_fields as $flush_field) {
			if ($event[$flush_field] != '') {
				$flush_sql = "DELETE FROM redcap_data WHERE project_id = '$project_id' AND record = '$subject_id' AND event_id = '$event_id' AND field_name = '$flush_field'";
				if (!$debug) {
					if (db_query($flush_sql)) {
						target_log_event($flush_sql, 'redcap_data', 'delete', $subject_id, "$flush_field = ''", 'Delete record', $delete_message, $project_id, $event_id);
					} else {
						error_log(db_error() . ': ' . $flush_sql);
						echo db_error() . "<br />" . $flush_sql;
					}
				} else {
					show_var($flush_sql);
					error_log("DEBUG: " . $flush_sql);
				}

			}
		}
 	}
	foreach ($data[$subject_id] AS $subject) {
		foreach ($subject AS $event_id => $event) {
			if ($event['cm_cmoccur'] == 'Y') {
				code_pt($project_id, $subject_id, $event_id, $event['cm_suppcm_indcmodf'], $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt, $message);
				if ($debug) {
					error_log("DEBUG: Coded INDC PT: subject=$subject_id, event=$event_id for INDICATION {$event['cm_suppcm_indcmodf']}");
				}
				code_bodsys($project_id, $subject_id, $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc, $message);
				if ($debug) {
					error_log("DEBUG: Coded INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['cm_suppcm_indcod']}");
				}
				code_atc_soc($project_id, $subject_id, $event_id, $event['cm_cmdecod'], $event['cm_suppcm_atcname'], $event['cm_suppcm_atc2name'], $debug, $recode_atc, $update_message);
				if ($debug) {
					error_log("DEBUG: Coded ATCs: subject=$subject_id, event=$event_id for CONMED {$event['cm_cmdecod']}");
				}
			}
		}
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}