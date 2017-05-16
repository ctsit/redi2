<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 4/9/14
 * Time: 11:55 AM
 */
/**
 * includes
 */
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/includes/timeline_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
/**
 * variables
 */
$subjid = $_GET['record'];
$project_id = !isset($project_id) ? $_GET['pid'] : $project_id;
$eventAtts = array();
/**
 * restrict access to one or more pids
 */
$allowed_pids = array('38');
REDCap::allowProjects($allowed_pids);
/**
 * set header so browser knows it's JSON
 */
header('Content-Type: application/json; charset=utf-8');
/**
 * adverse events
 */
$fields = array('ae_aestdtc', 'ae_aeterm', 'ae_oth_aeterm', 'ae_aetoxgr', 'ae_aesev', 'trans_cmoccur', 'ae_aecontrt', 'ae_suppae_aexacerb', 'ae_aeser', 'ae_aesdth', 'ae_aeslife', 'ae_aeshosp', 'ae_aesdisab', 'ae_aescong', 'ae_aesmie', 'ae_suppae_aesosp', 'ae_aerel', 'ae_suppae_aeregmod', 'ifn_suppae_ifnacnd', 'rib_suppae_ribacnd', 'daa_suppae_daaacnd', 'ae_aeout', 'ae_aeendtc');
$ae_data = REDCap::getData('array', $subjid, $fields);
foreach ($ae_data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		if ($event['ae_aeser'] == 'Y') {
			$color = 'red';
		} else {
			$color = 'orange';
		}
		$ae_url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=adverse_events&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			if ($item != '') {
				if (!in_array($field, array('ae_aestdtc', 'ae_aeendtc'))) {
					if ($field == 'ae_oth_aeterm') {
						if ($item != '') {
							$item = $event['ae_oth_aeterm'];
							$event['ae_aeterm'] = $event['ae_oth_aeterm'];
						}
						continue;
					}
					if ($field == 'ae_aeterm' && $item = 'OTHER') {
						$item = $event['ae_oth_aeterm'];
					}
					if (!in_array($field, array('ae_aeterm', 'ae_oth_aeterm'))) {
						$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . "</strong>";
					}
				}
			}
		}
		if ($event['ae_aestdtc'] != '') {
			$eventAtts[] = get_event_array($event['ae_aestdtc'], $event['ae_aeendtc'], '', implode("<br />", $desc_array), $event['ae_aeterm'], $color, '', $ae_url);
		}
	}
}
/**
 * transplants
 */
$fields = array('livtrp_cestdtc');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=onpost_tx_liver_transplant&id=$subjid&event_id=$event_id";
	$desc_array = array();
	foreach ($subject AS $event_id => $event) {
		$eventAtts[] = get_event_array($event['livtrp_cestdtc'], '', '', '', 'Liver Transplant', 'blue', '', $url, '');
	}
}
/**
 * generate and return JSON to timeline script.
 */
if (isset($subjid)) {
	$json_data = array(
		//Timeline attributes
		'dateTimeFormat' => 'Gregorian', //JSON!
		//Event attributes
		'events' => $eventAtts
	);
	$json_encoded = json_encode($json_data);
	echo $json_encoded;
}