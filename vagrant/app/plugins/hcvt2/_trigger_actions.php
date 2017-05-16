<?php
define("NOAUTH", true);
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
$timer_start = microtime(true);
$query_string = "";
$debug = false;
/**
 * recoding
 */
$recode_llt = true;
$recode_pt = true;
$recode_soc = true;
$recode_atc = false;
$recode_cm = true;
/**
 * Don't do anything unless the $_POST has data in it
 */
if ($_POST) {
	/**
	 * initialize variables
	 * $record, $instrument, $redcap_event_name and $redcap_data_access_group will be read from the $_POST
	 */
	$record = '';
	$redcap_event_name = '';
	$instrument = '';
	$redcap_data_access_group = '';
	$kv = array();
	foreach ($_POST as $key => $value) {
		$kv[] = "$key=$value";
		$$key = $value;
	}
	$query_string = join("&", $kv);
	if ($debug) {
		//error_log("POST: " . $query_string);
	}
	$_GET['pid'] = $_POST['project_id'];
	/**
	 * includes
	 */
	$base_path = dirname(dirname(dirname(__FILE__)));
	require_once $base_path . "/redcap_connect.php";
	require_once $base_path . '/plugins/includes/functions.php';
	require_once APP_PATH_DOCROOT . '/Config/init_project.php';
	/**
	 * restricted use
	 */
	$allowed_pids = array('26');
	REDCap::allowProjects($allowed_pids);
	if (!isset($project_id)) {
		$project_id = $_POST['project_id'];
	}
	/**
	 * This script should only be run by the Data Entry Trigger process, so let's set a USERID for logging
	 */
	if (!defined("USERID")) {
		define("USERID", '[DET]');
	}
	/**
	 * project metadata
	 */
	$project = new Project();
	/**
	 * is this form locked?
	 * If the form is locked, the save that triggered this script represents the
	 * action of locking the form, not a change of data within the form
	 */
	$form_is_locked = false;
	$event_id = $project->getEventIdUsingUniqueEventName($redcap_event_name);
	$locked_result = db_query("SELECT ld_id FROM redcap_locking_data WHERE project_id = '$project_id' AND record = '$record' AND event_id = '$event_id' AND form_name = '$instrument'");
	if ($locked_result) {
		$locked = db_fetch_assoc($locked_result);
		if (isset($locked['ld_id'])) {
			$form_is_locked = true;
		}
	}
	/**
	 * perform different actions depending upon which form ($instrument) was submitted
	 */
	switch ($instrument) {
		case 'demographics':
			/**
			 * SET Data Access Group based upon dm_usubjid prefix
			 */
			$debug = false;
			$fields = array('dm_usubjid');
			$data = REDCap::getData('array', $record, $fields);
			foreach ($data AS $subject) {
				foreach ($subject AS $event_id => $event) {
					if ($event['dm_usubjid'] != '') {
						/**
						 * find which DAG this subject belongs to
						 */
						$site_prefix = substr($event['dm_usubjid'], 0, 3) . '%';
						$dag_query = "SELECT group_id, group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_name LIKE '$site_prefix'";
						$dag_result = db_query($dag_query);
						if ($dag_result) {
							$dag = db_fetch_assoc($dag_result);
							if (isset($dag['group_id'])) {
								/**
								 * For each event in project for this subject, determine if this subject_id has been added to its appropriate DAG. If it hasn't, make it so.
								 * First, we need a list of events for which this subject has data
								 */
								$subject_events_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND field_name = '" . $instrument . "_complete'";
								$subject_events_result = db_query($subject_events_query);
								if ($subject_events_result) {
									while ($subject_events_row = db_fetch_assoc($subject_events_result)) {
										if (isset($subject_events_row['event_id'])) {
											$_GET['event_id'] = $subject_events_row['event_id']; // for logging
											/**
											 * The subject has data in this event_id
											 * does the subject have corresponding DAG assignment?
											 */
											$has_event_data_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND event_id = '" . $subject_events_row['event_id'] . "' AND field_name = '__GROUPID__'";
											$has_event_data_result = db_query($has_event_data_query);
											if ($has_event_data_result) {
												$has_event_data = db_fetch_assoc($has_event_data_result);
												if (!isset($has_event_data['event_id'])) {
													/**
													 * Subject does not have a matching DAG assignment for this data
													 * construct proper matching __GROUPID__ record and insert
													 */
													$insert_dag_query = "INSERT INTO redcap_data SET record = '$record', event_id = '" . $subject_events_row['event_id'] . "', value = '" . $dag['group_id'] . "', project_id = '$project_id', field_name = '__GROUPID__'";
													if (!$debug) {
														if (db_query($insert_dag_query)) {
															target_log_event($insert_dag_query, 'redcap_data', 'insert', $record, $dag['group_name'], 'Assign record to Data Access Group (' . $dag['group_name'] . ')');
															show_var($insert_dag_query, '', 'green');
														} else {
															error_log("SQL INSERT FAILED: " . db_error() . "\n");
															echo db_error() . "\n";
														}
													} else {
														show_var($insert_dag_query);
														error_log('(TESTING) NOTICE: ' . $insert_dag_query);
													}
												}
												db_free_result($has_event_data_result);
											}
										}
									}
									db_free_result($subject_events_result);
								}
							}
							db_free_result($dag_result);
						}
					}
				}
			}
			break;
		/**
		 * SITE SOURCE UPLOAD FORM
		 * ACTION: when a site uploads new source, record it for later retrieval by send_siteupload_digest.php
		 */
		case 'site_source_upload_form':
			$today = date('Y-m-d');
			if (db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '$redcap_data_access_group', action_date = '$today'")) {
				/*error_log("NOTICE: Site $redcap_data_access_group uploaded source to record $record in event $redcap_event_name");*/
			} else {
				error_log("ERROR: INSERT failed for target_email_actions");
			}
			break;
		/**
		 * SOURCE UPLOAD FORM
		 * ACTION: when a foreign abstraction site uploads new source, record it for later retrieval by send_siteupload_digest.php
		 */
		case 'source_upload_form':
			if (!$form_is_locked) {
				if (substr($redcap_data_access_group, 0, 3) >= '300') {
					$today = date('Y-m-d');
					if (db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '$redcap_data_access_group', action_date = '$today'")) {
						error_log("NOTICE: Site $redcap_data_access_group uploaded source to record $record in event $redcap_event_name");
					} else {
						error_log("ERROR: INSERT failed for target_email_actions");
					}
				}
			}
			break;
		/**
		 * ADVERSE EVENTS
		 * ACTION: auto-code AE
		 */
		case 'adverse_events':
			$debug = false;
			/**
			 * AE_AEDECOD
			 */
			$fields = array("ae_aeterm", "ae_oth_aeterm", "ae_aemodify");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['ae_aeterm']), fix_case($event['ae_oth_aeterm']), $event['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event['ae_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['ae_aeterm']} - {$event['ae_oth_aeterm']}");
					}
				}
			}
			/**
			 * AE_AEDECOD
			 */
			$fields = array("ae_aemodify", "ae_aedecod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, fix_case($event['ae_aemodify']), $event['ae_aedecod'], 'ae_aedecod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEDECOD {$event['ae_aedecod']}: subject=$subject_id, event=$event_id for AE {$event['ae_aemodify']}");
					}
				}
			}
			/**
			 * AE_AEBODSYS
			 */
			$fields = array("ae_aedecod", "ae_aebodsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['ae_aedecod'], $event['ae_aebodsys'], 'ae_aebodsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event['ae_aedecod']}");
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Code AE) took $timer_time seconds");
			}
			break;
		/**
		 * MEDICAL HISTORY
		 * ACTION: auto-code MH
		 */
		case 'key_medical_history':
			$debug = false;
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$mh_prefixes = array('othpsy', 'othca');
			/**
			 * MH_MHMODIFY
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_oth_mhterm", $prefix . "_mhmodify");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_llt($project_id, $subject_id, $event_id, fix_case($event[$prefix . "_oth_mhterm"]), '', $event[$prefix . "_mhmodify"], $prefix . "_mhmodify", $debug, $recode_llt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHMODIFY {$event[$prefix . "_mhmodify"]}: subject=$subject_id, event=$event_id for MH {$event[$prefix . "_oth_mhterm"]}");
						}
					}
				}
			}
			/**
			 * PREFIX_MHDECOD
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhmodify", $prefix . "_mhdecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_mhmodify"], $event[$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHDECOD {$event[$prefix . '_mhdecod']}: subject=$subject_id, event=$event_id for MHMODIFY {$event[$prefix . '_mhmodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_mhBODSYS
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhdecod", $prefix . "_mhbodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_mhdecod"], $event[$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHBODSYS {$event[$prefix . "_mhbodsys"]}: subject=$subject_id, event=$event_id for MHDECOD {$event[$prefix . "_mhdecod"]}");
						}
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Code MH) took $timer_time seconds");
			}
			break;
		/**
		 * EOT
		 */
		case 'early_discontinuation_eot':
			$debug = false;
			/**
			 * EOT_AEDECOD
			 */
			$fields = array("eot_suppds_ncmpae", "eot_oth_suppds_ncmpae", "eot_aemodify", "eot_dsterm");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if ($event['eot_dsterm'] == 'ADVERSE_EVENT') {
						code_llt($project_id, $subject_id, $event_id, fix_case($event['eot_suppds_ncmpae']), fix_case($event['eot_oth_suppds_ncmpae']), $event['eot_aemodify'], 'eot_aemodify', $debug, $recode_llt);
						if ($debug) {
							error_log("INFO (TESTING EOT): Coded EOT_AEMODIFY {$event['eot_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['eot_suppds_ncmpae']} - {$event['eot_oth_suppds_ncmpae']}");
						}
						/**
						 * AE_AEDECOD
						 */
						$ptfields = array("eot_aemodify", "eot_aedecod");
						$ptdata = REDCap::getData('array', $record, $ptfields, $redcap_event_name);
						foreach ($ptdata AS $ptsubject_id => $ptsubject) {
							foreach ($ptsubject AS $ptevent_id => $ptevent) {
								code_pt($project_id, $subject_id, $ptevent_id, fix_case($ptevent['eot_aemodify']), $ptevent['eot_aedecod'], 'eot_aedecod', $debug, $recode_pt);
								if ($debug) {
									error_log("DEBUG: Coded EOT_AEDECOD {$ptevent['eot_aedecod']}: subject=$ptsubject_id, event=$ptevent_id for AEMODIFY {$ptevent['eot_aemodify']}");
								}
							}
						}
						/**
						 * EOT_AEBODSYS
						 */
						$soc_fields = array("eot_aedecod", "eot_aebodsys");
						$soc_data = REDCap::getData('array', $record, $soc_fields, $redcap_event_name);
						foreach ($soc_data AS $soc_subject_id => $soc_subject) {
							foreach ($soc_subject AS $soc_event_id => $soc_event) {
								code_bodsys($project_id, $soc_subject_id, $soc_event_id, $soc_event['eot_aedecod'], $soc_event['eot_aebodsys'], 'eot_aebodsys', $debug, $recode_soc);
								if ($debug) {
									error_log("DEBUG: Coded SOC: subject=$soc_subject_id, event=$soc_event_id for AE {$soc_event['eot_aedecod']}");
								}
							}
						}
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Code AE (EOT)) took $timer_time seconds");
			}
			break;
		/**
		 * TX stop AEs
		 */
		case 'interferon_administration':
		case 'ribavirin_administration':
		case 'telaprevir_administration':
		case 'boceprevir_administration':
		case 'simeprevir_administration':
		case 'sofosbuvir_administration':
		case 'daclatasvir_administration':
		case 'harvoni_administration':
		case 'ombitasvir_paritaprevir':
		case 'dasabuvir':
			$debug = false;
			$tx_prefix = array_search(substr($instrument, 0, strpos($instrument, '_')), $tx_fragment_labels);
			/**
			 * AE_AEMODIFY
			 */
			$fields = array($tx_prefix . '_suppcm_cmncmpae', $tx_prefix . '_oth_suppcm_cmncmpae', $tx_prefix . '_aemodify');
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event[$tx_prefix . '_suppcm_cmncmpae']), fix_case($event[$tx_prefix . '_oth_suppcm_cmncmpae']), $event[$tx_prefix . '_aemodify'], $tx_prefix . '_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event[$tx_prefix . '_aemodify']}: subject=$subject_id, event=$event_id for AE {$event[$tx_prefix . '_suppcm_cmncmpae']} - {$event[$tx_prefix . '_oth_suppcm_cmncmpae']}");
					}
				}
			}
			/**
			 * AE_AEDECOD
			 */
			$fields = array($tx_prefix . '_aemodify', $tx_prefix . "_aedecod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, fix_case($event[$tx_prefix . '_aemodify']), $event[$tx_prefix . '_aedecod'], $tx_prefix . '_aedecod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEDECOD {$event[$tx_prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AE {$event[$tx_prefix . '_aemodify']}");
					}
				}
			}
			/**
			 * AE_AEBODSYS
			 */
			$fields = array($tx_prefix . '_aedecod', $tx_prefix . '_aebodsys');
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event[$tx_prefix . '_aedecod'], $event[$tx_prefix . '_aebodsys'], $tx_prefix . '_aebodsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event[$tx_prefix . '_aedecod']}");
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Code TX STOP AE) took $timer_time seconds");
			}
			break;
		/**
		 * CONMEDS
		 * ACTION: auto-code CONMEDS
		 */
		case 'conmeds':
			$debug = false;
			/**
			 * CM_CMDECOD
			 */
			$fields = array("cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if (isset($event['cm_cmtrt']) && $event['cm_cmtrt'] != '') {
						$med = array();
						$med_result = db_query("SELECT DISTINCT drug_name FROM _whodrug_mp_us WHERE drug_name = '" . prep($event['cm_cmtrt']) . "'");
						if ($med_result) {
							$med = db_fetch_assoc($med_result);
							if (isset($med['drug_name']) && $med['drug_name'] != '') {
								update_field_compare($subject_id, $project_id, $event_id, $med['drug_name'], $event['cm_cmdecod'], 'cm_cmdecod', $debug);
							}
						}
						if ($debug) {
							error_log("DEBUG: Coded CONMED: subject=$subject_id, event=$event_id for CMTRT {$event['cm_cmtrt']}");
						}
					} else {
						update_field_compare($subject_id, $project_id, $event_id, '', $event['cm_cmdecod'], 'cm_cmdecod', $debug);
					}
				}
			}
			/**
			 * cm_suppcm_mktstat
			 * PRESCRIPTION or OTC
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_mktstat");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data as $subject_id => $subject) {
				foreach ($subject as $event_id => $event) {
					if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
						if ($debug) {
							error_log("DEBUG: $subject_id Marketing Status = " . get_conmed_mktg_status($event['cm_cmdecod']));
						}
						update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcmodf");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					/**
					 * re-code all nutritional support to nutritional supplement
					 */
					if ($event['cm_oth_cmindc'] == 'Nutritional support') {
						$event['cm_oth_cmindc'] = 'Nutritional supplement';
					}
					code_llt($project_id, $subject_id, $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded INDC LLT: {} subject=$subject_id, event=$event_id for INDICATION {$event['cm_cmindc']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_suppcm_indcmodf", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, $event['cm_suppcm_indcmodf'], $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded INDC PT: subject=$subject_id, event=$event_id for INDICATION {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCSYS
			 */
			$fields = array("cm_suppcm_indcod", "cm_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_ATCNAME
			 * CM_SUPPCM_ATC2NAME
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_atcname", "cm_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc($project_id, $subject_id, $event_id, $event['cm_cmdecod'], $event['cm_suppcm_atcname'], $event['cm_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded ATCs: subject=$subject_id, event=$event_id for CONMED {$event['cm_cmdecod']}");
					}
				}
			}
			/**
			 * immunosuppressive?
			 */
			$fields = array('cm_cmdecod', 'cm_suppcm_cmimmuno');
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
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
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Code CONMEDS) took $timer_time seconds");
			}
			break;
		case 'transfusions':
			$debug = false;
			/**
			 * XFSN_CMDECOD
			 */
			$fields = array("xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if (isset($event['xfsn_cmtrt']) && $event['xfsn_cmtrt'] != '') {
						$med = array();
						$med_result = db_query("SELECT DISTINCT drug_coded FROM _target_xfsn_coding WHERE drug_name = '" . prep($event['xfsn_cmtrt']) . "'");
						if ($med_result) {
							$med = db_fetch_assoc($med_result);
							if (isset($med['drug_coded']) && $med['drug_coded'] != '') {
								update_field_compare($subject_id, $project_id, $event_id, $med['drug_coded'], $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
							}
						}
						if ($debug) {
							error_log("DEBUG: Coded Transfusion: subject=$subject_id, event=$event_id for CMTRT {$event['xfsn_cmtrt']}");
						}
					} else {
						update_field_compare($subject_id, $project_id, $event_id, '', $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCOD
			 */
			$fields = array("xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['xfsn_cmindc']), fix_case($event['xfsn_oth_cmindc']), $event['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDC: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCSYS
			 */
			$fields = array("xfsn_suppcm_indcod", "xfsn_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['xfsn_suppcm_indcod'], $event['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['xfsn_suppcm_indcod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_ATCNAME
			 * XFSN_SUPPCM_ATC2NAME
			 */
			$fields = array("xfsn_cmdecod", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc_xfsn($project_id, $subject_id, $event_id, $event['xfsn_cmdecod'], $event['xfsn_suppcm_atcname'], $event['xfsn_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN ATCs: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Code TRANSFUSION) took $timer_time seconds");
			}
			break;

		case 'ae_coding':
			$debug = false;
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$ae_prefixes = array('ae');
			/**
			 * AE_AEMODIFY
			 */
			$fields = array("ae_aeterm", "ae_oth_aeterm", "ae_aemodify");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['ae_aeterm']), fix_case($event['ae_oth_aeterm']), $event['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event['ae_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['ae_aeterm']} - {$event['ae_oth_aeterm']}");
					}
				}
			}
			/**
			 * PREFIX_AEDECOD
			 * uses $tx_prefixes preset array
			 */
			foreach ($ae_prefixes AS $prefix) {
				$fields = array($prefix . "_aemodify", $prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_aemodify"], $event[$prefix . "_aedecod"], $prefix . "_aedecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_AEDECOD {$event[$prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event[$prefix . '_aemodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_AEBODSYS
			 * uses $tx_prefixes preset array
			 */
			foreach ($ae_prefixes AS $prefix) {
				$fields = array($prefix . "_aedecod", $prefix . "_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_aedecod"], $event[$prefix . "_aebodsys"], $prefix . "_aebodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event[$prefix . "_aedecod"]}");
						}
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Coding) took $timer_time seconds");
			}
			break;

		case 'mh_coding':
			$debug = false;
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$mh_prefixes = array('othpsy', 'othca');
			/**
			 * MH_MHMODIFY
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_oth_mhterm", $prefix . "_mhmodify");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_llt($project_id, $subject_id, $event_id, fix_case($event[$prefix . "_oth_mhterm"]), '', $event[$prefix . "_mhmodify"], $prefix . "_mhmodify", $debug, $recode_llt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHMODIFY {$event[$prefix . "_mhmodify"]}: subject=$subject_id, event=$event_id for MH {$event[$prefix . "_oth_mhterm"]}");
						}
					}
				}
			}
			/**
			 * PREFIX_MHDECOD
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhmodify", $prefix . "_mhdecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_mhmodify"], $event[$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHDECOD {$event[$prefix . '_mhdecod']}: subject=$subject_id, event=$event_id for MHMODIFY {$event[$prefix . '_mhmodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_mhBODSYS
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhdecod", $prefix . "_mhbodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_mhdecod"], $event[$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded  " . strtoupper($prefix) . "_MHBODSYS {$event[$prefix . "_mhbodsys"]}: subject=$subject_id, event=$event_id for MHDECOD {$event[$prefix . "_mhdecod"]}");
						}
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Coding) took $timer_time seconds");
			}
			break;

		case 'cm_coding':
			$debug = false;
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$recode_atc = false;
			$recode_cm = true;
			/**
			 * CM_CMDECOD
			 */
			$fields = array("cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_cm($project_id, $subject_id, $event_id, $event, $debug, $recode_cm);
					if ($debug) {
						error_log("DEBUG: Coded CONMED: subject=$subject_id, event=$event_id for CMTRT {$event['cm_cmtrt']}");
					}
				}
			}
			/**
			 * cm_suppcm_mktstat
			 * PRESCRIPTION or OTC
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_mktstat");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data as $subject_id => $subject) {
				foreach ($subject as $event_id => $event) {
					if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
						if ($debug) {
							error_log("DEBUG: $subject_id Marketing Status = " . get_conmed_mktg_status($event['cm_cmdecod']));
						}
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcmodf");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					/**
					 * re-code all nutritional support to nutritional supplement
					 */
					if ($event['cm_oth_cmindc'] == 'Nutritional support') {
						$event['cm_oth_cmindc'] = 'Nutritional supplement';
					}
					code_llt($project_id, $subject_id, $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded INDC LLT: {} subject=$subject_id, event=$event_id for INDICATION {$event['cm_cmindc']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_suppcm_indcmodf", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, $event['cm_suppcm_indcmodf'], $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded INDC PT: subject=$subject_id, event=$event_id for INDICATION {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCSYS
			 */
			$fields = array("cm_suppcm_indcod", "cm_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_ATCNAME
			 * CM_SUPPCM_ATC2NAME
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_atcname", "cm_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc_soc($project_id, $subject_id, $event_id, $event['cm_cmdecod'], $event['cm_suppcm_atcname'], $event['cm_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded ATCs: subject=$subject_id, event=$event_id for CONMED {$event['cm_cmdecod']}");
					}
				}
			}
			/**
			 * XFSN_CMDECOD
			 */
			$fields = array("xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if (isset($event['xfsn_cmtrt']) && $event['xfsn_cmtrt'] != '') {
						$med = array();
						$med_result = db_query("SELECT DISTINCT drug_coded FROM _target_xfsn_coding WHERE drug_name = '" . prep($event['xfsn_cmtrt']) . "'");
						if ($med_result) {
							$med = db_fetch_assoc($med_result);
							if (isset($med['drug_coded']) && $med['drug_coded'] != '') {
								update_field_compare($subject_id, $project_id, $event_id, $med['drug_coded'], $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
							}
						}
						if ($debug) {
							error_log("DEBUG: Coded Transfusion: subject=$subject_id, event=$event_id for CMTRT {$event['xfsn_cmtrt']}");
						}
					} else {
						update_field_compare($subject_id, $project_id, $event_id, '', $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCOD
			 */
			$fields = array("xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['xfsn_cmindc']), fix_case($event['xfsn_oth_cmindc']), $event['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDC: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCSYS
			 */
			$fields = array("xfsn_suppcm_indcod", "xfsn_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['xfsn_suppcm_indcod'], $event['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['xfsn_suppcm_indcod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_ATCNAME
			 * XFSN_SUPPCM_ATC2NAME
			 */
			$fields = array("xfsn_cmdecod", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc_xfsn($project_id, $subject_id, $event_id, $event['xfsn_cmdecod'], $event['xfsn_suppcm_atcname'], $event['xfsn_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN ATCs: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Coding) took $timer_time seconds");
			}
			break;
			
		case 'ex_coding':
			$debug = false;
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$recode_atc = false;
			$recode_cm = true;
			$tx_prefixes[] = 'eot';
			/**
			 * PREFIX_AEDECOD
			 * uses $tx_prefixes preset array
			 */
			foreach ($tx_prefixes AS $prefix) {
				$fields = array($prefix . "_aemodify", $prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_aemodify"], $event[$prefix . "_aedecod"], $prefix . "_aedecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_AEDECOD {$event[$prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event[$prefix . '_aemodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_AEBODSYS
			 * uses $tx_prefixes preset array
			 */
			foreach ($tx_prefixes AS $prefix) {
				$fields = array($prefix . "_aedecod", $prefix . "_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
                        code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_aedecod"], $event[$prefix . "_aebodsys"], $prefix . "_aebodsys", $debug, $recode_soc);
                        if ($debug) {
                            error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event[$prefix . "_aedecod"]}");
                        }
					}
				}
			}
			break;
		case 'cbc':
		/*case 'inr':*/
		case 'hcv_rna_results':
			$debug = false;
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
		if ($debug) {
			error_log("DEBUG: This DET action (Standardize $instrument) took $timer_time seconds");
		}
			break;
		case 'chemistry':
			$debug = false;
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			/**
			 * Creatinine Clearance (Cockcroft-Gault Equation)
			 * IF([chem_lbdtc] = "", null, round(((140 - ([chem_lbdtc].substring(0,4) - [brthyr])) * [weight_suppvs_wtkg] * (IF([dm_sex] = "0", .85, 1)) / (72 * [creat_lbstresn])), 0))
			 */
			$chem_fields = array('chem_lbdtc', 'creat_lbstresn');
			$fields = array('dm_brthyr', 'weight_suppvs_wtkg', 'dm_sex');
			$data = REDCap::getData('array', $record, $fields, $project->firstEventId);
			foreach ($data as $vitals) {
				foreach ($vitals as $vital) {
					if ($vital['dm_brthyr'] != '' && $vital['weight_suppvs_wtkg'] != '' && $vital['dm_sex'] != '') {
						$chem_data = REDCap::getData('array', $record, $chem_fields, $redcap_event_name);
						foreach ($chem_data as $chem_values) {
							foreach ($chem_values as $chem_event => $values) {
								unset($creatinine_clearance);
								if ($values['chem_lbdtc'] != '' && $values['creat_lbstresn'] != '') {
									$sex = isset($vital['dm_sex']) ? $vital['dm_sex'] : 'F';
									$chem_age = (substr($values['chem_lbdtc'], 0, 4)) - $vital['dm_brthyr'];
									$sex_factor = $sex == 'F' ? .85 : 1;
									$creatinine_clearance = round(((140 - $chem_age) * $vital['weight_suppvs_wtkg'] * $sex_factor) / (72 * $values['creat_lbstresn']));
									update_field_compare($record, $project_id, $chem_event, $creatinine_clearance, get_single_field($record, $project_id, $chem_event, 'crcl_lborres', ''), 'crcl_lborres', $debug);
								}
							}
						}
					}
				}
			}
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Standardize $instrument) took $timer_time seconds");
			}
			break;
		/**
		 * all other forms do nothing
		 */
		default:
			break;
	}
}