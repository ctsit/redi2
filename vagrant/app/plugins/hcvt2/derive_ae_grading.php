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
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
/**
 * MAIN
 */
$ae_fields = array("ae_aecontrt");
$decod_prefixes = array('ae', 'ifn', 'rib', 'boc', 'tvr', 'sim', 'sof', 'dcv', 'hvn', 'vpk', 'dbv', 'eot');
foreach ($decod_prefixes AS $decod_prefix) {
	$ae_fields[] = $decod_prefix . '_aemodify';
	$ae_fields[] = $decod_prefix . '_aedecod';
	$ae_fields[] = $decod_prefix . '_suppae_aesevdrv';
}
$ae_data = REDCap::getData('array', $subjects, $ae_fields);
$cm_fields = array("cm_cmstdtc", "cm_cmdecod", "cm_suppcm_cmprtrt", "cm_suppcm_indcod", "cm_suppcm_mktstat");
$cm_data = REDCap::getData('array', $subjects, $cm_fields);
$transfusion_fields = array("xfsn_cmstdtc", "xfsn_cmindc");
$trans_data = REDCap::getData('array', $subjects, $transfusion_fields);
$tx_field_prefixes = array('ifn', 'rib', 'boc', 'tvr', 'sim', 'sof', 'dcv', 'hvn', 'vpk', 'dbv');
$peg_riba_fragments = array('#_aedecod', '#_aemodify', '#_suppae_#acn', "#_suppcm_cmadj", "#_esc_suppcm_cmadj", "#_suppcm_cmtrtout");
$daa_field_fragments = array("_aedecod", "_aemodify", "_suppae_daaacn", "_suppcm_cmadj", "_suppcm_cmtrtout");
$peg_riba_fields = array();
$daa_fields = array();
foreach ($tx_field_prefixes AS $prefix) {
	if ($prefix == 'ifn' || $prefix == 'rib') {
		foreach ($peg_riba_fragments as $fragment) {
			$peg_riba_fields[] = str_replace('#', $prefix, $fragment);
		}
	} else {
		foreach ($daa_field_fragments as $fragment) {
			$daa_fields[] = $prefix . $fragment;
		}
	}
}
$peg_riba_data = REDCap::getData('array', $subjects, $peg_riba_fields);
$daa_data = REDCap::getData('array', $subjects, $daa_fields);
/**
 * get AEs
 */
