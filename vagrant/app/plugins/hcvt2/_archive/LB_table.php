<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 12/18/13
 * Time: 9:20 AM
 */
$debug = true;
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
$constants['DOMAIN'] = 'LB';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$meta_fields = array('lbtest', 'lbtestcd');
/**
 * construct array of lbtestcd / lbtest pairs from fields where lbtest is selected from radio button list, not specified in a hidden descriptive field
 * the value of lbtest becomes lbtestcd (key), the labels of lbtest are parsed into (value)
 */
$enum = array();
$enum_result = db_query("SELECT DISTINCT m.element_enum, m.field_name FROM redcap_metadata m JOIN redcap_data d ON d.field_name = m.field_name AND d.project_id = m.project_id WHERE d.project_id = '$project_id' AND d.field_name LIKE '%\_lbtest'");
if ($enum_result) {
	while ($enum_row = db_fetch_assoc($enum_result)) {
		$enum_raw = explode('\n', $enum_row['element_enum']);
		foreach ($enum_raw AS $enum_outer) {
			$enum_inner = explode(',', trim($enum_outer));
			$enum[$enum_row['field_name']][$enum_inner[0]] = trim($enum_inner[1]);
		}
	}
	db_free_result($enum_result);
}
if ($debug) {
	show_var($enum);
}
/**
 * build fields query
 */
$fields_query = "(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
orresu.field_name AS lborresu,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') OR orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu')) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata dtc ON dtc.project_id = testcd.project_id AND dtc.form_name = testcd.form_name
WHERE testcd.field_name LIKE '%\_lbtestcd'
AND dtc.field_name LIKE '%\_lbdtc'
AND LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) != 'fib'
AND LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) != 'cap'
AND LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) != 'fibscn'
AND testcd.project_id = '$project_id'";
$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
orresu.field_name AS lborresu,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') OR orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu')) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata dtc ON LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) = LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1) AND dtc.project_id = testcd.project_id AND dtc.form_name = testcd.form_name
WHERE testcd.field_name LIKE '%\_lbtestcd'
AND dtc.field_name LIKE '%\_lbdtc'
AND (LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) = 'cap'
OR LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) = 'fibscn')
AND testcd.project_id = '$project_id'";
$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.value AS lbtestcd,
NULL AS lbtest,
orres.field_name AS lborres,
orresu.field_name AS lborresu,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc
FROM
(SELECT DISTINCT testcd.value, testcd.field_name, testcd.project_id, testcd_meta.form_name
FROM redcap_data testcd
LEFT OUTER JOIN redcap_metadata testcd_meta
ON testcd.field_name = testcd_meta.field_name AND testcd.project_id = testcd_meta.project_id
WHERE testcd.project_id = '$project_id' AND testcd_meta.form_name IS NOT NULL AND testcd.value IS NOT NULL AND testcd.value != ''
) testcd
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') OR orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu')) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata dtc ON (dtc.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbdtc') OR dtc.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbdtc')) AND dtc.project_id = testcd.project_id AND dtc.form_name = testcd.form_name
WHERE testcd.field_name LIKE '%\_lbtest'";
/*$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
NULL AS lborresu,
NULL AS utype,
NULL AS ulabel,
dtc.field_name AS lbdtc
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata dtc ON (dtc.field_name = 'chem_lbdtc' OR dtc.field_name = 'chem_im_lbdtc') AND dtc.project_id = testcd.project_id
WHERE (testcd.field_name = 'meld_lbtestcd' OR testcd.field_name = 'meld_im_lbtestcd')
AND testcd.project_id = '$project_id'";*/
$fields_query .= ")";
if ($debug) {
	show_var($fields_query, 'FIELDS');
}

echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of lbtestcd, lbtest, lborres, lborresu, lbdtc for each form
 */
$timer['fields_start'] = microtime(true);
$fields_result = db_query($fields_query);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		$descriptive_units = false;
		if ($fields['lbtestcd'] == 'OTHER') {
			continue;
		}
		/**
		 * descriptive units handling
		 * where lborresu is element_type=descriptive, use the label as the unit
		 * this is done where there is only one possible unit
		 */
		if ($fields['utype'] == 'descriptive') {
			$fields['orresu'] = $fields['ulabel'];
			$descriptive_units = true;
		}
		unset($fields['utype'], $fields['ulabel']);
		$count = count($fields) - count($meta_fields);
		$vals_data = get_vals_query($project_id, $fields, $meta_fields, $count, $enum, "AND orres.lborres IS NOT NULL AND orres.lborres != ''\n");
		$fields_collection[] = $vals_data['fields_collection'];
		$vals_query = $vals_data['query'];
		$vals_query .= ", dtc.lbdtc ASC";
		if ($debug) {
			show_var($vals_query);
		}
		if (!$debug) {
			$vals_result = db_query($vals_query);
			if ($vals_result) {
				$inner_vals = array();
				while ($vals = db_fetch_assoc($vals_result)) {
					if (!in_array('OTHER', $vals)) {
						if ($descriptive_units) {
							$vals['lborresu'] = $fields['orresu'];
						}
						foreach ($fields AS $field_key => $field_name) {
							$inner_vals[$field_key] = $field_name;
						}
						foreach ($vals AS $val_key => $val_val) {
							$inner_vals[$val_key] = $val_val;
						}
						unset($inner_vals['dm_usubjid']);
						$vals_array[$vals['dm_usubjid']][] = $inner_vals;
					}
				}
				db_free_result($vals_result);
			}
		}
	}
	db_free_result($fields_result);
}
$timer['main_start'] = microtime(true);
if (!$debug) {
	/**
	 * Main loop
	 */
	$main_result = db_query("SELECT DISTINCT record AS subjid, value AS usubjid FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'dm_usubjid' ORDER BY abs(record) ASC");
	if ($main_result) {
		$sorter = new FieldSorter('lbdtc');
		while ($main_row = db_fetch_assoc($main_result)) {
			$seq = 1;
			$constants['USUBJID'] = $constants['STUDYID'] . '-' . $main_row['usubjid'];
			/**
			 * sort $vals_array by date
			 */
			usort($vals_array[$main_row['usubjid']], array($sorter, "cmp"));
			foreach ($vals_array[$main_row['usubjid']] AS $subj_array) {
				if ($subj_array['lborres'] != '') {
					$query[] = "('{$constants['STUDYID']}', '{$constants['DOMAIN']}', '{$constants['USUBJID']}', '$seq', '{$subj_array['lbtestcd']}', '{$subj_array['lbtest']}', '{$subj_array['lborres']}', '{$subj_array['lborresu']}', '{$subj_array['lbdtc']}')";
					$seq++;
				}
			}
		}
		db_free_result($main_result);
	}
	$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `LBSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBTESTCD` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBTEST` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBORRES` VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBORRESU` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_lbtestcd` (`USUBJID`,`LBTESTCD`),
  KEY `usubjid_lbdtc` (`USUBJID`,`LBDTC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	$truncate_query = "TRUNCATE TABLE $table_name";
	$columns = "(STUDYID, DOMAIN, USUBJID, LBSEQ, LBTESTCD, LBTEST, LBORRES, LBORRESU, LBDTC)";
	$sql = "INSERT INTO $table_name $columns VALUES" . implode(",\n", $query);
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