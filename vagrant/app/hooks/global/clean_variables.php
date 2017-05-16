<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 7/20/15
 * Time: 3:44 PM
 */
/**
 * variables
 */
global $Proj;
if (!isset($project_id)) {
	$project_id = $Proj->project_id;
}
if (!isset($record)) {
	$record = isset($_GET['id']) ? $_GET['id'] : null;
}
if (!isset($instrument)) {
	$instrument = isset($_GET['page']) ? $_GET['page'] : null;
}
if (!isset($event_id)) {
	$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;
}
if (!isset($group_id)) {
	$group_id = null;
}