foreach ($ae_data AS $subject_id => $subject) {
	d($subject_id);
	if ($debug) {
		error_log("INFO: SUBJECT $subject_id");
	}
	foreach ($subject AS $event_id => $event) {
		foreach ($decod_prefixes as $decod_prefix) {
			$ae_grade = array('MILD' => null, 'MODERATE' => null, 'SEVERE' => null);
			$this_grade_array = array();
			$this_grade = null;
			if ($event[$decod_prefix . '_aedecod'] != '' || $event[$decod_prefix . '_aemodify'] != '') {
				d('TESTING ' . $decod_prefix . '_aedecod' . ' in EVENT ' . $event_id, $event[$decod_prefix . '_aedecod']);
				foreach ($cm_data[$subject_id] AS $cm_event_id => $cm_event) {
					/**
					 * if our ae_aedecod == cm_suppcm_indcod and this is not a pre-baseline CM
					 */
					if (($cm_event['cm_suppcm_indcod'] == $event[$decod_prefix . '_aedecod'] || $cm_event['cm_suppcm_indcod'] == $event[$decod_prefix . '_aemodify']) && $cm_event['cm_suppcm_cmprtrt'] == 'N') {
						d('AE -> INDC: received CONMED', $cm_event['cm_suppcm_indcod']);
						if ($event[$decod_prefix . '_aedecod'] == 'Anemia' || $event[$decod_prefix . '_aedecod'] == 'Anaemia' || $event[$decod_prefix . '_aemodify'] == 'Anemia' || $event[$decod_prefix . '_aemodify'] == 'Anaemia') {
							/**
							 * ae_aedecod = ANEMIA?
							 */
							foreach ($trans_data[$subject_id] AS $trans_event_id => $trans_event) {
								if (!isset($ae_grade['SEVERE']) && $trans_event['xfsn_cmindc'] == 'ANAEMIA') {
									/**
									 * AE is SEVERE
									 */
									$ae_grade['SEVERE'] = 2;
								}
							}
						} elseif (!isset($ae_grade['MODERATE']) && ($cm_event['cm_cmdecod'] != '' && $cm_event['cm_suppcm_mktstat'] == 'PRESCRIPTION')) {
							/**
							 * AE is MODERATE
							 */
							$ae_grade['MODERATE'] = 1;
						} elseif (!isset($ae_grade['MILD']) && (($cm_event['cm_cmdecod'] != '' && $cm_event['cm_suppcm_mktstat'] == 'OTC') || $cm_event['cm_cmdecod'] == '')) {
							/**
							 * AE is MILD
							 */
							$ae_grade['MILD'] = 0;
						}
					}
				}
				/**
				 * If we don't already have a moderate or severe, check treatment drug reduced or discon
				 */
				//d($ae_grade);
				foreach ($tx_field_prefixes AS $prefix) {
					//d($prefix);
					//if ($decod_prefix == $prefix) {
						if (!isset($ae_grade['SEVERE'])) {
							if ($prefix == 'ifn' || $prefix == 'rib') {
								foreach ($peg_riba_data[$subject_id] AS $tx_event_id => $tx_event) {
									if (($tx_event[str_replace('#', $prefix, '#_aedecod')] == $event[$decod_prefix . '_aedecod'] || $tx_event[str_replace('#', $prefix, '#_aemodify')] == $event[$decod_prefix . '_aemodify']) && $tx_event[str_replace('#', $prefix, '#_suppcm_cmtrtout')] == 'PREMATURELY_DISCONTINUED') {
										/**
										 * AE is SEVERE
										 */
										$ae_grade['SEVERE'] = 2;
										d('AE -> DISCON AE', $event[$decod_prefix . '_aedecod']);
										if ($debug) {
											echo('<div class = "red">PEG-RIBA SEVERE');
											echo('<h4>AE: ' . $event[$decod_prefix . '_aedecod'] . '</h4>');
											echo('<h4>TRTAE: ' . $tx_event[str_replace('#', $prefix, '#_aedecod')] . '</h4>');
											echo('<h4>TRTOUT: ' . $tx_event[str_replace('#', $prefix, '#_suppcm_cmtrtout')] . '</h4>');
											echo('</div>');
										}
									}
								}
							} else {
								foreach ($daa_data[$subject_id] AS $tx_event_id => $tx_event) {
									if (($tx_event[$prefix . '_aedecod'] == $event[$decod_prefix . '_aedecod'] || $tx_event[$prefix . '_aemodify'] == $event[$decod_prefix . '_aemodify']) && $tx_event[$prefix . '_suppcm_cmtrtout'] == 'PREMATURELY_DISCONTINUED') {
										/**
										 * AE is SEVERE
										 */
										$ae_grade['SEVERE'] = 2;
										d('AE -> DISCON AE', $event[$decod_prefix . '_aedecod']);
										if ($debug) {
											echo('<div class = "red">DAA SEVERE');
											echo('<h4>AE: ' . $event[$decod_prefix . '_aedecod'] . '</h4>');
											echo('<h4>TRTAE: ' . $tx_event[$prefix . '_aedecod'] . '</h4>');
											echo('<h4>TRTOUT: ' . $tx_event[$prefix . '_suppcm_cmtrtout'] . '</h4>');
											echo('</div>');
										}
									}
								}
							}
						}
						if (!isset($ae_grade['MODERATE'])) {
							if ($prefix == 'ifn' || $prefix == 'rib') {
								foreach ($peg_riba_data[$subject_id] AS $tx_event_id => $tx_event) {
									if (($tx_event[str_replace('#', $prefix, '#_aedecod')] == $event[$decod_prefix . '_aedecod'] || $tx_event[str_replace('#', $prefix, '#_aemodify')] == $event[$decod_prefix . '_aemodify']) && $tx_event[str_replace('#', $prefix, '#_suppae_#acn')] == 'DOSE_REDUCED') {
										/**
										 * AE is MODERATE
										 */
										$ae_grade['MODERATE'] = 1;
										d('AE -> DOSE REDUCED AE', $event[$decod_prefix . '_aedecod']);
										if ($debug) {
											echo('<div class = "yellow">PEG-RIBA MODERATE');
											echo('<h4>AE: ' . $event[$decod_prefix . '_aedecod'] . '</h4>');
											echo('<h4>TRTAE: ' . $tx_event[str_replace('#', $prefix, '#_aedecod')] . '</h4>');
											echo('<h4>TRTACN: ' . $tx_event[str_replace('#', $prefix, '#_suppae_#acn')] . '</h4>');
											echo('</div>');
										}
									}
								}
							} else {
								foreach ($daa_data[$subject_id] AS $tx_event_id => $tx_event) {
									if (($tx_event[$prefix . '_aedecod'] == $event[$decod_prefix . '_aedecod'] || $tx_event[$prefix . '_aemodify'] == $event[$decod_prefix . '_aemodify']) && $tx_event[$prefix . '_suppae_daaacn'] == 'DOSE_REDUCED') {
										/**
										 * AE is MODERATE
										 */
										$ae_grade['MODERATE'] = 1;
										d('AE -> DOSE REDUCED AE', $event[$decod_prefix . '_aedecod']);
										if ($debug) {
											echo('<div class = "yellow">DAA MODERATE');
											echo('<h4>AE: ' . $event[$decod_prefix . '_aedecod'] . '</h4>');
											echo('<h4>TRTAE: ' . $tx_event[$prefix . '_aedecod'] . '</h4>');
											echo('<h4>TRTACN: ' . $tx_event[$prefix . '_suppae_daaacn'] . '</h4>');
											echo('</div>');
										}
									}
								}
							}
						}
					//}
				}
				/**
				 * find the highest grade
				 */
				//d('MOST SEVERE', $ae_grade);
				$this_grade_array = $ae_grade;
				sort($this_grade_array);
				$this_grade = array_pop($this_grade_array);
				d('GRADE', array_search($this_grade, $ae_grade));
				if ($debug) {
					error_log("INFO: AE " . $event[$decod_prefix . '_aedecod'] . " grade = " . $this_grade);
				}
				update_field_compare($subject_id, $project_id, $event_id, array_search($this_grade, $ae_grade), $event[$decod_prefix . '_suppae_aesevdrv'], $decod_prefix . '_suppae_aesevdrv', $debug);
			}
		}
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;