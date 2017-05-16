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
global $Proj;
Kint::enabled($debug);

$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'],0,4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'EX';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$vals_array = array();
$regimen_data = REDCap::getData('array', $subjects, $regimen_fields, $Proj->firstEventId);

/**
 * Query to get arrays of cmtrt, cmindc, cmdose, cmdosu, cmdosfrq, cmstdtc, cmendtc for each form
 * This query is finely tuned to capture all combinations of fields in use with
 * HCVT2 and STEADFAST.
 */
$fields_query = "SELECT DISTINCT
	'dm_usubjid',
	trt.element_type AS type,
	trt.element_label AS label,
	trt.field_name AS extrt,
	dose.field_name AS exdose,
	dosu.field_name AS exdosu,
	dosfrq.field_name AS exdosfrq,
	oth_dosfrq.field_name AS oth_exdosfrq,
	stdtc.field_name AS exstdtc,
	endtc.field_name AS exendtc,
	dosu.element_type AS utype,
	dosu.element_label AS ulabel
	FROM redcap_metadata trt
	LEFT OUTER JOIN redcap_metadata stdtc ON stdtc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmstdtc') AND trt.project_id = stdtc.project_id AND trt.form_name = stdtc.form_name
	LEFT OUTER JOIN redcap_metadata dose ON dose.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdose') AND trt.project_id = dose.project_id AND trt.form_name = dose.form_name
	LEFT OUTER JOIN redcap_metadata dosu ON dosu.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdosu') AND trt.project_id = dosu.project_id AND trt.form_name = dosu.form_name
	LEFT OUTER JOIN redcap_metadata dosfrq ON (dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdosfrq')) AND trt.project_id = dosfrq.project_id AND trt.form_name = dosfrq.form_name
	LEFT OUTER JOIN redcap_metadata oth_dosfrq ON (oth_dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_oth_cmdosfrq')) AND trt.project_id = oth_dosfrq.project_id AND trt.form_name = oth_dosfrq.form_name
	LEFT OUTER JOIN redcap_metadata endtc ON endtc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmendtc') AND trt.project_id = endtc.project_id AND trt.form_name = endtc.form_name
	WHERE trt.project_id = '$project_id'
	AND trt.field_name LIKE '%\_cmtrt'
	AND trt.element_type = 'descriptive'";
if ($subjects != '') {
	d($fields_query);
}

echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of fields
 */
$timer['start_fields'] = microtime(true);
$ex_fields_result = db_query($fields_query);
$ex_fields = array('dm_usubjid');
if ($ex_fields_result) {
	while ($ex_fields_row = db_fetch_assoc($ex_fields_result)) {
		if (isset($ex_fields_row['extrt'])) {
			$ex_fields[] = $ex_fields_row['extrt'];
		}
		if (isset($ex_fields_row['exdose'])) {
			$ex_fields[] = $ex_fields_row['exdose'];
		}
		if (isset($ex_fields_row['exdosu'])) {
			$ex_fields[] = $ex_fields_row['exdosu'];
		}
		if (isset($ex_fields_row['exdosfrq'])) {
			$ex_fields[] = $ex_fields_row['exdosfrq'];
		}
		if (isset($ex_fields_row['exstdtc'])) {
			$ex_fields[] = $ex_fields_row['exstdtc'];
		}
		if (isset($ex_fields_row['exendtc'])) {
			$ex_fields[] = $ex_fields_row['exendtc'];
		}
	}
	db_free_result($ex_fields_result);
}
$ex_fields = array_unique($ex_fields);
$data = REDCap::getData('array', $subjects, $ex_fields);
$timer['have_data'] = microtime(true);
$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		foreach ($data AS $subject_id => $subject) {
			unset($usubjid);
			foreach ($subject AS $event_id => $event) {
				$inner_vals = array();
				if (!isset($usubjid)) {
					$usubjid = $event['dm_usubjid'];
				}
				if ($event[$fields['exstdtc']] != '') {
					/**
					 * transform: get extrt from metadata
					 */
					if ($fields['type'] == 'descriptive') {
						$event[$fields['extrt']] = $fields['label'];
					}
					/**
					 * transform: descriptive units handling
					 * where cmdosu is element_type=descriptive, use the label as the unit
					 * this is done where there is only one possible unit
					 */
					if ($fields['utype'] == 'descriptive') {
						$event[$fields['exdosu']] = $fields['ulabel'];
					}
					/**
					 * transform: eliminate _oth_cmdosfrq
					 */
					if ($event[$fields['exdosfrq']] == 'OTHER') {
						$event[$fields['exdosfrq']] = $event[$fields['oth_exdosfrq']];
					}
					/**
					 * transform: eliminate _oth_cmdose
					 */
					if ($event[$fields['exdose']] == 'OTHER') {
						$event[$fields['exdose']] = NULL;
					}
					/**
					 * fix dosing on Harvoni
					 */
					if ($event[$fields['extrt']] == 'Harvoni') {
						if ($event[$fields['exdose']] != '') {
							$event[$fields['exdose']] = '1';
							$event[$fields['exdosu']] = 'tablet';
						}
					}
					/**
					 * fix dosing on Dasabuvir
					 */
					if ($event[$fields['extrt']] == 'Dasabuvir') {
						if ($event[$fields['exdosu']] != '') {
							$event[$fields['exdose']] = '250';
							$event[$fields['exdosu']] = 'mg';
						}
					}
					/**
					 * fix dosing and EXTRT for Viekira
					 */
					if (strpos($event[$fields['extrt']], 'Viekira') !== false) {
						$event[$fields['extrt']] = 'Technivie';
						if (isset($event[$fields['exdose']])) {
							if ($event[$fields['exdose']] == '1') {
								$event[$fields['exdosu']] = 'tablet';
							} else {
								$event[$fields['exdosu']] = 'tablets';
							}
						}
					}
					/**
					 * build values array
					 */
					foreach ($fields AS $key => $field) {
						if (!in_array($field, array($fields['dm_usubjid'], $fields['type'], $fields['label'], $fields['utype'], $fields['ulabel'], $fields['oth_exdosfrq']))) {
							$inner_vals[$key] = $event[$field];
						}
					}
					$vals_array[$usubjid][] = $inner_vals;
				}
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('exstdtc');
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
	if ($subjects != '') {
		d($subj_val_array);
	}
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['exstdtc'] != '') {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['extrt']) . ',' . // extrt
				fix_null($subj_array['extrt']) . ',' . // exdecod
				fix_null($subj_array['exdose']) . ',' .
				fix_null($subj_array['exdosu']) . ',' .
				fix_null($subj_array['exdosfrq']) . ',' .
				fix_null($subj_array['exstdtc']) . ',' .
				fix_null($subj_array['exendtc']) .
				')';
			$seq++;
		}
	}
}
$timer['end_fields'] = microtime(true);
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` varchar(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'EX',
  `USUBJID` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `EXSEQ` int(8) DEFAULT NULL,
  `EXTRT` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EXDECOD` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EXDOSE` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EXDOSU` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EXDOSFRQ` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EXSTDTC` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EXENDTC` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_extrt` (`USUBJID`,`EXTRT`),
  KEY `usubjid_exstdtc` (`USUBJID`,`EXSTDTC`),
  KEY `usubjid_exendtc` (`USUBJID`,`EXENDTC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name;";
$columns = "(STUDYID, DOMAIN, USUBJID, EXSEQ, EXTRT, EXDECOD, EXDOSE, EXDOSU, EXDOSFRQ, EXSTDTC, EXENDTC)";
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
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;