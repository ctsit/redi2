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
 * HCV RNA
 */
$fields = array('hcv_lbdtc', 'hcv_supplb_hcvquant', 'hcv_lbstresn', 'hcv_lbstresu', 'hcv_supplb_hcvdtct');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=hcv_rna_results&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			if ($item != '' && !in_array($field, array('hcv_lbdtc', 'hcv_lbstresu'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . ' ' . $event[$prefix . 'lbstresu'] . "</strong>";
			}
		}
		if ($event['hcv_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['hcv_lbdtc'], '', '', implode("<br />", $desc_array), 'HCV RNA', $color, '', $url);
		}
	}
}
/**
 * HCV RNA IMPORTED
 */
$fields = array('hcv_im_lbdtc', 'hcv_im_supplb_hcvquant', 'hcv_im_lbstresn', 'hcv_im_lbstresu', 'hcv_im_supplb_hcvdtct');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=hcv_rna_imported&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			if ($item != '' && !in_array($field, array('hcv_im_lbdtc', 'hcv_im_lbstresu'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . ' ' . $event[$prefix . 'lborresu'] . "</strong>";
			}
		}
		if ($event['hcv_im_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['hcv_im_lbdtc'], '', '', implode("<br />", $desc_array), 'HCV RNA(I)', $color, '', $url);
		}
	}
}
/**
 * CBC
 */
$fields = array('cbc_lbdtc', 'wbc_lbstresn', 'wbc_lbstresu', 'neut_lbstresn', 'neut_lbstresu', 'anc_lbstresn', 'anc_lbstresu', 'lymce_lbstresn', 'lymce_lbstresu', 'lym_lbstresn', 'lym_lbstresu', 'plat_lbstresn', 'plat_lbstresu', 'hemo_lbstresn', 'hemo_lbstresu');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=cbc&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			$prefix = substr($field, 0, strpos($field, '_') + 1);
			if ($item != '' && !in_array($field, array('cbc_lbdtc', 'wbc_lbstresu', 'neut_lbstresu', 'anc_lbstresu', 'lymce_lbstresu', 'lym_lbstresu', 'plat_lbstresu', 'hemo_lbstresu'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . ' ' . $event[$prefix . 'lbstresu'] . "</strong>";
			}
		}
		if ($event['cbc_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['cbc_lbdtc'], '', '', implode("<br />", $desc_array), 'CBC', $color, '', $url);
		}
	}
}
/**
 * CBC IMPORTED
 */
$fields = array('cbc_im_lbdtc', 'wbc_im_lbstresn', 'wbc_im_lbstresu', 'neut_im_lbstresn', 'neut_im_lbstresu', 'anc_im_lbstresn', 'anc_im_lbstresu', 'lymce_im_lbstresn', 'lymce_im_lbstresu', 'lym_im_lbstresn', 'lym_im_lbstresu', 'plat_im_lbstresn', 'plat_im_lbstresu', 'hemo_im_lbstresn', 'hemo_im_lbstresu');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=cbc_imported&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			$prefix = substr($field, 0, strpos($field, '_') + 1);
			if ($item != '' && !in_array($field, array('cbc_im_lbdtc', 'wbc_im_lbstresu', 'neut_im_lbstresu', 'anc_im_lbstresu', 'lymce_im_lbstresu', 'lym_im_lbstresu', 'plat_im_lbstresu', 'hemo_im_lbstresu'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . ' ' . $event[$prefix . 'im_lbstresu'] . "</strong>";
			}
		}
		if ($event['cbc_im_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['cbc_im_lbdtc'], '', '', implode("<br />", $desc_array), 'CBC(I)', $color, '', $url);
		}
	}
}
/**
 * Chemistry
 */
