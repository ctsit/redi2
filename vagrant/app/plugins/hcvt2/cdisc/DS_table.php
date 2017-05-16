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
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'],0,4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'DS';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$vals_array = array();
$ignore_fields = array('termtype', 'dscat');
$meta_types = array('descriptive');
$meta_fields = array('dm_usubjid', 'dsterm', 'dsstdtc', 'dsae');
$ae_array = array('ADVERSE EVENT', 'DEATH');
$ncomplt_array = array('COMPLETED', 'LOST TO FOLLOWUP', 'LACK OF EFFICACY', 'WITHDRAWAL BY SUBJECT', 'NON COMPLIANCE WITH STUDY DRUG');
$other_array = array('OTHER', 'SCREEN FAILURE');
/**
 * Query to get arrays of fatestcd, fatest, faorres, facat, faobj
 */
$fields_query = "SELECT DISTINCT
'dm_usubjid',
term.field_name AS dsterm,
othterm.field_name AS oth_dsterm,
term.element_type AS termtype,
IF(decod.element_label IS NOT NULL, decod.element_label, 'dsdecod') AS dsdecod,
stdtc.field_name AS dsstdtc,
cat.element_label AS dscat,
ncmpae.field_name AS dsae
FROM redcap_metadata term
LEFT OUTER JOIN redcap_metadata othterm ON othterm.field_name = CONCAT(LEFT(term.field_name, INSTR(term.field_name, '_')-1), '_oth_dsterm') AND othterm.project_id = term.project_id AND othterm.form_name = term.form_name
LEFT OUTER JOIN redcap_metadata decod ON decod.field_name = CONCAT(LEFT(term.field_name, INSTR(term.field_name, '_')-1), '_dsdecod') AND decod.project_id = term.project_id AND decod.form_name = term.form_name
LEFT OUTER JOIN redcap_metadata stdtc ON (stdtc.field_name = CONCAT(LEFT(term.field_name, INSTR(term.field_name, '_')-1), '_dsstdtc') OR stdtc.field_name = CONCAT(LEFT(term.field_name, INSTR(term.field_name, '_')-1), '_dssstdtc')) AND stdtc.project_id = term.project_id AND stdtc.form_name = term.form_name
LEFT OUTER JOIN redcap_metadata cat ON cat.field_name = CONCAT(LEFT(term.field_name, INSTR(term.field_name, '_')-1), '_dscat') AND cat.project_id = term.project_id AND cat.form_name = term.form_name
LEFT OUTER JOIN redcap_metadata ncmpae ON ncmpae.field_name = 'eot_aedecod' AND ncmpae.project_id = term.project_id
WHERE term.field_name LIKE '%\_dsterm' AND term.field_name NOT LIKE '%\_oth_dsterm'
AND term.project_id = '$project_id'";

echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$ds_fields_result = db_query($fields_query);
if ($ds_fields_result) {
	while ($ds_fields_row = db_fetch_assoc($ds_fields_result)) {
		foreach ($ds_fields_row AS $field_key => $field_name) {
			if (isset($field_name)) {
				if (in_array($ds_fields_row['termtype'], $meta_types)) {
					if (in_array($field_key, $meta_fields)) {
						$ds_fields[] = $field_name;
					}
				} else {
					if (!in_array($field_key, $ignore_fields)) {
						$ds_fields[] = $field_name;
					}
				}
			}
		}
	}
	$ds_fields[] = 'dm_rfstdtc';
	db_free_result($ds_fields_result);
}
$ds_fields = array_unique($ds_fields);
//d($ds_fields);
$data = REDCap::getData('array', $subjects, $ds_fields);
$timer['have_data'] = microtime(true);
$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
/**
 * Get arrays of fatestcd, fatest, faorres, faorresu, fadtc for each form
 */
