<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 12/18/13
 * Time: 9:20 AM
 */
$debug = false;
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
$constants['DOMAIN'] = 'CM';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$vals_array = array();
/**
 * Query to get arrays of cmtrt, cmindc, cmdose, cmdosu, cmdosfrq, cmstdtc, cmendtc for each form
 * This query is finely tuned to capture all combinations of fields in use with
 * HCVT2 and STEADFAST.
 */
$fields_query = "SELECT DISTINCT
	'dm_usubjid',
	trt.element_type AS type,
	trt.element_label AS label,
	trt.field_name AS cmtrt,
	cod.field_name AS cmdecod,
	indc.field_name AS cmindc,
	dose.field_name AS cmdose,
	dosu.field_name AS cmdosu,
	dosfrq.field_name AS cmdosfrq,
	stdtc.field_name AS cmstdtc,
	endtc.field_name AS cmendtc
	FROM redcap_metadata trt
	LEFT OUTER JOIN redcap_metadata cod ON cod.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdecod') AND trt.project_id = cod.project_id
	LEFT OUTER JOIN redcap_metadata indc ON (indc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmindc') OR indc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_oth_cmindc')) AND trt.project_id = indc.project_id AND trt.form_name = indc.form_name
	LEFT OUTER JOIN redcap_metadata stdtc ON stdtc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmstdtc') AND trt.project_id = stdtc.project_id AND trt.form_name = stdtc.form_name
	LEFT OUTER JOIN redcap_metadata dose ON dose.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdose') AND trt.project_id = dose.project_id AND trt.form_name = dose.form_name
	LEFT OUTER JOIN redcap_metadata dosu ON dosu.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdosu') AND trt.project_id = dosu.project_id AND trt.form_name = dosu.form_name
	LEFT OUTER JOIN redcap_metadata dosfrq ON (dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmdosfrq') OR dosfrq.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_oth_cmdosfrq')) AND trt.project_id = dosfrq.project_id AND trt.form_name = dosfrq.form_name
	LEFT OUTER JOIN redcap_metadata endtc ON endtc.field_name = CONCAT(LEFT(trt.field_name, INSTR(trt.field_name, '_')-1), '_cmendtc') AND trt.project_id = endtc.project_id AND trt.form_name = endtc.form_name
	WHERE trt.project_id = '$project_id'
	AND trt.field_name LIKE '%\_cmtrt'";
if ($debug) {
	show_var($fields_query, 'FIELDS');
}
$main_query = "SELECT DISTINCT d.record AS subjid, d1.value AS usubjid
FROM redcap_data d
JOIN redcap_data d1
ON d.record = d1.record AND d.project_id = d1.project_id
WHERE d.project_id = '$project_id'
AND d.record != ''
AND d1.field_name = 'dm_usubjid'
ORDER BY abs(d.record) ASC";
/**
 * dynamically construct the values query
 */
$timer['start_fields'] = microtime(true);
$fields_result = db_query($fields_query);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		if ($fields['type'] == 'descriptive') {
			$fields['cmtrt'] = $fields['label'];
			//$fields['cmindc'] = 'Hepatitis C';
			$meta_fields = array('cmtrt');
			unset($fields['type'], $fields['label']);
			$count = count($fields) - count($meta_fields);
			$has_meta = true;
		} else {
			$meta_fields = array();
			unset($fields['type'], $fields['label']);
			$count = count($fields) - 1;
			$has_meta = false;
		}
		foreach ($fields AS $key => $field) {
			if (strpos($field, '_oth') !== false) {
				$fields_with_oth[$field] = substr($field, strrpos($field, '_') + 1);
			}
		}
		$dup_fields = array_unique(array_values($fields_with_oth));
		$vals_data = get_vals_query($project_id, $fields, $meta_fields, $count);
		$vals_query = $vals_data['query'];
		$fields_collection[] = $vals_data['fields_collection'];
		/**
		 * add any per-domain ordering to $vals_query
		 */
		$vals_query .= ", stdtc.cmstdtc ASC";
		if ($debug) {
			show_var($vals_query, 'VALUES');
		}
		$vals_result = db_query($vals_query);
		if ($vals_result) {
			$inner_vals = array();
			while ($vals = db_fetch_assoc($vals_result)) {
				/**
				 * exclude rows with CMINDC = OTHER, or where CMTRT = ''
				 */
				if ($vals['cmindc'] != 'OTHER' && $vals['cmtrt'] != '') {
					foreach ($vals AS $val_key => $val_val) {
						$inner_vals[$val_key] = $val_val;
					}
					unset($inner_vals['dm_usubjid']);
					$check_array = array_filter($inner_vals, 'strlen');
					if (!$has_meta) {
						$vals_array[$vals['dm_usubjid']][] = $inner_vals;
					} elseif ($has_meta && count($check_array) > 1) {
						if ($debug) {
							//show_var($check_array, 'CHECK ARRAY');
						}
						$vals_array[$vals['dm_usubjid']][] = $inner_vals;
					}
				}
			}
			db_free_result($vals_result);
		}
	}
	db_free_result($fields_result);
}

$timer['start_main'] = microtime(true);
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Main loop
 */
$main_result = db_query($main_query);
if ($main_result) {
	$sorter = new FieldSorter('cmstdtc');
	while ($main_row = db_fetch_assoc($main_result)) {
		$last_vals = array();
		$seq = 1;
		$constants['USUBJID'] = $constants['STUDYID'] . '-' . $main_row['usubjid'];
		/**
		 * sort this subject's results on cmstdtc so SEQ is temporally correct
		 */
		usort($vals_array[$main_row['usubjid']], array($sorter, "cmp"));
		foreach ($vals_array[$main_row['usubjid']] AS $subj_array) {
			$same_row = array();
			foreach ($last_vals as $last_row) {
				$diff = array_diff_assoc($last_row, $subj_array);
				if (count($diff) <= count($dup_fields)) {
					foreach ($dup_fields AS $prev_val) {
						if (array_key_exists($prev_val, $diff) && isset($diff[$prev_val]) && (!isset($subj_array[$prev_val]) || $subj_array[$prev_val] == '')) {
							$same_row[] = true;
						}
					}
				}
			}
			$rows_match = eval("return (" . implode(' || ', $same_row) . ");");
			if (!$rows_match) {
				$query[] = "('{$constants['STUDYID']}', '{$constants['DOMAIN']}', '{$constants['USUBJID']}', '$seq', " . prep_implode($subj_array) . ")";
				$seq++;
				$last_vals[] = $subj_array;
			}
		}
	}
	db_free_result($main_result);
}
$timer['end_fields'] = microtime(true);
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'CM',
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `CMSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CMTRT` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
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
$columns = "(STUDYID, DOMAIN, USUBJID, CMSEQ, CMTRT, CMINDC, CMDOSE, CMDOSU, CMDOSFRQ, CMSTDTC, CMENDTC)";
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
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;