$fields = array('chem_lbdtc', 'alt_lbstresn', 'alt_lbstresu', 'ast_lbstresn', 'ast_lbstresu', 'alp_lbstresn', 'alp_lbstresu', 'tbil_lbstresn', 'tbil_lbstresu', 'dbil_lbstresn', 'dbil_lbstresu', 'alb_lbstresn', 'alb_lbstresu', 'creat_lbstresn', 'creat_lbstresu', 'gluc_lbstresn', 'gluc_lbstresu', 'k_lbstresn', 'k_lbstresu', 'sodium_lbstresn', 'sodium_lbstresu');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=chemistry&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			$prefix = substr($field, 0, strpos($field, '_') + 1);
			if ($item != '' && !in_array($field, array('chem_lbdtc', 'alt_lbstresu', 'ast_lbstresu', 'alp_lbstresu', 'tbil_lbstresu', 'dbil_lbstresu', 'alb_lbstresu', 'creat_lbstresu', 'gluc_lbstresu', 'k_lbstresu', 'sodium_lbstresu'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . ' ' . $event[$prefix . 'lbstresu'] . "</strong>";
			}
		}
		if ($event['chem_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['chem_lbdtc'], '', '', implode("<br />", $desc_array), 'Chemistry', $color, '', $url);
		}
	}
}
/**
 * Chemistry imported
 */
$fields = array('chem_im_lbdtc', 'alt_im_lbstresn', 'alt_im_lbstresu', 'ast_im_lbstresn', 'ast_im_lbstresu', 'alp_im_lbstresn', 'alp_im_lbstresu', 'tbil_im_lbstresn', 'tbil_im_lbstresu', 'dbil_im_lbstresn', 'dbil_im_lbstresu', 'alb_im_lbstresn', 'alb_im_lbstresu', 'creat_im_lbstresn', 'creat_im_lbstresu', 'gluc_im_lbstresn', 'gluc_im_lbstresu', 'k_im_lbstresn', 'k_im_lbstresu', 'sodium_im_lbstresn', 'sodium_im_lbstresu');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=chemistry_imported&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			$prefix = substr($field, 0, strpos($field, '_') + 1);
			if ($item != '' && !in_array($field, array('chem_im_lbdtc', 'alt_im_lbstresu', 'ast_im_lbstresu', 'alp_im_lbstresu', 'tbil_im_lbstresu', 'dbil_im_lbstresu', 'alb_im_lbstresu', 'creat_im_lbstresu', 'gluc_im_lbstresu', 'k_im_lbstresu', 'sodium_im_lbstresu'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . ' ' . $event[$prefix . 'im_lbstresu'] . "</strong>";
			}
		}
		if ($event['chem_im_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['chem_im_lbdtc'], '', '', implode("<br />", $desc_array), 'Chem(I)', $color, '', $url);
		}
	}
}
/**
 * INR
 */
$fields = array('inr_lbdtc', 'inr_lborres');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=inr&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			$prefix = substr($field, 0, strpos($field, '_') + 1);
			if ($item != '' && !in_array($field, array('inr_lbdtc'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . "</strong>";
			}
		}
		if ($event['inr_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['inr_lbdtc'], '', '', implode("<br />", $desc_array), 'INR', $color, '', $url);
		}
	}
}
/**
 * INR IMPORTED
 */
$fields = array('inr_im_lbdtc', 'inr_im_lborres');
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event_id => $event) {
		$desc_array = array();
		$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&page=inr&id=$subjid&event_id=$event_id";
		foreach ($event AS $field => $item) {
			$prefix = substr($field, 0, strpos($field, '_') + 1);
			if ($item != '' && !in_array($field, array('inr_im_lbdtc'))) {
				$desc_array[] = get_field_label($field, $project_id) . ': <strong>' . $item . "</strong>";
			}
		}
		if ($event['inr_im_lbdtc'] != '') {
			$eventAtts[] = get_event_array($event['inr_im_lbdtc'], '', '', implode("<br />", $desc_array), 'INR(I)', $color, '', $url);
		}
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