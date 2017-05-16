<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 2/2/16
 * Time: 4:39 PM
 */
/**
 * constants for labeling field values
 */
$ie_criteria_labels = array('INCL01' => 'Subject unable to provide Informed Consent', 'INCL02' => 'Subject less than 18 years old', 'INCL03' => 'Subject has not started HCV treatment regimen', 'EXCL01' => 'Subject is participating in another HCV treatment trial');
$tx_fragment_labels = array('ifn' => 'interferon', 'rib' => 'ribavirin', 'rbv' => 'ribavirin', 'tvr' => 'telaprevir', 'boc' => 'boceprevir', 'sim' => 'simeprevir', 'sof' => 'sofosbuvir', 'dcv' => 'daclatasvir', 'hvn' => 'harvoni', 'vpk' => 'ombitasvir', 'dbv' => 'dasabuvir', 'zep' => 'zepatier');
$tx_prefixes = array('ifn', 'rib', 'tvr', 'boc', 'sim', 'sof', 'dcv', 'hvn', 'vpk', 'dbv', 'zep');
$tx_prefixes_bms = array('rbv', 'sof', 'dcv');
$regimen_fields = array('reg_suppcm_regimen', 'ifn_cmstdtc', 'rib_cmstdtc', 'rbv_cmstdtc', 'boc_cmstdtc', 'tvr_cmstdtc', 'sim_cmstdtc', 'sof_cmstdtc', 'sof_exstdtc', 'dcv_exstdtc', 'dcv_cmstdtc', 'hvn_cmstdtc', 'vpk_cmstdtc', 'dbv_cmstdtc', 'zep_cmstdtc');
$intended_regimens = array(
	'HVN' => array('HVN' => 'Harvoni'),
	'HVN_RBV' => array('HVN/RBV' => 'Harvoni/Ribavirin'),
	'VPK' => array('VPK' => 'Viekira Pak'),
	'VPK_RBV' => array('VPK/RBV' => 'Viekira Pak/Ribavirin'),
	'TCN' => array('TCN' => 'Technivie'),
	'TCN_RBV' => array('TCN/RBV' => 'Technivie/Ribavirin'),
	'ZEP' => 'Zepatier',
	'ZEP_RBV' => 'Zepatier/Ribavirin',
	'SMV_SOF' => array('SOF/SMV' => 'Sofosbuvir/Simeprevir'),
	'RBV_SMV_SOF' => array('SOF/SMV/RBV' => 'Sofosbuvir/Simeprevir/Ribavirin'),
	'RBV_SOF' => array('SOF/RBV' => 'Sofosbuvir/Ribavirin'),
	'IFN_RBV_SOF' => array('SOF/PEG/RBV' => 'Sofosbuvir/Interferon/Ribavirin'),
	'SOF_RBV_DCV' => array('SOF/DCV/RBV' => 'Sofosbuvir/Daclatasvir/Ribavirin'),
	'DCV_SOF' => array('SOF/DCV' => 'Sofosbuvir/Daclatasvir'),
	'SIM_PEG_RBV' => array('SMV/PEG/RBV' => 'Simeprevir/Interferon/Ribavirin'),
	'IFN_RBV_TPV' => array('PEG/RBV/TPV' => 'Interferon/Ribavirin/Telaprevir'),
	'TPV_SOF' => array('SOF/TPV' => 'Sofosbuvir/Telaprevir'),
	'IFN_RBV' => array('PEG/RBV' => 'Interferon/Ribavirin'),
	'UNKNOWN' => array('UNK' => 'Unknown'),
	'OTHER' => array('OTHER' => 'Other')
);
$tx_to_arm = array(
	'SOF' => array('1' => array('SOF' => 'Sofosbuvir')),
	'SIM' => array('2' => array('SMV' => 'Simeprevir')),
	'IFN' => array('3' => array('PEG' => 'Interferon')),
	'DCV' => array('4' => array('DCV' => 'Daclatasvir')),
	'HVN' => array('5' => array('HVN' => 'Harvoni')),
	'VPK' => array('6' => array('VPK' => 'Viekira')),
	'DBV' => array('7' => array('DBV' => 'Dasabuvir')),
	'ZEP' => array('8' => array('ZEP' => 'Zepatier')),
	'RIB' => array('9' => array('RBV' => 'Ribavirin')),
	'RBV' => array('10' => array('RBV' => 'Ribavirin')),
	'BOC' => array('11' => array('BOC' => 'Boceprevir')),
	'TPV' => array('12' => array('TPV' => 'Telaprevir'))
);
$no_conversion = array();
$counts_conversion = array('10^3/L' => ' / 1000000', 'IU/L' => ' * 1000', 'cells/uL' => ' / 1000', 'cell/mm3' => ' / 1000', 'cells/mm3' => ' / 1000', '10^6/uL' => ' / 1000', '10^9/uL' => ' / 1000000', '10^3/mL' => ' / 1000', 'log IU/mL' => 'exp');
$gdl_conversion = array('mg/dL' => ' * 1000', 'g/L' => ' / 10', 'mg/L' => ' / 10000', 'g/mL' => ' / 100');
$iul_conversion = array('IU/mL' => ' / 1000');
$bili_conversion = array('g/dL' => ' / 1000', 'umol/L' => ' / 17.1', 'mcmol/L' => ' / 17.1', 'mg/L' => ' / 10');
$creat_conversion = array('g/dL' => ' / 1000', 'mg/L' => ' / 10', 'umol/L' => ' / 88.4', 'mcmol/L' => ' / 88.4', 'mmol/L' => ' / .0884');
$gluc_conversion = array('g/dL' => ' / 1000', 'mg/L' => ' / 10', 'mmol/L' => ' * 18.01801801801802');
$labs_array = array(
	'wbc' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'neut' => array('units' => '%', 'conversion' => $no_conversion),
	'anc' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'lymce' => array('units' => '%', 'conversion' => $no_conversion),
	'lym' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'plat' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
	'hemo' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
	'alt' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
	'ast' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
	'alp' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
	'tbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
	'dbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
	'alb' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
	'creat' => array('units' => 'mg/dL', 'conversion' => $creat_conversion),
	'gluc' => array('units' => 'mg/dL', 'conversion' => $gluc_conversion),
	'k' => array('units' => 'mmol/L', 'conversion' => $no_conversion),
	'sodium' => array('units' => 'mmol/L', 'conversion' => $no_conversion),
	'inr' => array('units' => '', 'conversion' => $no_conversion),
	'hcv' => array('units' => 'IU/mL', 'conversion' => $counts_conversion)
);
/**
 * VARS
 */
