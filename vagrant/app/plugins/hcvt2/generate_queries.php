<?php
/**
 * WARNING: this is not the only place where code must be changed to put this plugin into test mode. See below...
 */
$debug = true;
$timer = array();
$timer['start'] = microtime(true);

$lasheaka_only = false;
$send_to_field = true;

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';
Kint::enabled($debug);
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * $rules -
 * an array of arrays: "rule_id" => "name_of_field_being_queried"
 */
$project = new Project();
$table_csv = "";
$rules = array();
$rule_id_result = db_query("SELECT rule_id, rule_name, SUBSTRING(SUBSTRING_INDEX(rule_logic, ']', 1), LOCATE('[', rule_logic)+1) AS rule_field FROM `redcap_data_quality_rules` WHERE project_id = '$project_id' ORDER BY rule_order ASC");
?>
	<h3>Generate and send queries</h3>
	<p>Queries will be sent to the user who abstracted the first field in the rule. If the first field is blank, the
		plugin looks for the user who abstracted the second field in the rule. If both of these fail, the query will be
		sent to Lasheaka.</p>
	<p>This makes it imperative that the logic in the rule be constructed such that if we want to send a query to a
		blank field, the second field in the rule should be an abstracted one. Derived fields should be placed later in
		the rule, preferably at the end.</p>
	<h4>Select the Data Quality rule to query</h4>
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
			&nbsp;&nbsp;
			<input type='submit' value='Generate queries for this rule'/>
		</div>
	</form>
<?php

