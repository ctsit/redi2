<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 2/12/14
 * Time: 11:47 AM
 * Project: HCV-TARGET 2.0
 * Purpose: Provide an application for coding AEs and ConMeds
 */
$debug = true;
$subjects = '740'; // '' = ALL
$recode_llt = true;
$recode_pt = true;
$recode_soc = true;
$recode_atc = true;
$timer_start = microtime(true);
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
/**
 * $fields = array("dm_subjid", "ifn_oth_suppcm_cmncmpae", "rib_oth_suppcm_cmncmpae", "boc_oth_suppcm_cmncmpae", "tvr_oth_suppcm_cmncmpae", "sim_oth_suppcm_cmncmpae", "sof_oth_suppcm_cmncmpae", "eot_oth_suppds_ncmpae", "cm_cmindc");
 */
$fields = array("dm_subjid", "ae_aeterm", "ae_oth_aeterm", "ae_aemodify", "ae_aedecod", "ae_aebodsys",
	"othpsy_mhterm", "othpsy_oth_mhterm", "othpsy_mhmodify", "othpsy_mhdecod", "othpsy_mhbodsys",
	"othca_mhterm", "othca_oth_mhterm", "othca_mhmodify", "othca_mhdecod", "othca_mhbodsys",
	"ifn_suppcm_cmncmpae", "ifn_oth_suppcm_cmncmpae", "ifn_aemodify", "ifn_aedecod", "ifn_aebodsys",
	"rib_suppcm_cmncmpae", "rib_oth_suppcm_cmncmpae", "rib_aemodify", "rib_aedecod", "rib_aebodsys",
	"boc_suppcm_cmncmpae", "boc_oth_suppcm_cmncmpae", "boc_aemodify", "boc_aedecod", "boc_aebodsys",
	"tvr_suppcm_cmncmpae", "tvr_oth_suppcm_cmncmpae", "tvr_aemodify", "tvr_aedecod", "tvr_aebodsys",
	"sim_suppcm_cmncmpae", "sim_oth_suppcm_cmncmpae", "sim_aemodify", "sim_aedecod", "sim_aebodsys",
	"sof_suppcm_cmncmpae", "sof_oth_suppcm_cmncmpae", "sof_aemodify", "sof_aedecod", "sof_aebodsys",
	"dcv_suppcm_cmncmpae", "dcv_oth_suppcm_cmncmpae", "dcv_aemodify", "dcv_aedecod", "dcv_aebodsys",
	"hvn_suppcm_cmncmpae", "hvn_oth_suppcm_cmncmpae", "hvn_aemodify", "hvn_aedecod", "hvn_aebodsys",
	"vpk_suppcm_cmncmpae", "vpk_oth_suppcm_cmncmpae", "vpk_aemodify", "vpk_aedecod", "vpk_aebodsys",
	"dbv_suppcm_cmncmpae", "dbv_oth_suppcm_cmncmpae", "dbv_aemodify", "dbv_aedecod", "dbv_aebodsys",
	"eot_suppds_ncmpae", "eot_oth_suppds_ncmpae", "eot_aemodify", "eot_aedecod", "eot_aebodsys",
	"cm_cmtrt", "cm_cmdecod", "cm_suppcm_mktstat", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod", "cm_suppcm_indcsys", "cm_suppcm_atcname", "cm_suppcm_atc2name");
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		/**
		 * AE_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['ae_aeterm']), fix_case($event['ae_oth_aeterm']), $event['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
		/**
		 * MH_MHMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['othca_mhterm']), fix_case($event['othca_oth_mhterm']), $event['othca_mhmodify'], 'othca_mhmodify', $debug, $recode_llt);
		/**
		 * MH_MHMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['othpsy_mhterm']), fix_case($event['othpsy_oth_mhterm']), $event['othpsy_mhmodify'], 'othpsy_mhmodify', $debug, $recode_llt);
		/**
		 * IFN_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['ifn_suppcm_cmncmpae']), fix_case($event['ifn_oth_suppcm_cmncmpae']), $event['ifn_aemodify'], 'ifn_aemodify', $debug, $recode_llt);
		/**
		 * RIB_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['rib_suppcm_cmncmpae']), fix_case($event['rib_oth_suppcm_cmncmpae']), $event['rib_aemodify'], 'rib_aemodify', $debug, $recode_llt);
		/**
		 * BOC_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['boc_suppcm_cmncmpae']), fix_case($event['boc_oth_suppcm_cmncmpae']), $event['boc_aemodify'], 'boc_aemodify', $debug, $recode_llt);
		/**
		 * TVR_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['tvr_suppcm_cmncmpae']), fix_case($event['tvr_oth_suppcm_cmncmpae']), $event['tvr_aemodify'], 'tvr_aemodify', $debug, $recode_llt);
		/**
		 * SIM_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['sim_suppcm_cmncmpae']), fix_case($event['sim_oth_suppcm_cmncmpae']), $event['sim_aemodify'], 'sim_aemodify', $debug, $recode_llt);
		/**
		 * SOF_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['sof_suppcm_cmncmpae']), fix_case($event['sof_oth_suppcm_cmncmpae']), $event['sof_aemodify'], 'sof_aemodify', $debug, $recode_llt);
		/**
		 * DCV_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['dcv_suppcm_cmncmpae']), fix_case($event['dcv_oth_suppcm_cmncmpae']), $event['dcv_aemodify'], 'dcv_aemodify', $debug, $recode_llt);
		/**
		 * HVN_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['hvn_suppcm_cmncmpae']), fix_case($event['hvn_oth_suppcm_cmncmpae']), $event['hvn_aemodify'], 'hvn_aemodify', $debug, $recode_llt);
		/**
		 * VPK_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['vpk_suppcm_cmncmpae']), fix_case($event['vpk_oth_suppcm_cmncmpae']), $event['vpk_aemodify'], 'vpk_aemodify', $debug, $recode_llt);
		/**
		 * DBV_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['dbv_suppcm_cmncmpae']), fix_case($event['dbv_oth_suppcm_cmncmpae']), $event['dbv_aemodify'], 'dbv_aemodify', $debug, $recode_llt);
		/**
		 * EOT_AEMODIFY
		 */
		code_llt($project_id, $subject_id, $event_id, fix_case($event['eot_suppds_ncmpae']), fix_case($event['eot_oth_suppds_ncmpae']), $event['eot_aemodify'], 'eot_aemodify', $debug, $recode_llt);
		/**
		 * AE_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['ae_aemodify']), $event['ae_aedecod'], 'ae_aedecod', $debug, $recode_pt);
		/**
		 * MH_MHDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['othpsy_mhmodify']), $event['othpsy_mhdecod'], 'othpsy_mhdecod', $debug, $recode_pt);
		/**
		 * MH_MHDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['othca_mhmodify']), $event['othca_mhdecod'], 'othca_mhdecod', $debug, $recode_pt);
		/**
		 * IFN_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['ifn_aemodify']), $event['ifn_aedecod'], 'ifn_aedecod', $debug, $recode_pt);
		/**
		 * RIB_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['rib_aemodify']), $event['rib_aedecod'], 'rib_aedecod', $debug, $recode_pt);
		/**
		 * BOC_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['boc_aemodify']), $event['boc_aedecod'], 'boc_aedecod', $debug, $recode_pt);
		/**
		 * TVR_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['tvr_aemodify']), $event['tvr_aedecod'], 'tvr_aedecod', $debug, $recode_pt);
		/**
		 * SIM_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['sim_aemodify']), $event['sim_aedecod'], 'sim_aedecod', $debug, $recode_pt);
		/**
		 * SOF_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['sof_aemodify']), $event['sof_aedecod'], 'sof_aedecod', $debug, $recode_pt);
		/**
		 * DCV_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['dcv_aemodify']), $event['dcv_aedecod'], 'dcv_aedecod', $debug, $recode_pt);
		/**
		 * HVN_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['hvn_aemodify']), $event['hvn_aedecod'], 'hvn_aedecod', $debug, $recode_pt);
		/**
		 * VPK_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['vpk_aemodify']), $event['vpk_aedecod'], 'vpk_aedecod', $debug, $recode_pt);
		/**
		 * DBV_AEDECOD
		 */
		code_pt($project_id, $subject_id, $event_id, fix_case($event['dbv_aemodify']), $event['dbv_aedecod'], 'dbv_aedecod', $debug, $recode_pt);
		/**
		 * AE_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['ae_aedecod'], $event['ae_aebodsys'], 'ae_aebodsys', $debug, $recode_soc);
		/**
		 * MH_MHBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['othca_mhdecod'], $event['othca_mhbodsys'], 'othca_mhbodsys', $debug, $recode_soc);
		/**
		 * MH_MHBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['othpsy_mhdecod'], $event['othpsy_mhbodsys'], 'othpsy_mhbodsys', $debug, $recode_soc);
		/**
		 * IFN_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['ifn_aedecod'], $event['ifn_aebodsys'], 'ifn_aebodsys', $debug, $recode_soc);
		/**
		 * RIB_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['rib_aedecod'], $event['rib_aebodsys'], 'rib_aebodsys', $debug, $recode_soc);
		/**
		 * BOC_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['boc_aedecod'], $event['boc_aebodsys'], 'boc_aebodsys', $debug, $recode_soc);
		/**
		 * TVR_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['tvr_aedecod'], $event['tvr_aebodsys'], 'tvr_aebodsys', $debug, $recode_soc);
		/**
		 * SIM_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['sim_aedecod'], $event['sim_aebodsys'], 'sim_aebodsys', $debug, $recode_soc);
		/**
		 * SOF_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['sof_aedecod'], $event['sof_aebodsys'], 'sof_aebodsys', $debug, $recode_soc);
		/**
		 * DCV_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['dcv_aedecod'], $event['dcv_aebodsys'], 'dcv_aebodsys', $debug, $recode_soc);
		/**
		 * HVN_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['hvn_aedecod'], $event['hvn_aebodsys'], 'hvn_aebodsys', $debug, $recode_soc);
		/**
		 * EOT_AEBODSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['eot_aedecod'], $event['eot_aebodsys'], 'eot_aebodsys', $debug, $recode_soc);
		/**
		 * CM_CMDECOD
		 */
		if (isset($event['cm_cmtrt']) && $event['cm_cmtrt'] != '') {
			$med = array();
			$med_result = db_query("SELECT DISTINCT drug_name FROM _whodrug_mp_us WHERE drug_name = '" . prep($event['cm_cmtrt']) . "'");
			if ($med_result) {
				$med = db_fetch_assoc($med_result);
				if ($event['cm_cmdecod'] == '' && isset($med['drug_name']) && $med['drug_name'] != '') {
					update_field_compare($subject_id, $project_id, $event_id, $med['drug_name'], $event['cm_cmdecod'], 'cm_cmdecod', $debug);
				}
			}
		} else {
			update_field_compare($subject_id, $project_id, $event_id, '', $event['cm_cmdecod'], 'cm_cmdecod', $debug);
		}
		/**
		 * cm_suppcm_mktstat
		 * PRESCRIPTION or OTC
		 */
		if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
			update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
		}
		/**
		 * IMMUNOSUPPRESSANTS
		 */
		if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
			$immun_flag = 'N';
			$immun_meds = array();
			$immun_meds_result = db_query("SELECT * FROM _target_meds_of_interest WHERE cm_cmcat != 'steroid' AND cm_cmtrt = '{$event['cm_cmdecod']}'");
			if ($immun_meds_result) {
				while ($immun_meds_row = db_fetch_assoc($immun_meds_result)) {
					$immun_meds[] = $immun_meds_row['cm_cmtrt'];
				}
				db_free_result($immun_meds_result);
			}
			if (count($immun_meds) != 0) {
				$immun_flag = 'Y';
				if ($debug) {
					show_var($immun_meds);
				}
			}
			update_field_compare($subject_id, $project_id, $event_id, $immun_flag, $event['cm_suppcm_cmimmuno'], 'cm_suppcm_cmimmuno', $debug);
		}
		/**
		 * CM_SUPPCM_INDCOD
		 */
		/**
		 * re-code all nutritional support to nutritional supplement
		 */
		if ($event['cm_oth_cmindc'] == 'Nutritional support') {
			$event['cm_oth_cmindc'] = 'Nutritional supplement';
		}
		code_llt($project_id, $subject_id, $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_llt);
		/**
		 * CM_SUPPCM_INDCSYS
		 */
		code_bodsys($project_id, $subject_id, $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
		/**
		 * CM_SUPPCM_ATCNAME, CM_SUPPCM_ATC2NAME
		 */
		code_atc($project_id, $subject_id, $event_id, $event['cm_cmdecod'], $event['cm_suppcm_atcname'], $event['cm_suppcm_atc2name'], $debug, $recode_atc);
	}
}
$timer_stop = microtime(true);
$timer_time = number_format(($timer_stop - $timer_start), 2);
echo 'This page loaded in ', $timer_time / 60, ' minutes';