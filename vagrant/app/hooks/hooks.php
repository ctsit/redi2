<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 7/14/15
 * Time: 2:29 PM
 */

/**
 * This function is executed each time a Data Entry form is loaded.
 * @param $project_id
 * @param null $record
 * @param $instrument
 * @param $event_id
 * @param null $group_id
 */
function redcap_data_entry_form($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL) {
	/**
	 * global hooks
	 * place global modifications here
	 */
	$project_handler_script = dirname(__FILE__) . "/global/redcap_data_entry_form.php";
	if (file_exists($project_handler_script)) {
		include_once $project_handler_script;
	}
	/**
	 * project-specific hooks
	 */
	$project_handler_script = dirname(__FILE__) . "/pid{$project_id}/redcap_data_entry_form.php";
	if (file_exists($project_handler_script)) {
		include_once $project_handler_script;
	}
}

/**
 * This function executes each time a Data Entry form is saved.
 * @param $project_id
 * @param null $record
 * @param $instrument
 * @param $event_id
 * @param null $group_id
 * @param null $survey_hash
 * @param null $response_id
 */
function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL) {
	/**
	 * global hooks
	 * place global modifications here
	 */
	$project_handler_script = dirname(__FILE__) . "/global/redcap_save_record.php";
	if (file_exists($project_handler_script)) {
		include $project_handler_script;
	}
	/**
	 * project-specific hooks
	 */
	$project_handler_script = dirname(__FILE__) . "/pid{$project_id}/redcap_save_record.php";
	if (file_exists($project_handler_script)) {
		include $project_handler_script;
	}
}

/**
 * This function executes each time a Data Entry form is saved.
 * @param $project_id
 * @param null $record
 * @param $instrument
 * @param $event_id
 * @param null $group_id
 * @param null $survey_hash
 * @param null $response_id
 */
function redcap_survey_page($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL)
{
	/**
	 * global hooks
	 * place global modifications here
	 */
	$project_handler_script = dirname(__FILE__) . "/global/redcap_survey_page.php";
	if (file_exists($project_handler_script)) {
		include $project_handler_script;
	}
	/**
	 * project-specific hooks
	 */
	$project_handler_script = dirname(__FILE__) . "/pid{$project_id}/redcap_survey_page.php";
	if (file_exists($project_handler_script)) {
		include $project_handler_script;
	}
}

/**
 * This function executes each time a Data Entry form is saved.
 * @param $project_id
 * @param null $record
 * @param $instrument
 * @param $event_id
 * @param null $group_id
 * @param null $survey_hash
 * @param null $response_id
 */
function redcap_survey_complete($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL)
{
	/**
	 * global hooks
	 * place global modifications here
	 */
	$project_handler_script = dirname(__FILE__) . "/global/redcap_survey_complete.php";
	if (file_exists($project_handler_script)) {
		include $project_handler_script;
	}
	/**
	 * project-specific hooks
	 */
	$project_handler_script = dirname(__FILE__) . "/pid{$project_id}/redcap_survey_complete.php";
	if (file_exists($project_handler_script)) {
		include $project_handler_script;
	}
}