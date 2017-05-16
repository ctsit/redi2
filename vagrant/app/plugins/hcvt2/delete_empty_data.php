<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = true;
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
$rows_deleted = 0;
/**
 * project metadata
 */
$project = new Project();
$my_branching_logic = new BranchingLogic();
?>
<div class="red"><h1>WARNING!!!</h1>

	<h2>This plugin will destroy data! Do not use unless you know what you're doing!! IF YOU DIDN'T MAKE A BACKUP, IT'S TOO LATE!!!</h2>

	<p>All actions taken by this plugin are logged. You've been warned.</p></div>
<?php
/**
 * WORKING SET OF STUDY_IDs
 */
$empty_result = db_query("SELECT * FROM redcap_data WHERE project_id = '$project_id' AND value = '' ORDER BY abs(record) ASC");
if ($empty_result) {
	while ($empty_row = db_fetch_assoc($empty_result)) {
		$this_form_name = $project->metadata[$empty_row['field_name']]['form_name'];
		$locked_result = db_query("SELECT ld_id FROM redcap_locking_data WHERE project_id = '$project_id' AND record = '{$empty_row['record']}' AND event_id = '{$empty_row['event_id']}' AND form_name = '$this_form_name'");
		if ($locked_result) {
			$locked = db_fetch_assoc($locked_result);
			if (!isset($locked['ld_id'])) {
				$rows_deleted++;
				$delete_query = "DELETE FROM redcap_data WHERE record = '{$empty_row['record']}' AND project_id = '$project_id' AND event_id = '{$empty_row['event_id']}' AND field_name = '{$empty_row['field_name']}' AND value = '" . prep($empty_row['value']) . "' LIMIT 1";
				if (!$debug) {
					if (db_query($delete_query)) {
						target_log_event($delete_query, 'redcap_data', 'delete', $empty_row['record'], "{$empty_row['field_name']} = '{$empty_row['value']}'", 'Delete blank value', '', $project_id, $empty_row['event_id']);
					} else {
						error_log("SQL DELETE FAILED: " . db_error() . ': ' . $delete_query);
						echo db_error() . "<br />" . $delete_query;
					}
				} else {
					show_var($delete_query);
				}
			} else {
				$this_field_hidden = $my_branching_logic->allFieldsHidden($empty_row['record'], $empty_row['event_id'], array($empty_row['field_name']));
				$history = Form::getDataHistoryLog($empty_row['record'], $empty_row['event_id'], $empty_row['field_name']);
				if ($this_field_hidden) {
					$rows_deleted++;
					echo "<div class='red'>LOCKED with BLANK. Value is hidden. DELETE.</div>";
					show_var($history, "{$empty_row['record']}, {$empty_row['event_id']}, {$empty_row['field_name']}", 'red');
				} else {
					echo "<div class='green'>LOCKED with BLANK. Value is shown. Make no changes.</div>";
					show_var($history, "{$empty_row['record']}, {$empty_row['event_id']}, {$empty_row['field_name']}", 'green');
				}
			}
		}
	}
	db_free_result($empty_result);
}
echo $rows_deleted . " ROWS DELETED.<br />";