$dm_array = array('dm_rfstdtc', 'dm_brthyr', 'dm_brthdtc', 'dm_race', 'dm_sex', 'age_suppvs_age', 'dis_suppfa_txendt', 'dis_dsstdy', 'hcv_suppfa_svr12dt', 'hcv_suppfa_svr24dt', 'eot_dsterm', 'dm_actarm', 'dm_actarmcd');
$tx_array = array('ifn_cmstdtc', 'rib_cmstdtc', 'tvr_cmstdtc', 'sof_cmstdtc', 'sim_cmstdtc', 'boc_cmstdtc', 'dcv_cmstdtc', 'hvn_cmstdtc', 'vpk_cmstdtc', 'dbv_cmstdtc', 'zep_cmstdtc', 'hvn_exstdtc', 'vpk_exstdtc', 'dbv_exstdtc', 'zep_exstdtc', 'rbv_exstdtc');
$bmi_array = array('height_vsorresu', 'height_vsorres', 'height_suppvs_htcm', 'weight_vsorresu', 'weight_vsorres', 'weight_suppvs_wtkg', 'bmi_suppvs_bmi');
$daa_array = array('tvr_cmstdtc', 'sof_cmstdtc', 'sim_cmstdtc', 'boc_cmstdtc', 'dcv_cmstdtc', 'hvn_cmstdtc', 'vpk_cmstdtc', 'dbv_cmstdtc', 'zep_cmstdtc', 'hvn_exstdtc', 'vpk_exstdtc', 'dbv_exstdtc', 'zep_exstdtc');
$plat140_fields = array('plat_lbstresn', 'cbc_lbdtc', 'plat_im_lborres', 'cbc_im_lbdtc', 'plt_suppfa_faorres');
$cirr_fields = array('livbp_mhoccur', 'livbp_facat', 'livbp_faorres', 'fib_lborres', 'fibscn_lborres', 'fib_lbtest', 'asc_mhoccur', 'pht_faorres', 'egd_faorres', 'plt_suppfa_faorres', 'cirr_suppfa_cirrstat', 'cirr_suppfa_cirrovrd');
$endt_fields = array('ifn_cmendtc', 'ifn_suppcm_cmtrtout', 'rib_cmendtc', 'rib_suppcm_cmtrtout', 'boc_cmendtc', 'boc_suppcm_cmtrtout', 'tvr_cmendtc', 'tvr_suppcm_cmtrtout', 'sim_cmendtc', 'sim_suppcm_cmtrtout', 'sof_cmendtc', 'sof_suppcm_cmtrtout', 'dcv_cmendtc', 'dcv_suppcm_cmtrtout', 'hvn_cmendtc', 'hvn_suppcm_cmtrtout', 'vpk_cmendtc', 'vpk_suppcm_cmtrtout', 'dbv_cmendtc', 'dbv_suppcm_cmtrtout', 'zep_cmendtc', 'zep_suppcm_cmtrtout', 'hvn_exendtc', 'hvn_suppex_extrtout', 'vpk_exendtc', 'vpk_suppex_extrtout', 'dbv_exendtc', 'dbv_suppex_extrtout', 'zep_exendtc', 'zep_suppex_extrtout', 'rbv_exendtc', 'rbv_suppex_extrtout');
$hcv_fields = array('hcv_lbdtc', 'hcv_im_lbdtc');
$crcl_fields = array('chem_lbdtc', 'creat_lbstresn', 'crcl_lborres', 'crcl_lbblfl');
$crcl_im_fields = array('chem_im_lbdtc', 'creat_im_lbstresn', 'crcl_im_lborres', 'crcl_im_lbblfl');
$egfr_fields = array('chem_lbdtc', 'egfr_lborres', 'creat_lbstresn', 'egfr_lbblfl');
$egfr_im_fields = array('egfr_im_lborres', 'egfr_im_lbblfl', 'chem_im_lbdtc', 'creat_im_lbstresn', 'creat_im_nxtrust');

