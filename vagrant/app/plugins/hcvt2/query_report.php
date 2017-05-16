<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = true;
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
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * WORKING SET OF QUERY_IDs
 */
$query_status_sql = "SELECT `status`.status_id AS query_id, `status`.record AS subject_id, `status`.field_name, `status`.query_status, CONCAT(users.user_firstname, ' ', users.user_lastname) AS assigned_to, res.ts AS date_time, CONCAT(cur_users.user_firstname, ' ', cur_users.user_lastname) AS response_by, res.response, res.comment FROM
(SELECT * FROM `redcap_data_quality_status` WHERE project_id = '$project_id') `status`
LEFT JOIN
(SELECT * FROM redcap_data_quality_resolutions) res ON `status`.status_id = res.status_id
LEFT JOIN
(SELECT ui_id, user_firstname, user_lastname FROM redcap_user_information) users ON `status`.assigned_user_id = users.ui_id
LEFT JOIN
(SELECT ui_id, user_firstname, user_lastname FROM redcap_user_information) cur_users ON res.user_id = cur_users.ui_id
WHERE `status`.query_status != 'CLOSED'
ORDER BY `status`.status_id ASC, res.ts ASC";
$query_status_result = db_query($query_status_sql);
if ($query_status_result) {
	while ($query_status_row = db_fetch_assoc($query_status_result)) {
		/**
		 * initialize the working arrays
		 */
		$data_row = array();
		/**
		 * Freeze the query_id for future use and start the data row
		 */
		if (isset($query_status_row['query_id'])) {
			$query_id = $query_status_row['query_id'];
			$data_row['Query ID'] = $query_id;
		} else {
			$data_row['Query ID'] = '--';
		}
		$status_array[$query_id][] = $query_status_row['query_status'];
	}
}
if ($debug) {
	show_var($status_array);
}