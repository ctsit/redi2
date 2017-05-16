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
$project = new Project();

$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($project->project['project_name'],0,4) . substr($project->project['project_name'], strpos($project->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'VS';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$vals_array = array();
$meta_fields = array('vstest', 'vstestcd');
/**
 * Query to get arrays of vstestcd, vstest, vsorres, vsorresu
 */
$fields_query = "SELECT DISTINCT
'dm_usubjid',
'dm_rfstdtc' AS vsdtc,
testcd.element_label AS vstestcd,
test.element_label AS vstest,
orres.field_name AS vsorres,
orresu.field_name AS vsorresu
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_vstest') AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_vsorres') AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_vsorresu') AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
WHERE testcd.field_name LIKE '%\_vstestcd'
AND testcd.project_id = '$project_id'";
if ($debug) {
	show_var($fields_query, 'FIELDS');
}
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$vs_fields_result = db_query($fields_query);
$vs_fields = array('dm_usubjid');
if ($vs_fields_result) {
	while ($vs_fields_row = db_fetch_assoc($vs_fields_result)) {
		foreach ($vs_fields_row AS $field_key => $field_name) {
			$vs_fields[] = $field_name;
		}
	}
	db_free_result($vs_fields_result);
}
$vs_fields = array_unique($vs_fields);
$data = REDCap::getData('array', $subjects, $vs_fields);
$timer['have_data'] = microtime(true);


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
				$event[$fields['vstestcd']] = $fields['vstestcd'];
				$event[$fields['vstest']] = $fields['vstest'];
				/**
				 * build values array
				 */
				foreach ($fields AS $key => $field) {
					if (!in_array($key, array('dm_usubjid'))) {
						$inner_vals[$key] = $event[$field];
					}
				}
				$vals_array[$usubjid][] = $inner_vals;
			}
		}
	}
	db_free_result($fields_result);
}

$timer['start_main'] = microtime(true);
/**
 * Main loop
 */
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	if ($debug) {
		//show_var($subj_val_array, 'SUBJ VAL', 'blue');
	}
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		$query[] = '(' .
			fix_null($constants['STUDYID']) . ',' .
			fix_null($constants['DOMAIN']) . ',' .
			fix_null($constants['USUBJID']) . ',' .
			fix_null($seq) . ',' .
			fix_null($subj_array['vstestcd']) . ',' .
			fix_null($subj_array['vstest']) . ',' .
			fix_null($subj_array['vsorres']) . ',' . // vsorres
			fix_null($subj_array['vsorresu']) . ',' .
			fix_null($subj_array['vsorres']) . ',' . // vsstresn
			fix_null($subj_array['vsdtc']) .
			')';
		$seq++;
	}
}
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `VSSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VSTESTCD` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VSTEST` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VSORRES` VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VSORRESU` VARCHAR(12) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VSSTRESN` VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VSDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_vstestcd` (`USUBJID`,`VSTESTCD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name";
$columns = "(STUDYID, DOMAIN, USUBJID, VSSEQ, VSTESTCD, VSTEST, VSORRES, VSORRESU, VSSTRESN, VSDTC)";
$sql = "INSERT INTO $table_name $columns VALUES" . implode(",\n", $query);
if (!$debug) {
	if (db_query($table_create_query)) {
		echo "$table_name exists<br />";
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
	} else {
		error_log("CREATE TABLE FAILED: " . db_error() . "\n");
		echo db_error() . "<br />";
	}
} else {
	show_var($sql);
}
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;