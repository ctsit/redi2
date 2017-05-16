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
/**
 * instantiate some needed variables
 */
$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'],0,4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_')+1, 1));
$constants['DOMAIN'] = 'AE';
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
$column_array = array("STUDYID", "DOMAIN", "USUBJID", "AESEQ", "AETERM", 'AEMODIFY', "AELLT", "AELLTCD", "AEDECOD", "AEPTCD", "AEHLT", "AEHLTCD", "AEHLGT", "AEHLGTCD", "AEBODSYS", "AEBDSYCD", "AESOC", "AESOCCD", "AESEV", "AESER", "AEACN", "AEREL", "AEOUT", "AESCONG", "AESDISAB", "AESDTH", "AESHOSP", "AESLIFE", "AESMIE", "AECONTRT", "AETOXGR", "AESTDTC", "AEENDTC");
/**
 * tell him what he's won, Johnny
 */
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * write a query to return a list of fields for which we want to extract data for a given domain
 * @TODO: investigate aeoccur further. this may have moved to FA domain
 */
$fields_query = "SELECT DISTINCT 'dm_usubjid', term.aeterm, oth_term.oth_aeterm, occur.aeoccur, stdtc.aestdtc, toxgr.aetoxgr, sev.aesev, supp_sev.aesuppsev, contrt.aecontrt, ser.aeser, sdth.aesdth, shosp.aeshosp, slife.aeslife, sdisab.aesdisab, scong.aescong, smie.aesmie, rel.aerel, `out`.aeout, endtc.aeendtc, `decod`.field_name AS aedecod, bodsys.field_name AS aebodsys, modify.field_name AS aemodify, acn.field_name AS aeacn
FROM
(SELECT DISTINCT term.element_type AS type, term.element_label AS label, term.field_name AS aeterm, term.form_name, term.project_id FROM redcap_metadata term WHERE term.field_name LIKE '%\_aeterm' AND term.field_name NOT LIKE '%\_oth_aeterm') term
LEFT OUTER JOIN
(SELECT DISTINCT oth_term.field_name AS oth_aeterm, oth_term.form_name, oth_term.project_id FROM redcap_metadata oth_term WHERE oth_term.field_name LIKE '%\_oth_aeterm') oth_term ON term.project_id = oth_term.project_id AND term.form_name = oth_term.form_name
LEFT OUTER JOIN
(SELECT DISTINCT stdtc.field_name AS aestdtc, stdtc.form_name, stdtc.project_id FROM redcap_metadata stdtc WHERE stdtc.field_name LIKE '%\_aestdtc') stdtc ON term.project_id = stdtc.project_id AND term.form_name = stdtc.form_name
LEFT OUTER JOIN
(SELECT DISTINCT endtc.field_name AS aeendtc, endtc.form_name, endtc.project_id FROM redcap_metadata endtc WHERE endtc.field_name LIKE '%\_aeendtc') endtc ON term.project_id = endtc.project_id AND term.form_name = endtc.form_name
LEFT OUTER JOIN
(SELECT DISTINCT occur.field_name AS aeoccur, occur.form_name, occur.project_id FROM redcap_metadata occur WHERE occur.field_name LIKE '%\_aeoccur') occur ON term.project_id = occur.project_id AND term.form_name = occur.form_name
LEFT OUTER JOIN
(SELECT DISTINCT toxgr.field_name AS aetoxgr, toxgr.form_name, toxgr.project_id FROM redcap_metadata toxgr WHERE toxgr.field_name LIKE '%\_aetoxgr') toxgr ON term.project_id = toxgr.project_id AND term.form_name = toxgr.form_name
LEFT OUTER JOIN
(SELECT DISTINCT sev.field_name AS aesev, sev.form_name, sev.project_id FROM redcap_metadata sev WHERE sev.field_name LIKE '%\_aesev') sev ON term.project_id = sev.project_id
LEFT OUTER JOIN
(SELECT DISTINCT supp_sev.field_name AS aesuppsev, supp_sev.form_name, supp_sev.project_id FROM redcap_metadata supp_sev WHERE supp_sev.field_name = 'ae_suppae_aesevdrv') supp_sev ON term.project_id = supp_sev.project_id
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
LEFT OUTER JOIN redcap_metadata `modify` ON term.project_id = `modify`.project_id AND `decod`.form_name = `modify`.form_name AND `modify`.field_name = CONCAT(LEFT(`decod`.field_name, INSTR(`decod`.field_name, '_')-1), '_aemodify')
LEFT OUTER JOIN redcap_metadata `acn` ON term.project_id = `acn`.project_id AND `decod`.form_name = `acn`.form_name AND `acn`.field_name = CONCAT(LEFT(`decod`.field_name, INSTR(`decod`.field_name, '_')-1), '_aeacn')
WHERE term.project_id = '$project_id'";