$fields_result = db_query($fields_query);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		if ($subjects != '') {
			d($fields);
		}
		foreach ($data AS $subject_id => $subject) {
			$usubjid = '';
			foreach ($subject as $event_id => $event) {
				if ($debug && $subjects != '') {
					d($event, 'EVENT');
				}
				$inner_vals = array();
				if ($usubjid == '') {
					$usubjid = $event['dm_usubjid'];
				}
				/**
				 * derive the dsstdy for this event
				 */
				if ($event[$fields['dsstdtc']] != '' && $event['dm_rfstdtc'] != '') {
					$rfstdtc = new DateTime($event['dm_rfstdtc']);
					$dsstdtc = new DateTime($event[$fields['dsstdtc']]);
					$dsstdy = $rfstdtc->diff($dsstdtc);
					$event['dsstdy'] = $dsstdy->format('%R%a') + 1;
				} else {
					$event['dsstdy'] = null;
				}
				/**
				 * this may seem kludgy. it's needed to fit SDTM's somwhat arbitrary labeling of DS events.
				 */
				$event[$fields['dscat']] = $fields['dscat'];
				if (!is_array($event[$fields['dsterm']])) {
					$event[$fields['dsterm']] = str_replace('_', ' ', $event[$fields['dsterm']]);
					if (in_array($event[$fields['dsterm']], $ncomplt_array)) {
						$decod = $event[$fields['dsterm']];
						$event[$fields['dsdecod']] = $decod;
					}
					if (in_array($event[$fields['dsterm']], $other_array)) {
						$event[$fields['dsdecod']] = $event[$fields['dsterm']];
						$event[$fields['dsterm']] = strtoupper($event[$fields['oth_dsterm']]);
					}
					if ($fields['dscat'] == 'PROTOCOL MILESTONE') {
						$event[$fields['dsterm']] = $fields['dsdecod'];
						$event[$fields['dsdecod']] = $fields['dsdecod'];
					}
					if ($event[$fields['dsae']] != '' && in_array($event[$fields['dsterm']], $ae_array)) {
						$decod = $event[$fields['dsterm']];
						$event[$fields['dsterm']] = strtoupper($event[$fields['dsae']]);
						$event[$fields['dsdecod']] = $decod;
					}
					/*if (isset($event[$fields['dsstdtc']]) && !in_array($event[$fields['dsterm']], array('ADVERSE_EVENT', 'COMPLETED', 'LOST_TO_FOLLOWUP', 'LACK_OF_EFFICACY', 'WITHDRAWAL_BY_SUBJECT', 'INFORMED CONSENT OBTAINED'))) {
						$event[$fields['dsdecod']] = 'PROTOCOL_VIOLATION';
					} else {
						$event[$fields['dsdecod']] = $event[$fields['dsterm']];
					}*/
				} else {
					if ($event[$fields['dsterm']][$event[$fields['dsdecod']]] == '1') {
						/**
						 * provisional until I have data which with I can test this case
						 */
						$event[$fields['dscat']] = $fields['dscat'];
						$event[$fields['dsdecod']] = $fields['dsdecod'];
						$event[$fields['dsterm']] = $fields['dsdecod'];
					} else {
						continue;
					}
				}
				/*
				 * add dsstdy to the $fields
				 */
				$fields['dsstdy'] = 'dsstdy';
				/**
				 * build values array
				 */
				foreach ($fields AS $key => $field) {
					if (!in_array($field, array($fields['dm_usubjid'], $fields['termtype']))) {
						$inner_vals[$key] = $event[$field];
					}
				}
				$vals_array[$usubjid][] = $inner_vals;
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('dsstdtc');
			usort($vals_array[$usubjid], array($sorter, "cmp"));
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
	//d($subj_val_array);
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['dsterm'] != '') {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['dsterm']) . ',' .
				fix_null($subj_array['dsdecod']). ',' .
				fix_null($subj_array['dsstdtc']) . ',' .
				fix_null($subj_array['dsstdy']) . ',' .
				fix_null($subj_array['dscat']) .
				')';
			$seq++;
		}
	}
}
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `DSSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `DSTERM` CHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `DSDECOD` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `DSSTDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `DSSTDY` VARCHAR(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `DSCAT` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_dsterm` (`USUBJID`,`DSTERM`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name";
$columns = "(STUDYID, DOMAIN, USUBJID, DSSEQ, DSTERM, DSDECOD, DSSTDTC, DSSTDY, DSCAT)";
$sql = "INSERT INTO $table_name $columns VALUES\n";
$sql .= implode(",\n", $query);
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
			error_log("SQL INSERT FAILED: " . db_error());
			echo db_error() . "<br />";
		}
	} else {
		error_log("TRUNCATE FAILED: " . db_error());
		echo db_error() . "<br />";
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;