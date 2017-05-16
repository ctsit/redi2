<?php

$debug = true;

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . 'Config/init_project.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/math_functions.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

/**
 * $rules -
 * an array of arrays: "rule_id" => "name_of_field_being_queried"
 */
//$rules = array("353" => "wbc_lborres", "355" => 'anc_lborres');
$rules = array('965' => '');

foreach ($rules as $rule_id => $field) {

// Instantiate DataQuality object
	$dq = new DataQuality();
// Get rule info
	$rule_info = $dq->getRule($rule_id);

	if ($debug) {
		show_var($rule_info, 'RULE INFO');
	}

// Execute this rule
	$dq->executeRule($rule_id);

// Log the event
	if (!$debug) {
		// Only log this event for the LAST ajax request sent (since sometimes multiple serial requests are sent)
		log_event($sql_all,"redcap_data_quality_rules","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID,"Execute data quality rule(s)");
	}

	$rule_results = $dq->getLogicCheckResults();

	foreach ($rule_results AS $results) {
		foreach ($results AS $result) {
			if ($debug) {
				show_var($result);
			}
			$user_result = db_query("SELECT ui.ui_id FROM
			(SELECT user FROM `redcap_log_event` WHERE project_id = '$project_id' AND object_type = 'redcap_data' AND pk = '{$result['record']}' AND data_values LIKE '%$field%' AND event_id = '{$result['event_id']}' ORDER BY ts DESC LIMIT 1) name
			LEFT JOIN
			(SELECT ui_id, username FROM `redcap_user_information`) ui
			ON name.user = ui.username");
			if ($user_result) {
				$user = db_fetch_assoc($user_result);
				if ($debug) {
					show_var($user['ui_id'], 'UI_ID');
				}
				db_free_result($user_result);
			}
			$history = getFieldDataResHistoryTarget($result['record'], $result['event_id'], $field);
			/**
			 * we don't want to duplicate queries
			 * if the result is excluded or has a query history, ignore it
			 */
			if (!($result['exclude'] || count($history) > 0)) {
				if ($debug) {
					show_var($history, 'HISTORY', 'red');
				}
				$dr_status = 'OPEN';
				$non_rule = null;
				$response_requested = '1';
				$response = NULL;
				$drw_log = "Open data query";
				// Insert new or update existing
				$sql = "insert into redcap_data_quality_status (rule_id, non_rule, project_id, record, event_id, field_name, query_status, assigned_user_id)
				values (" . checkNull($rule_id) . ", " . checkNull($non_rule) . ", " . PROJECT_ID . ", '" . prep($result['record']) . "',
				{$result['event_id']}, " . checkNull($field) . ", " . checkNull($dr_status) . ", " . checkNull($user['ui_id']) . ")
				on duplicate key update query_status = " . checkNull($dr_status) . ", status_id = LAST_INSERT_ID(status_id)";
				if ($debug) {
					show_var($sql, 'INSERT STATUS', 'red');
				}
				if (true) {
				//if (db_query($sql)) {
					// Get cleaner_id
					$status_id = db_insert_id();
					// Get current user's ui_id
					$userInitiator = User::getUserInfo(USERID);
					// Add new row to data_resolution_log
					$sql = "insert into redcap_data_quality_resolutions (status_id, ts, user_id, response_requested,
					response, comment, current_query_status, upload_doc_id)
					values ($status_id, '" . NOW . "', " . checkNull($userInitiator['ui_id']) . ",
					" . checkNull($response_requested) . ", " . checkNull($response) . ",
					" . checkNull($rule_info['name']) . ", " . checkNull($dr_status) . ", " . checkNull($_POST['upload_doc_id']) . ")";
					if (!$debug) {
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
					} else {
						show_var($sql, 'INSERT RESOLUTIONS', 'red');
					}
				}
			}
		}
	}
}
