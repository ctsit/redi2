<?php
global $lang, $app_title, $userid, $user_rights;
$debug = false;
$timer = array();
$timer['start'] = microtime(true);

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
global $Proj;
Kint::enabled($debug);
/**
 * $rules -
 * an array of arrays: "rule_id" => "name_of_field_being_queried"
 */
$table_csv = "";
$rules = array();
$rule_sql = "SELECT rule_id, rule_name, SUBSTRING(SUBSTRING_INDEX(rule_logic, ']', 1), LOCATE('[', rule_logic)+1) AS rule_field FROM `redcap_data_quality_rules` WHERE project_id = '$project_id' ORDER BY rule_order ASC";
$rule_id_result = db_query($rule_sql);
?>
	<h3>Generate a report of Data Quality rule results</h3>
	<p>Select a Data Quality rule</p>
	<form action="<?php echo $_SERVER['PHP_SELF'] . '?pid=' . $project_id; ?> " method="post">
		<div class="data" style='max-width:700px;'>
			<select class="x-form-text x-form-field"
			        name="rule_id"
			        style="height: 22px; padding-right: 0;"
			        id="rule_id">
				<option value>-- Select Rule --</option>
				<?php
				if ($rule_id_result) {
					while ($rule_id_row = db_fetch_assoc($rule_id_result)) {
						$rules[$rule_id_row['rule_id']] = $rule_id_row['rule_field'];
						if ($_POST['rule_id'] == $rule_id_row['rule_id']) {
							echo "<option value='{$rule_id_row['rule_id']}' selected>{$rule_id_row['rule_name']}</option>";
						} else {
							echo "<option value='{$rule_id_row['rule_id']}'>{$rule_id_row['rule_name']}</option>";
						}
					}
				}
				?>
			</select>
			<br/>
			<input type='submit' value='Generate report for this rule'/>
		</div>
	</form>
<?php

if (isset($_POST['rule_id']) && $_POST['rule_id'] != 'ALL_RULES') {
	$rule_id = $_POST['rule_id'];
// Instantiate DataQuality object
	$dq = new DataQuality();
// Get rule info
	$rule_info = $dq->getRule($rule_id);

	d($rule_info);

	/**
	 * get the first field in the rule logic - this is where the query will be put
	 */
	$fields = array_keys(getBracketedFields($rule_info['logic'], true, true, false));
	$clean_fields = array();
	foreach ($fields AS $key => $val) {
		if (strpos($val, '.') !== false) {
			$clean_fields[$key] = substr($val, strpos($val, '.') + 1);
		}
	}
	$field = get_first_of_array($clean_fields);
	d($field);

// Execute this rule
	$dq->executeRule($rule_id);

// Log the event
	if (!$debug) {
		// Only log this event for the LAST ajax request sent (since sometimes multiple serial requests are sent)
		log_event($rule_sql, "redcap_data_quality_rules", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Execute data quality rule(s)");
	}

	$rule_results = $dq->getLogicCheckResults();

	foreach ($rule_results AS $results) {
		foreach ($results AS $result) {
			$data_row = array();
			$data_array = array();
			d($result);
			$history = getFieldDataResHistoryTarget($result['record'], $result['event_id'], $field);
			/**
			 * get the query result data from data_display and put it into an array for addition to the report
			 */
			$raw_data_rows = explode('|||', strip_tags(str_replace('<br>', '|||', $result['data_display'])));
			foreach ($raw_data_rows AS $raw_data_row) {
				$this_row_array = explode(': ', $raw_data_row);
				$data_array[$this_row_array[0]] = $this_row_array[1];
			}
			ksort($data_array);
			d($data_array);
			/**
			 * we don't want to duplicate queries
			 * if the result is excluded or has a query history, ignore it
			 */
			if (!$result['exclude']) {
				d($history);
				//$data_row['monitor'] = $result['record'] & 1 ? 'dianne_mattingly' : 'wendy_robertson';
				$data_row['subjid'] = quote_wrap($result['record']);
				$data_row['usubjid'] = quote_wrap(get_single_field($result['record'], PROJECT_ID, $Proj->firstEventId, 'dm_usubjid', ''));
				$data_row['event'] = quote_wrap(REDCap::getEventNames(false, false, $result['event_id']));
				//$data_row['field'] = quote_wrap($Proj->metadata[$field]['element_label']);
				//$data_row['data'] = quote_wrap(strip_tags(str_replace('<br>', ', ', $result['data_display'])));
				foreach ($data_array AS $key => $val) {
					$data_row[quote_wrap($Proj->metadata[$key]['element_label'] . " [$key]")] = quote_wrap($val);
				}
				$data_row['description'] = quote_wrap($rule_info['name']);
				$data_row["Queries on $field"] = quote_wrap(count($history));
				$row_csv = implode(',', $data_row) . "\n";
				$table_csv .= $row_csv;
			}
		}
	}
	$headers = implode(',', array_keys($data_row)) . "\n";
	if (!$debug) {
		create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, $clean_fields, null, $project_id, substr(camelCase($rule_info['name']), 0, 20) . "_REPORT_", $debug, $rule_info['name']);
	}
	d($headers);
	d($table_csv);
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;
