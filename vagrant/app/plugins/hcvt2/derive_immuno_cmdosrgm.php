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
/**
 * MAIN
 */
/**
 * initial dose
 */
$fields = array('imminit_cmdose', 'imminit_cmdosrgm');
$data = REDCap::getData('array', '', $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		if ($event['imminit_cmdose'] != '') {
			update_field_compare($subject_id, $project_id, $event_id, 'FIXED', $event['imminit_cmdosrgm'], 'imminit_cmdosrgm', $debug);
		}
	}
}
/**
 * final dose
 */
$fields = array('immfinl_cmdose', 'immfinl_cmdosrgm');
$data = REDCap::getData('array', '', $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		if ($event['immfinl_cmdose'] != '') {
			update_field_compare($subject_id, $project_id, $event_id, 'FIXED', $event['immfinl_cmdosrgm'], 'immfinl_cmdosrgm', $debug);
		}
	}
}