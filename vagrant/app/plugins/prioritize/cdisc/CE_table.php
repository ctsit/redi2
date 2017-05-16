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
$constants['DOMAIN'] = 'CE';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$fields_with_oth = array();
$vals_array = array();
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * query fragment for MedDRA data
 */
$meddra_query = "SELECT pt.aedecod, soc.aesoc
FROM
(SELECT DISTINCT llt.llt_name AS aellt,
llt.llt_code AS aelltcd,
llt.pt_code
FROM _meddra_low_level_term llt
) llt
LEFT OUTER JOIN
(SELECT DISTINCT pt.pt_name AS aedecod,
pt.pt_code as aeptcd,
pt.pt_soc_code AS aebdsycd
FROM _meddra_pref_term pt
) pt
ON llt.pt_code = CONVERT(pt.aeptcd USING utf8) COLLATE utf8_unicode_ci
LEFT OUTER JOIN
(SELECT DISTINCT hlt.hlt_name AS aehlt,
hlt.hlt_code AS aehltcd,
hlt_pt.pt_code
FROM _meddra_hlt_pref_term hlt
LEFT OUTER JOIN _meddra_hlt_pref_comp hlt_pt
ON hlt.hlt_code = hlt_pt.hlt_code
) hlt
ON llt.pt_code = CONVERT(hlt.pt_code USING utf8) COLLATE utf8_unicode_ci
LEFT OUTER JOIN
(SELECT DISTINCT hlgt.hlgt_name AS aehlgt,
hlgt.hlgt_code AS aehlgtcd,
hlgt_hlt.hlt_code
FROM _meddra_hlgt_pref_term hlgt
LEFT OUTER JOIN _meddra_hlgt_hlt_comp hlgt_hlt
ON hlgt.hlgt_code = hlgt_hlt.hlgt_code
) hlgt
ON hlt.aehltcd = CONVERT(hlgt.hlt_code USING utf8) COLLATE utf8_unicode_ci
LEFT OUTER JOIN
(SELECT soc.soc_name AS aesoc,
soc.soc_code
FROM _meddra_soc_term soc
) soc
ON pt.aebdsycd = CONVERT(soc.soc_code USING utf8) COLLATE utf8_unicode_ci";
/**
 * Query to get arrays of ceterm, ceoccur, cestdtc
 */
$fields_query = "SELECT 'dm_usubjid', term.type, term.label, term.ceterm, occur.ceoccur, stdtc.cestdtc
FROM
(SELECT DISTINCT term.element_type AS type, term.element_label AS label, term.field_name AS ceterm, term.form_name, term.project_id FROM redcap_metadata term WHERE term.field_name LIKE '%\_ceterm') term
LEFT OUTER JOIN
(SELECT DISTINCT occur.form_name, occur.project_id, occur.field_name AS ceoccur FROM redcap_metadata occur WHERE occur.field_name LIKE '%\_ceoccur') occur ON (occur.ceoccur = CONCAT(LEFT(term.ceterm, INSTR(term.ceterm, '_')-1), '_ceoccur') OR occur.ceoccur = CONCAT(LEFT(term.ceterm, INSTR(term.ceterm, '_')-1), '_oth_ceoccur')) AND  term.project_id = occur.project_id
LEFT OUTER JOIN
(SELECT DISTINCT stdtc.form_name, stdtc.project_id, stdtc.field_name AS cestdtc FROM redcap_metadata stdtc WHERE stdtc.field_name LIKE '%\_cestdtc') stdtc ON (stdtc.cestdtc = CONCAT(LEFT(term.ceterm, INSTR(term.ceterm, '_')-1), '_cestdtc') OR stdtc.cestdtc = CONCAT(LEFT(term.ceterm, INSTR(term.ceterm, '_')-1), '_oth_cestdtc')) AND  term.project_id = stdtc.project_id
WHERE term.project_id = '$project_id'";
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$ce_fields_result = db_query($fields_query);
$ce_fields = array('dm_usubjid');
if ($ce_fields_result) {
	while ($ce_fields_row = db_fetch_assoc($ce_fields_result)) {
		foreach ($ce_fields_row AS $field_key => $field_name) {
			if (!in_array($field_key, array('type', 'label')) && isset($field_name)) {
				$ce_fields[] = $field_name;
			}
		}
	}
	db_free_result($ce_fields_result);
}
$ce_fields = array_unique($ce_fields);
/**
 * fake CEENDTC because we don't collect it
 */
$ce_fields[] = 'ce_ceendtc';
$data = REDCap::getData('array', $subjects, $ce_fields);
$timer['have_data'] = microtime(true);
/**
 * run the fields query and construct the values array
 */
$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		d($fields);
		foreach ($data AS $subject_id => $subject) {
			$usubjid = '';
			foreach ($subject AS $event_id => $event) {
				$inner_vals = array();
				if ($usubjid == '') {
					$usubjid = $event['dm_usubjid'];
				}
				if ($fields['type'] == 'descriptive') {
					$event[$fields['ceterm']] = $fields['label'];
				}
				/**
				 * build values array
				 */
				foreach ($fields AS $key => $field) {
					if (!in_array($key, array('dm_usubjid', 'type', 'label')) && $event[$fields['ceoccur']] != '') {
						$inner_vals[$key] = $event[$field];
					}
				}
				$vals_array[$usubjid][] = $inner_vals;
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('cestdtc');
			usort($vals_array[$usubjid], array($sorter, "cmp"));
		}
	}
	db_free_result($fields_result);
}
$timer['start_main'] = microtime(true);
/**
 * Main loop
 */
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	d($subj_val_array);
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['ceterm'] != '') {
			/**
			 * Get MedDRA terms and codes
			 */
			$aeterm = $subj_array['aeterm'];
			$meddra = array();
			$meddra_result = db_query($meddra_query . " WHERE llt.aellt = '" . prep($subj_array['ceterm']) . "'");
			if ($meddra_result) {
				$meddra = db_fetch_assoc($meddra_result);
				db_free_result($meddra_result);
			}
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['ceterm']) . ',' .
				fix_null($meddra['aedecod']) . ',' .
				fix_null($meddra['aesoc']) . ',' .
				fix_null($subj_array['ceoccur']) . ',' .
				fix_null($subj_array['cestdtc']) . ',' .
				fix_null($subj_array['ceendtc']) . ',' .
				fix_null('Y') .
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
  `STUDYID` CHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `CESEQ` INT(8) DEFAULT NULL,
  `CETERM` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CEDECOD` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CEBODSYS` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CEOCCUR` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CESTDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CEENDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CEPRESP` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$table_create_query .= "ALTER TABLE `$table_name`
  ADD KEY `ix_ce_usubjid` (`USUBJID`), ADD KEY `ix_ce_ceterm` (`CETERM`), ADD KEY `ix_ce_ceoccur` (`CEOCCUR`);";
$truncate_query = "TRUNCATE TABLE $table_name;";
$columns = "(STUDYID, DOMAIN, USUBJID, CESEQ, CETERM, CEDECOD, CEBODSYS, CEOCCUR, CESTDTC, CEENDTC, CEPRESP)";
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
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;