<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$getdebug = $_GET['debug'] ? $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 * adjust dirname depth as needed
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
global $Proj;
Kint::enabled($debug);
/**
 * APRI
 */
$uln = 40;
$chem_fields = array('chem_lbdtc', 'ast_lbstresn');
$cbc_fields = array('cbc_lbdtc', 'plat_lbstresn', 'apri_lborres');
$cbc_data = REDCap::getData('array', '', $cbc_fields);
foreach ($cbc_data AS $subject_id => $subject) {
	$chem_events = array();
	$chem_data = REDCap::getData('array', $subject_id, $chem_fields);
	foreach ($subject AS $event_id => $event) {
		$apri_score = '';
		if ($event['cbc_lbdtc'] != '' && $event['plat_lbstresn'] != '' && is_numeric($event['plat_lbstresn'])) {
			foreach ($chem_data AS $chem_subject) {
				foreach ($chem_subject AS $chem_event) {
					if ($chem_event['chem_lbdtc'] != '' && $chem_event['ast_lbstresn'] != '' && $chem_event['chem_lbdtc'] == $event['cbc_lbdtc'] && is_numeric($chem_event['ast_lbstresn'])) {
						$apri_score = (string)round(((($chem_event['ast_lbstresn'] / $uln) / $event['plat_lbstresn']) * 100), 2);
					}
				}
			}
		}
		update_field_compare($subject_id, $project_id, $event_id, $apri_score, $event['apri_lborres'], 'apri_lborres', $debug);
	}
}