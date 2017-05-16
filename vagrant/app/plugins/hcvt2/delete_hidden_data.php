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
$rows_deleted = 0;
/**
 * project metadata
 */
$project = new Project();
$my_branching_logic = new BranchingLogic();
$baseline_event_id = $project->firstEventId;
$fields_result = db_query("SELECT field_name, element_label FROM redcap_metadata WHERE project_id = '$project_id' AND element_type IN ('text', 'notes', 'autocomplete') AND field_name NOT LIKE '%\_complete' ORDER BY field_order ASC");
?>
	<div class="red"><h1>WARNING!!!</h1>

		<h2 class="red">This plugin will destroy data! Do not use unless you know what you're doing!!</h2>
		<h3 class="red">IF YOU HAVEN'T MADE A BACKUP, DO IT NOW.</h3>
		<p>All actions taken by this plugin are logged. You've been warned.</p></div>
	<h3>DELETE hidden data from a selected variable</h3>
	<h4>Select the source field containing the data to be DELETED if hidden</h4>
	<form action="<?php echo $_SERVER['PHP_SELF'] . '?pid=' . $project_id; ?> " method="post">
		<div class="data" style='max-width:700px;'>
			<select class="x-form-text x-form-field"
			        name="field_name"
			        style="height: 22px; padding-right: 0;"
			        id="field_name">
				<option value>-- Select Field --</option>
				<?php
				if ($fields_result) {
					while ($fields_row = db_fetch_assoc($fields_result)) {
						if ($_POST['field_name'] == $fields_row['field_name']) {
							echo "<option value='{$fields_row['field_name']}' selected>" . "[" . $fields_row['field_name'] . "] - " . substr($fields_row['element_label'], 0, 60) . "</option>";
						} else {
							echo "<option value='{$fields_row['field_name']}'>" . "[" . $fields_row['field_name'] . "] - " . substr($fields_row['element_label'], 0, 60) . "</option>";
						}
					}
				}
				?>
			</select>
			&nbsp;&nbsp;
			<input type='submit' value='DELETE hidden data from this variable'/>
		</div>
	</form>
<?php
if (isset($_POST['field_name'])) {
	$timer['main_start'] = microtime(true);
	/**
	 * WORKING SET OF STUDY_IDs
	 */
	$timer['start_main'] = microtime(true);
//$empty_result = db_query("SELECT * FROM redcap_data WHERE project_id = '$project_id' AND value = '' ORDER BY abs(record) ASC");
	/**
	 * exclude dm_subjid from query, as RED-I writes orphan records to this field, and we don't want to screw that up, do we?
	 */
	$empty_result = db_query("SELECT * FROM redcap_data WHERE project_id = '$project_id' AND field_name = '{$_POST['field_name']}' ORDER BY abs(record) ASC");
	if ($empty_result) {
		while ($empty_row = db_fetch_assoc($empty_result)) {
			$history = Form::getDataHistoryLog($empty_row['record'], $empty_row['event_id'], $empty_row['field_name']);
			$all_fields_hidden = $my_branching_logic->allFieldsHidden($empty_row['record'], $empty_row['event_id'], array($empty_row['field_name']));
			if ($debug) {
				/**
				 * pick apart the hidden field logic - something's not right with fib_lbtest logic. ?'null'?
				 */
				$fieldsDependent = getDependentFields(array($empty_row['field_name']), false, true);
				$unique_event_name = $project->getUniqueEventNames($empty_row['event_id']);
				$record_data = Records::getSingleRecordData($empty_row['record'], array_merge($fieldsDependent, array($empty_row['field_name'])));
				$logic = $project->metadata[$empty_row['field_name']]['branching_logic'];
				if ($longitudinal) {
					$logic = LogicTester::logicPrependEventName($logic, $unique_event_name);
				}
				if (LogicTester::isValid($logic)) {
					$displayField = LogicTester::apply($logic, $record_data);
					$displayField = $displayField ? false : true;
				}
				show_var($fieldsDependent, 'DEP FIELDS');
				show_var($unique_event_name, 'unique event name');
				show_var($record_data, 'record data');
				show_var($logic, 'logic');
				show_var($displayField, 'all hidden?');
			}
			/**
			 * if all values are hidden for this field, delete the row.
			 */
			if ($all_fields_hidden) {
				$rows_deleted++;
				$delete_query = "DELETE FROM redcap_data WHERE record = '{$empty_row['record']}' AND project_id = '$project_id' AND event_id = '{$empty_row['event_id']}' AND field_name = '{$empty_row['field_name']}' AND value = '" . prep($empty_row['value']) . "'";
				if (!$debug) {
					if (db_query($delete_query)) {
						target_log_event($delete_query, 'redcap_data', 'delete', $empty_row['record'], "{$empty_row['field_name']} = '{$empty_row['value']}'", 'Delete hidden blank value', '', $project_id, $empty_row['event_id']);
					} else {
						error_log("SQL DELETE FAILED: " . db_error() . ': ' . $delete_query);
						echo db_error() . "<br />" . $delete_query;
					}
				} else {
					echo "<div class='red'>ALL values for this field are hidden. DELETE value: {$empty_row['value']}.</div>";
					show_var($history, "{$empty_row['record']}, {$empty_row['event_id']}, {$empty_row['field_name']}", 'red');
					show_var($delete_query);
				}
			} else {
				/**
				 * All are not hidden. If the most recent value shown is not THIS VALUE, then delete the row
				 */
				$mutable_history = $history;
				$most_recent_event = array_pop($mutable_history);
				if (($most_recent_event['value'] != $empty_row['value']) || $most_recent_event['value'] == '') {
					$rows_deleted++;
					$delete_query = "DELETE FROM redcap_data WHERE record = '{$empty_row['record']}' AND project_id = '$project_id' AND event_id = '{$empty_row['event_id']}' AND field_name = '{$empty_row['field_name']}' AND value = '" . prep($empty_row['value']) . "' LIMIT 1";
					if (!$debug) {
						if (db_query($delete_query)) {
							target_log_event($delete_query, 'redcap_data', 'delete', $empty_row['record'], "{$empty_row['field_name']} = '{$empty_row['value']}'", 'Delete hidden blank value', '', $project_id, $empty_row['event_id']);
						} else {
							error_log("SQL DELETE FAILED: " . db_error() . ': ' . $delete_query);
							echo db_error() . "<br />" . $delete_query;
						}
					} else {
						echo "<div class='red'>NON-MATCHING value is shown. THIS value ({$empty_row['value']}) is hidden. DELETE row.</div>";
						show_var($history, "{$empty_row['record']}, {$empty_row['event_id']}, {$empty_row['field_name']}", 'red');
						show_var($most_recent_event['value'], 'MOST RECENT', 'red');
						show_var($delete_query);
					}
				} else {
					/**
					 * do nothing
					 */
					if ($debug) {
						echo "<div class='green'>THIS value ({$empty_row['value']}) is shown. DO NOTHING.</div>";
						show_var($history, "{$empty_row['record']}, {$empty_row['event_id']}, {$empty_row['field_name']}", 'green');
						show_var($most_recent_event['value'], 'MOST RECENT', 'green');
					}
				}
			}
		}
		db_free_result($empty_result);
	}
	echo $rows_deleted . " ROWS DELETED.<br />";
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}