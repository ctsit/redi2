<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 2014-07-16
 */
/**
 * TESTING
 */
$debug = false;
$timer_start = microtime(true);
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
/**
 * restricted use
 */
$allowed_pids = array('38');
REDCap::allowProjects($allowed_pids);
/**
 * project metadata
 */
global $Proj;
$first_event_id = $Proj->firstEventId;
$fields_result = db_query("SELECT field_name, element_label FROM redcap_metadata WHERE project_id = '$project_id' AND element_type != 'descriptive' ORDER BY field_order ASC");
?>
<div class="red"><h1>WARNING!!!</h1><h2>This plugin will destroy data! Do not use unless you know what you're doing!!</h2><p>All actions taken by this plugin are logged. You've been warned!</p></div>
<h3>Reassign data from one variable to another variable</h3>
<h4>Select the source field containing the data to be migrated</h4>
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
							echo "<option value='{$fields_row['field_name']}' selected>" . "[" .  $fields_row['field_name'] . "] - " . substr($fields_row['element_label'], 0, 60) . "</option>";
						} else {
							echo "<option value='{$fields_row['field_name']}'>" . "[" . $fields_row['field_name'] . "] - " . substr($fields_row['element_label'], 0, 60) . "</option>";
						}
					}
				}
				?>
			</select>
			<h4>Enter the name of the new field to which this data should be assigned</h4>
			<input name="new_field" type="text" value = "<?php echo $_POST['new_field'] ?>"/>
				&nbsp;&nbsp;
			<input type='submit' value='Migrate data to new variable'/>
		</div>
	</form>
<?php
if (isset($_POST['field_name']) && isset($_POST['new_field'])) {
	$fields[] = $field_name = $_POST['field_name'];
	$fields[] = $new_field = $_POST['new_field'];
	/**
	 * move any data found in redcap_data WHERE field_name = $field_name into $field_name
	 * then delete the original data, while logging all actions
	 */
	$data = REDCap::getData('array', '', $fields);
	foreach ($data AS $subject_id => $subject) {
		if ($debug) {
			show_var($subject_id, 'SUBJID', 'blue');
		}
		foreach ($subject AS $event_id => $event) {
			update_field_compare($subject_id, $project_id, $event_id, $event[$field_name], $event[$new_field], $new_field, $debug);
			update_field_compare($subject_id, $project_id, $event_id, '', $event[$field_name], $field_name, $debug);
		}
	}
	/**
	 * re-assign any OPEN data quality queries assigned to $field_name
	 */
	$data_quality_status_result = db_query("SELECT record, event_id FROM redcap_data_quality_status WHERE project_id = '$project_id' AND field_name = '$field_name' AND query_status != 'CLOSED'");
	if ($data_quality_status_result) {
		while ($dq_status = db_fetch_assoc($data_quality_status_result)) {
			$data_quality_status_query = "UPDATE redcap_data_quality_status SET field_name = '$new_field' WHERE record = '{$dq_status['record']}' AND project_id = '$project_id' AND event_id = '{$dq_status['event_id']}' AND field_name = '$field_name'";
			if (!$debug) {
				if (db_query($data_quality_status_query)) {
					//target_log_event($data_quality_status_query, 'redcap_data_quality_status', 'update', $subject_id, "field_name = '$new_field'", 'Update data quality status', '', $project_id);
					REDCap::logEvent('Update data quality status', "field_name = '$new_field'", $data_quality_status_query, $dq_status['record'], $dq_status['event_id']);
				} else {
					error_log(db_error() . ': ' . $data_quality_status_query);
					echo(db_error() . ": " . $data_quality_status_query . "<br />");
				}
			} else {
				show_var($data_quality_status_query);
			}
		}
	}
	/**
	 * flush and log any '' values for $field_name
	 */
	$find_blank_values_query = "SELECT record, event_id FROM redcap_data WHERE project_id = '$project_id' AND field_name = '$field_name' AND value = ''";
	$find_blanks_result = db_query($find_blank_values_query);
	if ($find_blanks_result) {
		while ($found_blank_row = db_fetch_assoc($find_blanks_result)) {
			$delete_blank_values_query = "DELETE FROM redcap_data WHERE project_id = '$project_id' AND field_name = '$field_name' AND record = '{$found_blank_row['record']}' AND event_id = '{$found_blank_row['event_id']}' AND value = ''";
			if (!$debug) {
				if (db_query($delete_blank_values_query)) {
					//target_log_event($delete_blank_values_query, 'redcap_data', 'delete', $subject_id, "$field_name = ''", "Delete blank $field_name", '', $project_id);
					REDCap::logEvent("Delete blank $field_name", "$field_name = ''", $delete_blank_values_query, $found_blank_row['record'], $found_blank_row['event_id']);
				} else {
					error_log(db_error() . ': ' . $delete_blank_values_query);
					echo(db_error() . ": " . $delete_blank_values_query . "<br />");
				}
			} else {
				show_var($delete_blank_values_query);
			}
		}
		db_free_result($find_blanks_result);
	}
} else {
	print "<h3>You must select both a source (existing) and destination (new) field.</h3>";
}
$timer_stop = microtime(true);
$timer_time = number_format(($timer_stop - $timer_start), 2);
echo 'This page loaded in ', $timer_time / 60, ' minutes';