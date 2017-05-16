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
$debug = $_GET['debug'] ? (bool)$_GET['debug'] : false;
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require APP_PATH_CLASSES . "Message.php";
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
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
$actions_result = db_query("SELECT DISTINCT * FROM target_email_actions WHERE (digest_date IS NULL OR digest_date = '') AND project_id = '$project_id' ORDER BY redcap_data_access_group ASC, abs(record) ASC");
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
		if (!$debug) {
			if (!db_query("UPDATE target_email_actions SET digest_date = '$today' WHERE action_id = '$action_id'")) {
				error_log("ERROR: failed to update target_email_actions.digest_date");
			}
		}
		d($actions_row);
	}
	if ($item_count > 0) {
		/**
		 * construct html for email
		 */
		$html = "<div>";
		$html .= RCView::h1(array('style' => 'font-family:Verdana;font-size:14px;'), "New Site Source Uploads");
		$html .= RCView::p(array('style' => 'font-family:Verdana;font-size:10px;'), "Click each link to go to Site Source Uploads Form");
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
			'Ken Bergquist' => 'kbergqui@email.unc.edu',
			'Lasheaka McClellan' => 'Lasheaka.McClellan@medicine.ufl.edu',
			'Tyre Johnson' => 'tyre.johnson@medicine.ufl.edu',
			'Dona-Marie Mintz' => 'Dona-Marie.Mintz@medicine.ufl.edu',
			'Nicholas Slater' => 'Nicholas.Slater@medicine.ufl.edu'
		);
		//$to = array('Ken Bergquist' => 'kbergqui@email.unc.edu');
		$from = array('Ken Bergquist' => 'kbergqui@email.unc.edu');
		$subject = "HCV-TARGET 2 Site Source Upload Notification";
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
					error_log("ERROR: Failed to send Site Source Upload digest");
				}
			}
		}
		d($email);
	} else {
		error_log("NOTICE: No site source uploads were available to be digested");
	}
}