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
require_once $base_path . '/plugins/hcvt2/cdisc/AE_table2.php';
require_once $base_path . '/plugins/hcvt2/cdisc/CE_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/CM_table2.php';
require_once $base_path . '/plugins/hcvt2/cdisc/CO_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/DM_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/DS_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/EX_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/FA_table.php';
//require_once $base_path . '/plugins/hcvt2/cdisc/IE_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/LB_table2.php';
require_once $base_path . '/plugins/hcvt2/cdisc/MH_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/SU_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/SUPP_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/SV_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/TA_table.php';
require_once $base_path . '/plugins/hcvt2/cdisc/VS_table.php';
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;