/**
 * add all these constants to $GLOBALS array
 */
$GLOBALS['ie_criteria_labels'] = $ie_criteria_labels;
$GLOBALS['tx_fragment_labels'] = $tx_fragment_labels;
$GLOBALS['tx_prefixes'] = $tx_prefixes;
$GLOBALS['tx_prefixes_bms'] = $tx_prefixes_bms;
$GLOBALS['regimen_fields'] = $regimen_fields;
$GLOBALS['intended_regimens'] = $intended_regimens;
$GLOBALS['tx_to_arm'] = $tx_to_arm;
$GLOBALS['no_conversion'] = $no_conversion;
$GLOBALS['counts_conversion'] = $counts_conversion;
$GLOBALS['gdl_conversion'] = $gdl_conversion;
$GLOBALS['iul_conversion'] = $iul_conversion;
$GLOBALS['bili_conversion'] = $bili_conversion;
$GLOBALS['creat_conversion'] = $creat_conversion;
$GLOBALS['gluc_conversion'] = $gluc_conversion;
$GLOBALS['labs_array'] = $labs_array;
$GLOBALS['dm_array'] = $dm_array;
$GLOBALS['tx_array'] = $tx_array;
$GLOBALS['bmi_array'] = $bmi_array;
$GLOBALS['daa_array'] = $daa_array;
$GLOBALS['plat140_fields'] = $plat140_fields;
$GLOBALS['cirr_fields'] = $cirr_fields;
$GLOBALS['endt_fields'] = $endt_fields;
$GLOBALS['hcv_fields'] = $hcv_fields;
$GLOBALS['crcl_fields'] = $crcl_fields;
$GLOBALS['crcl_im_fields'] = $crcl_im_fields;
$GLOBALS['egfr_fields'] = $egfr_fields;
$GLOBALS['egfr_im_fields'] = $egfr_im_fields;