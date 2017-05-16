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
$constants['DOMAIN'] = 'CM';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$vals_array = array();
/**
 * Query to get arrays of cmtrt, cmindc, cmdose, cmdosu, cmdosfrq, cmstdtc, cmendtc for each form
 * This query is finely tuned to capture all combinations of fields in use with
 * HCVT2 and STEADFAST.
 */
$fields_query = "SELECT DISTINCT
	'dm_usubjid',
	trt.field_name AS cmtrt,
	IF(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1) = 'cm', 'cm_suppcm_atc2name', 'xfsn_suppcm_atc2name') AS cmcat,
	cod.field_name AS cmdecod,
	indc.field_name AS cmindc,
	oth_indc.field_name AS oth_cmindc,
	IF(dose.field_name IS NULL, 'cm_cmdose', dose.field_name) AS cmdose,
	IF(dosu.field_name IS NULL, 'cm_cmdosu', dosu.field_name) AS cmdosu,
	IF(dosfrq.field_name IS NULL, 'cm_cmdosfrq', dosfrq.field_name) AS cmdosfrq,
	stdtc.field_name AS cmstdtc,
	IF(endtc.field_name IS NULL, 'cm_cmendtc', endtc.field_name) AS cmendtc,
	'cm_suppcm_cmimmuno' AS cmimmuno,
	'imminit_cmdose' AS imm_cmdose,
	'imminit_cmdosu' AS imm_cmdosu,
	'imminit_cmdosfrq' AS imm_cmdosfrq,
	dosu.element_type AS utype,
	dosu.element_label AS ulabel
	FROM redcap_metadata trt
	LEFT OUTER JOIN redcap_metadata cod ON cod.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdecod') AND trt.project_id = cod.project_id
	LEFT OUTER JOIN redcap_metadata indc ON indc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmindc') AND trt.project_id = indc.project_id AND trt.form_name = indc.form_name
	LEFT OUTER JOIN redcap_metadata oth_indc ON oth_indc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_oth_cmindc') AND trt.project_id = oth_indc.project_id AND trt.form_name = oth_indc.form_name
	LEFT OUTER JOIN redcap_metadata stdtc ON stdtc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmstdtc') AND trt.project_id = stdtc.project_id AND trt.form_name = stdtc.form_name
	LEFT OUTER JOIN redcap_metadata dose ON dose.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdose') AND trt.project_id = dose.project_id AND trt.form_name = dose.form_name
	LEFT OUTER JOIN redcap_metadata dosu ON dosu.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdosu') AND trt.project_id = dosu.project_id AND trt.form_name = dosu.form_name
	LEFT OUTER JOIN redcap_metadata dosfrq ON (dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdosfrq') OR dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_oth_cmdosfrq')) AND trt.project_id = dosfrq.project_id AND trt.form_name = dosfrq.form_name
	LEFT OUTER JOIN redcap_metadata endtc ON endtc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmendtc') AND trt.project_id = endtc.project_id AND trt.form_name = endtc.form_name
	WHERE trt.project_id = '$project_id'
	AND trt.field_name LIKE '%\_cmtrt'
	AND trt.element_type != 'descriptive'";
d($fields_query, 'FIELDS');

echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of fields
 */
