<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
global $project_id;
/**
 * debug
 */
$getdebug = $_GET['debug'] ? $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
/**
 * timing
 */
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($enable_kint);
/**
 * working arrays
 */
$forms_array = array('chemistry_imported', 'cbc_imported', 'hcv_rna_imported');
foreach ($forms_array AS $form) {
	standardize_lab_form($form, $project_id, $subjects, null, $debug);
}
//global $labs_array, $no_conversion, $counts_conversion, $gdl_conversion, $iul_conversion, $bili_conversion, $creat_conversion, $gluc_conversion;
//$fields = array();
//foreach ($labs_array AS $lab_prefix => $lab_params) {
//	$fields[] = $lab_prefix . "_im_lborres";
//	$fields[] = $lab_prefix . "_im_lborresu";
//	$fields[] = $lab_prefix . "_im_lbstresn";
//	$fields[] = $lab_prefix . "_im_lbstresu";
//	$fields[] = $lab_prefix . "_im_nxtrust";
//}
///**
// * get working data
// */
//$data = REDCap::getData('array', $subjects, $fields);
//foreach ($data AS $subjid => $subject) {
//	d($subjid);
//	foreach ($subject AS $event_id => $event) {
//		$pairs = array();
//		/**
//		 * rotate REDCap::getData array into value/unit pairs and store in an array
//		 */
//		foreach ($event AS $field => $value) {
//			$pairs[substr($field, 0, strpos($field, '_'))][substr($field, strrpos($field, '_') + 1)] = trim($value);
//		}
//		//d($pairs);
//		foreach ($pairs AS $prefix => $this_lab) {
//			$has_valid_units = false;
//			/**
//			 * if $this_lab has both value and units AND it's not untrusted:
//			 */
//			if ($this_lab['lborres'] != '' && $this_lab['lborresu'] != '') {
//				/**
//				 * get preferred $units for this test
//				 */
//				$units = $labs_array[$prefix]['units'];
//				/**
//				 * assume the entry is correct
//				 */
//				$lbstresn = $this_lab['lborres'];
//				$lbstresu = $this_lab['lborresu'];
//				/**
//				 * if preferred $units were not entered:
//				 */
//				if (strtolower($this_lab['lborresu']) != strtolower($units)) {
//					if ($debug) {
//						d($this_lab['lborresu'] . ': ' . $units, "$prefix UNITS MISMATCH");
//					}
//					/**
//					 * check _units_view for equivalents to lborresu
//					 */
//					$units_result = db_query("SELECT unit_name FROM _units_view WHERE unit_value = '$units'");
//					if ($units_result) {
//						while ($units_row = db_fetch_assoc($units_result)) {
//							/**
//							 * if we have a matching equivalent, use the preferred units
//							 */
//							if (strtolower($units_row['unit_name']) == strtolower($this_lab['lborresu'])) {
//								$lbstresu = $units;
//								if ($debug) {
//									d('FOUND EQV', $lbstresu);
//								}
//								$has_valid_units = true;
//								continue;
//							}
//						}
//					}
//					if ($debug && !$has_valid_units) {
//						d('NO EQV', $lbstresu);
//					}
//					/**
//					 * do units conversion if necessary
//					 */
//					$conv = $labs_array[$prefix]['conversion'];
//					if (count($conv) > 0) {
//						foreach ($conv AS $unit => $formula) {
//							$units_result = db_query("SELECT unit_value FROM _units_view WHERE unit_name = '$lbstresu'");
//							if ($units_result) {
//								while ($units_row = db_fetch_assoc($units_result)) {
//									if (strtolower($units_row['unit_value']) == strtolower($unit)) {
//										$lbstresu = $units_row['unit_value'];
//										$has_valid_units = true;
//										continue;
//									}
//								}
//							}
//							/**
//							 * if the entered units are to be converted:
//							 */
//							if (strtolower($this_lab['lborresu']) == strtolower($unit) || strtolower($lbstresu) == strtolower($unit)) {
//								if ($debug) {
//									d('CONVERSION', $formula);
//								}
//								/**
//								 * convert to standard units
//								 */
//								if ($formula == 'exp') {
//									$lbstresn = (string)round(pow(10, $this_lab['lborres']));
//								} else {
//									$lbstresn = (string)round(eval("return (" . $this_lab['lborres'] . $formula . ");"), 3);
//								}
//								if ($debug) {
//									d('CORRECTED VALUE', $lbstresn);
//								}
//								$lbstresu = $units;
//								$has_valid_units = true;
//							}
//						}
//					}
//				} else {
//					$lbstresu = $units;
//					$has_valid_units = true;
//				}
//				if ($has_valid_units && $this_lab['nxtrust'] != 'N') {
//					update_field_compare($subjid, $project_id, $event_id, $lbstresn, $this_lab['lbstresn'], $prefix . '_im_lbstresn', $debug);
//					update_field_compare($subjid, $project_id, $event_id, $lbstresu, $this_lab['lbstresu'], $prefix . '_im_lbstresu', $debug);
//				}
//				/**
//				 * if the standardized value has changed since it was last standardized, reset the nxtrust flag
//				 * Thank you , REDi.
//				 */
//				/*if ($has_valid_units && $lbstresn != $this_lab['lbstresn'] && $prefix != 'hcv') {
//					update_field_compare($subjid, $project_id, $event_id, '', $this_lab['nxtrust'], $prefix . '_im_nxtrust', $debug);
//				}*/
//			} elseif ($this_lab['lborresu'] == '' && $this_lab['lborres'] != '') {
//				/**
//				 * NO BLANK UNITS
//				 */
//				update_field_compare($subjid, $project_id, $event_id, '', $this_lab['lbstresn'], $prefix . '_im_lbstresn', $debug);
//				update_field_compare($subjid, $project_id, $event_id, '', $this_lab['lbstresu'], $prefix . '_im_lbstresu', $debug);
//				/*if ($prefix != 'hcv') {
//					update_field_compare($subjid, $project_id, $event_id, '', $this_lab['nxtrust'], $prefix . '_im_nxtrust', $debug);
//				}*/
//			} else {
//				update_field_compare($subjid, $project_id, $event_id, $this_lab['lborres'], $this_lab['lbstresn'], $prefix . '_im_lbstresn', $debug);
//				update_field_compare($subjid, $project_id, $event_id, $this_lab['lborresu'], $this_lab['lbstresu'], $prefix . '_im_lbstresu', $debug);
//			}
//		}
//		/**
//		 * @TODO: set form_complete
//		 */
//	}
//}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;