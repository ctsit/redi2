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
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
global $Proj;
Kint::enabled($debug);

$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'], 0, 4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_') + 1, 1));
$constants['DOMAIN'] = 'FA';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$vals_array = array();
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Query to get arrays of fatestcd, fatest, faorres, facat, faobj
 */
$fields_query = "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS fatestcd,
test.element_label AS fatest,
orres.field_name AS faorres,
cat.field_name AS facat,
obj.element_label AS faobj,
dtc.field_name AS fadtc
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_fatest') AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_faorres') AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata cat ON cat.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_facat') AND cat.project_id = testcd.project_id AND cat.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata obj ON obj.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_faobj') AND obj.project_id = testcd.project_id AND obj.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata dtc ON dtc.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_suppmh_mhstyr') AND dtc.project_id = testcd.project_id
WHERE testcd.field_name LIKE '%\_fatestcd'
AND testcd.project_id = '$project_id'";
d($fields_query);
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$fa_fields_result = db_query($fields_query);
if ($fa_fields_result) {
	while ($fa_fields_row = db_fetch_assoc($fa_fields_result)) {
		foreach ($fa_fields_row AS $field_key => $field_name) {
			$fa_fields[] = $field_name;
		}
	}
	db_free_result($fa_fields_result);
}
$fa_fields = array_unique($fa_fields);
$data = REDCap::getData('array', $subjects, $fa_fields);
$timer['have_data'] = microtime(true);
if ($subjects != '') {
	d($data);
}

$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		foreach ($data AS $subject_id => $subject) {
			$usubjid = '';
			foreach ($subject AS $event_id => $event) {
				$inner_vals = array();
				if ($usubjid == '') {
					$usubjid = $event['dm_usubjid'];
				}
				/**
				 * append fastresc and fastresn to $fields
				 */
				$fields['fastresc'] = 'fastresc';
				$fields['fastresn'] = 'fastresn';
				/**
				 * pass metadata fields forward to the $event
				 */
				$event[$fields['fatest']] = $fields['fatest'];
				$event[$fields['fatestcd']] = $fields['fatestcd'];
				$event[$fields['faobj']] = $fields['faobj'];
				$event[$fields['fastresc']] = !is_numeric($event[$fields['faorres']]) ? $event[$fields['faorres']] : NULL;
				/**
				 * if ishak or unknown scales were used for fibrosis, standardize the scale value
				 */
				if ($event[$fields['facat']] == 'ISHAK' || $event[$fields['facat']] == 'UNKNOWN') {
					if ($event[$fields['faorres']] >= '5') {
						$event[$fields['fastresn']] = '4';
					} elseif ($event[$fields['faorres']] == '3' || $event[$fields['faorres']] == '4') {
						$event[$fields['fastresn']] = '3';
					} else {
						$event[$fields['fastresn']] = $event[$fields['faorres']];
					}
				} elseif (is_numeric($event[$fields['faorres']])) {
					$event[$fields['fastresn']] = $event[$fields['faorres']];
				} else {
					$event[$fields['fastresn']] = NULL;
				}
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
d($vals_array);
$timer['start_main'] = microtime(true);
/**
 * Main loop
 */
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	if ($subjects != '') {
		d($subj_val_array);
	}
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['faorres'] != '') {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['fatestcd']) . ',' .
				fix_null($subj_array['fatest']) . ',' .
				fix_null($subj_array['faorres']) . ',' .
				fix_null($subj_array['fastresc']) . ',' .
				fix_null($subj_array['fastresn']) . ',' .
				fix_null(fix_case($subj_array['facat'])) . ',' .
				fix_null($subj_array['faobj']) . ',' .
				fix_null($subj_array['fadtc']) .
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
  `FASEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FATESTCD` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FATEST` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FAORRES` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FASTRESC` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FASTRESN` VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FACAT` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FAOBJ` VARCHAR(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `FADTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_fatestcd` (`USUBJID`,`FATESTCD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name";
$columns = "(STUDYID, DOMAIN, USUBJID, FASEQ, FATESTCD, FATEST, FAORRES, FASTRESC, FASTRESN, FACAT, FAOBJ, FADTC)";
$sql = "INSERT INTO $table_name $columns VALUES" . implode(",\n", $query);
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