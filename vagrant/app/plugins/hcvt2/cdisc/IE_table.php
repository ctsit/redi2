<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 1/15/14
 * Time: 12:08 PM
 * Purpose: Create CDISC table for IE domain
 */
$debug = false;
$timer_start = microtime(true);
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
/**
 * if we need metadata from the project, this is where we can get it
 */
$testcd_enum = $Proj->metadata['ie_ietestcd']['element_enum'];
$testcd_outer = explode(' \n ', $testcd_enum);
$testcd_inner = array();
foreach ($testcd_outer AS $testcd) {
	$testcd = explode(', ', $testcd);
	$testcd_inner[$testcd[0]] = $testcd[1];
}
/**
 * instantiate some needed variables
 */
$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'], 0, 4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_') + 1, 1));
$constants['DOMAIN'] = 'IE';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * query to get IE field values
 */
$values_query = "SELECT DISTINCT '{$constants['STUDYID']}' AS STUDYID,
'{$constants['DOMAIN']}' AS DOMAIN,
CONCAT('{$constants['STUDYID']}', '-', a.value) AS USUBJID,
testcdval.value AS IETESTCD,";
$parse_eval_str = '';
$paren_str = '';
foreach ($testcd_inner AS $cdkey => $cdval) {
	$parse_eval_str .= "\nIF(testcdval.value = '{$cdkey}', '{$cdval}', ";
	$paren_str .= ')';
}
$values_query .= $parse_eval_str . "NULL" . $paren_str . " AS IETEST,\n";
$values_query .= "IF(SUBSTR(testcdval.value,1,4) = 'INCL', 'INCLUSION', IF(SUBSTR(testcdval.value,1,4) = 'EXCL', 'EXCLUSION', NULL)) AS IECAT,
IF(SUBSTR(testcdval.value,1,4) = 'INCL', 'N', IF(SUBSTR(testcdval.value,1,4) = 'EXCL', 'Y', NULL)) AS IEORRES,
stresc.value AS IESTRESC
FROM redcap_data a
LEFT OUTER JOIN _hcvt2_testcd testcd ON testcd.domain = 'IE'
LEFT OUTER JOIN redcap_data testcdval ON a.record = testcdval.record AND a.project_id = testcdval.project_id
LEFT OUTER JOIN redcap_data stresc ON a.record = stresc.record AND a.project_id = stresc.project_id AND testcdval.event_id = stresc.event_id
WHERE a.field_name = 'dm_usubjid'
AND testcdval.field_name = 'ie_ietestcd'
AND testcdval.value IS NOT NULL
AND testcdval.value != ''
AND stresc.field_name = 'ie_iestresc'
AND a.project_id = '$project_id'\n";
d($values_query);
$fields_collection = array("dm_subjid", "dm_usubjid", "ie_ietestcd", "ie_iestresc");

/**
 * Main loop
 * Iterate subjects
 */
$main_query = "SELECT DISTINCT d.record AS subjid, d1.value AS usubjid
FROM redcap_data d
JOIN redcap_data d1
ON d.record = d1.record AND d.project_id = d1.project_id
WHERE d.project_id = '$project_id'
AND d.record != ''
AND d1.field_name = 'dm_usubjid'
ORDER BY d.record ASC";
$main_result = db_query($main_query);
if ($main_result) {
	while ($main_row = db_fetch_assoc($main_result)) {
		/**
		 * do stuff
		 */
		$seq = 1;
		$constants['USUBJID'] = $constants['STUDYID'] . '-' . $main_row['usubjid'];
		/**
		 * construct query to select rows for insert to IE table
		 */
		$subj_values_query = $values_query . "AND a.record = '{$main_row['subjid']}'";
		$subj_values_result = db_query($subj_values_query);
		if ($subj_values_result) {
			while ($vals = db_fetch_assoc($subj_values_result)) {
				if ($vals['IETESTCD'] != '') {
					$query[] = '(' .
						fix_null($constants['STUDYID']) . ',' .
						fix_null($constants['DOMAIN']) . ',' .
						fix_null($constants['USUBJID']) . ',' .
						fix_null($seq) . ',' .
						fix_null($vals['IETESTCD']) . ',' .
						fix_null($vals['IETEST']) . ',' .
						fix_null($vals['IECAT']) . ',' .
						fix_null($vals['IEORRES']) . ',' .
						fix_null($vals['IESTRESC']) .
						')';
					$seq++;
				}
			}
		}
	}
	db_free_result($main_result);
}
/**
 * end Main Loop
 */
/**
 * if not exists, create your domain data table
 */
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
	  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
	  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'IE',
	  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
	  `IESEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IETESTCD` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IETEST` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IECAT` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IEORRES` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IESTRESC` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
	  KEY `ix_usubjid` (`USUBJID`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
/**
 * truncate to clear old data
 */
$truncate_query = "TRUNCATE TABLE $table_name";
/**
 * change to match table above
 */
$columns = "(STUDYID, DOMAIN, USUBJID, IESEQ, IETESTCD, IETEST, IECAT, IEORRES, IESTRESC)";
/**
 * insert our rows to our new or freshly emptied table
 */
$sql = "INSERT INTO $table_name $columns VALUES\n" . implode(",\n", $query);
d($sql);
if (defined("USERID")) {
	$userid = USERID;
} else if (in_array(CRON_PAGE, non_auth_pages())) {
	$userid = "[CRON]";
} else {
	$userid = '';
}
if (!$debug) {
	if (!empty($query)) {
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
		echo "<h2>NO RESULTS FOUND. Table is empty.</h2>";
		create_cdisc_download($table_name, $lang, $app_title, $userid, $user_rights, $chkd_fields, '', $project_id, $constants['DOMAIN'], $debug);
	}
}
$timer_stop = microtime(true);
$timer_time = number_format(($timer_stop - $timer_start), 2);
echo 'This page loaded in ', $timer_time / 60, ' minutes';