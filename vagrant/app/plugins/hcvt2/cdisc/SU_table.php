<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 1/8/14
 * Time: 9:43 AM
 * Purpose: This is a shell for plugin development in CDISC-compliant REDCap projects. It is meant to create a table of CDISC domain data
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
/**
 * if we need metadata from the project, this is where we can get it
 */
$project = new Project();
/**
 * instantiate some needed variables
 */
$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($project->project['project_name'], 0, 4) . substr($project->project['project_name'], strpos($project->project['project_name'], '_') + 1, 1));
$constants['DOMAIN'] = 'SU';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
/**
 * if we need to skip any metadata fields the query returns when outputting the data domain, they go here
 */
//$meta_fields = array('sutrt');
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * write a query to return a list of fields for which we want to extract data for a given domain
 */
$fields_query = "SELECT DISTINCT
'dm_usubjid',
'dm_rfstdtc' AS sustdtc,
trt.element_type AS type,
trt.element_label AS label,
trt.field_name AS sutrt,
ncf.field_name AS suncf,
dosfrq.field_name AS sudosfrq
FROM redcap_metadata trt
LEFT OUTER JOIN redcap_metadata dosfrq ON dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_sudosfrq') AND trt.form_name = dosfrq.form_name
LEFT OUTER JOIN redcap_metadata ncf ON ncf.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_suppsu_suncf') AND trt.form_name = ncf.form_name
WHERE trt.field_name LIKE '%\_sutrt'
AND trt.project_id = '$project_id'";
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$su_fields_result = db_query($fields_query);
$su_fields = array('dm_usubjid');
if ($su_fields_result) {
	while ($su_fields_row = db_fetch_assoc($su_fields_result)) {
		foreach ($su_fields_row AS $field_key => $field_name) {
			if (!in_array($field_key, array('type', 'label'))) {
				$su_fields[] = $field_name;
			}
		}
	}
	db_free_result($su_fields_result);
}
$su_fields = array_unique($su_fields);
$data = REDCap::getData('array', $subjects, $su_fields);
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
				if ($fields['type'] == 'descriptive') {
					$event[$fields['sutrt']] = $fields['label'];
				}
				if ($event[$fields['suncf']] == 'NEVER' || $event[$fields['suncf']] == 'UNKNOWN') {
					$event[$fields['suoccur']] = 'N';
				} elseif ($event[$fields['suncf']] == 'FORMER' || $event[$fields['suncf']] == 'CURRENT') {
					$event[$fields['suoccur']] = 'Y';
				} else {
					$event[$fields['suoccur']] = '';
				}
				/**
				 * build values array
				 */
				$fields['suoccur'] = 'suoccur';
				foreach ($fields AS $key => $field) {
					if (!in_array($key, array('dm_usubjid', 'type', 'label'))) {
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
			fix_null($subj_array['sutrt']) . ',' . // sutrt
			fix_null($subj_array['sutrt']) . ',' . // sucat
			fix_null($subj_array['sutrt']) . ',' . // sudecod
			fix_null($subj_array['sudosfrq']) . ',' .
			fix_null($subj_array['suoccur']) . ',' .
			fix_null($subj_array['sustdtc']) . ',' . // sustdtc
			fix_null($subj_array['sustdtc']) .
			')';
		$seq++;
	}
}
/**
 * end Main Loop
 */
/**
 * if not exists, create your domain data table
 */
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` CHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `SUSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUTRT` VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUCAT` VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUDECOD` VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUDOSFRQ` VARCHAR(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUOCCUR` CHAR(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUSTDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SUENDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `ix_su_usubjid` (`USUBJID`),
  KEY `ix_su_sutrt` (`SUTRT`),
  KEY `ix_su_sudosfrq` (`SUDOSFRQ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
/**
 * truncate to clear old data
 */
$truncate_query = "TRUNCATE TABLE $table_name";
/**
 * change to match table above
 */
$columns = "(STUDYID, DOMAIN, USUBJID, SUSEQ, SUTRT, SUCAT, SUDECOD, SUDOSFRQ, SUOCCUR, SUSTDTC, SUENDTC)";
/**
 * insert our rows to our new or freshly emptied table
 */
$sql = "INSERT INTO $table_name $columns VALUES\n" . implode(",\n", $query);
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
} else {
	show_var($sql);
}
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;