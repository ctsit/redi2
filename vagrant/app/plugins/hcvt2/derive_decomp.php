<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = $_GET['debug'] ? (bool)$_GET['debug'] : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
/**
 * timing
 */
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/redcap_connect.php';
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * INIT VARS
 */
Kint::enabled($debug);
global $Proj;
$first_event = $Proj->firstEventId;
/**
 * MAIN
 * $decomp_pts = array('Acute hepatic failure', 'Ascites', 'Oesophageal varices haemorrhage', 'Hepatic encephalopathy', 'Mental status changes', 'confusional state', 'Hepatorenal syndrome', 'Hepatopulmonary syndrome', 'Peritonitis bacterial');
 */
$decomp_pts = array('Acute hepatic failure', 'Ascites', 'Oesophageal varices haemorrhage', 'Hepatic encephalopathy', 'Hepatorenal syndrome', 'Hepatopulmonary syndrome', 'Peritonitis bacterial', 'Hepatic hydrothorax', 'Subacute hepatic failure');
//$decomp_llt = array('Decompensated cirrhosis');
$decomp_conmeds = array('rifaximin', 'xifaxan', 'lactulose');
$fields = array('ae_oth_aeterm', 'ae_aemodify', 'ae_aedecod', 'ae_suppae_aexacerb', 'cm_cmdecod', 'cm_suppcm_cmprtrt', 'cm_suppcm_indcod', 'cm_suppcm_exacindc', 'cm_suppcm_prphindc', 'cirr_suppfa_cirrstat', 'cirr_suppfa_decomp', 'dcp_mhoccur');
$data = REDCap::getData('array', $subjects, $fields);
d($data);
foreach ($data AS $subject_id => $subject) {
	/**
	 * SUBJECT-LEVEL vars
	 */
	$_decomp = $subject[$first_event]['cirr_suppfa_decomp'];
//	$decompensated = $_decomp == 'Y' ? true : false;
	$decompensated = false;
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
//		if (!$decompensated) {
//			if (in_array($event['ae_aedecod'], $decomp_pts) ||
//				($event['cm_suppcm_cmprtrt'] == 'N' && $event['cm_suppcm_exacindc'] == 'Y' && $event['cm_suppcm_prphindc'] != 'Y' && in_array($event['cm_cmdecod'], $decomp_conmeds)) ||
//				(in_array($event['ae_oth_aeterm'], $decomp_llt) && $event['ae_suppae_aexacerb'] == 'Y')
//			) {
//				$decompensated = true;
//				d($event);
//			}
//			if ($subject[$first_event]['dcp_mhoccur'] == 'Y') { // if history of decomp
//			} else { // no history of decomp or unknown
//				if ((in_array($event['ae_aedecod'], $decomp_pts) && $event['ae_suppae_aexacerb'] != 'Y') ||
//					($event['cm_suppcm_cmprtrt'] == 'N' && $event['cm_suppcm_exacindc'] != 'Y' && $event['cm_suppcm_prphindc'] != 'Y' && in_array($event['cm_cmdecod'], $decomp_conmeds)) ||
//					(in_array($event['ae_oth_aeterm'], $decomp_llt) && $event['ae_suppae_aexacerb'] != 'Y')
//				) {
//					$decompensated = true;
//					d($event);
//				}
//			}
//		}
		d($event);
		if (!$decompensated && (
				in_array($event['ae_aedecod'], $decomp_pts) ||
				($event['cm_suppcm_cmprtrt'] == 'N' && $event['cm_suppcm_prphindc'] != 'Y' && in_array($event['cm_cmdecod'], $decomp_conmeds))
			)
		) {
			$decompensated = true;
		}
	}
	$decomp = $decompensated ? 'Y' : 'N';
	update_field_compare($subject_id, $project_id, $first_event, $decomp, $_decomp, 'cirr_suppfa_decomp', $debug);
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}