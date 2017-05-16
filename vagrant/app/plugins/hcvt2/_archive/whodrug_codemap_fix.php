<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = false;
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * do stuff
 */
$ddafix_query = "SELECT * FROM _whodrug_dda_delete";
$ddafix_result = db_query($ddafix_query);
if ($ddafix_result) {
	while ($ddafix_row = db_fetch_assoc($ddafix_result)) {
		$dda_delete_sql = "DELETE FROM _whodrug_dda_target WHERE drug_rec_num = '{$ddafix_row['drug_rec_num']}' AND atc_code LIKE '{$ddafix_row['atc2_code']}%'";
		if (!$debug) {
			if (!db_query($dda_delete_sql)) {
				error_log("SQL DELETE FAILED: " . db_error() . "\n");
				echo db_error() . "<br />";
			} else {
				echo "SUCCESS!<br />";
			}
		} else {
			show_var($dda_delete_sql);
		}
	}
}