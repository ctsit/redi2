<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
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
 * adjust dirname depth as needed
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
/**
 * working arrays
 */
$counts_conversion = array('10^3/L' => ' / 1000000', 'IU/L' => ' * 1000', 'cells/uL' => ' / 1000', 'cell/mm3' => ' / 1000', 'cells/mm3' => ' / 1000', '10^6/uL' => ' / 1000', '10^9/uL' => ' / 1000000', '10^3/mL' => ' / 1000', 'log IU/mL' => 'exp');
$iul_conversion = array('IU/mL' => ' / 1000');
$bili_conversion = array('g/dL' => ' / 1000', 'umol/L' => ' / 17.1', 'mcmol/L' => ' / 17.1', 'mg/L' => ' / 10');
$gdl_conversion = array('mg/dL' => ' * 1000', 'g/L' => ' / 10', 'mg/L' => ' / 10000');
$labs_array = array(
	'wbc' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'neut' => array('units' => '%', 'conversion' => array()),
	'anc' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'lymce' => array('units' => '%', 'conversion' => array()),
	'lym' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'plat' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'hemo' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
	'alt' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
	'ast' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
	'tbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
	'dbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
	'alb' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
	'creat' => array('units' => 'mg/dL', 'conversion' => array('g/dL' => ' / 1000', 'mg/L' => ' / 10', 'umol/L' => ' / 88.4', 'mcmol/L' => ' / 88.4', 'mmol/L' => ' / .0884')),
	'gluc' => array('units' => 'mg/dL', 'conversion' => array('g/dL' => ' / 1000', 'mg/L' => ' / 10', 'mmol/L' => ' * 18.01801801801802')),
	'k' => array('units' => 'mmol/L', 'conversion' => array()),
	'sodium' => array('units' => 'mmol/L', 'conversion' => array()),
	'inr' => array('units' => '', 'conversion' => array()),
	'hcv' => array('units' => 'IU/mL', 'conversion' => $counts_conversion)
);
$fields = array();
foreach ($labs_array AS $lab_prefix => $lab_params) {
	$fields[] = $lab_prefix . "_lborres";
	$fields[] = $lab_prefix . "_lborresu";
	$fields[] = $lab_prefix . "_lbstresn";
	$fields[] = $lab_prefix . "_lbstresu";
}
/**
 * get working data
 */
$timer['have_fields'] = microtime(true);
$data = REDCap::getData('array', $subjects, $fields);
$timer['have_data'] = microtime(true);
foreach ($data AS $subjid => $subject) {
	if ($debug) {
		show_var($subjid, 'SUBJID', 'blue');
	}
	foreach ($subject AS $event_id => $event) {
		$pairs = array();
		/**
		 * rotate REDCap::getData array into value/unit pairs and store in an array
		 */
		foreach ($event AS $field => $value) {
			$pairs[substr($field, 0, strpos($field, '_'))][substr($field, strrpos($field, '_') + 1)] = trim($value);
		}
		foreach ($pairs AS $prefix => $this_lab) {
			$has_valid_units = false;
			/**
			 * if $this_lab has both value and units:
			 */
			if ($this_lab['lborres'] != '' && $this_lab['lborresu'] != '') {
				/**
				 * get preferred $units for this test
				 */
				$units = $labs_array[$prefix]['units'];
				/**
				 * assume the entry is correct
				 */
				$lbstresn = $this_lab['lborres'];
				$lbstresu = $this_lab['lborresu'];
				/**
				 * if preferred $units were not entered:
				 */
				if (strtolower($this_lab['lborresu']) != strtolower($units)) {
					if ($debug) {
						show_var($this_lab['lborresu'] . ': ' . $units, "$prefix UNITS MISMATCH");
					}
					/**
					 * check _units_view for equivalents to lborresu
					 */
					$units_result = db_query("SELECT unit_name FROM _units_view WHERE unit_value = '$units'");
					if ($units_result) {
						while ($units_row = db_fetch_assoc($units_result)) {
							/**
							 * if we have a matching equivalent, use the preferred units
							 */
							if (strtolower($units_row['unit_name']) == strtolower($this_lab['lborresu'])) {
								$lbstresu = $units;
								if ($debug) {
									show_var($lbstresu, 'FOUND EQV');
								}
								$has_valid_units = true;
								continue;
							}
						}
					}
					if ($debug && !$has_valid_units) {
						show_var($lbstresu, 'NO EQV');
					}
					/**
					 * do units conversion if necessary
					 */
					$conv = $labs_array[$prefix]['conversion'];
					if (count($conv) > 0) {
						foreach ($conv AS $unit => $formula) {
							$units_result = db_query("SELECT unit_value FROM _units_view WHERE unit_name = '$lbstresu'");
							if ($units_result) {
								while ($units_row = db_fetch_assoc($units_result)) {
									if (strtolower($units_row['unit_value']) == strtolower($unit)) {
										$lbstresu = $units_row['unit_value'];
										$has_valid_units = true;
										continue;
									}
								}
							}
							/**
							 * if the entered units are to be converted:
							 */
							if (strtolower($this_lab['lborresu']) == strtolower($unit) || strtolower($lbstresu) == strtolower($unit)) {
								if ($debug) {
									show_var($formula, 'CONVERSION', 'red');
								}
								/**
								 * convert to standard units
								 */
								if ($formula == 'exp') {
									$lbstresn = (string)round(pow(10, $this_lab['lborres']));
								} else {
									$lbstresn = (string)round(eval("return (" . $this_lab['lborres'] . $formula . ");"), 3);
								}
								if ($debug) {
									show_var($lbstresn, 'CORRECTED VALUE', 'green');
								}
								$lbstresu = $units;
								$has_valid_units = true;
							}
						}
					}
				} else {
					$lbstresu = $units;
					$has_valid_units = true;
				}
				if ($has_valid_units) {
					update_field_compare($subjid, $project_id, $event_id, $lbstresn, $this_lab['lbstresn'], $prefix . '_lbstresn', $debug);
					update_field_compare($subjid, $project_id, $event_id, $lbstresu, $this_lab['lbstresu'], $prefix . '_lbstresu', $debug);
				}
			} elseif ($this_lab['lborresu'] == '' && $this_lab['lborres'] != '') {
				/**
				 * NO BLANK UNITS
				 */
				update_field_compare($subjid, $project_id, $event_id, '', $this_lab['lbstresn'], $prefix . '_lbstresn', $debug);
				update_field_compare($subjid, $project_id, $event_id, '', $this_lab['lbstresu'], $prefix . '_lbstresu', $debug);
			} else {
				update_field_compare($subjid, $project_id, $event_id, $this_lab['lborres'], $this_lab['lbstresn'], $prefix . '_lbstresn', $debug);
				update_field_compare($subjid, $project_id, $event_id, $this_lab['lborresu'], $this_lab['lbstresu'], $prefix . '_lbstresu', $debug);
			}
		}
		/**
		 * @TODO: set form_complete
		 */
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;