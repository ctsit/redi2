<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 1/7/15
 * Time: 3:40 PM
 * Project: HCV-TARGET 2.0
 * Purpose: Derive CDISC variable AEACN
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
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
/**
 *
 */
$action_priority = array(4 => 'DRUG_WITHDRAWN', 3 => 'DRUG_INTERRUPTED', 2 => 'DOSE_REDUCED', 1 => 'DOSE_INCREASED', 0 => '');
$ae_fields = array('ae_aedecod', 'ae_aestdtc', 'ae_suppae_aeregmod', 'ifn_suppae_ifnacnd', 'rib_suppae_ribacnd', 'daa_suppae_daaacnd');
$data = REDCap::getData('array', $subjects, $ae_fields);
/**
 * get EX drug data:
 * _aedecod = AE
 * _cmadj = reason for modification (ADVERSE_EVENT)
 * _cmtrtout = ACN
 * When xxx_suppcm_cmadj = 'ADVERSE_EVENT' and ae_aedecod = xxx_aedecod, the xxx_suppcm_cmtrtout is a candidate for AE_AEACN
 */
$tx_fields = array(
	'ifn_cmstdtc', 'ifn_cmendtc', 'ifn_aedecod', 'ifn_suppcm_cmadj', 'ifn_esc_suppcm_cmadj', 'ifn_suppcm_cmtrtout', 'ifn_cmdose', 'ifn_suppae_ifnacn',
	'rib_cmstdtc', 'rib_cmendtc', 'rib_aedecod', 'rib_suppcm_cmadj', 'rib_esc_suppcm_cmadj', 'rib_suppcm_cmtrtout', 'rib_cmdose', 'rib_suppae_ribacn',
	'boc_cmstdtc', 'boc_cmendtc', 'boc_aedecod', 'boc_suppcm_cmadj', 'boc_suppcm_cmtrtout', 'boc_cmdose', 'boc_suppae_daaacn',
	'tvr_cmstdtc', 'tvr_cmendtc', 'tvr_aedecod', 'tvr_suppcm_cmadj', 'tvr_suppcm_cmtrtout', 'tvr_cmdose', 'tvr_suppae_daaacn',
	'sim_cmstdtc', 'sim_cmendtc', 'sim_aedecod', 'sim_suppcm_cmadj', 'sim_suppcm_cmtrtout', 'sim_cmdose', 'sim_suppae_daaacn',
	'sof_cmstdtc', 'sof_cmendtc', 'sof_aedecod', 'sof_suppcm_cmadj', 'sof_suppcm_cmtrtout', 'sof_cmdose', 'sof_suppae_daaacn',
	'dcv_cmstdtc', 'dcv_cmendtc', 'dcv_aedecod', 'dcv_suppcm_cmadj', 'dcv_suppcm_cmtrtout', 'dcv_cmdose', 'dcv_suppae_daaacn',
	'hvn_cmstdtc', 'hvn_cmendtc', 'hvn_aedecod', 'hvn_suppcm_cmadj', 'hvn_suppcm_cmtrtout', 'hvn_cmdose', 'hvn_suppae_daaacn',
	'vpk_cmstdtc', 'vpk_cmendtc', 'vpk_aedecod', 'vpk_suppcm_cmadj', 'vpk_suppcm_cmtrtout', 'vpk_cmdose', 'vpk_suppae_daaacn',
	'dbv_cmstdtc', 'dbv_cmendtc', 'dbv_aedecod', 'dbv_suppcm_cmadj', 'dbv_suppcm_cmtrtout', 'dbv_cmdose', 'dbv_suppae_daaacn'
);
$tx_data = REDCap::getData('array', $subjects, $tx_fields);
$actions = array();
$treatments = array();
/**
 * find subject AEs with matching AEs in TX drugs
 * get the event_id of the AE (so we can code the matching AEACN)
 */
