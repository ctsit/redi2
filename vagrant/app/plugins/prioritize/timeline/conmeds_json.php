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
 * project metadata
 */
$project = new Project();
$first_event_id = $project->firstEventId;
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
 * conmeds
 */
$fields = array('cm_cmstdtc', 'cm_cmtrt', 'cm_cmindc', 'cm_oth_cmindc', 'cm_suppcm_exacindc', 'cm_suppcm_prphindc');
$conmeds_data = REDCap::getData('array', $subjid, $fields);
foreach ($conmeds_data AS $subject_id => $subject) {
	$rfstdtc = get_single_field($subject_id, $project_id, $first_event_id, 'dm_rfstdtc', '');
	foreach ($subject AS $event_id => $event) {
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=conmeds&id=$subjid&event_id=$event_id";
		$desc_array = array();
		foreach ($event AS $field => $item) {
			if ($item != '' && !in_array($field, array('cm_cmstdtc', 'cm_oth_cmindc', 'cm_cmtrt'))) {
				if ($field == 'cm_cmindc' && $item == 'OTHER') {
					$item = $event['cm_oth_cmindc'];
				}
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . "</strong>";
			}
		}
		$event_start = $event['cm_cmstdtc'] != '' ? $event['cm_cmstdtc'] : $rfstdtc;
		if ($event['cm_cmtrt'] != '') {
			$eventAtts[] = get_event_array($event_start, '', '', implode("<br />", $desc_array), $event['cm_cmtrt'], '', '', $url);
		}
	}
}
/**
 * transfusions
 */
$fields = array('xfsn_cmstdtc', 'xfsn_cmtrt', 'xfsn_cmdose', 'xfsn_cmindc');
$transfusion_data = REDCap::getData('array', $subjid, $fields);
foreach ($transfusion_data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=transfusions&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			if ($item != '' && !in_array($field, array('xfsn_cmstdtc', 'xfsn_cmtrt'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . "</strong>";
			}
		}
		$eventAtts[] = get_event_array($event['xfsn_cmstdtc'], '', '', implode("<br />", $desc_array), $event['xfsn_cmtrt'], 'red', '', $url);
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