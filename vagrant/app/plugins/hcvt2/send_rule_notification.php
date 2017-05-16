<?php
/*define("NOAUTH", true);
define("CRON_PAGE", $_SERVER['PHP_SELF']);*/
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 8/20/14
 * Time: 9:29 AM
 */
/**
 * debug
 */
$getdebug = $_GET['debug'] ? $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_CLASSES . "Message.php";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
global $Proj, $project_id;
d($Proj);
/**
 * get DQ rule output
 */
if (isset($_GET['rule_id']) && $_GET['rule_id'] != 'ALL_RULES' && is_numeric($_GET['rule_id'])) {
	$rule_id = $_GET['rule_id'];
	/**
	 * Instantiate DataQuality object
	 */
	$dq = new DataQuality();
	d($dq);
	/**
	 * get rule info
	 */
	$rule_info = $dq->getRule($rule_id);
	d($rule_info);
	/**
	 * get the first field in the rule logic
	 */
	$field = array_shift(array_keys(getBracketedFields($rule_info['logic'], true, true, false)));
	if (strpos($field, '.') !== false) {
		$field = substr($field, strpos($field, '.') + 1);
	}
	d($field);
	$destination_form = $Proj->metadata[$field]['form_name'];
	/**
	 * Execute this rule
	 */
	$dq->executeRule($rule_id);
//	$results_table = $dq->displayResultsTable($rule_info);
//	print $results_table[2];
//	print $results_table[1];
	/**
	 * cycle through rule results
	 */
	$rule_results = $dq->getLogicCheckResults();

	foreach ($rule_results AS $results) {
		foreach ($results AS $result) {
			$dag_prefix = substr(get_single_field($result['record'], $project_id, $Proj->firstEventId, 'dm_usubjid', ''), 0, 3);
			$dag_result = db_query("SELECT group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND LEFT(group_name, 3) = '$dag_prefix'");
			if ($dag_result) {
				$dag_name = db_result($dag_result, 0, 'group_name');
				$dag_name = prep($dag_name);
			}
			/**
			 * if the result is excluded ignore it
			 */
			if ($result['exclude'] != 1) {
				$today = date('Y-m-d');
				$redcap_event_name = $Proj->getUniqueEventNames($result['event_id']);
				$check_table = array();
				$check_table_result = db_query("SELECT * FROM _target_notifications WHERE project_id = '$project_id' AND record = '{$result['record']}' AND redcap_event_name = '$redcap_event_name' AND redcap_data_access_group = '$dag_name' AND form_name = '$destination_form' AND type = 'rule' AND type_id = '$rule_id' AND action_date = '$today'");
				if ($check_table_result) {
					$check_table = db_fetch_assoc($check_table_result);
				} else {
					error_log(db_error());
				}
				if (count($check_table) == 0) {
					$sql = "INSERT INTO _target_notifications SET project_id = '$project_id', record = '{$result['record']}', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '$dag_name', form_name = '$destination_form', type = 'rule', type_id = '$rule_id', action_date = '$today'";
					if (!$debug) {
						if (!db_query($sql)) {
							error_log(db_error());
						}
					} else {
						d($sql);
					}
				}
			}
		}
	}
	/**
	 * initialize variables
	 */
	$item_count = 0;
	$today = date('Y-m-d');
	$rows = '';
	/**
	 * query target_email_actions for any actions that haven't yet been digested.
	 * digest and send them
	 */
	$actions_result = db_query("SELECT DISTINCT * FROM _target_notifications WHERE (digest_date IS NULL OR digest_date = '') AND project_id = '$project_id' AND type = 'rule' AND type_id = '$rule_id' AND sent = 0 ORDER BY abs(record) ASC");
	if ($actions_result) {
		while ($actions_row = db_fetch_assoc($actions_result)) {
			$item_count++;
			foreach ($actions_row AS $key => $value) {
				$$key = $value;
			}
			$event_keys = Event::getUniqueKeys($project_id);
			$event_id = array_search($redcap_event_name, $event_keys);
			$url = APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/DataEntry/index.php?pid=$project_id&id=$record&event_id=$event_id&page=$form_name";
			$rows .= RCView::tr(array('style' => 'border:1px solid #d0d0d0;'),
				RCView::td(array('class' => 'data_1', 'style' => "font-family:Verdana;font-size:8pt;background-color:" . row_style($item_count) . ";border:1px solid #CCCCCC;text-align:left;padding:5px 8px;"),
					"<a href='{$url}'>$record</a>"
				) .
				RCView::td(array('class' => 'data_1', 'style' => "font-family:Verdana;font-size:8pt;background-color:" . row_style($item_count) . ";border:1px solid #CCCCCC;text-align:left;padding:5px 8px;"),
					$redcap_data_access_group
				)
			);
			$update_query = "UPDATE _target_notifications SET digest_date = '$today', sent = 1 WHERE action_id = '$action_id'";
			if (!$debug) {
				if (!db_query($update_query)) {
					error_log(db_error());
				}
			} else {
				d($update_query);
			}
		}
		if ($item_count > 0) {
			/**
			 * construct html for email
			 */
			$html = "<div>";
			$html .= RCView::h1(array('style' => 'font-family:Verdana;font-size:14px;'), "New {$rule_info['name']}");
			$html .= RCView::p(array('style' => 'font-family:Verdana;font-size:10px;'), "Click each link to go to Form");
			$html .= RCView::table(array('id' => 'site_source_uploads', 'class' => 'dt', 'cellspacing' => '0', 'style' => 'width:500px;'),
				RCView::tr('',
					RCView::th(array('class' => 'header', 'style' => 'background-color:#FFFFE0;height:18px;border:1px solid #CCCCCC;text-align:left;padding:5px 8px;width:50%;font-weight:bold;font-family:Arial,Helvetica,sans-serif;font-size:12px;'),
						"Subject #"
					) .
					RCView::th(array('class' => 'header', 'style' => 'background-color:#FFFFE0;height:18px;border:1px solid #CCCCCC;text-align:left;padding:5px 8px;width:50%;font-weight:bold;font-family:Arial,Helvetica,sans-serif;font-size:12px;'),
						"Site"
					)
				) .
				$rows
			);
			$html .= "</div>";
			/**
			 * set up email for sending
			 */
			$to = array(
				'Ken Bergquist' => 'kbergqui@email.unc.edu'
			);
			//$to = array('Ken Bergquist' => 'kbergqui@email.unc.edu');
			$from = array('Ken Bergquist' => 'kbergqui@email.unc.edu');
			$subject = "HCV-TARGET {$rule_info['name']} Notification";
			$email = new Message ();
			foreach ($from as $name => $address) {
				$email->setFrom($address);
				$email->setFromName($name);
			}
			$email->setSubject($subject);
			$email->setBody($html);
			foreach ($to as $name => $address) {
				$email->setTo($address);
				$email->setToName($name);
				if (!$debug) {
					if (!$email->send()) {
						error_log("ERROR: Failed to send {$rule_info['name']} digest");
					}
				}
			}
			d($email);
		} else {
			error_log("NOTICE: No {$rule_info['name']} were available to be digested");
		}
	}
}