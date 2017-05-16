<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 3/13/14
 * Time: 1:17 PM
 */
$timer = array();
$timer['start'] = microtime(true);
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
$user_rights['data_export_tool'] = true;
require_once $base_path . '/plugins/prioritize/cdisc/AE_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/CE_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/CM_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/CO_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/DM_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/DS_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/EX_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/FA_table.php';
//require_once $base_path . '/plugins/prioritize/cdisc/IE_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/LB_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/MH_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/SU_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/SUPP_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/SV_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/TA_table.php';
require_once $base_path . '/plugins/prioritize/cdisc/VS_table.php';
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;