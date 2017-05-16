<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 1/8/14
 * Time: 9:43 AM
 * Purpose: This is a shell for plugin development in CDISC-compliant REDCap projects. It is meant to create a table of CDISC domain data
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
/**
 * if we need metadata from the project, this is where we can get it
 */
$project = new Project();
/**
 * instantiate some needed variables
 */
$query = array();
$constants = array();
$fields_with_oth = array();
$constants['STUDYID'] = strtoupper(substr($project->project['project_name'],0,4) . substr($project->project['project_name'], strpos($project->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'AE';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$column_array = array("STUDYID", "DOMAIN", "USUBJID", "AESEQ", "AETERM", "AELLT", "AELLTCD", "AEDECOD", "AEPTCD", "AEHLT", "AEHLTCD", "AEHLGT", "AEHLGTCD", "AEBODSYS", "AEBDSYCD", "AESOC", "AESOCCD", "AESEV", "AESER", "AEREL", "AEOUT", "AESCONG", "AESDISAB", "AESDTH", "AESHOSP", "AESLIFE", "AESMIE", "AECONTRT", "AETOXGR", "AESTDTC", "AEENDTC");
/**
 * if we need to skip any metadata fields the query returns when outputting the data domain, they go here
 */
$meta_fields = array();
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * write a query to return a list of fields for which we want to extract data for a given domain
 * @TODO: investigate aeoccur further. this may have moved to FA domain
 */
$fields_query = "SELECT DISTINCT 'dm_usubjid', term.type, term.label, term.aeterm, occur.aeoccur, stdtc.aestdtc, toxgr.aetoxgr, sev.aesev, contrt.aecontrt, ser.aeser, sdth.aesdth, shosp.aeshosp, slife.aeslife, sdisab.aesdisab, scong.aescong, smie.aesmie, rel.aerel, `out`.aeout, endtc.aeendtc, `decod`.field_name AS aedecod, bodsys.field_name AS aebodsys
FROM
(SELECT DISTINCT term.element_type AS type, term.element_label AS label, term.field_name AS aeterm, term.form_name, term.project_id FROM redcap_metadata term WHERE term.field_name LIKE '%\_aeterm') term
LEFT OUTER JOIN
(SELECT DISTINCT stdtc.field_name AS aestdtc, stdtc.form_name, stdtc.project_id FROM redcap_metadata stdtc WHERE stdtc.field_name LIKE '%\_aestdtc') stdtc ON term.project_id = stdtc.project_id AND term.form_name = stdtc.form_name
LEFT OUTER JOIN
(SELECT DISTINCT endtc.field_name AS aeendtc, endtc.form_name, endtc.project_id FROM redcap_metadata endtc WHERE endtc.field_name LIKE '%\_aeendtc') endtc ON term.project_id = endtc.project_id AND term.form_name = endtc.form_name
LEFT OUTER JOIN
(SELECT DISTINCT occur.field_name AS aeoccur, occur.form_name, occur.project_id FROM redcap_metadata occur WHERE occur.field_name LIKE '%\_aeoccur') occur ON term.project_id = occur.project_id AND term.form_name = occur.form_name
LEFT OUTER JOIN
(SELECT DISTINCT toxgr.field_name AS aetoxgr, toxgr.form_name, toxgr.project_id FROM redcap_metadata toxgr WHERE toxgr.field_name LIKE '%\_aetoxgr') toxgr ON term.project_id = toxgr.project_id AND term.form_name = toxgr.form_name
LEFT OUTER JOIN
(SELECT DISTINCT sev.field_name AS aesev, sev.form_name, sev.project_id FROM redcap_metadata sev WHERE sev.field_name LIKE '%\_aesev' OR sev.field_name = 'ae_suppae_aesevdrv') sev ON term.project_id = sev.project_id
LEFT OUTER JOIN
(SELECT DISTINCT contrt.field_name AS aecontrt, contrt.form_name, contrt.project_id FROM redcap_metadata contrt WHERE contrt.field_name LIKE '%\_aecontrt') contrt ON term.project_id = contrt.project_id AND term.form_name = contrt.form_name
LEFT OUTER JOIN
(SELECT DISTINCT ser.field_name AS aeser, ser.form_name, ser.project_id FROM redcap_metadata ser WHERE ser.field_name LIKE '%\_aeser') ser ON term.project_id = ser.project_id AND term.form_name = ser.form_name
LEFT OUTER JOIN
(SELECT DISTINCT sdth.field_name AS aesdth, sdth.form_name, sdth.project_id FROM redcap_metadata sdth WHERE sdth.field_name LIKE '%\_aesdth') sdth ON term.project_id = sdth.project_id AND term.form_name = sdth.form_name
LEFT OUTER JOIN
(SELECT DISTINCT slife.field_name AS aeslife, slife.form_name, slife.project_id FROM redcap_metadata slife WHERE slife.field_name LIKE '%\_aeslife') slife ON term.project_id = slife.project_id AND term.form_name = slife.form_name
LEFT OUTER JOIN
(SELECT DISTINCT shosp.field_name AS aeshosp, shosp.form_name, shosp.project_id FROM redcap_metadata shosp WHERE shosp.field_name LIKE '%\_aeshosp') shosp ON term.project_id = shosp.project_id AND term.form_name = shosp.form_name
LEFT OUTER JOIN
(SELECT DISTINCT sdisab.field_name AS aesdisab, sdisab.form_name, sdisab.project_id FROM redcap_metadata sdisab WHERE sdisab.field_name LIKE '%\_aesdisab') sdisab ON term.project_id = sdisab.project_id AND term.form_name = sdisab.form_name
LEFT OUTER JOIN
(SELECT DISTINCT scong.field_name AS aescong, scong.form_name, scong.project_id FROM redcap_metadata scong WHERE scong.field_name LIKE '%\_aescong') scong ON term.project_id = scong.project_id AND term.form_name = scong.form_name
LEFT OUTER JOIN
(SELECT DISTINCT smie.field_name AS aesmie, smie.form_name, smie.project_id FROM redcap_metadata smie WHERE smie.field_name LIKE '%\_aesmie') smie ON term.project_id = smie.project_id AND term.form_name = smie.form_name
LEFT OUTER JOIN
(SELECT DISTINCT rel.field_name AS aerel, rel.form_name, rel.project_id FROM redcap_metadata rel WHERE rel.field_name LIKE '%\_aerel') rel ON term.project_id = rel.project_id AND term.form_name = rel.form_name
LEFT OUTER JOIN
(SELECT DISTINCT `out`.field_name AS aeout, `out`.form_name, `out`.project_id FROM redcap_metadata `out` WHERE `out`.field_name LIKE '%\_aeout') `out` ON term.project_id = `out`.project_id AND term.form_name = `out`.form_name
LEFT OUTER JOIN redcap_metadata `decod` ON term.project_id = `decod`.project_id AND `decod`.field_name = 'ae_aedecod'
LEFT OUTER JOIN redcap_metadata `bodsys` ON term.project_id = `bodsys`.project_id AND `decod`.form_name = `bodsys`.form_name AND `bodsys`.field_name = CONCAT(LEFT(`decod`.field_name, INSTR(`decod`.field_name, '_')-1), '_aebodsys')
WHERE term.project_id = '$project_id'";
if ($debug) {
	show_var($fields_query);
}
/**
 * query fragment for MedDRA data
 * @TODO: create table for mapping AEBODSYS (SOC) and add AESOC and AESOCCD to the query
 */
$meddra_query = "SELECT llt.aellt, llt.aelltcd, pt.aedecod, pt.aeptcd, hlt.aehlt, hlt.aehltcd, hlgt.aehlgt, hlgt.aehlgtcd, soc.aesoc, pt.aebdsycd
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
 * subjects loop query
 */
$main_query = "SELECT DISTINCT d.record AS subjid, d1.value AS usubjid
FROM redcap_data d
JOIN redcap_data d1
ON d.record = d1.record AND d.project_id = d1.project_id
WHERE d.project_id = '$project_id'
AND d.record != ''
AND d1.field_name = 'dm_usubjid'
ORDER BY d.record ASC";
/**
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$fields_result = db_query($fields_query);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		if ($debug) {
			//show_var($fields);
		}
		unset($fields['type'], $fields['label']);
		$count = count($fields) - count($meta_fields);
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
		$vals_query .= ", stdtc.aestdtc ASC";
		if ($debug) {
			show_var($vals_query);
		}
		$vals_result = db_query($vals_query);
		if ($vals_result) {
			$inner_vals = array();
			while ($vals = db_fetch_assoc($vals_result)) {
				foreach ($vals AS $val_key => $val_val) {
					$inner_vals[$val_key] = htmlspecialchars_decode($val_val);
				}
				unset($inner_vals['dm_usubjid']);
				$vals_array[$vals['dm_usubjid']][] = $inner_vals;
			}
			db_free_result($vals_result);
		}
	}
	db_free_result($fields_result);
}
/**
 * Main loop
 * Iterate subjects
 */
$timer['start_main'] = microtime(true);
$main_result = db_query($main_query);
if ($main_result) {
	$sorter = new FieldSorter('aestdtc');
	while ($main_row = db_fetch_assoc($main_result)) {
		/**
		 * do stuff
		 */
		$seq = 1;
		$last_vals = array();
		$constants['USUBJID'] = $constants['STUDYID'] . '-' . $main_row['usubjid'];
		/**
		 * sort this subject's results on aestdtc so SEQ is temporally correct
		 */
		usort($vals_array[$main_row['usubjid']], array($sorter, "cmp"));
		foreach ($vals_array[$main_row['usubjid']] AS $subj_array) {
			$same_row = array();
			$use_oth = array();
			if ($debug) {
				show_var($subj_array, 'SUBJ_ARRAY');
				//show_var($last_vals, 'LAST VALS');
			}
			foreach ($last_vals as $last_row) {
				$diff = array_diff_assoc($last_row, $subj_array);
				if ($debug) {
					//show_var($diff, 'DIFF');
				}
				if (count($diff) <= count($dup_fields)) {
					foreach ($dup_fields AS $prev_val) {
						if (array_key_exists($prev_val, $diff) && isset($diff[$prev_val]) && (!isset($subj_array[$prev_val]) || $subj_array[$prev_val] == '')) {
							$same_row[] = true;
							if ($debug) {
								//echo "FOUND DUPLICATE ROW";
							}
						}
					}
				}
			}
			$rows_match = eval("return (" . implode(' || ', $same_row) . ");");
			if (!in_array('OTHER', $subj_array) && !$rows_match && $subj_array['aeterm'] != '') {
				/**
				 * Get MedDRA terms and codes
				 */
				$aeterm = fix_case($subj_array['aeterm']);
				$meddra = array();
				$meddra_result = db_query($meddra_query . " WHERE llt.aellt = '" . prep($subj_array['aedecod']) . "'");
				if ($meddra_result) {
					$meddra = db_fetch_assoc($meddra_result);
					db_free_result($meddra_result);
				}
				$query[] = "('" . prep($constants['STUDYID']) . "', '" .
					prep($constants['DOMAIN']) . "', '" .
					prep($constants['USUBJID']) . "', '" .
					prep($seq) . "', '" .
					prep(fix_case($subj_array['aeterm'])) . "', '" .
					prep($meddra['aellt']) . "', '" .
					prep($meddra['aelltcd']) . "', '" .
					//prep($subj_array['aedecod']) . "', '" .
					prep($meddra['aedecod']) . "', '" .
					prep($meddra['aeptcd']) . "', '" .
					prep($meddra['aehlt']) . "', '" .
					prep($meddra['aehltcd']) . "', '" .
					prep($meddra['aehlgt']) . "', '" .
					prep($meddra['aehlgtcd']) . "', '" .
					prep($subj_array['aebodsys']) . "', '" .
					prep($meddra['aebdsycd']) . "', '" .
					prep($meddra['aesoc']) . "', '" .
					prep($meddra['aebdsycd']) . "', '" .
					prep($subj_array['aesev']) . "', '" .
					prep($subj_array['aeser']) . "', '" .
					prep($subj_array['aerel']) . "', '" .
					prep($subj_array['aeout']) . "', '" .
					prep($subj_array['aescong']) . "', '" .
					prep($subj_array['aesdisab']) . "', '" .
					prep($subj_array['aesdth']) . "', '" .
					prep($subj_array['aeshosp']) . "', '" .
					prep($subj_array['aeslife']) . "', '" .
					prep($subj_array['aesmie']) . "', '" .
					prep($subj_array['aecontrt']) . "', '" .
					prep($subj_array['aetoxgr']) . "', '" .
					prep($subj_array['aestdtc']) . "', '" .
					prep($subj_array['aeendtc']) . "')";
				$seq++;
				$last_vals[] = $subj_array;
			}
		}
	}
	db_free_result($main_result);
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
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'AE',
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `AESEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AETERM` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AELLT` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AELLTCD` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEDECOD` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEPTCD` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEHLT` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEHLTCD` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEHLGT` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEHLGTCD` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEBODSYS` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEBDSYCD` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESOC` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESOCCD` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESEV` CHAR(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESER` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEREL` CHAR(12) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEOUT` CHAR(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESCONG` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESDISAB` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESDTH` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESHOSP` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESLIFE` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESMIE` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AECONTRT` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AETOXGR` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AESTDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEENDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `ix_aellt` (`AELLT`),
  KEY `ix_aeterm` (`AETERM`),
  KEY `ix_aedecod` (`AEDECOD`),
  KEY `ix_aebodsys` (`AEBODSYS`),
  KEY `ix_aesoc` (`AESOC`),
  KEY `ix_aestdtc` (`AESTDTC`),
  KEY `ix_aeendtc` (`AEENDTC`)
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
} else {
	show_var($sql);
}
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;