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
$subjects = ''; // '' = ALL
$recode_pt = false; // setting true will overwrite all currently derived values.
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
$plugin_title = "Derive AEDECOD for all AEMODIFY";
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
$fields = array("ae_aemodify", "ae_aedecod");
$aefields = array("ae_aemodify", "ae_aedecod");
$ptfields = array("eot_aemodify", "eot_aedecod");
foreach ($tx_prefixes as $tx_prefix) {
    $txfields[] = $tx_prefix . '_aemodify';
    $txfields[] = $tx_prefix . "_aedecod";
}
$fields = array_merge($aefields, $ptfields, $txfields);
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
    foreach ($subject AS $event_id => $event) {
        /**
         * AE_AEDECOD
         */
        code_pt($project_id, $subject_id, $event_id, fix_case($event['ae_aemodify']), $event['ae_aedecod'], 'ae_aedecod', $debug, $recode_pt);
        if ($debug) {
            error_log("INFO (TESTING): Coded AE_AEDECOD {$event['ae_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event['ae_aemodify']}");
        }
        /**
         * EOT_AEDECOD
         */
        code_pt($project_id, $subject_id, $event_id, fix_case($event['eot_aemodify']), $event['eot_aedecod'], 'eot_aedecod', $debug, $recode_pt);
        if ($debug) {
            error_log("INFO (TESTING): Coded EOT_AEDECOD {$event['eot_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event['eot_aemodify']}");
        }
        /**
         * TXDRUG_AEDECOD
         */
        foreach ($tx_prefixes as $tx_prefix) {
            code_pt($project_id, $subject_id, $event_id, fix_case($event[$tx_prefix . '_aemodify']), $event[$tx_prefix . '_aedecod'], $tx_prefix . '_aedecod', $debug, $recode_pt);
            if ($debug) {
                error_log("INFO (TESTING): Coded " . strtoupper($tx_prefix) . "_AEDECOD {$event[$tx_prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event[$tx_prefix . '_aemodify']}");
            }
        }
    }
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}