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
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
/**
 * if we need metadata from the project, this is where we can get it
 */
global $Proj;
$first_event_id = $Proj->firstEventId;
/**
 * instantiate some needed variables
 */
$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'],0,4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'SV';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
/**
 * this query will capture all DTC variables for all findings domains (where VISITS are captured) plus AEs
 */
$fields_query = "SELECT DISTINCT stdtc.field_name AS svstdtc FROM
redcap_metadata stdtc WHERE (
field_name LIKE '%lbdtc'
OR field_name LIKE '%aestdtc'
OR field_name LIKE '%iedtc'
OR field_name LIKE '%egdtc'
OR field_name LIKE '%qsdtc'
OR field_name LIKE '%scdtc'
OR field_name LIKE '%vsdtc'
OR field_name LIKE '%dadtc'
OR field_name LIKE '%mbdtc'
OR field_name LIKE '%msdtc'
OR field_name LIKE '%ppdtc'
OR field_name LIKE '%pcdtc'
OR field_name LIKE '%fadtc'
OR field_name = 'dm_rfstdtc'
OR field_name = 'consent_dssstdtc'
) AND field_name NOT LIKE '%supp%' AND stdtc.project_id = '$project_id'";
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";

/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$sv_fields_result = db_query($fields_query);
$sv_fields = array('dm_usubjid', 'consent_dssstdtc', 'dm_rfstdtc');
if ($sv_fields_result) {
	while ($sv_fields_row = db_fetch_assoc($sv_fields_result)) {
		foreach ($sv_fields_row AS $field_key => $field_name) {
			$sv_fields[] = $field_name;
		}
	}
	db_free_result($sv_fields_result);
}
$sv_fields = array_unique($sv_fields);
$data = REDCap::getData('array', $subjects, $sv_fields);
$timer['have_data'] = microtime(true);
/**
 * run the fields query and construct the values array
 */
$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	$vals_array = get_visits($fields_result, $data, $first_event_id);
	db_free_result($fields_result);
}
d($vals_array);
/**
 * Main loop
 */
$column_array = array('STUDYID', 'DOMAIN', 'USUBJID', 'VISITNUM', 'VISIT', 'VISITDY', 'SVSTDTC', 'SVENDTC', 'SVSTDY', 'SVENDY', 'SVUPDES');
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	if ($subjects != '') {
		d($subj_val_array);
	}
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if (isset($subj_array['svstdtc'])) {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['visit']) . ',' .
				fix_null($subj_array['visitdy']) . ',' .
				fix_null($subj_array['svstdtc']) . ',' .
				fix_null($subj_array['svstdtc']) . ',' .
				fix_null($subj_array['svstdy']) . ',' .
				fix_null($subj_array['svstdy']) . ',' .
				fix_null($subj_array['svupdes']) .
				')';
			$seq++;
		}
	}
}
if ($subjects != '') {
	d($query);
}
/**
 * end Main Loop
 */
$timer['end_main'] = microtime(true);
/**
 * if not exists, create your domain data table
 */
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` CHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'SV',
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `VISITNUM` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VISIT` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VISITDY` INT(8) DEFAULT NULL,
  `SVSTDTC` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SVENDTC` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SVSTDY` INT(8) DEFAULT NULL,
  `SVENDY` INT(8) DEFAULT NULL,
  `SVUPDES` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$table_create_query .= "ALTER TABLE `$table_name`
  ADD KEY `ix_usubjid` (`USUBJID`), ADD KEY `ix_svstdtc` (`SVSTDTC`);";
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
			error_log("SQL INSERT FAILED: " . db_error());
			echo db_error() . "<br />";
		}
	} else {
		error_log("TRUNCATE FAILED: " . db_error());
		echo db_error() . "<br />";
	}
}
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;