$timer['start_fields'] = microtime(true);
$cm_fields_result = db_query($fields_query);
$cm_fields = array('dm_usubjid');
if ($cm_fields_result) {
	while ($cm_fields_row = db_fetch_assoc($cm_fields_result)) {
		if (isset($cm_fields_row['cmtrt'])) {
			$cm_fields[] = $cm_fields_row['cmtrt'];
		}
		if (isset($cm_fields_row['cmcat'])) {
			$cm_fields[] = $cm_fields_row['cmcat'];
		}
		if (isset($cm_fields_row['cmdecod'])) {
			$cm_fields[] = $cm_fields_row['cmdecod'];
		}
		if (isset($cm_fields_row['cmindc'])) {
			$cm_fields[] = $cm_fields_row['cmindc'];
		}
		if (isset($cm_fields_row['oth_cmindc'])) {
			$cm_fields[] = $cm_fields_row['oth_cmindc'];
		}
		if (isset($cm_fields_row['cmdose'])) {
			$cm_fields[] = $cm_fields_row['cmdose'];
		}
		if (isset($cm_fields_row['cmdosu'])) {
			$cm_fields[] = $cm_fields_row['cmdosu'];
		}
		if (isset($cm_fields_row['cmdosfrq'])) {
			$cm_fields[] = $cm_fields_row['cmdosfrq'];
		}
		if (isset($cm_fields_row['cmstdtc'])) {
			$cm_fields[] = $cm_fields_row['cmstdtc'];
		}
		if (isset($cm_fields_row['cmendtc'])) {
			$cm_fields[] = $cm_fields_row['cmendtc'];
		}
		if (isset($cm_fields_row['cmimmuno'])) {
			$cm_fields[] = $cm_fields_row['cmimmuno'];
		}
		if (isset($cm_fields_row['imm_cmdose'])) {
			$cm_fields[] = $cm_fields_row['imm_cmdose'];
		}
		if (isset($cm_fields_row['imm_cmdosu'])) {
			$cm_fields[] = $cm_fields_row['imm_cmdosu'];
		}
		if (isset($cm_fields_row['imm_cmdosfrq'])) {
			$cm_fields[] = $cm_fields_row['imm_cmdosfrq'];
		}
	}
	db_free_result($cm_fields_result);
}
$cm_fields = array_unique($cm_fields);
$data = REDCap::getData('array', $subjects, $cm_fields);
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
				if ($event[$fields['cmtrt']] != '') {
					/**
					 * transform: descriptive units handling
					 * where cmdosu is element_type=descriptive, use the label as the unit
					 * this is done where there is only one possible unit
					 */
					if ($fields['utype'] == 'descriptive') {
						$event[$fields['cmdosu']] = $fields['ulabel'];
					}
					/**
					 * transform: eliminate OTHER indications and clean up radio button value case
					 */
					if ($event[$fields['cmindc']] == 'OTHER') {
						$event[$fields['cmindc']] = $event[$fields['oth_cmindc']];
					} else {
						$event[$fields['cmindc']] = fix_case($event[$fields['cmindc']]);
					}
					/**
					 * transform: immunosuppressant dosages
					 */
					if ($event[$fields['cmimmuno']] == 'Y') {
						$event[$fields['cmdose']] = $event[$fields['imm_cmdose']];
						$event[$fields['cmdosu']] = $event[$fields['imm_cmdosu']];
						$event[$fields['cmdosfrq']] = $event[$fields['imm_cmdosfrq']];
					}
					/**
					 * build values array
					 */
					foreach ($fields AS $key => $field) {
						if (!in_array($field, array($fields['dm_usubjid'], $fields['utype'], $fields['ulabel'], $fields['oth_cmindc'], $fields['cmimmuno'], $fields['imm_cmdose'], $fields['imm_cmdosu'], $fields['imm_cmdosfrq']))) {
							$inner_vals[$key] = $event[$field];
						}
					}
					$vals_array[$usubjid][] = $inner_vals;
				}
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('cmstdtc');
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
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['cmtrt'] != '') {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['cmtrt']) . ',' .
				fix_null($subj_array['cmcat']) . ',' .
				fix_null($subj_array['cmdecod']) . ',' .
				fix_null($subj_array['cmindc']) . ',' .
				fix_null($subj_array['cmdose']) . ',' .
				fix_null($subj_array['cmdosu']) . ',' .
				fix_null($subj_array['cmdosfrq']) . ',' .
				fix_null($subj_array['cmstdtc']) . ',' .
				fix_null($subj_array['cmendtc']) .
				')';
			$seq++;
		}
	}
}
$timer['end_fields'] = microtime(true);
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'CM',
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `CMSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMTRT` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMCAT` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMDECOD` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMINDC` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMDOSE` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMDOSU` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMDOSFRQ` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMSTDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMENDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_cmtrt` (`USUBJID`,`CMTRT`),
  KEY `usubjid_cmstdtc` (`USUBJID`,`CMSTDTC`),
  KEY `usubjid_cmendtc` (`USUBJID`,`CMENDTC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name;";
$columns = "(STUDYID, DOMAIN, USUBJID, CMSEQ, CMTRT, CMCAT, CMDECOD, CMINDC, CMDOSE, CMDOSU, CMDOSFRQ, CMSTDTC, CMENDTC)";
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