d($fields_query);
/**
 * query fragment for MedDRA data
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
 * Get arrays of field_names
 */
$timer['start_fields'] = microtime(true);
$ae_fields_result = db_query($fields_query);
$ae_fields = array('dm_usubjid');
if ($ae_fields_result) {
	while ($ae_fields_row = db_fetch_assoc($ae_fields_result)) {
		foreach ($ae_fields_row AS $field_key => $field_name) {
			$ae_fields[] = $field_name;
		}
	}
	db_free_result($ae_fields_result);
}
$ae_fields = array_unique($ae_fields);
$data = REDCap::getData('array', $subjects, $ae_fields);
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
				if ($event[$fields['aeterm']] != '') {
					/**
					 * transform: eliminate OTHER AEs and clean up radio button value case
					 */
					if ($event[$fields['aeterm']] == 'OTHER') {
						$event[$fields['aeterm']] = $event[$fields['oth_aeterm']];
					} else {
						$event[$fields['aeterm']] = fix_case($event[$fields['aeterm']]);
					}
					/**
					 * build values array
					 */
					foreach ($fields AS $key => $field) {
						if (!in_array($field, array($fields['dm_usubjid'], $fields['oth_aeterm']))) {
							$inner_vals[$key] = $event[$field];
						}
					}
					$vals_array[$usubjid][] = $inner_vals;
				}
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('aestdtc');
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
		if ($subj_array['aeterm'] != '') {
			/**
			 * Get MedDRA terms and codes
			 */
			$aeterm = $subj_array['aeterm'];
			$meddra = array();
			$meddra_result = db_query($meddra_query . " WHERE llt.aellt = '" . prep($subj_array['aedecod']) . "'");
			if ($meddra_result) {
				$meddra = db_fetch_assoc($meddra_result);
				db_free_result($meddra_result);
			}
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['aeterm']) . ',' .
				fix_null($subj_array['aemodify']) . ',' .
				fix_null($meddra['aellt']) . ',' .
				fix_null($meddra['aelltcd']) . ',' .
				fix_null($subj_array['aedecod']) . ',' .
				fix_null($meddra['aeptcd']) . ',' .
				fix_null($meddra['aehlt']) . ',' .
				fix_null($meddra['aehltcd']) . ',' .
				fix_null($meddra['aehlgt']) . ',' .
				fix_null($meddra['aehlgtcd']) . ',' .
				fix_null($subj_array['aebodsys']) . ',' .
				fix_null($meddra['aebdsycd']) . ',' .
				fix_null($meddra['aesoc']) . ',' .
				fix_null($meddra['aebdsycd']) . ',' .
				fix_null(fix_case($subj_array['aesev'])) . ',' .
				fix_null($subj_array['aeser']) . ',' .
				fix_null(fix_case($subj_array['aeacn'])) . ',' .
				fix_null(fix_case($subj_array['aerel'])) . ',' .
				fix_null(fix_case($subj_array['aeout'])) . ',' .
				fix_null($subj_array['aescong']) . ',' .
				fix_null($subj_array['aesdisab']) . ',' .
				fix_null($subj_array['aesdth']) . ',' .
				fix_null($subj_array['aeshosp']) . ',' .
				fix_null($subj_array['aeslife']) . ',' .
				fix_null($subj_array['aesmie']) . ',' .
				fix_null($subj_array['aecontrt']) . ',' .
				fix_null($subj_array['aetoxgr']) . ',' .
				fix_null($subj_array['aestdtc']) . ',' .
				fix_null($subj_array['aeendtc']) .
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
  `DOMAIN` CHAR(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'AE',
  `USUBJID` CHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `AESEQ` INT(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AETERM` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AEMODIFY` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
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
  `AEACN` CHAR(32) COLLATE utf8_unicode_ci DEFAULT NULL,
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
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;