<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 8/27/13
 * Time: 4:06 PM
 */
$debug = $_GET['debug'] ? (bool)$_GET['debug'] : false;
$subjects = $_GET['id'] ? $_GET['id'] : '';
$enable_kint = $debug && $subjects != '' ? true : false;
/**
 * timing
 */
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict use of this plugin to the appropriate project
 */
$allowed_pid = '26';
REDCap::allowProjects($allowed_pid);
Kint::enabled($enable_kint);
/**
 * project metadata
 */
global $Proj;
$first_event_id = $Proj->firstEventId;
$plugin_title = "Derive values";
/**
 * plugin title
 */
echo "<h3>$plugin_title</h3>";

$timer['set tx data'] = microtime(true);
set_tx_data($subjects, $debug);
$timer['set bmi'] = microtime(true);
set_bmi($subjects, $debug);
$timer['set cbc'] = microtime(true);
set_cbc_flags($subjects, $debug);
$timer['set cirrhosis'] = microtime(true);
set_cirrhosis($subjects, $debug);
$timer['set crcl'] = microtime(true);
set_crcl($subjects, null, 'both', $debug);
$timer['set trt exp'] = microtime(true);
set_treatment_exp($subjects, $debug);
$timer['set egfr'] = microtime(true);
set_egfr($subjects, null, 'both', $debug);
/**
 * timing
 */
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;