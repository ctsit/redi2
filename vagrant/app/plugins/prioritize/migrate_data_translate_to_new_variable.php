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
$new_fields_result = db_query("SELECT field_name, element_label, element_type FROM redcap_metadata WHERE project_id = '$project_id' AND element_type IN ('radio', 'sql', 'autocomplete') ORDER BY field_order ASC");
?>
<div class="red"><h1>WARNING!!!</h1><h2>This plugin will destroy data! Do not use unless you know what you're doing!!</h2><p>All actions taken by this plugin are logged. You've been warned!</p></div>
<h3>Reassign data from one variable to another variable, deleting the original, reassigned values</h3>
<h4>Select the source field containing the data to be migrated</h4>
	<form id="field_val_select" action="<?php echo $_SERVER['PHP_SELF'] . '?pid=' . $project_id; ?> " method="post">
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
			<p>&nbsp;</p>
			<h4>Enter the value already entered in this field that will be translated</h4>
			<input name="xlate_value" type="text" value="<?php echo $_POST['xlate_value'] ?>"/>
			<p>&nbsp;</p>
			<h4>Select the field whose value you wish to set (limited to radio buttons, Autocomplete and SQL field types)</h4>
			<select class="x-form-text x-form-field"
			        name="new_field_name"
			        style="height: 22px; padding-right: 0;"
			        id="new_field_name">
				<option value>-- Select Field --</option>
				<?php
				if ($new_fields_result) {
					while ($new_fields_row = db_fetch_assoc($new_fields_result)) {
						if ($_POST['new_field_name'] == $new_fields_row['field_name']) {
							echo "<option value='{$new_fields_row['field_name']}' selected>" . "[" . $new_fields_row['field_name'] . "] - " . substr($new_fields_row['element_label'], 0, 60) . "</option>";
						} else {
							echo "<option value='{$new_fields_row['field_name']}'>" . "[" . $new_fields_row['field_name'] . "] - " . substr($new_fields_row['element_label'], 0, 60) . "</option>";
						}
					}
				}
				?>
			</select>
		<?php
		if (isset($_POST['new_field_name'])) {
			?>
			<p>&nbsp;</p>
			<h4>Select the new value for this field</h4>
			<select class="x-form-text x-form-field"
			        name="new_value"
			        style="height: 22px; padding-right: 0;"
			        id="new_value">
				<option value>-- Select Value --</option>
				<?php
				if ($Proj->metadata[$_POST['new_field_name']]['element_type'] == 'radio') {
					foreach (parseEnum($Proj->metadata[$_POST['new_field_name']]['element_enum']) as $this_code => $this_label) {
						if ($_POST['new_value'] == $this_code) {
							echo "<option value='{$this_code}' selected>{$this_label}</option>";
						} else {
							echo "<option value='{$this_code}'>{$this_label}</option>";
						}
					}
				} else {
					$field_enum_result = db_query($Proj->metadata[$_POST['new_field_name']]['element_enum']);
					$field_name = db_field_name($field_enum_result, 0);
					if ($field_enum_result) {
						while ($field_enum_row = db_fetch_assoc($field_enum_result)) {
							$this_value = $field_enum_row[$field_name];
							if ($_POST['new_value'] == $this_value) {
								echo "<option value='{$this_value}' selected>{$this_value}</option>";
							} else {
								echo "<option value='{$this_value}'>{$this_value}</option>";
							}
						}
					}
				}
				?>
			</select>
			&nbsp;&nbsp;
			<input type='submit' value='Translate and set new value'/>
		<?php
		}
		?>
		</div>
	</form>
<?php
if (isset($_POST['field_name']) && isset($_POST['xlate_value']) && isset($_POST['new_field_name']) && isset($_POST['new_value'])) {
	$fields[] = $field_name = $_POST['field_name'];
	$fields[] = $new_field = $_POST['new_field_name'];
	$xlate_value = $_POST['xlate_value'];
	$new_value = $_POST['new_value'];
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
			if ($event[$field_name] == $xlate_value) {
				update_field_compare($subject_id, $project_id, $event_id, $new_value, $event[$new_field], $new_field, $debug);
				update_field_compare($subject_id, $project_id, $event_id, '', $event[$field_name], $field_name, $debug);
			}
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
?>
	<script type="text/javascript">
		$(document).ready(function () {
			$("#new_field_name").change(function () {
				$("#field_val_select").trigger('submit');
			});
		});
	</script>
<?php