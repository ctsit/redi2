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
Kint::enabled($debug);
global $Proj;
$baseline_event_id = $Proj->firstEventId;
$count = 0;
/**
 * WORKING SET OF STUDY_IDs
 */
$fields = array('dm_rfstdtc');
$sim_fields = array('sim_cmstdtc');
$sof_fields = array('sof_cmstdtc');
$rbv_fields = array('rib_cmstdtc');
$hcv_fields = array('hcv_lbdtc', 'hcv_lbstresn', 'hcv_supplb_hcvdtct');
$table_csv = "";
$data = REDCap::getData('array', $subjects, $fields, $Proj->firstEventId);
foreach ($data AS $subject_id => $subject) {
	$sim = false;
	$sof = false;
	$has_sim = false;
	$has_sof = false;
	$has_rbv = false;
	$regimen = '';
	foreach ($subject AS $event_id => $event) {
		if ($event['dm_rfstdtc'] != '') {
			$sim_data = REDCap::getData('array', $subject_id, $sim_fields);
			foreach ($sim_data AS $sim_subject) {
				foreach ($sim_subject AS $sim_event) {
					if ($sim_event['sim_cmstdtc'] != '') {
						$sim = true;
						if (!$has_sim) {
							$regimen = 'SIM';
							$has_sim = true;
						}
					}
				}
			}
			$sof_data = REDCap::getData('array', $subject_id, $sof_fields);
			foreach ($sof_data AS $sof_subject) {
				foreach ($sof_subject AS $sof_event) {
					if ($sof_event['sof_cmstdtc'] != '') {
						$sof = true;
						if (!$has_sof) {
							$regimen = $regimen . ' SOF';
							$has_sof = true;
						}
					}
				}
			}
			$rbv_data = REDCap::getData('array', $subject_id, $rbv_fields);
			foreach ($rbv_data AS $rbv_subject) {
				foreach ($rbv_subject AS $rbv_event) {
					if ($rbv_event['rib_cmstdtc'] != '') {
						if (!$has_rbv) {
							$regimen = $regimen . ' RBV';
							$has_rbv = true;
						}
					}
				}
			}
			if ($sim && $sof) {
				$wk4_start_obj = new DateTime($event['dm_rfstdtc']);
				$wk4_start_obj->add(new DateInterval('P20D'));
				$wk4_start_date = $wk4_start_obj->format('Y-m-d');
				$wk4_end_obj = new DateTime($event['dm_rfstdtc']);
				$wk4_end_obj->add(new DateInterval('P36D'));
				$wk4_end_date = $wk4_end_obj->format('Y-m-d');
				$hcv_data = REDCap::getData('array', $subject_id, $hcv_fields);
				foreach ($hcv_data AS $hcv_subject) {
					foreach ($hcv_subject AS $hcv_event) {
						if (($wk4_start_date < $hcv_event['hcv_lbdtc'] && $hcv_event['hcv_lbdtc'] < $wk4_end_date) && ($hcv_event['hcv_lbstresn'] != '' || $hcv_event['hcv_supplb_hcvdtct'] != '')) {
							$data_row = array();
							if ($debug) {
								show_var($subject_id, 'SUBJECT', 'blue');
								show_var($regimen, 'REGIMEN');
								show_var($event['dm_rfstdtc'], 'START DATE');
								show_var($wk4_start_date, '4WK START');
								show_var($hcv_event['hcv_lbdtc'], 'HCVRNA DATE');
								show_var($wk4_end_date, '4WK END');
								show_var($hcv_event['hcv_lbstresn'], 'QUANT');
								show_var($hcv_event['hcv_supplb_hcvdtct'], 'DETECT');
							}
							$data_row['subjid'] = $subject_id;
							$data_row['regimen'] = $regimen;
							$data_row['tx start'] = $event['dm_rfstdtc'];
							$data_row['wk4 low'] = $wk4_start_date;
							$data_row['hcvrna date'] = $hcv_event['hcv_lbdtc'];
							$data_row['wk4 hi'] = $wk4_end_date;
							if ($hcv_event['hcv_lbstresn'] != '') {
								$data_row['hcvrna'] = $hcv_event['hcv_lbstresn'];
							} else {
								$data_row['hcvrna'] = $hcv_event['hcv_supplb_hcvdtct'];
							}
							$count++;
							$row_csv = implode(',', $data_row) . "\n";
							$table_csv .= $row_csv;
						}
					}
				}
			}
		}
	}
}
show_var($count);
$headers = implode(',', array_keys($data_row)) . "\n";
	create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, "SIM_SOF_HCVRNA_WK4", $debug);