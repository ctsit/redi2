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
 * prettify field names
 */
$fields = array();
$pretty_field_names = array();
$pretty_field_name_result = db_query("SELECT field_name, element_label FROM redcap_metadata WHERE project_id = '$project_id' AND element_type != 'descriptive' AND form_name = 'fibrosis_staging' AND field_name NOT IN ('cir_nx_nxmesg', 'plat140_nx_nxmesg', 'livbp_suppmh_nomhstyr', 'egd_mhoccur', 'pht_mhoccur', 'fib_lbstat', 'fib_supplb_nolbdtc', 'fibscn_lbstat', 'fibscn_supplb_nolbdtc', 'cap_lbstat', 'cap_supplb_nolbdtc', 'fibrosis_staging_complete')");
if ($pretty_field_name_result) {
	while ($field_name_row = db_fetch_assoc($pretty_field_name_result)) {
		$pretty_field_names[$field_name_row['field_name']] = $field_name_row['element_label'];
		$fields[] = $field_name_row['field_name'];
	}
}
if ($debug) {
	show_var($fields);
}
$pretty_field_names['cirr_suppfa_cirrstat'] = 'Cirrhosis status';
$pretty_field_names['plt_suppfa_faorres'] = 'Platelets <140K';
$pretty_field_names['livbp_faorres'] = 'Fibrosis score (adjusted)';
/**
 * WORKING DATA
 */
$pts = array();
$table_csv = "";
$fields = array_merge(array('cirr_suppfa_cirrstat'), $fields, array('plt_suppfa_faorres'));
$pt_result = db_query("SELECT DISTINCT record FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'cirr_suppfa_cirrstat' AND value != '' ORDER BY abs(record) ASC");
if ($pt_result) {
	while ($pt_row = db_fetch_assoc($pt_result)) {
		$pts[] = $pt_row['record'];
	}
}
$data = REDCap::getData('array', $pts, $fields);
foreach ($data AS $subject_id => $subject) {
	$data_row = array();
	$data_row['subjid'] = $subject_id;
	foreach ($subject AS $event_id => $event) {
		/**
		 * if ishak or unknown scales were used, adjust the scale value
		 */
		if ($event['livbp_facat'] == 'ISHAK' || $event['livbp_facat'] == 'UNKNOWN') {
			if ($event['livbp_faorres'] >= '5') {
				$event['livbp_faorres'] = '4';
			} elseif ($event['livbp_faorres'] == '3' || $event['livbp_faorres'] == '4') {
				$event['livbp_faorres'] = '3';
			}
		}
		switch ($event['fib_lbtest']) {
			case 'FBRTST':
				$equiv_lo = .60;
				break;
			case 'FBRSPCT':
				/**
				 * what corresponds to an F4 when the threshold for F2-F4 is at 42?
				 */
				$equiv_lo = 69;
				break;
			case 'ELFG':
				$equiv_lo = '';
				break;
			case 'HEPASCR':
				/**
				 * score >= 0.5 corresponds to F2 - F4
				 */
				$equiv_lo = .7;
				break;
			case 'FBRMTR':
				$equiv_lo = '';
				break;
			case 'OTHER':
				$equiv_lo = '';
				break;
			default:
				$equiv_lo = '';
		}
		foreach ($event AS $key => $value) {
			$data_row[$pretty_field_names[$key]] = '"' . $value . '"';
			if ($key == 'fib_lborres') {
				$data_row['Serum Fibrosis F4 Eqv'] = '"' . $equiv_lo . '"';
			}
			if ($key == 'fibscn_lborres' && $value != '') {
				$data_row['Fibroscan/Fibrosure F4 Eqv'] = '"' . 8.5 . '"';
			} elseif ($key == 'fibscn_lborres') {
				$data_row['Fibroscan/Fibrosure F4 Eqv'] = '""';
			}
		}
		$row_csv = implode(',', $data_row) . "\n";
		$table_csv .= $row_csv;
	}
}
$headers = implode(',', array_keys($data_row)) . "\n";
if (!$debug) {
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, "CIRRHOSIS_VALUES", $debug);
} else {
	show_var($table_csv);
}