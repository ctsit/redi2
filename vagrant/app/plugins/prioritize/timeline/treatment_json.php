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
$track_count = 0;
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
 * Treatment duration
 */
//$fields = array("dm_rfstdtc", "eot_dsstdtc", "reg_suppcm_regimen", "reg_oth_suppcm_regimen");
//$data = REDCap::getData('array', $subjid, $fields);
//foreach ($data AS $subject) {
//	foreach ($subject AS $event) {
//		foreach ($event AS $field => $item) {
//			if (!in_array($field, array("dm_rfstdtc", "eot_dsstdtc", "reg_oth_suppcm_regimen"))) {
//				if ($field == 'reg_suppcm_regimen' && $item == 'OTHER') {
//					$item = $event['reg_oth_suppcm_regimen'];
//				}
//				$desc_array[] = get_field_label($field, $project_id) . ': ' . fix_case($item);
//			}
//		}
//		if ($event['eot_dsstdtc'] == '') {
//			$title = 'Treatment start';
//		} else {
//			$title = 'Treatment duration';
//		}
//		$eventAtts[] = get_event_array($event['dm_rfstdtc'], $event['eot_dsstdtc'], '', implode("<br />", $desc_array), $title, 'green', '', '', $track_count);
//		if ($event['eot_dsstdtc'] == '') {
//			$eventAtts[] = get_event_array(add_date($event['dm_rfstdtc'], 85), '', '', implode("<br />", $desc_array), $title . ' + 12 Weeks', 'orange', '', '');
//		}
//	}
//}
/**
 * treatment
 */
$treatments = array('interferon' => 'ifn', 'ribavirin' => 'rib', 'boceprevir' => 'boc', 'telaprevir' => 'tvr', 'simeprevir' => 'sim', 'sofosbuvir' => 'sof', 'daclatasvir' => 'dcv', 'harvoni' => 'hvn', 'ombitasvir_paritaprevir' => 'vpk', 'dasabuvir' => 'dbv', 'zepatier' => 'zep');
foreach ($treatments AS $treatment => $prefix) {
	$treatment_page = in_array($prefix, array('dbv', 'vpk')) ? $treatment : $treatment . '_administration';
	$tx_name = get_field_label($prefix . '_cmtrt', $project_id);
	$fields = array($prefix . '_cmstdtc', $prefix . '_cmdose', $prefix . '_cmdosu', $prefix . '_cmdosfrq', $prefix . '_oth_cmdosfrq', $prefix . '_suppcm_cmtrtout', $prefix . '_suppcm_cmadj', $prefix . '_esc_suppcm_cmadj', $prefix . '_oth_suppcm_cmadj', $prefix . '_suppcm_cmncmpae', $prefix . '_oth_suppcm_cmncmpae', $prefix . '_suppae_ifnacn', $prefix . '_cmendtc', $prefix . '_suppcm_cmreasnost');
	$tx_data = REDCap::getData('array', $subjid, $fields);
	foreach ($tx_data AS $subject) {
		foreach ($subject AS $event_id => $event) {
			$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=$treatment_page&id=$subjid&event_id=$event_id";
			$desc_array = array();
			foreach ($event AS $field => $item) {
				if ($item != '' && !in_array($field, array($prefix . '_cmstdtc', $prefix . '_cmendtc', $prefix . '_cmdosu', $prefix . '_cmdosfrq', $prefix . '_oth_cmdosfrq', $prefix . '_oth_suppcm_cmadj'))) {
					if ($event[$prefix . '_cmdosfrq'] == 'OTHER') {
						$item = $event[$prefix . '_oth_cmdosfrq'];
						$event[$prefix . '_cmdosfrq'] = $event[$prefix . '_oth_cmdosfrq'];
					}
					if (($field == $prefix . '_suppcm_cmadj' || $field == $prefix . '_esc_suppcm_cmadj') && $item == 'OTHER') {
						$item = $event[$prefix . '_oth_suppcm_cmadj'];
					}
					if ($field == $prefix . '_cmdose') {
						$item = $item . ' ' . $event[$prefix . '_cmdosu'] . ' ' . $event[$prefix . '_cmdosfrq'];
					}
					if ($field == $prefix . '_suppcm_cmtrtout' && $item == 'ONGOING') {
						$earliest_end = date("Y-m-d");
					} else {
						$earliest_end = null;
					}
					$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . "</strong>";
				}
			}
			if ($event[$prefix . '_cmstdtc'] != '') {
				$track_count++;
				$eventAtts[] = get_event_array($event[$prefix . '_cmstdtc'], $event[$prefix . '_cmendtc'], $earliest_end, implode("<br />", $desc_array), $tx_name, '', '', $url, $track_count);
			}
		}
	}
}
/**
 * generate and return JSON to timeline script.
 */
if (isset($subjid)) {
	$json_data = array(
		'dateTimeFormat' => 'Gregorian', //JSON!
		//Event attributes
		'events' => $eventAtts
	);
	$json_encoded = json_encode($json_data);
	echo $json_encoded;
}