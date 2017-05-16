<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 8/27/13
 * Time: 4:06 PM
*/
$debug = false;
$subjects = ''; // '' = ALL
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict use of this plugin to the appropriate project
 */
$allowed_pid = '26';
REDCap::allowProjects($allowed_pid);
/**
 * project metadata
 */
$project = new Project();
$baseline_event_id = $project->firstEventId;
$plugin_title = "Derive values";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * VARS
 */
$dm_array = array('dm_rfstdtc', 'dm_brthyr', 'dm_race', 'dm_sex', 'age_suppvs_age', 'dis_suppfa_txendt', 'dis_dsstdy', 'hcv_suppfa_svr12dt', 'hcv_suppfa_svr24dt');
$tx_array = array('ifn_cmstdtc', 'rib_cmstdtc', 'tvr_cmstdtc', 'sof_cmstdtc', 'sim_cmstdtc', 'boc_cmstdtc', 'dcv_cmstdtc', 'hvn_cmstdtc', 'vpk_cmstdtc', 'dbv_cmstdtc');
$endt_fields = array('ifn_cmendtc', 'ifn_suppcm_cmtrtout', 'rib_cmendtc', 'rib_suppcm_cmtrtout', 'boc_cmendtc', 'boc_suppcm_cmtrtout', 'tvr_cmendtc', 'tvr_suppcm_cmtrtout', 'sim_cmendtc', 'sim_suppcm_cmtrtout', 'sof_cmendtc', 'sof_suppcm_cmtrtout', 'dcv_cmendtc', 'dcv_suppcm_cmtrtout', 'hvn_cmendtc', 'hvn_suppcm_cmtrtout', 'vpk_cmendtc', 'vpk_suppcm_cmtrtout', 'dbv_cmendtc', 'dbv_suppcm_cmtrtout');
$fields = array_merge($dm_array, $tx_array, $bmi_array, $daa_array, $chem_fields, $plat140_fields, $egfr_fields, $cirr_fields, $endt_fields, $hcv_fields);
/**
 * MAIN
 */
$timer['start_data'] = microtime(true);
$data = REDCap::getData('array', $subjects, $fields);
$timer['start_data_loop'] = microtime(true);
foreach ($data AS $subject_id => $subject) {
	if ($debug) {
		show_var($subject_id, 'Subject ID', 'blue');
	}
	$fields = array();
	$data = array();
	$stack = array();
	$start_stack = array();
	unset($tx_start_date, $stop_date);
	$end_values = array();
	/**
	 * EVENT LEVEL ACTIONS
	 */
	foreach ($subject AS $event_id => $event) {
		/**
		 * build dm_rfstdtc array
		 */
		foreach ($tx_array AS $tx_start) {
			if ($event[$tx_start] != '') {
				$start_stack[] = $event[$tx_start];
			}
		}
		/**
		 * build entdtc array
		 */
		foreach ($endt_fields AS $endt_field) {
			$end_values[$event_id][$endt_field] = $event[$endt_field];
		}
	}
	/**
	 * SUBJECT LEVEL
	 */
	/**
	 * All with dependency on start date
	 */
	rsort($start_stack);
	$tx_start_date = array_pop($start_stack);
	if (isset($tx_start_date)) {
		/**
		 * Date of last dose of HCV treatment or Treatment stop date
		 * dis_suppfa_txendt
		 */
		$endt_prefix_array = array('ifn', 'rib', 'boc', 'tvr', 'sim', 'sof', 'dcv', 'hvn', 'vpk', 'dbv');
		$stack = array();
		foreach ($endt_prefix_array AS $endt_prefix) {
			foreach ($end_values AS $event) {
				if ($event[$endt_prefix . '_cmendtc'] != '' && ($event[$endt_prefix . '_suppcm_cmtrtout'] == 'COMPLETE') || $event[$endt_prefix . '_suppcm_cmtrtout'] == 'PREMATURELY_DISCONTINUED') {
					if ($debug) {
						show_var($event, 'PREFIX ' . $endt_prefix, 'red');
					}
					$stack[] = $event[$endt_prefix . '_cmendtc'];
				}
			}
		}
		sort($start_stack);
		sort($stack);
		if ($debug) {
			show_var($start_stack, 'START STACK sort', 'red');
			show_var($stack, 'STACK sort', 'red');
		}
		$last_date_in_start_stack = array_pop($start_stack);
		$last_date_in_stack = array_pop($stack);
		$stop_date = $last_date_in_stack <= $last_date_in_start_stack ? null : $last_date_in_stack;
		update_field_compare($subject_id, $project_id, $baseline_event_id, $stop_date, $subject[$baseline_event_id]['dis_suppfa_txendt'], 'dis_suppfa_txendt', $debug);

		/**
		 * HCV treatment duration
		 * dm_rfstdtc, dis_suppfa_txendt, dis_dsstdy
		 */
		if (isset($stop_date)) {
			$tx_start_date_obj = new DateTime($tx_start_date);
			$tx_stop_date_obj = new DateTime($stop_date);
			$tx_duration = $tx_start_date_obj->diff($tx_stop_date_obj);
			$dis_dsstdy = $tx_duration->format('%R%a') + 1;
			update_field_compare($subject_id, $project_id, $baseline_event_id, $dis_dsstdy, $subject[$baseline_event_id]['dis_dsstdy'], 'dis_dsstdy', $debug);
		}
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;