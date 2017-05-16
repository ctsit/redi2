<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 8-26-2015
 */
/**
 * TESTING
 */
$debug = false;
$subjects = ''; // '' = ALL
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
/**
 * project metadata
 */
global $Proj;
$baseline_event_id = $Proj->firstEventId;
$plugin_title = "Derive stuff";
$drugs_out = array();
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 */
$timer['main_start'] = microtime(true);
/**
 * WHO Drug queries
 */
$sql = "REPLACE INTO _target_contra_meds (tx_name, drug_name, drug_common) VALUES\n";
//$substances = array('ethinylestradiol', 'hypericum');
//foreach ($substances as $substance) {
//	$substance_result = db_query("SELECT DISTINCT drug.drug_name FROM
//    (SELECT drug_name, med_prod_id FROM `_whodrug_mp`) AS drug
//    RIGHT OUTER JOIN
//            (
//        SELECT ing.medprod_id, sub.substance_name FROM
//        (SELECT * FROM `_whodrug_sun` WHERE substance_name LIKE '%" . $substance . "%') AS sub
//        LEFT OUTER JOIN
//        (SELECT * FROM `_whodrug_ing`) AS ing
//        ON sub.substance_id = ing.substance_id
//        WHERE sub.substance_id IS NOT NULL
//            ) AS sun
//    ON drug.med_prod_id = sun.medprod_id
//    WHERE drug.drug_name IS NOT NULL");
//	if ($substance_result) {
//		if (db_num_rows($substance_result) > 0) {
//			while ($drug_row = db_fetch_assoc($substance_result)) {
//				$drugs_out[$drug_row['drug_name']] = $substance;
//			}
//		} else {
//			d("no rows for $substance");
//		}
//	} else {
//		error_log(db_error() . "\n");
//		d($substance, db_error());
//	}
//}

$other_drugs = array('Alfuzosin',
	'colchicine',
	'Carbamazepine',
	'phenytoin',
	'phenobarbital',
	'Gemfibrozil',
	'Rifampin',
	'Ergotamine',
	'dihydroergotamine',
	'ergonovine',
	'methylergonovine',
	'Lovastatin',
	'simvastatin',
	'Pimozide',
	'Efavirenz',
	'Sildenafil',
	'REVATIO',
	'Triazolam',
	'midazolam'
);

//$other_drugs = array('Alfuzosin');
//$other_drugs = array('colchicine');
//$other_drugs = array('Carbamazepine');
//$other_drugs = array('phenobarbital');
//$other_drugs = array('phenytoin');
//$other_drugs = array('Gemfibrozil');
//$other_drugs = array('Rifampin');
//$other_drugs = array('Ergotamine');
//$other_drugs = array('dihydroergotamine');
//$other_drugs = array('ergonovine');
//$other_drugs = array('methylergonovine');
//$other_drugs = array('Lovastatin');
//$other_drugs = array('simvastatin');
//$other_drugs = array('Pimozide');
//$other_drugs = array('Efavirenz');
//$other_drugs = array('Sildenafil');
//$other_drugs = array('REVATIO');
//$other_drugs = array('Triazolam');
//$other_drugs = array('midazolam');
foreach ($other_drugs as $other_drug){
	$other_drug_result = db_query("SELECT DISTINCT drug.drug_name FROM
	(SELECT drug_name, med_prod_id, drug_rec_num FROM _whodrug_mp) drug
	RIGHT OUTER JOIN
	(SELECT drug_rec_num FROM _whodrug_mp WHERE drug_name = '$other_drug') ids
	ON ids.drug_rec_num = drug.drug_rec_num");
	if ($other_drug_result) {
		if (db_num_rows($other_drug_result) > 0) {
			while ($drug_row = db_fetch_assoc($other_drug_result)) {
				$drugs_out[$drug_row['drug_name']] = $other_drug;
			}
		} else {
			d("no rows for $other_drug");
		}
	} else {
		error_log(db_error() . "\n");
		d($other_drug, db_error());
	}
}
/**
 * process all drugs in $drugs_out to populate table
 */
d($drugs_out);
if (!empty($drugs_out)) {
	foreach ($drugs_out AS $drug_out => $other_drug) {
		$query[] = '(' .
			fix_null('vpk') . ',' .
			fix_null($drug_out) . ',' .
			fix_null($other_drug) .
			')';
	}
	$sql .= implode(",\n", $query);
}
!d($sql);
if (!$debug) {
	if (db_query($sql)) {
		d('INSERT successful');
	} else {
		error_log("SQL INSERT FAILED: " . db_error() . "\n");
		d(db_error());
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;