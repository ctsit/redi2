<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 12/18/13
 * Time: 9:20 AM
 */
$getdebug = $_GET['debug'] ? $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;

$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';

// Restrict access to the desired projects
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
global $Proj;
Kint::enabled($debug);

$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($Proj->project['project_name'], 0, 4) . substr($Proj->project['project_name'], strpos($Proj->project['project_name'], '_') + 1, 1));
$constants['DOMAIN'] = 'LB';
$table_name = strtolower("_{$constants['STUDYID']}_{$constants['DOMAIN']}");
$sv_table_name = strtolower("_{$constants['STUDYID']}_sv");
$lbref_table_name = strtolower("_{$constants['STUDYID']}_lbref");
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
			$enum[$enum_row['field_name']][trim($enum_inner[1])] = trim($enum_inner[0]);
		}
	}
	db_free_result($enum_result);
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
stresn.field_name AS lbstresn,
stresu.field_name AS lbstresu,
blfl.field_name AS lbblfl,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON ((orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') AND INSTR(orres.field_name, '_im_') = 0) OR (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres') AND INSTR(orres.field_name, '_im_') <> 0)) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON ((orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') AND INSTR(orres.field_name, '_im_') = 0) OR (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata stresn ON ((stresn.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbstresn') AND INSTR(orres.field_name, '_im_') = 0) OR (stresn.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbstresn') AND INSTR(orres.field_name, '_im_') <> 0)) AND stresn.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata stresu ON ((stresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbstresu') AND INSTR(orres.field_name, '_im_') = 0) OR (stresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbstresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND stresu.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON dtc.project_id = testcd.project_id AND dtc.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
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
stresn.field_name AS lbstresn,
stresu.field_name AS lbstresu,
blfl.field_name AS lbblfl,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON ((orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') AND INSTR(orres.field_name, '_im_') = 0) OR (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres') AND INSTR(orres.field_name, '_im_') <> 0)) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON ((orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') AND INSTR(orres.field_name, '_im_') = 0) OR (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata stresn ON ((stresn.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbstresn') AND INSTR(orres.field_name, '_im_') = 0) OR (stresn.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbstresn') AND INSTR(orres.field_name, '_im_') <> 0)) AND stresn.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata stresu ON ((stresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbstresu') AND INSTR(orres.field_name, '_im_') = 0) OR (stresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbstresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND stresu.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON LEFT(dtc.field_name, INSTR(dtc.field_name, '_')-1) = LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1) AND dtc.project_id = testcd.project_id AND dtc.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
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
test.field_name AS lbtest,
orres.field_name AS lborres,
orresu.field_name AS lborresu,
stresn.field_name AS lbstresn,
stresu.field_name AS lbstresu,
blfl.field_name AS lbblfl,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM
(SELECT DISTINCT testcd.value, testcd.field_name, testcd.project_id, testcd_meta.form_name, testcd_meta.element_enum
FROM redcap_data testcd
LEFT OUTER JOIN redcap_metadata testcd_meta
ON testcd.field_name = testcd_meta.field_name AND testcd.project_id = testcd_meta.project_id
WHERE testcd.project_id = '$project_id' AND testcd_meta.form_name IS NOT NULL AND testcd_meta.element_enum IS NOT NULL AND testcd.value IS NOT NULL AND testcd.value != ''
) AS testcd
LEFT OUTER JOIN redcap_metadata test ON ((test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') AND INSTR(test.field_name, '_im_') = 0) OR (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest') AND INSTR(test.field_name, '_im_') <> 0)) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON ((orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') AND INSTR(orres.field_name, '_im_') = 0) OR (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres') AND INSTR(orres.field_name, '_im_') <> 0)) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON ((orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') AND INSTR(orres.field_name, '_im_') = 0) OR (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata stresn ON ((stresn.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbstresn') AND INSTR(orres.field_name, '_im_') = 0) OR (stresn.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbstresn') AND INSTR(orres.field_name, '_im_') <> 0)) AND stresn.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata stresu ON ((stresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbstresu') AND INSTR(orres.field_name, '_im_') = 0) OR (stresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbstresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND stresu.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON (dtc.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbdtc') OR dtc.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbdtc')) AND dtc.project_id = testcd.project_id AND dtc.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
WHERE testcd.field_name LIKE '%\_lbtest'";
$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
NULL AS lborresu,
orres.field_name AS lbstresn,
NULL AS lbstresu,
blfl.field_name AS lbblfl,
NULL AS utype,
NULL AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON ((dtc.field_name = 'chem_lbdtc' AND INSTR(orres.field_name, '_im_') = 0) OR (dtc.field_name = 'chem_im_lbdtc' AND INSTR(orres.field_name, '_im_') <> 0)) AND dtc.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
WHERE (testcd.field_name = 'meld_lbtestcd' OR testcd.field_name = 'meld_im_lbtestcd')
AND testcd.project_id = '$project_id'";
$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
NULL AS lborresu,
orres.field_name AS lbstresn,
NULL AS lbstresu,
blfl.field_name AS lbblfl,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON ((orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') AND INSTR(orres.field_name, '_im_') = 0) OR (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON ((dtc.field_name = 'chem_lbdtc' AND INSTR(orres.field_name, '_im_') = 0) OR (dtc.field_name = 'chem_im_lbdtc' AND INSTR(orres.field_name, '_im_') <> 0)) AND dtc.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
WHERE (testcd.field_name = 'egfr_lbtestcd' OR testcd.field_name = 'egfr_im_lbtestcd')
AND testcd.project_id = '$project_id'";
$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
NULL AS lborresu,
orres.field_name AS lbstresn,
NULL AS lbstresu,
blfl.field_name AS lbblfl,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON ((orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') AND INSTR(orres.field_name, '_im_') = 0) OR (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON ((dtc.field_name = 'chem_lbdtc' AND INSTR(orres.field_name, '_im_') = 0) OR (dtc.field_name = 'chem_im_lbdtc' AND INSTR(orres.field_name, '_im_') <> 0)) AND dtc.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
WHERE (testcd.field_name = 'apri_lbtestcd' OR testcd.field_name = 'apri_im_lbtestcd')
AND testcd.project_id = '$project_id'";
$fields_query .= ")
		UNION
		(";
$fields_query .= "SELECT DISTINCT
'dm_usubjid',
testcd.element_label AS lbtestcd,
test.element_label AS lbtest,
orres.field_name AS lborres,
NULL AS lborresu,
orres.field_name AS lbstresn,
NULL AS lbstresu,
blfl.field_name AS lbblfl,
orresu.element_type AS utype,
orresu.element_label AS ulabel,
dtc.field_name AS lbdtc,
trust.field_name AS trust
FROM redcap_metadata testcd
LEFT OUTER JOIN redcap_metadata test ON (test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbtest') OR test.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbtest')) AND test.project_id = testcd.project_id AND test.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orres ON (orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborres') OR orres.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborres')) AND orres.project_id = testcd.project_id AND orres.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata orresu ON ((orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lborresu') AND INSTR(orres.field_name, '_im_') = 0) OR (orresu.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lborresu') AND INSTR(orres.field_name, '_im_') <> 0)) AND orresu.project_id = testcd.project_id AND orresu.form_name = testcd.form_name
LEFT OUTER JOIN redcap_metadata blfl ON ((blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_lbblfl') AND INSTR(orres.field_name, '_im_') = 0) OR (blfl.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_')-1), '_im_lbblfl') AND INSTR(orres.field_name, '_im_') <> 0)) AND blfl.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata dtc ON ((dtc.field_name = 'chem_lbdtc' AND INSTR(orres.field_name, '_im_') = 0) OR (dtc.field_name = 'chem_im_lbdtc' AND INSTR(orres.field_name, '_im_') <> 0)) AND dtc.project_id = testcd.project_id
LEFT OUTER JOIN redcap_metadata trust ON ((trust.field_name = CONCAT(LEFT(testcd.field_name, INSTR(testcd.field_name, '_im')-1), '_im_nxtrust'))) AND trust.project_id = testcd.project_id
WHERE (testcd.field_name = 'crcl_lbtestcd' OR testcd.field_name = 'crcl_im_lbtestcd')
AND testcd.project_id = '$project_id'";
$fields_query .= ")";
/**
 * build array of reference ranges for later use
 */
