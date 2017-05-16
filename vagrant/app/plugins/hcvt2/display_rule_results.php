<?php

$debug = false;
$timer = array();
$timer['start'] = microtime(true);

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
global $Proj;
Kint::enabled($debug);
$plugin_title = "Display Data Quality Rule results";
echo "<h3>$plugin_title</h3>";

if (isset($_GET['rule_id']) && $_GET['rule_id'] != 'ALL_RULES') {
	$rule_id = $_GET['rule_id'];
// Instantiate DataQuality object
	$dq = new DataQuality();
// Get rule info
	$rule_info = $dq->getRule($rule_id);

	d($rule_info, 'RULE INFO');

	/**
	 * get the first field in the rule logic - this is where the query will be put
	 */
	$field = array_shift(array_keys(getBracketedFields($rule_info['logic'], true, true, false)));
	if (strpos($field, '.') !== false) {
		$field = substr($field, strpos($field, '.') + 1);
	}
	d($field, 'FIELD');

// Execute this rule
	$dq->executeRule($rule_id);
	$results_table = $dq->displayResultsTable($rule_info);
	print $results_table[2];
	print $results_table[1];

// Log the event
//	if (!$debug) {
//		// Only log this event for the LAST ajax request sent (since sometimes multiple serial requests are sent)
//		log_event($sql_all, "redcap_data_quality_rules", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Execute data quality rule(s)");
//	}
//
//	$rule_results = $dq->getLogicCheckResults();
//
//	foreach ($rule_results AS $results) {
//		foreach ($results AS $result) {
//			$data_row = array();
//			$data_array = array();
//			d($result);
//			$history = getFieldDataResHistoryTarget($result['record'], $result['event_id'], $field);
//			/**
//			 * get the query result data from data_display and put it into an array for addition to the report
//			 */
//			$raw_data_rows = explode('|||', strip_tags(str_replace('<br>', '|||', $result['data_display'])));
//			foreach ($raw_data_rows AS $raw_data_row) {
//				$this_row_array = explode(': ', $raw_data_row);
//				$data_array[$this_row_array[0]] = $this_row_array[1];
//			}
//			ksort($data_array);
//			d($data_array);
//			/**
//			 * we don't want to duplicate queries
//			 * if the result is excluded or has a query history, ignore it
//			 */
//			if (!($result['exclude'] || count($history) > 0)) {
//				d($history);
//				//$data_row['monitor'] = $result['record'] & 1 ? 'dianne_mattingly' : 'wendy_robertson';
//				$data_row['subjid'] = quote_wrap($result['record']);
//				$data_row['usubjid'] = quote_wrap(get_single_field($result['record'], PROJECT_ID, $Proj->firstEventId, 'dm_usubjid', ''));
//				$data_row['event'] = quote_wrap(REDCap::getEventNames(false, false, $result['event_id']));
//				//$data_row['field'] = quote_wrap($Proj->metadata[$field]['element_label']);
//				//$data_row['data'] = quote_wrap(strip_tags(str_replace('<br>', ', ', $result['data_display'])));
//				foreach ($data_array AS $key => $val) {
//					/**
//					 * with field names
//					 * $data_row[$key] = quote_wrap($val);
//					 */
//					/**
//					 * with labels
//					 */
//					$data_row[quote_wrap($Proj->metadata[$key]['element_label'] . " [$key]")] = quote_wrap($val);
//				}
//				$data_row['description'] = quote_wrap($rule_info['name']);
//				$row_csv = implode(',', $data_row) . "\n";
//				$table_csv .= $row_csv;
//			}
//		}
//	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;