if (isset($_POST['rule_id'])) {
	$rule_id = $_POST['rule_id'];
// Instantiate DataQuality object
	$dq = new DataQuality();
// Get rule info
	$rule_info = $dq->getRule($rule_id);
	d($rule_info);

	/**
	 * get the first field in the rule logic - this is where the query will be put
	 */
	$rule_fields = array_keys(getBracketedFields($rule_info['logic'], true, true, false));
	$field = array_shift($rule_fields);
	if (strpos($field, '.') !== false) {
		$field = substr($field, strpos($field, '.') + 1);
	}
	$field2 = array_shift($rule_fields);
	if (strpos($field2, '.') !== false) {
		$field2 = substr($field, strpos($field2, '.') + 1);
	}
	d($field);

// Execute this rule
	$dq->executeRule($rule_id);

// Log the event
	if (!$debug) {
		// Only log this event for the LAST ajax request sent (since sometimes multiple serial requests are sent)
		log_event($sql_all, "redcap_data_quality_rules", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Execute data quality rule(s)");
	}

	$rule_results = $dq->getLogicCheckResults();

	foreach ($rule_results AS $results) {
		foreach ($results AS $result) {
			d($result);
			$data_row = array();
			$data_array = array();
			unset($assigned_user_id);
			if (!$lasheaka_only) {
				$user_result = db_query("SELECT ui.ui_id FROM
				(SELECT DISTINCT user FROM `redcap_log_event` WHERE project_id = '$project_id' AND object_type = 'redcap_data' AND pk = '{$result['record']}' AND (data_values LIKE '%$field%' OR data_values LIKE '%$field2%') AND event_id = '{$result['event_id']}' AND (event = 'INSERT' OR event = 'UPDATE') ORDER BY ts ASC LIMIT 1) name
				LEFT JOIN
				(SELECT ui_id, username FROM `redcap_user_information` WHERE ui_id != 90 AND user_suspended_time IS NULL) ui
				ON name.user = ui.username");
				if ($user_result) {
					while ($user_row = db_fetch_assoc($user_result)) {
						if (isset($user_row['ui_id'])) {
							$assigned_user_id = $user_row['ui_id'];
						}
					}
					db_free_result($user_result);
				}
				if (!isset($assigned_user_id)) {
					$assigned_user_id = 93;
				}
			} else {
				/**
				 * send all queries to Lasheaka
				 */
				$assigned_user_id = 93;
			}
			/**
			 * use this $history if we ever work out how to use a rule to send a query to a field without causing trouble when closing the query.
			 */
			$history = getFieldDataResHistoryTarget($result['record'], $result['event_id'], $field);
			d($history);
			/**
			 * use this one until then, which returns all history for all fields for this record => event
			 */
			//$history = getFieldDataResHistoryTarget($result['record'], $result['event_id'], '');
			/**
			 * we don't want to duplicate queries
			 * if the result is excluded or has a query history, ignore it
			 * First, determine if we have an open query on this rule
			 */
			$has_open_query = false;
			$has_history = count($history) > 0 ? true : false;
			if ($has_history) {
				foreach ($history AS $history_query) {
					$rule_history_result = db_query("SELECT rule_id FROM redcap_data_quality_status WHERE status_id = '{$history_query['status_id']}'");
					if ($rule_history_result) {
						$rule_history = db_fetch_assoc($rule_history_result);
						if (($rule_history['rule_id'] == (int)$rule_id && ($history_query['query_status'] == 'OPEN') || $history_query['exclude'] == '1')) {
							$has_open_query = true;
						}
					}
				}
			}
			d($has_open_query);
			/**
			 * if this query isn't excluded (because it's already been resolved)
			 * and we don't already have an open query, open one.
			 */
			if (!$result['exclude'] && !$has_open_query && !$has_history) {
				/**
				 * get the query result data from data_display and put it into an array for addition to the report
				 */
				$raw_data_rows = explode(', ', strip_tags(str_replace('<br>', ', ', $result['data_display'])));
				foreach ($raw_data_rows AS $raw_data_row) {
					$this_row_array = explode(': ', $raw_data_row);
					$data_array[$this_row_array[0]] = $this_row_array[1];
				}
				krsort($data_array);
				//$data_row['monitor'] = $result['record'] & 1 ? 'dianne_mattingly' : 'wendy_robertson';
				$data_row['subjid'] = quote_wrap($result['record']);
				$data_row['usubjid'] = quote_wrap(get_single_field($result['record'], PROJECT_ID, $project->firstEventId, 'dm_usubjid', ''));
				$data_row['event'] = quote_wrap(REDCap::getEventNames(false, false, $result['event_id']));
				//$data_row['field'] = quote_wrap($project->metadata[$field]['element_label']);
				//$data_row['data'] = quote_wrap(strip_tags(str_replace('<br>', ', ', $result['data_display'])));
				foreach ($data_array AS $key => $val) {
					$data_row[$key] = quote_wrap($val);
				}
				$data_row['description'] = quote_wrap($rule_info['name']);
				$row_csv = implode(',', $data_row) . "\n";
				$table_csv .= $row_csv;
				/**
				 * prep for insert status
				 */
				$dr_status = 'OPEN';
				if (!$send_to_field) {
					$non_rule = NULL;
					unset($field);
				} else {
					$non_rule = 1;
					unset($rule_id);
				}
				$response_requested = '1';
				$response = NULL;
				$drw_log = "Open data query";
				// Insert new or update existing
				$status_sql = "insert into redcap_data_quality_status
				(rule_id, non_rule, project_id, record, event_id, field_name, query_status, assigned_user_id)
				values
				(" . checkNull($rule_id) . ", " . checkNull($non_rule) . ", " . PROJECT_ID . ", '" . prep($result['record']) . "', {$result['event_id']}, " . checkNull($field) . ", " . checkNull($dr_status) . ", " . $assigned_user_id . ")
				on duplicate key update query_status = " . checkNull($dr_status) . ", status_id = LAST_INSERT_ID(status_id)";
				if (!$debug) {
					if (db_query($status_sql)) {
						// Get cleaner_id
						$status_id = db_insert_id();
						// Get current user's ui_id
						$userInitiator = User::getUserInfo(USERID);
						// Add new row to data_resolution_log
						$sql = "insert into redcap_data_quality_resolutions
						(status_id, ts, user_id, response_requested, response, comment, current_query_status, upload_doc_id)
						values
						($status_id, '" . NOW . "', " . checkNull($userInitiator['ui_id']) . ", " . checkNull($response_requested) . ", " . checkNull($response) . ", " . checkNull($rule_info['name']) . ", " . checkNull($dr_status) . ", " . checkNull($_POST['upload_doc_id']) . ")";
						if (db_query($sql)) {
							// Success, so return content via JSON to redisplay with new changes made
							$res_id = db_insert_id();
							## Logging
							// Set data values as json_encoded
							$logDataValues = json_encode(array('res_id' => $res_id, 'record' => $result['record'], 'event_id' => $result['event_id'],
								'field' => $field, 'rule_id' => $rule_id));
							// Set event_id in query string for logging purposes only
							$_GET['event_id'] = $result['event_id'];
							// Log it
							log_event($sql, "redcap_data_quality_resolutions", "MANAGE", $result['record'], $logDataValues, $drw_log);
						} else {
							// ERROR!
							exit('0');
						}
					}
				}
				d($status_sql);
			}
		}
	}
	$headers = implode(',', array_keys($data_row)) . "\n";
	d($headers);
	d($table_csv);
	if (!$debug) {
		create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, substr(camelCase($rule_info['name']), 0, 20) . "_REPORT_", $debug, $rule_info['name']);
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;