$lbref = array();
$lbref_result = db_query("SELECT * FROM $lbref_table_name");
if ($lbref_result) {
	while ($lbref_row = db_fetch_assoc($lbref_result)) {
		$lbref[$lbref_row['LBTESTCD']]['LBSTNRLO'] = $lbref_row['LBSTNRLO'];
		$lbref[$lbref_row['LBTESTCD']]['LBSTNRHI'] = $lbref_row['LBSTNRHI'];
	}
}
if ($subjects != '') {
	d($enum);
}
d($fields_query);

echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * Get arrays of lbtestcd, lbtest, lborres, lborresu, lbdtc for each form
 */
$timer['fields_start'] = microtime(true);
$lab_fields_result = db_query($fields_query);
$lab_fields = array('dm_usubjid');
if ($lab_fields_result) {
	while ($lab_fields_row = db_fetch_assoc($lab_fields_result)) {
		if (isset($lab_fields_row['lborres'])) {
			$lab_fields[] = $lab_fields_row['lborres'];
		}
		if (isset($lab_fields_row['lborresu'])) {
			$lab_fields[] = $lab_fields_row['lborresu'];
		}
		if (isset($lab_fields_row['lbstresn'])) {
			$lab_fields[] = $lab_fields_row['lbstresn'];
		}
		if (isset($lab_fields_row['lbstresu'])) {
			$lab_fields[] = $lab_fields_row['lbstresu'];
		}
		if (isset($lab_fields_row['lbdtc'])) {
			$lab_fields[] = $lab_fields_row['lbdtc'];
		}
		if (isset($lab_fields_row['lbblfl'])) {
			$lab_fields[] = $lab_fields_row['lbblfl'];
		}
		if (isset($lab_fields_row['trust'])) {
			$lab_fields[] = $lab_fields_row['trust'];
		}
		/**
		 * for serum fibrosis test
		 */
		if (array_search($lab_fields_row['lbtestcd'], $enum[$lab_fields_row['lbtestcd']]) !== false) {
			$lab_fields[] = substr($lab_fields_row['lborres'], 0, strpos($lab_fields_row['lborres'], '_')) . '_lbtest';
		}
	}
	db_free_result($lab_fields_result);
}
$lab_fields = array_unique($lab_fields);
$data = REDCap::getData('array', $subjects, $lab_fields);
$timer['have_data'] = microtime(true);
$fields_result = db_query($fields_query);
$timer['have_fields'] = microtime(true);
if ($fields_result) {
	while ($fields = db_fetch_assoc($fields_result)) {
		$prefix = substr($fields['lborres'], 0, strpos($fields['lborres'], '_'));
		$lbstresn_array = array_merge(array_values($enum[$fields['lbtest']]), array('INR', 'HCVGT'));
		foreach ($data AS $subject_id => $subject) {
			unset($usubjid);
			foreach ($subject AS $event_id => $event) {
				$inner_vals = array();
				if (!isset($usubjid)) {
					$usubjid = $event['dm_usubjid'];
				}
				if ($event[$fields['lborres']] != '' && $event[$fields['trust']] != 'N') {
					/**
					 * descriptive units handling
					 * where lborresu is element_type=descriptive, use the label as the unit
					 * this is done where there is only one possible unit
					 */
					if ($fields['utype'] == 'descriptive') {
						$event[$fields['lborresu']] = $fields['ulabel'];
					}
					/**
					 * INR, HCVGT lbstresn
					 */
					if (count($lbstresn_array) > 0 && $subjects != '') {
						d($lbstresn_array);
					}
					$fields['lbstresc'] = is_numeric($event[$fields['lborres']]) ? $fields['lbstresn'] : $fields['lborres'];
					/**
					 * HCV Genotype transform
					 * @TODO: change LBSTRESN back to float and test cleaned values that wouldn't float...
					 */
					if ($fields['lbtestcd'] == 'HCVGT') {
						$subtype = get_single_field($subject_id, $project_id, $Proj->firstEventId, 'hcvgt_s_lborres', '');
						if ($subtype != '' && $subtype != 'NOT_AVAILABLE') {
							$event[$fields['lborres']] = $event[$fields['lborres']] . $subtype;
						}
						$fields['lbstresc'] = $fields['lborres'];
						$event[$fields['lbstresn']] = is_numeric($event[$fields['lborres']]) ? $event[$fields['lborres']] : null;
					}
					if (in_array($fields['lbtestcd'], $lbstresn_array)) {
						$event[$fields['lbstresn']] = $event[$fields['lborres']];
					}
					/**
					 * iterate the fields and add to inner_vals array
					 */
					$fields['lbstnrlo'] = 'LBSTNRLO';
					$fields['lbstnrhi'] = 'LBSTNRHI';
					$fields['lbnrind'] = 'LBNRIND';
					$lbnrind = 'NORMAL';
					foreach ($fields AS $field_key => $field_name) {
						/**
						 * if this is a serum fibrosis test, we have to get the lbtestcd from the lbtest, which was chosen from a radio list
						 */
						$serum_fibrosis = $fields['lborres'] == 'fib_lborres' ? true : false;
						switch ($field_key) {
							case 'lbtestcd':
								$inner_vals[$field_key] = $field_name;
								break;
							case 'lbtest':
								if ($serum_fibrosis) {
									if ($event[$field_name] == $fields['lbtestcd']) {
										$inner_vals[$field_key] = array_search($event[$field_name], $enum[$prefix . '_lbtest']);
									}
								} else {
									$inner_vals[$field_key] = $field_name;
								}
								break;
							case 'lbstnrlo':
							case 'lbstnrhi':
								$inner_vals[$field_key] = $lbref[$fields['lbtestcd']][$field_name];
								break;
							case 'lbnrind':
								if (isset($event[$fields['lbstresn']]) && $event[$fields['lbstresn']] != '' && (isset($lbref[$fields['lbtestcd']]['LBSTNRLO']) || isset($lbref[$fields['lbtestcd']]['LBSTNRHI']))) {
									if (isset($lbref[$fields['lbtestcd']]['LBSTNRLO']) && $event[$fields['lbstresn']] < $lbref[$fields['lbtestcd']]['LBSTNRLO']) {
										$lbnrind = 'LOW';
									} elseif (isset($lbref[$fields['lbtestcd']]['LBSTNRHI']) && $event[$fields['lbstresn']] > $lbref[$fields['lbtestcd']]['LBSTNRHI']) {
										$lbnrind = 'HIGH';
									}
								} else {
									$lbnrind = 'N/A';
								}
								$inner_vals[$field_key] = $lbnrind;
								break;
							case 'lborres':
							case 'lbstresc':
								if ($serum_fibrosis) {
									if ($event[$fields['lbtest']] == $fields['lbtestcd']) {
										$inner_vals[$field_key] = $event[$field_name];
									}
								} else {
									$inner_vals[$field_key] = $event[$field_name];
								}
								break;
							case 'lbstresn':
								/**
								 * INR, HCVGT lbstresn
								 */
								if (in_array($fields['lbtestcd'], $lbstresn_array)) {
									$inner_vals[$field_key] = $event[$fields['lborres']];
								} else {
									$inner_vals[$field_key] = $event[$field_name];
								}
								break;
							case 'lborresu':
							case 'lbstresu':
								if ($fields['utype'] == 'descriptive') {
									$inner_vals[$field_key] = $fields['ulabel'];
								} elseif (isset($field_name) && isset($event[$field_name])) {
									$inner_vals[$field_key] = $event[$field_name];
								} else {
									$inner_vals[$field_key] = NULL;
								}
								break;
							case 'trust':
							case 'lbdtc':
							case 'lbblfl':
								if (isset($field_name) && isset($event[$field_name])) {
									$inner_vals[$field_key] = $event[$field_name];
								} else {
									$inner_vals[$field_key] = NULL;
								}
								break;
							default:
								break;
						}
					}
					unset($inner_vals['dm_usubjid']);
					$vals_array[$usubjid][] = $inner_vals;
				}
			}
			/**
			 * sort $vals_array by date
			 */
			$sorter = new FieldSorter('lbdtc');
			usort($vals_array[$usubjid], array($sorter, "cmp"));
		}
	}
	db_free_result($fields_result);
}
if ($subjects == '') {
	d($vals_array);
}
foreach ($vals_array as $subj_usubjid => $subj_val_array) {
	if ($subjects != '') {
		d($subj_val_array);
	}
	$seq = 1;
	$constants['USUBJID'] = $constants['STUDYID'] . '-' . $subj_usubjid;
	foreach ($subj_val_array AS $subj_array) {
		if ($subj_array['lborres'] != '') {
			$query[] = '(' .
				fix_null($constants['STUDYID']) . ',' .
				fix_null($constants['DOMAIN']) . ',' .
				fix_null($constants['USUBJID']) . ',' .
				fix_null($seq) . ',' .
				fix_null($subj_array['lbtestcd']) . ',' .
				fix_null($subj_array['lbtest']) . ',' .
				fix_null($subj_array['lborres']) . ',' .
				fix_null($subj_array['lborresu']) . ',' .
				fix_null($subj_array['lbstresc']) . ',' .
				fix_null($subj_array['lbstresn']) . ',' .
				fix_null($subj_array['lbstresu']) . ',' .
				fix_null($subj_array['lbstnrlo']) . ',' .
				fix_null($subj_array['lbstnrhi']) . ',' .
				fix_null($subj_array['lbnrind']) . ',' .
				fix_null($subj_array['lbdtc']) . ',' .
				fix_null(get_visit_num($sv_table_name, $constants['USUBJID'], $subj_array['lbdtc'])) . ',' .
				fix_null($subj_array['lbblfl']) .
				')';
			$seq++;
		}
	}
}
$timer['have_sql_array'] = microtime(true);
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
  `DOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
  `LBSEQ` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBTESTCD` VARCHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBTEST` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBORRES` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBORRESU` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBSTRESC` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBSTRESN` VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBSTRESU` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBSTNRLO` FLOAT COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBSTNRHI` FLOAT COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBNRIND` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBDTC` VARCHAR(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VISITNUM` CHAR(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LBBLFL` CHAR(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `usubjid_lbtestcd` (`USUBJID`,`LBTESTCD`),
  KEY `usubjid_lbdtc` (`USUBJID`,`LBDTC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name";
$columns = "(STUDYID, DOMAIN, USUBJID, LBSEQ, LBTESTCD, LBTEST, LBORRES, LBORRESU, LBSTRESC, LBSTRESN, LBSTRESU, LBSTNRLO, LBSTNRHI, LBNRIND, LBDTC, VISITNUM, LBBLFL)";
$sql = "INSERT INTO $table_name $columns VALUES\n";
$sql .= implode(",\n", $query);
d($query);
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
			if (is_array($lab_fields)) {
				foreach ($lab_fields AS $field_collection) {
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
d(memory_get_peak_usage(true));
d(realpath_cache_size());
d(realpath_cache_get());