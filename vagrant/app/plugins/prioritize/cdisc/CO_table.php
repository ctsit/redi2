<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 12/18/13
 * Time: 9:20 AM
 */
$debug = false;
$subjects = ''; // '' = ALL
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';

// Restrict access to the desired projects
$allowed_pids = array('38');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
global $Proj;

$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'],0,4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'CO';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$vals_array = array();
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Query to get arrays of fatestcd, fatest, faorres, facat, faobj
 */
$fields_query = "SELECT DISTINCT
'dm_usubjid',
val.field_name AS coval,
eval.field_name AS coeval,
dtc.field_name AS codtc
FROM redcap_metadata val
LEFT OUTER JOIN redcap_metadata eval ON eval.field_name = CONCAT(LEFT(val.field_name, INSTR(val.field_name, '_')-1), '_coeval') AND eval.project_id = val.project_id AND eval.form_name = val.form_name
LEFT OUTER JOIN redcap_metadata dtc ON dtc.field_name = CONCAT(LEFT(val.field_name, INSTR(val.field_name, '_')-1), '_codtc') AND dtc.project_id = val.project_id
WHERE val.field_name LIKE '%\_coval'
AND val.project_id = '$project_id'";
d($fields_query);
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$co_fields_result = db_query($fields_query);
if ($co_fields_result) {
	while ($co_fields_row = db_fetch_assoc($co_fields_result)) {
		foreach ($co_fields_row AS $field_key => $field_name) {
			$co_fields[] = $field_name;
		}
	}
	db_free_result($co_fields_result);
}
$co_fields = array_unique($co_fields);
$data = REDCap::getData('array', $subjects, $co_fields);
$timer['have_data'] = microtime(true);

$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		foreach ($data AS $subject_id => $subject) {
			$usubjid = '';
			foreach ($subject AS $event_id => $event) {
				if ($subjects != '') {
					d($event);
				}
				$inner_vals = array();
				if ($usubjid == '') {
					$usubjid = $event['dm_usubjid'];
				}
				/**
				 * some COVAL values will be text and some checkboxes
				 * handle strings and arrays in same field
				 */
				if (is_array($event[$fields['coval']])) {
					$event[$fields['coval']] = fix_case(array_search('1', $event[$fields['coval']]));
				}
				$event[$fields['coeval']] = fix_case($event[$fields['coeval']]);
				/**
				 * build values array
				 */
				foreach ($fields AS $key => $field) {
					if (!in_array($field, array($fields['dm_usubjid']))) {
						$inner_vals[$key] = $event[$field];
					}
				}
				$vals_array[$usubjid][] = $inner_vals;
			}
		}
	}
	db_free_result($fields_result);
}
if ($subjects != '') {
	d($vals_array);
}
$timer['start_main'] = microtime(true);
/**
 * Main loop
 */
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	//d($subj_val_array);
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['coval'] != '') {
			$query[] = '(' . 
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null(substr($subj_array['coval'], 0, 100)) . ',' .
				fix_null($subj_array['coeval']) . ',' .
				fix_null($subj_array['codtc']) .
				')';
			$seq++;
		}
	}
}
$timer['end_main'] = microtime(true);
/**
 * end Main Loop
 */
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `COSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `COVAL` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `COEVAL` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CODTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL
  KEY `usubjid_coval` (`USUBJID`,`COVAL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name";
$columns = "(STUDYID, DOMAIN, USUBJID, COSEQ, COVAL, COEVAL, CODTC)";
$sql = "INSERT INTO $table_name $columns VALUES\n" . implode(",\n", $query);
d($sql);
if (!$debug) {
	if (db_query($table_create_query)) {
		echo "$table_name exists<br />";
	}
	if (db_query($truncate_query)) {
		echo "$table_name has been truncated<br />";
		if (db_query($sql)) {
			echo "$table_name has been updated<br />";
			/**
			 * prep for download
			 */
			if (defined("USERID")) {
				$userid = USERID;
			} else if (in_array(CRON_PAGE, non_auth_pages())) {
				$userid = "[CRON]";
			} else {
				$userid = '';
			}
			if (is_array($fields_collection)) {
				foreach ($fields_collection AS $field_collection) {
					foreach ($field_collection AS $key => $val) {
						$chkd_fields_array[] = $key;
					}
				}
				$chkd_fields = "'" . implode("', '", array_unique($chkd_fields_array)) . "'";
			}
			create_cdisc_download($table_name, $lang, $app_title, $userid, $user_rights, $chkd_fields, '', $project_id, $constants['DOMAIN'], $debug);
		} else {
			error_log("SQL INSERT FAILED: " . db_error() . "\n");
			echo db_error() . "<br />";
		}
	} else {
		error_log("TRUNCATE FAILED: " . db_error() . "\n");
		echo db_error() . "<br />";
	}
}
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;