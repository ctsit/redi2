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
require_once $base_path . '/plugins/includes/export_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';

// Restrict access to the desired projects
$allowed_pids = array('38');
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
$constants['STUDYID'] = strtoupper(substr($project->project['project_name'],0,4) . substr($project->project['project_name'], strpos($project->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'TA';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$column_array = array("STUDYID", "DOMAIN", "ARMCD", "ARM", "TAETORD", "ETCD", "ELEMENT", "TABRANCH", "TATRANS", "EPOCH");
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of field_names
 */
$timer['start_main'] = microtime(true);
$ta_result = db_query("SELECT DISTINCT ACTARMCD, ACTARM from _hcvt2_dm");
if ($ta_result) {
	$seq = 1;
	while ($ta_row = db_fetch_assoc($ta_result)) {
		if ($ta_row['ACTARMCD'] != '' && !in_array($ta_row['ACTARMCD'], array('NOTTRT', 'SCRNFAIL'))) {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($ta_row['ACTARMCD']) . ',' .
				fix_null($ta_row['ACTARM']) . ',' .
				fix_null($seq) . ',' .
				fix_null('TRT') . ',' .
				fix_null('TREATMENT') . ',' .
				fix_null('') . ',' .
				fix_null('') . ',' .
				fix_null('TREATMENT') .
				')';
			$seq++;
		}
	}
}
/**
 * end Main Loop
 */
$timer['end_main'] = microtime(true);
/**
 * if not exists, create your domain data table
 */
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'DM',
  `ARMCD` VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  `ARM` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
  `TAETORD` INT(4) COLLATE utf8_unicode_ci DEFAULT NOT NULL,
  `ETCD` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NOT NULL,
  `ELEMENT` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NOT NULL,
  `TABRANCH` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `TATRANS` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EPOCH` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
/**
 * truncate to clear old data
 */
$truncate_query = "TRUNCATE TABLE $table_name";
/**
 * change to match table above
 */
$columns = "(" . implode(', ', $column_array) . ")";
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
			error_log("USER $userid attempted to create user file for domain {$constants['DOMAIN']}");
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