foreach ($data AS $subject_id => $subject) {
	$doses = array();
	foreach ($subject AS $ae_event_id => $ae_event) {
		if ($ae_event['ae_aedecod'] != '') {
			foreach ($tx_data[$subject_id] AS $tx_event_id => $tx_event) {
				$found_tx_array = array_keys($tx_event, $ae_event['ae_aedecod']);
				foreach ($found_tx_array as $found_tx) {
					$prefix = substr($found_tx, 0, strpos($found_tx, '_'));
					/**
					 * if the AE has been flagged as modifying TX dose for the current treatment,
					 */
					if (($prefix == 'ifn' && $ae_event['ifn_suppae_ifnacnd'] == 'Y') || ($prefix == 'rib' && $ae_event['rib_suppae_ribacnd'] == 'Y') || (!in_array($prefix, array('ifn', 'rib')) && $ae_event['daa_suppae_daaacnd'] == 'Y')) {
						/**
						 * ... and if this treatment was adjusted due to AE, and the AE caused treatment to be modified,
						 * and the AE == treatment AE, add data to the $actions array for processing
						 */
						if (($tx_event[$prefix . '_suppcm_cmadj'] == 'ADVERSE_EVENT' || $tx_event[$prefix . '_esc_suppcm_cmadj'] == 'ADVERSE_EVENT') && $ae_event['ae_suppae_aeregmod'] == 'Y' && $ae_event['ae_aedecod'] == $tx_event[$prefix . '_aedecod']) {
							if (in_array($prefix, array('ifn', 'rib'))) {
								$actions[$subject_id][$tx_event_id][$ae_event_id][$prefix]['txacn'] = $tx_event[$prefix . '_suppae_' . $prefix . 'acn'];
							} else {
								$actions[$subject_id][$tx_event_id][$ae_event_id][$prefix]['txacn'] = $tx_event[$prefix . '_suppae_daaacn'];
							}
							//$actions[$subject_id][$tx_event_id][$ae_event_id][$prefix]['txacn'] = $tx_event[$prefix . '_suppcm_cmtrtout'];
							$actions[$subject_id][$tx_event_id][$ae_event_id][$prefix]['enddate'] = $tx_event[$prefix . '_cmendtc'];
							if ($debug) {
								$actions[$subject_id][$tx_event_id][$ae_event_id][$prefix]['ae'] = $tx_event[$prefix . '_aedecod'];
							}
							if ($debug && $subjects != '') {
								d($ae_event['ae_aedecod']);
							}
						}
					}
				}
			}
		}
	}
}
/**
 * process actions
 */
$sorter = new FieldSorter('enddate');
uasort($actions, array($sorter, "cmp"));
foreach ($actions AS $subject_id => $subject) {
	if ($debug) {
		d($subject_id);
		if ($subjects != '') {
			d($subject);
			d($data[$subject_id]);
		}
	}
	/**
	 * pop the latest of these off the $subject array and set the ae_aeacn for the AE
	 */
	foreach (array_pop($subject) AS $ae_event_id => $ae_event) {
		foreach ($ae_event AS $tx_prefix => $ae_acn) {
			d($tx_prefix);
			d($ae_acn);
			$this_action = get_single_field($subject_id, $project_id, $ae_event_id, 'ae_aeacn', '');
			d(array_search($ae_acn['txacn'], $action_priority));
			d(array_search($this_action, $action_priority));
			/**
			 * make sure we have priorities for both $this_action and $ae_acn['txacn']
			 */
			if (array_search($ae_acn['txacn'], $action_priority) !== false && array_search($this_action, $action_priority) !== false) {
				/**
				 * determine priority of the current action. If its priority is higher than the one recorded, update.
				 */
				if (array_search($ae_acn['txacn'], $action_priority) > array_search($this_action, $action_priority)) {
					update_field_compare($subject_id, $project_id, $ae_event_id, $ae_acn['txacn'], $this_action, 'ae_aeacn', $debug);
				}
			}
		}
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;