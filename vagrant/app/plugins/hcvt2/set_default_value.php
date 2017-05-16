<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 2014-07-16
 */
/**
 * TESTING
 */
$debug = true;
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
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
global $Proj;
Kint::enabled($debug);
/**
 * project metadata
 */
$my_branching_logic = new BranchingLogic();
$baseline_event_id = $Proj->firstEventId;
$fields_result = db_query("SELECT field_name, element_label, element_type FROM redcap_metadata WHERE project_id = '$project_id' AND element_type IN ('radio', 'sql', 'select') ORDER BY field_order ASC");
?>
	<div class="red"><h1>WARNING!!!</h1>

		<h2>This plugin will destroy data! Do not use unless you know what you're doing!!</h2>

		<p>All actions taken by this plugin are logged. You've been warned!</p></div>
	<h3>Set default values</h3>
	<p>This will set a selected default value for all instances of a radio button, sql or select field (including per-form Complete fields) in all
		events in the project where the field is</p> <ul><li>not hidden by branching logic</li> <li>optionally where the form is set to Complete</li></ul> <p>This is useful when initializing a new
		variable. It can also be used where some data has already been saved to the field. It will not overwrite
		existing values.</p>
	<h4>Select the field whose value you wish to set</h4>
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
							echo "<option value='{$fields_row['field_name']}' selected>" . "[" . $fields_row['field_name'] . "] - " . substr($fields_row['element_label'], 0, 60) . "</option>";
						} else {
							echo "<option value='{$fields_row['field_name']}'>" . "[" . $fields_row['field_name'] . "] - " . substr($fields_row['element_label'], 0, 60) . "</option>";
						}
					}
				}
				?>
			</select>
			<?php
			if (isset($_POST['field_name'])) {
				$form_complete_checked = isset($_POST['form_complete']) ? 'checked' : '';
				d($form_complete_checked);
				$checkbox = RCView::checkbox(array('name' => 'form_complete', $form_complete_checked => $form_complete_checked)) . 'Set default value only if form is marked complete';
				?>
				<p>&nbsp;</p>
				<h4>Select the default value for this field</h4>
				<select class="x-form-text x-form-field"
				        name="default_value"
				        style="height: 22px; padding-right: 0;"
				        id="default_value">
					<option value>-- Select Value --</option>
					<?php
					if (in_array($Proj->metadata[$_POST['field_name']]['element_type'], array('radio', 'select'))) {
						foreach (parseEnum($Proj->metadata[$_POST['field_name']]['element_enum']) as $this_code => $this_label) {
							if ($_POST['default_value'] == $this_code) {
								echo "<option value='{$this_code}' selected>{$this_label}</option>";
							} else {
								echo "<option value='{$this_code}'>{$this_label}</option>";
							}
						}
					} else {
						$field_enum_result = db_query($Proj->metadata[$_POST['field_name']]['element_enum']);
						$field_name = db_field_name($field_enum_result, 0);
						if ($field_enum_result) {
							while ($field_enum_row = db_fetch_assoc($field_enum_result)) {
								$this_value = $field_enum_row[$field_name];
								if ($_POST['default_value'] == $this_value) {
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
				<?php echo $checkbox ?>
				<p>&nbsp;</p>
				<input type='submit' value='Set default value'/>
				<?php
			}
			?>
		</div>
	</form>
<?php
if (isset($_POST['field_name']) && isset($_POST['default_value'])) {
	/**
	 * set default value for the field
	 */
	$events = array();
	$this_form_name = $Proj->metadata[$_POST['field_name']]['form_name'];
	$event_result = db_query("SELECT DISTINCT forms.event_id FROM
	(SELECT * FROM redcap_events_forms) forms
	LEFT OUTER JOIN
	(SELECT * FROM redcap_events_metadata) events_meta
	ON forms.event_id = events_meta.event_id
	LEFT OUTER JOIN
	(SELECT * FROM redcap_events_arms) arm
	ON arm.arm_id = events_meta.arm_id
	WHERE arm.project_id = '$project_id'
	AND forms.form_name = '$this_form_name'");
	if ($event_result) {
		while ($events_row = db_fetch_assoc($event_result)) {
			$events[] = $events_row['event_id'];
		}
		$data = REDCap::getData('array', '', array($_POST['field_name'], $this_form_name . '_complete'), $events);
		$form_complete = isset($_POST['form_complete']) && $_POST['form_complete'] == 'on' ? true : false;
		//d($data);
		foreach ($data AS $subject_id => $subject) {
			foreach ($subject AS $event_id => $event) {
				$do_if_complete = $form_complete ? $event[$this_form_name . '_complete'] == '2' : true;
				$all_fields_hidden = $my_branching_logic->allFieldsHidden($subject_id, $event_id, array($_POST['field_name']));
				//d($event);
				if (!$all_fields_hidden && $event[$_POST['field_name']] != $_POST['default_value'] && $do_if_complete) {
					update_field_compare($subject_id, $project_id, $event_id, $_POST['default_value'], $event[$_POST['field_name']], $_POST['field_name'], $debug);
				}
			}
		}
	}
} else {
	print "<h3>You must select field name and default value</h3>";
}
?>
	<script type="text/javascript">
		$(document).ready(function () {
			$("#field_name").change(function () {
				$("#field_val_select").trigger('submit');
			});
		});
	</script>
<?php
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;