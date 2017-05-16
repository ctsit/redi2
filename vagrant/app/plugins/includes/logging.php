<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 7/10/13
 * Time: 1:18 PM
 */
/**
 * LOGGING for CRON and other extra-redcap needs
 */
//Function for logging events
/**
 * @param $sql
 * @param $table
 * @param $event
 * @param $record
 * @param $display
 * @param string $descrip
 * @param string $change_reason
 * @param null $project_id
 * @param null $event_id
 * @return bool|mysqli_result
 */
function target_log_event($sql, $table, $event, $record, $display, $descrip = "", $change_reason = "", $project_id = NULL, $event_id = null) {
	global $user_firstactivity, $rc_connection;

	// Log the event in the redcap_log_event table
	$ts = str_replace(array("-", ":", " "), array("", "", ""), NOW);
	$page = (defined("PAGE") ? PAGE : "");
//	$userid = (in_array(PAGE_FULL, non_auth_pages()) ? "[CRON]" : defined("USERID") ? USERID : "");
	// Pages that do not have authentication should have USERID set to [CRON]
	if (defined("USERID")) {
		$userid = USERID;
	} elseif (defined("CRON_PAGE") && in_array(CRON_PAGE, non_auth_pages())) {
		$userid = '[CRON]';
	} else {
		$userid = "";
	}
	$ip = (isset($userid) && $userid == "[survey respondent]") ? "" : getIpAddress(); // Don't log IP for survey respondents
	$event = strtoupper($event);
	if (!isset($event_id)) {
		$event_id = (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) ? $_GET['event_id'] : "NULL";
	}
	/**
	 * project_id override, for cross-project logging
	 * if project_id has not been passed in the call to this function,
	 * get it from the default constant. Otherwise, use the one passed in.
	 */
	if (!isset($project_id)) {
		$project_id = defined("PROJECT_ID") ? PROJECT_ID : 0;
	}

	// Query
	$sql = "INSERT INTO redcap_log_event
			(project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
			VALUES ($project_id, $ts, '" . prep($userid) . "', " . checkNull($ip) . ", '$page', '$event', '$table', " . checkNull($sql) . ",
			" . checkNull($record) . ", $event_id, " . checkNull($display) . ", " . checkNull($descrip) . ", " . checkNull($change_reason) . ")";
	$q = db_query($sql, $rc_connection);

	// FIRST/LAST ACTIVITY TIMESTAMP: Set timestamp of last activity (and first, if applicable)
	if (defined("USERID") && strpos(USERID, "[") === false) {
		// SET FIRST ACTIVITY TIMESTAMP: If this is the user's first activity to be logged in the log_event table, then log the time in the user_information table
		$sql_firstact = "";
		if ((!isset($user_firstactivity) || (isset($user_firstactivity) && empty($user_firstactivity)))) {
			$sql_firstact = ", user_firstactivity = '" . NOW . "'";
		}
		// SET LAST ACTIVITY TIMESTAMP
		$sql = "update redcap_user_information set user_lastactivity = '" . NOW . "' $sql_firstact
				where username = '" . prep(USERID) . "' limit 1";
		db_query($sql, $rc_connection);
	}

	// Return true/false success for logged event
	return $q;
}
/**
 * cron jobs should use this for logging
 */
function cron_log_event($sql, $table, $event, $record, $display, $descrip="", $change_reason="")
{
	global $user_firstactivity, $rc_connection;

	// Pages that do not have authentication that should have USERID set to [non-user]
	$nonAuthPages = array("_cron/cirrhosis_reporting.php", "_cron/push-hcvrna-monitoring.php", "_cron/push_durations.php", "_cron/push_durations_to_repo.php", "_cron/push_svr_actual_to_pivot.php", "push_svr_actual_to_pivot.php", "_cron/update_daa.php");

	// Log the event in the redcap_log_event table
	$ts = str_replace(array("-", ":", " "), array("", "", ""), NOW);
	$page = (defined("PAGE") ? PAGE : "");
	$userid = "[CRON]";
	$ip = (isset($userid) && $userid == "[survey respondent]") ? "" : getIpAddress(); // Don't log IP for survey respondents
	$event = strtoupper($event);
	$event_id = (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) ? $_GET['event_id'] : "NULL";
	$project_id = defined("PROJECT_ID") ? PROJECT_ID : 0;

	// Query
	$sql = "INSERT INTO redcap_log_event
			(project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
			VALUES ($project_id, $ts, '" . prep($userid) . "', " . checkNull($ip) . ", '$page', '$event', '$table', " . checkNull($sql) . ",
			" . checkNull($record) . ", $event_id, " . checkNull($display) . ", " . checkNull($descrip) . ", " . checkNull($change_reason) . ")";
	$q = db_query($sql, $rc_connection);

	// FIRST/LAST ACTIVITY TIMESTAMP: Set timestamp of last activity (and first, if applicable)
	if (defined("USERID") && strpos(USERID, "[") === false) {
		// SET FIRST ACTIVITY TIMESTAMP: If this is the user's first activity to be logged in the log_event table, then log the time in the user_information table
		$sql_firstact = "";
		if ((!isset($user_firstactivity) || (isset($user_firstactivity) && empty($user_firstactivity)))) {
			$sql_firstact = ", user_firstactivity = '" . NOW . "'";
		}
		// SET LAST ACTIVITY TIMESTAMP
		$sql = "update redcap_user_information set user_lastactivity = '" . NOW . "' $sql_firstact
				where username = '" . prep(USERID) . "' limit 1";
		db_query($sql, $rc_connection);
	}

	// Return true/false success for logged event
	return $q;
}

/**
 * @param $sql
 * @param $table
 * @param $event
 * @param $record
 * @param $display
 * @param string $descrip
 * @param string $change_reason
 * @param $userid
 * @return bool|mysqli_result
 *
 * This function should only be used when required, to impersonate another user for the purpose of ensuring data integrity.
 * One example of this purpose is to replicate Survey respondent input so survey functionality is maintained.
 */
function target_proxy_log_event($sql, $table, $event, $record, $display, $descrip = "", $change_reason = "", $userid = "")
{
	global $user_firstactivity, $rc_connection;

	// Pages that do not have authentication that should have USERID set to [non-user]
	$nonAuthPages = array("_cron/cirrhosis_reporting.php", "_cron/push-hcvrna-monitoring.php", "_cron/push_durations.php", "_cron/push_durations_to_repo.php", "_cron/push_svr_actual_to_pivot.php", "push_svr_actual_to_pivot.php", "_cron/update_daa.php");

	// Log the event in the redcap_log_event table
	$ts = str_replace(array("-", ":", " "), array("", "", ""), NOW);
	$page = (defined("PAGE") ? PAGE : "");
	$ip = (isset($userid) && $userid != "[Survey respondent]") ? "" : getIpAddress(); // Don't log IP for survey respondents
	$event = strtoupper($event);
	$event_id = (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) ? $_GET['event_id'] : "NULL";
	$project_id = defined("PROJECT_ID") ? PROJECT_ID : 0;

	// Query
	$sql = "INSERT INTO redcap_log_event
			(project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
			VALUES ($project_id, $ts, '" . prep($userid) . "', " . checkNull($ip) . ", '$page', '$event', '$table', " . checkNull($sql) . ",
			" . checkNull($record) . ", $event_id, " . checkNull($display) . ", " . checkNull($descrip) . ", " . checkNull($change_reason) . ")";
	$q = db_query($sql, $rc_connection);

	// FIRST/LAST ACTIVITY TIMESTAMP: Set timestamp of last activity (and first, if applicable)
	if (defined("USERID") && strpos(USERID, "[") === false) {
		// SET FIRST ACTIVITY TIMESTAMP: If this is the user's first activity to be logged in the log_event table, then log the time in the user_information table
		$sql_firstact = "";
		if ((!isset($user_firstactivity) || (isset($user_firstactivity) && empty($user_firstactivity)))) {
			$sql_firstact = ", user_firstactivity = '" . NOW . "'";
		}
		// SET LAST ACTIVITY TIMESTAMP
		$sql = "update redcap_user_information set user_lastactivity = '" . NOW . "' $sql_firstact
				where username = '" . prep(USERID) . "' limit 1";
		db_query($sql, $rc_connection);
	}

	// Return true/false success for logged event
	return $q;
}