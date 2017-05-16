<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * debug
 */
$getdebug = $_GET['debug'] ? (bool) $_GET['debug'] : false;
$debug = $getdebug ? true : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
Kint::enabled($debug);
global $Proj, $project_id;
/**
 * Proton pump inhibitor?
 */
set_ppi(null, null, $debug);