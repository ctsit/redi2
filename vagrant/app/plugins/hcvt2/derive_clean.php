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
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
}
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
Kint::enabled($debug);
/**
 * project metadata
 */
global $Proj;
$baseline_event_id = $Proj->firstEventId;
$plugin_title = "Clean spurious data from redcap_data";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 * find all values where historical situations or bugs have caused accidental and
 * unwanted proliferation of rows in redcap_data and delete all but one of those rows
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$delete_message = 'Delete record';
$clean_result = db_query("SELECT result.* FROM
(SELECT *, count(value) as cnt FROM redcap_data WHERE project_id = $project_id GROUP BY record, event_id, field_name, value) result
WHERE result.cnt > 1 ORDER BY abs(result.record) ASC");
if ($clean_result) {
	while ($row = db_fetch_assoc($clean_result)) {
		$row_count = $row['cnt'] - 1;
		$row_text = $row_count == 1 ? 'row' : 'rows';
		$change_message = "Remove $row_count spurious $row_text from redcap_data proliferated due to a sofware bug, repaired as of 2014-12-08.";
		$delete_query = "DELETE FROM redcap_data WHERE record = '{$row['record']}' AND field_name = '{$row['field_name']}' AND event_id = '{$row['event_id']}' AND value = '{$row['value']}' LIMIT {$row_count}";
		d($delete_query);
		if (!$debug) {
			if (db_query($delete_query)) {
				target_log_event($delete_query, 'redcap_data', 'delete', $row['record'], "{$row['field_name']} = '{$row['value']}'", $delete_message, $change_message, $project_id, $row['event_id']);
				d("SUCCESS", $delete_query);
			} else {
				error_log(db_error() . "\n");
				d(db_error());
			}
		}
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}