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
$subjects = ''; // '' = ALL
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
}
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
$allowed_pids = array('12');
REDCap::allowProjects($allowed_pids);
/**
 * project metadata
 */
$project = new Project();
$baseline_event_id = $project->firstEventId;
$plugin_title = "Transparently move to the next event for the current form";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
/*if ($_GET) {
	show_var($_GET, '$_GET');
}*/
if ($_SERVER) {
	$refer_outer = explode('&', substr($_SERVER['HTTP_REFERER'], strpos($_SERVER['HTTP_REFERER'], '?') + 1));
	$refer_inner = array();
	foreach ($refer_outer AS $refer) {
		$refer = explode('=', $refer);
		$refer_inner[$refer[0]] = $refer[1];
	}
	if ($debug) {
		show_var($refer_inner, 'post-process');
	}
	if (isset($refer_inner['page']) && isset($refer_inner['event_id']) && isset($refer_inner['id'])) {
		$next_event_id = getNextEventId($refer_inner['event_id'], $refer_inner['page']);
		if ($next_event_id !== false) {
			header('Location: ' . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=" . urlencode($refer_inner['id']) . "&event_id=$next_event_id&page={$refer_inner['page']}");
		} else {
			show_var($next_event_id);
		}
	} else {
		echo "<h1>You must first select a subject and a form to use this plugin.</h1>";
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}