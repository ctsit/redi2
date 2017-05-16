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
$constants['DOMAIN'] = 'MH';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$vals_array = array();
/**
 * Query to get arrays of cmtrt, cmindc, cmdose, cmdosu, cmdosfrq, cmstdtc, cmendtc for each form
 * This query is finely tuned to capture all combinations of fields in use with
 * HCVT2 and STEADFAST.
 */
$fields_query = "SELECT 'dm_usubjid', term.label, term.mhterm, oth_term.oth_mhterm, `modify`.mhmodify, occur.mhoccur, stdtc.mhstdtc
FROM
(SELECT DISTINCT term.element_type AS type, term.element_label AS label, term.field_name AS mhterm, term.form_name, term.project_id, term.field_order FROM redcap_metadata term WHERE term.field_name LIKE '%\_mhterm' AND term.field_name NOT LIKE '%\_oth_mhterm') term
LEFT OUTER JOIN
(SELECT DISTINCT oth_term.form_name, oth_term.project_id, oth_term.field_name AS oth_mhterm FROM redcap_metadata oth_term WHERE oth_term.field_name LIKE '%\_oth_mhterm') oth_term ON (oth_term.oth_mhterm = CONCAT(LEFT(term.mhterm, INSTR(term.mhterm, '_')-1), '_oth_mhterm')) AND  term.project_id = oth_term.project_id
LEFT OUTER JOIN
(SELECT DISTINCT `modify`.form_name, `modify`.project_id, `modify`.field_name AS mhmodify FROM redcap_metadata `modify` WHERE `modify`.field_name LIKE '%\_mhmodify') `modify` ON (`modify`.mhmodify = CONCAT(LEFT(term.mhterm, INSTR(term.mhterm, '_')-1), '_mhmodify')) AND  term.project_id = `modify`.project_id
LEFT OUTER JOIN
(SELECT DISTINCT stdtc.form_name, stdtc.project_id, stdtc.field_name AS mhstdtc FROM redcap_metadata stdtc WHERE stdtc.field_name LIKE '%\_suppmh_mhstyr') stdtc ON (stdtc.mhstdtc = CONCAT(LEFT(term.mhterm, INSTR(term.mhterm, '_')-1), '_suppmh_mhstyr')) AND  term.project_id = stdtc.project_id
LEFT OUTER JOIN
(SELECT DISTINCT occur.form_name, occur.project_id, occur.field_name AS mhoccur FROM redcap_metadata occur WHERE occur.field_name LIKE '%\_mhoccur') occur ON (occur.mhoccur = CONCAT(LEFT(term.mhterm, INSTR(term.mhterm, '_')-1), '_mhoccur') OR occur.mhoccur = CONCAT(LEFT(term.mhterm, INSTR(term.mhterm, '_')-1), '_oth_mhoccur')) AND  term.project_id = occur.project_id
WHERE term.project_id = '$project_id'
AND term.type = 'descriptive'";

echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$mh_fields_result = db_query($fields_query);
if ($mh_fields_result) {
	while ($mh_fields_row = db_fetch_assoc($mh_fields_result)) {
		foreach ($mh_fields_row AS $field_key => $field_name) {
			if (!in_array($field_key, array('label')) && isset($field_name)) {
				$mh_fields[] = $field_name;
			}
		}
	}
	db_free_result($mh_fields_result);
}
$mh_fields = array_unique($mh_fields);
if ($subjects != '') {
	d($mh_fields);
}
$data = REDCap::getData('array', $subjects, $mh_fields);
$timer['have_data'] = microtime(true);
$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		foreach ($data AS $subject_id => $subject) {
			$usubjid = '';
			foreach ($subject as $event_id => $event) {
				$inner_vals = array();
				if ($usubjid == '') {
					$usubjid = $event['dm_usubjid'];
				}
				if ($event[$fields['oth_mhterm']] != '' && isset($fields['oth_mhterm']) && $fields['mhterm'] != $fields['oth_mhterm']) {
					$event[$fields['mhterm']] = $event[$fields['oth_mhterm']];
					$event['mhpresp'] = '';
				} elseif ($event[$fields['oth_mhterm']] != '' && $fields['mhterm'] == $fields['oth_mhterm']) {
					$event[$fields['mhterm']] = $fields['label'];
					$event['mhpresp'] = 'Y';
				} else {
					$event[$fields['mhterm']] = $fields['label'];
					$event['mhpresp'] = 'Y';
				}
				if (isset($event[$fields['mhstdtc']]) && $event[$fields['mhstdtc']] != '' && strlen($event[$fields['mhstdtc']]) == 4) {
					$event[$fields['mhstdtc']] = $event[$fields['mhstdtc']] . '-01-01';
				}
				/**
				 * build values array
				 */
				$fields['mhpresp'] = 'mhpresp';
				foreach ($fields AS $key => $field) {
					if (!in_array($field, array($fields['dm_usubjid'], $fields['label'], $fields['oth_mhterm'])) || $fields['mhterm'] == $fields['oth_mhterm']) {
						$inner_vals[$key] = $event[$field];
					}
				}
				$vals_array[$usubjid][] = $inner_vals;
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('mhstdtc');
			usort($vals_array[$usubjid], array($sorter, "cmp"));
		}
	}
	db_free_result($fields_result);
}
if ($subjects != '') {
	d($vals_array);
}

$timer['start_main'] = microtime(true);
/**
 * Main loop
 */
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['mhoccur'] != '') {
			$llt = $subj_array['mhmodify'] != '' ? $subj_array['mhmodify'] : get_llt($subj_array['mhterm']);
			$pt = get_pt($llt);
			$soc = get_bodsys($pt);
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['mhterm']) . ',' .
				fix_null($llt) . ',' .
				fix_null($pt) . ',' .
				fix_null($soc) . ',' .
				fix_null($subj_array['mhoccur']) . ',' .
				fix_null($subj_array['mhpresp']) . ',' .
				fix_null($subj_array['mhstdtc']) . ',' .
				fix_null($subj_array['mhstdtc']) .
				')';
			$seq++;
		}
	}
}
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` CHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `MHSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHTERM` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHMODIFY` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHDECOD` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHBODSYS` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHOCCUR` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHPRESP` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHSTDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MHENDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `ix_mh_usubjid` (`USUBJID`),
  KEY `ix_mh_mhterm` (`MHTERM`),
  KEY `ix_mh_mhoccur` (`MHOCCUR`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name;";
$columns = "(STUDYID, DOMAIN, USUBJID, MHSEQ, MHTERM, MHMODIFY, MHDECOD, MHBODSYS, MHOCCUR, MHPRESP, MHSTDTC, MHENDTC)";
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