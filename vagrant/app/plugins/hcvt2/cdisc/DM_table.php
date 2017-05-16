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
//$subjects = array('3468', '4592', '4970'); // '' = ALL
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
//d($Proj->events[1]['name']);
$first_event_id = $Proj->firstEventId;
/**
 * instantiate some needed variables
 */
$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'],0,4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'DM';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$fields = array('USUBJID' => 'dm_usubjid', 'RFSTDTC' => 'dm_rfstdtc', 'RFENDTC' => 'eot_dsstdtc', 'AGE' => 'age_suppvs_age', 'SEX' => 'dm_sex', 'RACE' => 'dm_race', 'ETHNIC' => 'dm_ethnic', 'BRTHDTC' => 'dm_brthyr', 'DSTERM' => 'eot_dsterm', 'TXSTAT' => 'trt_suppcm_txstat');
$countries = array('0' => 'USA', '3' => 'DEU', '9' => 'FRA', '4' => 'ISR', 'CAN' => 'CAN');
$column_array = array("STUDYID", "DOMAIN", "USUBJID", "SUBJID", "RFSTDTC", "RFENDTC", "SITEID", "BRTHDTC", "AGE", "SEX", "RACE", "ETHNIC", "ARMCD", "ARM", "ACTARMCD", "ACTARM", "COUNTRY");
$regimen_data = REDCap::getData('array', $subjects, $regimen_fields, $first_event_id);
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$dm_fields = array('dm_usubjid');
foreach ($fields AS $field_key => $field_name) {
	$dm_fields[] = $field_name;
}
$dm_fields = array_unique($dm_fields);
$data = REDCap::getData('array', $subjects, $dm_fields, $first_event_id);
$timer['have_data'] = microtime(true);

foreach ($data AS $subject_id => $subject) {
	$usubjid = '';
	$subjid = $subject_id;
	/**
	 * treatment regimen (ARM)
	 */
	$regimen = get_regimen($regimen_data[$subject_id], $subject[$first_event_id][$fields['DSTERM']], $subject[$first_event_id][$fields['TXSTAT']]);
	/**
	 * subject data
	 */
	foreach ($subject AS $event_id => $event) {
		$inner_vals = array();
		if ($usubjid == '') {
			$usubjid = $event['dm_usubjid'];
		}
		/**
		 * build values array
		 */
		foreach ($fields AS $key => $field) {
			if (!in_array($field, array($fields['USUBJID']))) {
				$inner_vals[$key] = $event[$field];
			}
		}
		$inner_vals['SUBJID'] = $subjid;
		/**
		 * inject planned ARM into event
		 */
		$inner_vals['arm'] = $regimen['arm'];
		$inner_vals['armcd'] = $regimen['armcd'];
		/**
		 * inject actual ARM into event
		 */
		$inner_vals['actarm'] = $regimen['actarm'];
		$inner_vals['actarmcd'] = $regimen['actarmcd'];
		/**
		 * set up $vals_array
		 */
		$vals_array[$usubjid][] = $inner_vals;
	}
	/**
	 * sort $vals_array by date
	 */
	$sorter = new FieldSorter('RFSTDTC');
	usort($vals_array[$usubjid], array($sorter, "cmp"));
}
d($vals_array);

$timer['start_main'] = microtime(true);
/**
 * Main loop
 */
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	d($subj_val_array);
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	$constants['SITEID'] = substr($subj_usubjid, 0, 3);
	/**
	 * currently, the only Canadian site is 049, so set an exception for COUNTRY. This may need to change in the future as more Canadian sites come online
	 */
	$constants['COUNTRY'] = substr($subj_usubjid, 0, 3) == '049' ? 'CAN' : $countries[substr($subj_usubjid, 0, 1)];
	foreach ($subj_val_array AS $subj_array) {
		$query[] = '(' .
			fix_null($constants['STUDYID']) . ',' .
			fix_null($constants['DOMAIN']) . ',' .
			fix_null($constants['USUBJID']) . ',' .
			fix_null($subj_array['SUBJID']) . ',' .
			fix_null($subj_array['RFSTDTC']) . ',' .
			fix_null($subj_array['RFENDTC']) . ',' .
			fix_null($constants['SITEID']) . ',' .
			fix_null($subj_array['BRTHDTC']) . ',' .
			fix_null($subj_array['AGE']) . ',' .
			fix_null($subj_array['SEX']) . ',' .
			fix_null(fix_case($subj_array['RACE'])) . ',' .
			fix_null(fix_case($subj_array['ETHNIC'])) . ',' .
			fix_null($subj_array['armcd']) . ',' .
			fix_null($subj_array['arm']) . ',' .
			fix_null($subj_array['actarmcd']) . ',' .
			fix_null($subj_array['actarm']) . ',' .
			fix_null($constants['COUNTRY']) .
			')';
		$seq++;
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
  `STUDYID` CHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'DM',
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `SUBJID` CHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `RFSTDTC` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `RFENDTC` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SITEID` CHAR(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `BRTHDTC` CHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AGE` CHAR(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `SEX` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `RACE` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ETHNIC` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ARMCD` VARCHAR(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ARM` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ACTARMCD` VARCHAR(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ACTARM` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `COUNTRY` CHAR(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `ix_usubjid` (`USUBJID`),
  KEY `ix_subjid` (`SUBJID`)
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