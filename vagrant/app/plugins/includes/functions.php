<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 8/8/13
 * Time: 12:32 PM
 */
/**
 * timezone
 */
date_default_timezone_set('America/New_York');
/**
 * use our own logging
 */
require_once "logging.php";
/**
 * TARGET classes
 */
require_once $base_path . '/plugins/classes/kint/Kint.class.php';
require_once $base_path . '/plugins/classes/FieldSorter.php';
require_once $base_path . '/plugins/classes/Subject.php';
/**
 * for post-redcap-6 compatibility
 */
define("APP_PATH_DOCROOT_PARENT", dirname(dirname(dirname(__FILE__))) . DS);
define("OVERRIDES_ENABLED", TRUE); // Switch Overrides on and off
define("OVERRIDE_PATH", APP_PATH_DOCROOT_PARENT . "plugins/Overrides/"); // Provides a path to our overridden core files
define("PLUGIN_PATH", APP_PATH_WEBROOT_PARENT . "plugins/"); // Gives us a path to the Plugins directory
/**
 * for hcvt1 - deprecated
 */
if (defined(PROJECT_ID) && PROJECT_ID == '4') {
	include_once "hcvt1_constants.php";
}
/**
 * constants used throughout plugins
 */
require_once "constants.php";

foreach ($GLOBALS as $key => $val) {
	global $$key;
}

/**
 * @param $project_id
 * @param $field_name
 * @return string|null
 */
function get_field_label($field_name, $project_id)
{
	$field_label_result = db_query("SELECT element_label FROM redcap_metadata WHERE project_id = '$project_id' AND field_name = '$field_name'");
	if ($field_label_result) {
		$field_label = db_result($field_label_result, 0);
		db_free_result($field_label_result);
		return $field_label;
	} else {
		return null;
	}
}

/**
 * @param $item_count
 * @return string
 */
function row_style($item_count)
{
	if ($item_count & 1) {
		//odd
		return 'data_1';
	} else {
		//even
		return 'data_2';
	}
}

/**
 * @return array
 */
function non_auth_pages()
{
	/**
	 * only pages in the protected _cron directory are OK for AUTH=false
	 */
	$non_auth_pages = array();
	$base_path = dirname(dirname(dirname(__FILE__)));
	$cron_dir = $base_path . DS . "_cron";
	$cron_pages = scan_dirs($cron_dir);
	$server_docroot = str_replace("/", "\\", $_SERVER['DOCUMENT_ROOT']);
	if (count($cron_pages) > 0) {
		foreach ($cron_pages AS $cron_page) {
			$non_auth_pages[] = '/' . str_replace($server_docroot, '', $cron_page);
		}
	}
	return $non_auth_pages;
}

/**
 * @param $x number of days to translate to week number
 * @return int
 */
function which_week($x)
{
	$weeks = (round($x / 14)) * 2;
	return $weeks;
}

/**
 * @param int $x number of days to translate to week number
 * @param int $c number of days in period
 * @return int
 */
function which_week_old($x, $c = 7)
{
	$a = $x / $c;
	$b = (int)$a;
	if ($a == $b) {
//		 echo 'x is already an even multiple of c<br />';
		return $a;
	} elseif ($a == $b && !is_int($x / 2)) {
//		echo 'x is not an even multiple - shave one day<br />';
		return $a - 1;
	} else {
		if ($x >= 0) {
			$ceil = (($b + 1) * $c) / $c;
			return $ceil;
		} else {
			$ceil = (($b - 1) * $c) / $c;
			return $ceil;
		}
	}
}

/**
 * @param string $givendate
 * @param int $day
 * @param int $mth
 * @param int $yr
 * @return string
 */
function add_date($givendate, $day = 0, $mth = 0, $yr = 0)
{
	$cd = strtotime($givendate);
	$newdate = date('Y-m-d', mktime(date('h', $cd),
		date('i', $cd), date('s', $cd), date('m', $cd) + $mth,
		date('d', $cd) + $day, date('Y', $cd) + $yr));
	return $newdate;
}

/**
 * @param object|array|string $var
 * @param string $label
 * @param string $class
 */
function show_var($var, $label = '', $class = 'yellow')
{
	echo "<div class='$class'>$label<!--<pre>-->";
	echo var_dump($var);
	echo '<!--</pre>--></div>';
}

/**
 * @param $haystack
 * @return bool
 */
function find_wildcard($haystack)
{
	$pos = strpos($haystack, '%');
	if ($pos === false) {
		return false;
	} else {
		return true;
	}
}

/**
 * @param string $subjid
 * @param string $project_id
 * @param string $event_id
 * @param string $field_name
 * @param array $value_label_array
 * @return string
 */
function get_single_field($subjid, $project_id, $event_id = '', $field_name, $value_label_array = array())
{
	$single_field_query = "SELECT value
			FROM redcap_data
			WHERE record = '$subjid'
			AND project_id = '$project_id'
			AND field_name = '$field_name'";
	if ($event_id != '') {
		$single_field_query .= " AND event_id = '$event_id'";
	}
	$single_field_result = db_query($single_field_query);
	if ($single_field_result) {
		$single_field = db_fetch_assoc($single_field_result);
		db_free_result($single_field_result);
		if (isset($single_field['value']) && $single_field['value'] != '') {
			if (is_array($value_label_array)) {
				return $value_label_array[$single_field['value']];
			} else {
				return $single_field['value'];
			}
		} else {
			return null;
		}
	} else {
		return null;
	}
}

/**
 * @param $subjid
 * @param $project_id
 * @param string $event_id
 * @param $field_name
 * @return array|null
 */
function get_field_values($subjid, $project_id, $event_id = '', $field_name)
{
	$ret_val = array();
	$single_field_query = "SELECT value
			FROM redcap_data
			WHERE record = '$subjid'
			AND project_id = '$project_id'
			AND field_name = '$field_name'";
	if ($event_id != '') {
		$single_field_query .= " AND event_id = '$event_id'";
	}
	$single_field_result = db_query($single_field_query);
	if ($single_field_result) {
		while ($single_field = db_fetch_assoc($single_field_result)) {
			if (isset($single_field['value']) && $single_field['value'] != '') {
				$ret_val[] = $single_field['value'];
			} else {
				$ret_val[] = null;
			}
		}
		db_free_result($single_field_result);
	}
	return $ret_val;
}
/**
 * @param string $subjid
 * @param string $field_name
 * @param string $project_id
 * @param array $value_label_array
 * @return string
 */
function get_single_field_pending($subjid, $field_name, $project_id, $value_label_array = array())
{
	$single_field_query = "SELECT value
			FROM redcap_data
			WHERE record = '$subjid'
			AND project_id = '$project_id'
			AND field_name = '$field_name'";
	$single_field_result = db_query($single_field_query);
	if ($single_field_result) {
		$single_field = db_fetch_array($single_field_result, MYSQL_ASSOC);
		db_free_result($single_field_result);
		if (isset($single_field['value']) && $single_field['value'] != '') {
			if (is_array($value_label_array)) {
				return $value_label_array[$single_field['value']];
			} else {
				return $single_field['value'];
			}
		} else {
			return 'Data pending';
		}
	} else {
		return 'Data pending';
	}
}

/**
 * @param string $source_field
 * @param string $needle
 * @param string $haystack
 * @return string
 */
function get_corresponding_field_name($source_field, $needle = '%', $haystack)
{
	preg_match('/\d+/', $source_field, $field_index);
	return str_replace($needle, $field_index[0], $haystack);
}

/**
 * @param string $subjid
 * @param string $project_id
 * @param string $field_name
 * @param string $regexp
 * @param array $value_label_array
 * @return array
 */
function get_field_array_regexp($subjid, $project_id, $field_name, $regexp, $value_label_array = array())
{
	$return_array = array();
	$field_array_query = "SELECT field_name, value
	FROM redcap_data
	WHERE record = '$subjid'
	AND project_id = '$project_id'
	AND {$field_name} REGEXP \"{$regexp}\"";
	$field_array_result = db_query($field_array_query);
	if ($field_array_result) {
		while ($field_array = db_fetch_array($field_array_result, MYSQL_ASSOC)) {
			if (isset($field_array['value']) && $field_array['value'] != '') {
				if (is_array($value_label_array)) {
					$return_array[$field_array['field_name']] = $value_label_array[$field_array['value']];
				} else {
					$return_array[$field_array['field_name']] = $field_array['value'];
				}
			} else {
				$return_array[$field_array['field_name']] = null;
			}
		}
		db_free_result($field_array_result);
		return $return_array;
	} else {
		return null;
	}
}

/**
 * @param $subject_id string
 * @param $project_id string
 * @param $event_id string
 * @param $value string
 * @param $compare_value string
 * @param $field string
 * @param $debug boolean
 * @param $message string
 */
function update_field_compare($subject_id, $project_id, $event_id, $value, $compare_value, $field, $debug, $message = null)
{
	$update_message = 'Update record';
	$insert_message = 'Create record';
	$delete_message = 'Delete record';
	$compare_value = htmlspecialchars_decode($compare_value);
	$_GET['event_id'] = $event_id; // for logging
	if ((isset($compare_value) && $compare_value != '' && $value != '') && $value != $compare_value) {
		$update_query = "UPDATE redcap_data SET value = '" . prep($value) . "' WHERE record = '$subject_id' AND project_id = '$project_id' AND event_id = '$event_id' AND field_name = '$field' AND value = '$compare_value'";
		if (!$debug) {
			if (db_query($update_query)) {
				target_log_event($update_query, 'redcap_data', 'update', $subject_id, "$field = '$value'", $update_message, $message, $project_id, $event_id);
				//REDCap::logEvent('Update record', "$field = '$value'", $update_query, $subject_id, $event_id);
			} else {
				error_log("SQL UPDATE FAILED: " . db_error() . ': ' . $update_query);
				echo db_error() . "<br />" . $update_query;
			}
		} else {
			show_var($update_query);
			error_log("DEBUG: " . $update_query);
		}
	} elseif ((!isset($compare_value) || $compare_value == '') && $value != '') {
		$insert_query = "INSERT INTO redcap_data SET record = '$subject_id', project_id = '$project_id', event_id = '$event_id', value = '" . prep($value) . "', field_name = '$field'";
		if (!$debug) {
			if (db_query($insert_query)) {
				target_log_event($insert_query, 'redcap_data', 'insert', $subject_id, "$field = '$value'", $insert_message, $message, $project_id, $event_id);
				//REDCap::logEvent('Create record', "$field = '$value'", $insert_query, $subject_id, $event_id);
			} else {
				error_log("SQL INSERT FAILED: " . db_error() . ': ' . $insert_query);
				echo db_error() . "<br />" . $insert_query;
			}
		} else {
			show_var($insert_query);
			error_log("DEBUG: " . $insert_query);
		}
	} elseif ((isset($compare_value) && $compare_value != '') && $value == '') {
		$delete_query = "DELETE FROM redcap_data WHERE record = '$subject_id' AND project_id = '$project_id' AND event_id = '$event_id' AND field_name = '$field' AND value = '" . prep($compare_value) . "'";
		if (!$debug) {
			if (db_query($delete_query)) {
				target_log_event($delete_query, 'redcap_data', 'delete', $subject_id, "$field = '$compare_value'", $delete_message, $message, $project_id, $event_id);
				//REDCap::logEvent('Delete record', "$field = '$value'", $delete_query, $subject_id, $event_id);
			} else {
				error_log("SQL DELETE FAILED: " . db_error() . ': ' . $delete_query);
				echo db_error() . "<br />" . $delete_query;
			}
		} else {
			show_var($delete_query);
			error_log("DEBUG: " . $delete_query);
		}
	}
}

/**
 * @param $subject_id
 * @param $project_id
 * @param $event_id
 * @param $value
 * @param $compare_value
 * @param $field
 * @param $debug
 * @param null $message
 * @param null $userid
 */
function update_field_from_history($subject_id, $project_id, $event_id, $value, $compare_value, $field, $debug, $message = null, $userid = null)
{
	$update_message = 'Update record';
	$insert_message = 'Create record';
	$delete_message = 'Delete record';
	$compare_value = htmlspecialchars_decode($compare_value);
	$_GET['event_id'] = $event_id; // for logging
	if ((isset($compare_value) && $value != '') && $value != $compare_value) {
		$update_query = "UPDATE redcap_data SET value = '" . prep($value) . "' WHERE record = '$subject_id' AND project_id = '$project_id' AND event_id = '$event_id' AND field_name = '$field' AND value = '$compare_value'";
		if (!$debug) {
			if (db_query($update_query)) {
				target_proxy_log_event($update_query, 'redcap_data', 'update', $subject_id, "$field = '$value'", $update_message, $message, $userid);
				//REDCap::logEvent('Update record', "$field = '$value'", $update_query, $subject_id, $event_id);
			} else {
				error_log("SQL UPDATE FAILED: " . db_error() . ': ' . $update_query);
				echo db_error() . "<br />" . $update_query;
			}
		} else {
			show_var($update_query);
			error_log("DEBUG: " . $update_query);
		}
	} elseif ((!isset($compare_value) || $compare_value == '') && $value != '') {
		$insert_query = "INSERT INTO redcap_data SET record = '$subject_id', project_id = '$project_id', event_id = '$event_id', value = '" . prep($value) . "', field_name = '$field'";
		if (!$debug) {
			if (db_query($insert_query)) {
				target_proxy_log_event($insert_query, 'redcap_data', 'update', $subject_id, "$field = '$value'", $insert_message, $message, $userid);
				//REDCap::logEvent('Create record', "$field = '$value'", $insert_query, $subject_id, $event_id);
			} else {
				error_log("SQL INSERT FAILED: " . db_error() . ': ' . $insert_query);
				echo db_error() . "<br />" . $insert_query;
			}
		} else {
			show_var($insert_query);
			error_log("DEBUG: " . $insert_query);
		}
	} elseif ((isset($compare_value) && $compare_value != '') && $value == '') {
		$delete_query = "DELETE FROM redcap_data WHERE record = '$subject_id' AND project_id = '$project_id' AND event_id = '$event_id' AND field_name = '$field' AND value = '" . prep($compare_value) . "'";
		if (!$debug) {
			if (db_query($delete_query)) {
				target_proxy_log_event($delete_query, 'redcap_data', 'update', $subject_id, "$field = '$value'", $delete_message, $message, $userid);
				//REDCap::logEvent('Delete record', "$field = '$value'", $delete_query, $subject_id, $event_id);
			} else {
				error_log("SQL DELETE FAILED: " . db_error() . ': ' . $delete_query);
				echo db_error() . "<br />" . $delete_query;
			}
		} else {
			show_var($delete_query);
			error_log("DEBUG: " . $delete_query);
		}
	}
}

/**
 * @return string $outcome
 *
 * Use this version of the outcome engine in HCVT 2.0+
 * It takes into account all treatment for start/stop
 */
function get_outcome()
{
	global $started_tx, $stopped_tx, $post_tx_plus10w_scores, $last_hcvrna_bloq, $lost_to_followup, $tx_stopped_10_wks_ago, $hcv_fu_eligible;
	/**
	 * analyze post-$svr_class-th week outcomes
	 */
	if ($started_tx) { // Is the patient’s TX start date recorded
		if ($stopped_tx) { // Is the patient’s TX stop date recorded
			if (count($post_tx_plus10w_scores) > 0) { // Does the patient have HCV RNA result at 10 weeks or later post treatment
				if (get_end_of_array($post_tx_plus10w_scores) == '0') { // Is the last HCV RNA at 10 weeks or later post-treatment BLOQ?
					$outcome = 'SVR';
				} else {
					$outcome = get_failure();
				}
			} else {
				if ($last_hcvrna_bloq) { // Was last recorded HCV RNA BLOQ?
					if ($lost_to_followup || !$hcv_fu_eligible) { // Was the patient lost to follow-up?
						/*$outcome = get_failure();*/
						$outcome = 'LOST TO FOLLOWUP';
					} else { // not lost to followup
						if ($tx_stopped_10_wks_ago) {
							$outcome = 'QUERY HCVRNA';
						} else {
							$outcome = 'STATUS PENDING';
						}
					}
				} else {
					$outcome = get_failure();
				}
			}
		} else {
			$outcome = 'QUERY TX STOP';
		}
	} else {
		$outcome = 'QUERY TX START';
	}
	return $outcome;
}

/**
 * @return string
 */
function get_failure()
{
	global $on_tx_scores, $post_tx_plus10d_scores, $post_tx_scores, $eot_dsterm, $lost_to_followup, $hcv_fu_eligible;
	if (count($on_tx_scores) > 0 && in_array('0', $on_tx_scores)) { // Was BLOQ recorded at least once during treatment?
		if (get_end_of_array($on_tx_scores) == '1') { // After BLOQ and while still on TX, was LAST HCV RNA Quantified?
			$outcome = 'VIRAL BREAKTHROUGH';
		} else {
			$outcome = 'RELAPSE';
		}
	} else {
		if (count($post_tx_scores) > 0) { //Does the patient have ANY post-treatment HCV RNA?
			if (count($post_tx_plus10d_scores) > 0 && in_array('0', $post_tx_plus10d_scores)) { // Was BLOQ recorded at least once before EOT+10 days or no 10-day scores?
				if (get_end_of_array($on_tx_scores) == '1' || get_end_of_array($post_tx_plus10d_scores) == '1') { // While still on TX or EOT +10 days, was HCV RNA Quantified?
					$outcome = 'VIRAL BREAKTHROUGH';
				} else {
					if (count($on_tx_scores) > 0 || (count($on_tx_scores) == 0 && in_array('0', $post_tx_scores))) { // does subject have on-treatment HCVRNA, or no on-treatment HCVRNA with BLOQ after treatment
						$outcome = 'RELAPSE';
					} elseif (count($on_tx_scores) == 0 && !in_array('0', $post_tx_scores)) {
						$outcome = 'NON-RESPONDER';
					}
				}
			} else {
				$outcome = 'NON-RESPONDER';
			}
		} else {
			if (in_array($eot_dsterm, array('LACK_OF_EFFICACY'))) {
				$outcome = 'NON-RESPONDER';
			} else {
				if ($lost_to_followup || !$hcv_fu_eligible) {
					$outcome = 'LOST TO FOLLOWUP';
				} else {
					$outcome = 'QUERY HCVRNA';
				}
			}
		}
	}
	return $outcome;
}

/**
 * @param boolean $started_ifn
 * @param boolean $stopped_ifn
 * @param boolean $started_daa
 * @param boolean $has_10week_results
 * @param array $pt_hcvrna_after_scores
 * @param int $hcvrna_before_zero_count
 * @param array $hcvrna_before_scores
 * @param array $hcvrna_after_scores
 * @param boolean $last_hcvrna_bloq
 * @param boolean $lost_to_followup
 * @param boolean $ifn_stopped_10_wks_ago
 * @return string $outcome
 */

function outcome_engine($started_ifn, $stopped_ifn, $started_daa, $has_10week_results, $pt_hcvrna_after_scores, $hcvrna_before_zero_count, $hcvrna_before_scores, $hcvrna_after_scores, $last_hcvrna_bloq, $lost_to_followup, $ifn_stopped_10_wks_ago)
{
	/**
	 * analyze post-$svr_class-th week outcomes
	 */
	if ($started_ifn) { // Is the patient’s IFN start date recorded
		if ($stopped_ifn) { // Is the patient’s IFN stop date recorded
			if ($started_daa) { // Did the patient receive at least 1 dose of TPV or BOC?
				if ($has_10week_results) { // Does the patient have HCV RNA result at 10 weeks or later post treatment
					if (!in_array('1', $pt_hcvrna_after_scores)) { // Are all HCV RNA at 10 weeks or later post-treatment BLOQ?
						$outcome = 'SVR';
					} else {
						if ($hcvrna_before_zero_count > 0) { // Was BLOQ recorded at least once during treatment?
							if (array_pop($hcvrna_before_scores) == '1') { // After BLOQ and while still on IFN, was LAST HCV RNA Quantified?
								$outcome = 'VIRAL BREAKTHROUGH';
							} elseif (array_pop($hcvrna_after_scores) == '1') { // After IFN stop, was last HCV RNA quantified?
								$outcome = 'RELAPSE';
							} else { // Last HCV RNA not quantified
								$outcome = 'SVR';
							}
						} else {
							$outcome = 'NON-RESPONDER';
						}
					}
				} else {
					if ($last_hcvrna_bloq) { // Was last recorded HCV RNA BLOQ?
						if ($lost_to_followup) { // Was the patient lost to follow-up?
							if ($hcvrna_before_zero_count > 0) { // Was BLOQ recorded at least once during treatment?
								if (array_pop($hcvrna_before_scores) == '1') { // After BLOQ and while still on IFN, was LAST HCV RNA Quantified?
									$outcome = 'VIRAL BREAKTHROUGH';
								} elseif (array_pop($hcvrna_after_scores) == '1') { // After IFN stop, was last HCV RNA quantified?
									$outcome = 'RELAPSE';
								} else { // Last HCV RNA not quantified
									$outcome = 'STATUS PENDING';
								}
							} else {
								$outcome = 'NON-RESPONDER';
							}
						} else { // not lost to followup
							if ($ifn_stopped_10_wks_ago) {
								$outcome = 'QUERY HCVRNA';
							} else {
								$outcome = 'QUERY IFN STOP';
							}
						}
					} else {
						if ($hcvrna_before_zero_count > 0) { // Was BLOQ recorded at least once during treatment?
							if (array_pop($hcvrna_before_scores) == '1') { // After BLOQ and while still on IFN, was LAST HCV RNA Quantified?
								$outcome = 'VIRAL BREAKTHROUGH';
							} elseif (array_pop($hcvrna_after_scores) == '1') { // After IFN stop, was last HCV RNA quantified?
								$outcome = 'RELAPSE';
							} else { // Last HCV RNA not quantified
								$outcome = 'SVR';
							}
						} else {
							$outcome = 'NON-RESPONDER';
						}
					}
				}
			} else {
				$outcome = 'QUERY DAA START';
			}
		} else {
			$outcome = 'QUERY IFN STOP';
		}
	} else {
		$outcome = 'QUERY IFN START';
	}
	return $outcome;
}

/**
 * @param $text
 * @return string
 */
function fix_case($text)
{
	$cased_text = mb_convert_case(str_replace('_', ' ', $text), MB_CASE_TITLE);
	return $cased_text;
}

/**
 * @param $lang
 * @param $app_title
 * @param $userid
 * @param $headers
 * @param $user_rights
 * @param $table_csv
 * @param $fields
 * @param $parent_chkd_flds
 * @param $project_id
 * @param $export_file_name
 * @param $debug
 * @param $comment string
 */
function create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, $fields = '', $parent_chkd_flds, $project_id, $export_file_name, $debug, $comment = null)
{
//	$export_type = $fields == '' ? 0 : 1;
	$export_type = 0;
	/**
	 * when are we?
	 */
	$today = date("Y-m-d_Hi");
	/**
	 * Creates the data comma separated value file WITH header
	 */
// File names, comments
	$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20);
	$data_file_name_WH = $projTitleShort . "_" . $export_file_name . "_DATA_" . $today . ".csv";
	$today = date("Y-m-d-H-i-s");
	$docs_comment = $docs_comment_WH = $export_type ? "Data export file created by $userid on $today" : fix_case($export_file_name) . " file created by $userid on $today. $comment";

	/**
	 * setup values for value export logging
	 */
	$chkd_fields = implode(',', $fields);
	/**
	 * turn on/off exporting
	 */
	if ($user_rights['data_export_tool'] && !$debug) {
		$table_csv = addBOMtoUTF8($headers . $table_csv);
		$docs_size = strlen($table_csv);
			/**
			 * Store the file in the file system
			 */
		if (!DataExport::storeExportFile($data_file_name_WH, $table_csv, true)) {
			log_event("", "redcap_data", "data_export", "", str_replace("'", "", $chkd_fields) . (($parent_chkd_flds == "") ? "" : ", " . str_replace("'", "", $parent_chkd_flds)), "Data Export Failed");
			} else {
				log_event("", "redcap_data", "data_export", "", str_replace("'", "", $chkd_fields) . (($parent_chkd_flds == "") ? "" : ", " . str_replace("'", "", $parent_chkd_flds)), "Export data");
			}
//		$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) " . "VALUES ($project_id, '" . $data_file_name_WH . "', NULL, '" . TODAY . "','$docs_size','" . $docs_comment_WH . "','application/csv', NULL ,$export_type)";
//		if (!db_query($export_sql)) {
//			$is_export_error = true;
//		} else {
//		}

		unset($table_csv);

		$csv_img = "download_csvdata.gif";
		$csvexcel_img = "download_csvexcel_raw.gif";
		$csvexcellabels_img = "download_csvexcel_labels.gif";
		/**
		 * since we're not allowing download of csv headers only, we're going to reset the message shown in the export dialog
		 * and reset it later to the original value
		 */
		$temp_data_export_tool_118 = $lang['data_export_tool_118'];
		$lang['data_export_tool_118'] = 'You may download the survey results in CSV (comma-separated) format, which can be opened in Excel.';

		// Need docs_id from this operation to automatically build link
		$docsql = "SELECT docs_id FROM redcap_docs WHERE project_id = $project_id ORDER BY docs_id DESC LIMIT 1";
		$new_id = db_result(db_query($docsql), 0) - 1;

		/**
		 * print table header
		 */
		print "<h1>Export Data</h1>";
		print "<div style='max-width:700px;'>";
		print "<table style='border: 1px solid #DODODO; border-collapse: collapse; width: 100%'>
				<tr class='grp2'>
					<td colspan='2' style='font-family:Verdana;font-size:12px;text-align:left;'>
					<!--<a href='javascript:void(0)' onclick='window.print();' title='Print this page'>Print Report</a>--></td>
					<td style='font-family:Verdana;font-size:12px;text-align:center;'>
						{$lang['docs_58']}<br>{$lang['data_export_tool_51']}
					</td>
				</tr>";
		/**
		 * print csv export selection
		 */
		print '<tr class="odd">
					<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-left:1px solid #D0D0D0;">
						<img src="' . APP_PATH_IMAGES . 'excelicon.gif" title="' . $lang['data_export_tool_15'] . '" alt="' . $lang['data_export_tool_15'] . '" />
					</td>
					<td style="font-family:Verdana;font-size:11px;padding:10px;" valign="top">
						<b>' . $lang['data_export_tool_15'] . '</b><br>
						' . $lang['data_export_tool_118'] . '<br><br>
						<i>' . $lang['global_02'] . ': ' . $lang['data_export_tool_17'] . '</i>
					</td>
					<td valign="top" style="text-align:center;width:100px;padding-top:10px;">
						<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . ($new_id + 1) . '">
							<img src="' . APP_PATH_IMAGES . $csvexcellabels_img . '" title="' . $lang['data_export_tool_60'] . '" alt="' . $lang['data_export_tool_60'] . '"></a> &nbsp;
						<div style="text-align:left;padding:5px 0 1px;">
							<div style="line-height:5px;">
								<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
									href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;"
									onclick=\'$("#sendit_' . ($new_id - 1) . '").toggle("blind",{},"fast");\'>' . $lang['data_export_tool_66'] . '</a>
							</div>
							<div id="sendit_' . ($new_id - 1) . '" style="display:none;padding:4px 0 4px 6px;">
								<div>
									&bull; <a href="javascript:;" onclick="popupSendIt(' . ($new_id + 1) . ',2);" style="font-size:10px;">' . $lang['data_export_tool_120'] . '</a>
								</div>
								<div>
									&bull; <a href="javascript:;" onclick="popupSendIt(' . ($new_id - 1) . ',2);" style="font-size:10px;">' . $lang['data_export_tool_119'] . '</a>
								</div>
							</div>
						</div>
					</td>
				</tr>';

		/**
		 * print table footer
		 */

		print '</table>';
		print '</div>';
	}
}

/**
 * @param $table_name string
 * @param $lang
 * @param $app_title string
 * @param $userid string
 * @param $user_rights
 * @param $chkd_fields string
 * @param $parent_chkd_flds
 * @param $project_id
 * @param $export_file_name
 * @param $debug
 */
function create_cdisc_download($table_name, $lang, $app_title, $userid, $user_rights, $chkd_fields, $parent_chkd_flds, $project_id, $export_file_name, $debug)
{
	$data_row = array();
	$table_csv = "";
	$export_result = db_query("SELECT * FROM `$table_name`");
	if ($export_result) {
		while ($export_row = db_fetch_assoc($export_result)) {
			foreach ($export_row AS $export_key => $export_value) {
				$data_row[strtoupper($export_key)] = fix_null_sas($export_value);
			}
			$row_csv = implode(',', $data_row) . "\n";
			$table_csv .= $row_csv;
		}
		db_free_result($export_result);
	}
	/**
	 * when are we?
	 */
	//$today = date("Y-m-d_Hi");
	/**
	 * Creates the data comma separated value file WITH header
	 */
// File names, comments
	$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20);
	$data_file_name_WH = $export_file_name . ".csv";
	$today = date("Y-m-d-H-i-s");
	$docs_comment_WH = "CDISC $export_file_name domain data file created by $userid on $today.";

	/**
	 * setup values for value export logging
	 */
	$headers = implode(',', array_keys($data_row)) . "\n";
	/**
	 * turn on/off exporting
	 */
	if ($user_rights['data_export_tool'] && !$debug) {
		$table_csv = addBOMtoUTF8($headers . $table_csv);
		$docs_size = strlen($table_csv);
		$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) " . "VALUES ($project_id, '" . $data_file_name_WH . "', NULL, '" . TODAY . "','$docs_size','" . $docs_comment_WH . "','application/csv', NULL ,0)";
		if (!db_query($export_sql)) {
			$is_export_error = true;
		} else {
			/**
			 * Store the file in the file system
			 */
			if (!DataExport::storeExportFile($data_file_name_WH, $table_csv, db_insert_id(), $docs_size)) {
				$is_export_error = true;
			} else {
				log_event("", "redcap_data", "data_export", "", str_replace("'", "", $chkd_fields) . (($parent_chkd_flds == "") ? "" : ", " . str_replace("'", "", $parent_chkd_flds)), "Export data");
			}
		}

		unset($table_csv);

		$csv_img = "download_csvdata.gif";
		$csvexcel_img = "download_csvexcel_raw.gif";
		$csvexcellabels_img = "download_csvexcel_labels.gif";
		/**
		 * since we're not allowing download of csv headers only, we're going to reset the message shown in the export dialog
		 * and reset it later to the original value
		 */
		$temp_data_export_tool_118 = $lang['data_export_tool_118'];
		$lang['data_export_tool_118'] = "You may download the CDISC $table_name data in CSV (comma-separated) format, which can be opened in Excel.";

		// Need docs_id from this operation to automatically build link
		$docsql = "SELECT docs_id FROM redcap_docs WHERE project_id = $project_id ORDER BY docs_id DESC LIMIT 1";
		$new_id = db_result(db_query($docsql), 0) - 1;

		/**
		 * print table header
		 */
		print "<h1>Export Data</h1>";
		print "<div style='max-width:700px;'>";
		print "<table style='border: 1px solid #DODODO; border-collapse: collapse; width: 100%'>
				<tr class='grp2'>
					<td colspan='2' style='font-family:Verdana;font-size:12px;text-align:left;'>
					<!--<a href='javascript:void(0)' onclick='window.print();' title='Print this page'>Print Report</a>--></td>
					<td style='font-family:Verdana;font-size:12px;text-align:center;'>
						{$lang['docs_58']}<br>{$lang['data_export_tool_51']}
					</td>
				</tr>";
		/**
		 * print csv export selection
		 */
		print '<tr class="odd">
					<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-left:1px solid #D0D0D0;">
						<img src="' . APP_PATH_IMAGES . 'excelicon.gif" title="' . $lang['data_export_tool_15'] . '" alt="' . $lang['data_export_tool_15'] . '" />
					</td>
					<td style="font-family:Verdana;font-size:11px;padding:10px;" valign="top">
						<b>' . $lang['data_export_tool_15'] . '</b><br>
						' . $lang['data_export_tool_118'] . '<br><br>
						<i>' . $lang['global_02'] . ': ' . $lang['data_export_tool_17'] . '</i>
					</td>
					<td valign="top" style="text-align:center;width:100px;padding-top:10px;">
						<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . ($new_id + 1) . '">
							<img src="' . APP_PATH_IMAGES . $csvexcellabels_img . '" title="' . $lang['data_export_tool_60'] . '" alt="' . $lang['data_export_tool_60'] . '"></a> &nbsp;
						<div style="text-align:left;padding:5px 0 1px;">
							<div style="line-height:5px;">
								<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
									href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;"
									onclick=\'$("#sendit_' . ($new_id - 1) . '").toggle("blind",{},"fast");\'>' . $lang['data_export_tool_66'] . '</a>
							</div>
							<div id="sendit_' . ($new_id - 1) . '" style="display:none;padding:4px 0 4px 6px;">
								<div>
									&bull; <a href="javascript:;" onclick="popupSendIt(' . ($new_id + 1) . ',2);" style="font-size:10px;">' . $lang['data_export_tool_120'] . '</a>
								</div>
								<div>
									&bull; <a href="javascript:;" onclick="popupSendIt(' . ($new_id - 1) . ',2);" style="font-size:10px;">' . $lang['data_export_tool_119'] . '</a>
								</div>
							</div>
						</div>
					</td>
				</tr>';

		/**
		 * print table footer
		 */

		print '</table>';
		print '</div>';
		$lang['data_export_tool_118'] = $temp_data_export_tool_118;
	}
}

/**
 * @param $null_val
 * @return null or string
 */
function fix_null($null_val)
{
	if ($null_val == 'null' || $null_val == '' || !isset($null_val)) {
		$ret_val = "NULL";
	} elseif (is_numeric($null_val)) {
		$ret_val = $null_val;
	} else {
		$ret_val = quote_wrap(prep($null_val));
	}
	return $ret_val;
}

/**
 * @param $null_val
 * @return null or string
 */
function fix_csv_null($null_val)
{
	if ($null_val == 'null' || $null_val == '') {
		$ret_val = '';
	} elseif (is_numeric($null_val)) {
		$ret_val = $null_val;
	} else {
		$ret_val = quote_wrap($null_val);
	}
	return $ret_val;
}

/**
 * @param $val
 * @return null or string
 */
function fix_null_sas($val)
{
	if ($val == 'null' || $val == '' || !isset($val)) {
		$ret_val = NULL;
	} elseif (!is_numeric($val)) {
		$ret_val = quote_wrap(str_replace('\'', "%'", $val));
	} else {
		$ret_val = $val;
	}
	return $ret_val;
}

function make_sas_date($iso_8601_date)
{
	if (isset($iso_8601_date) && $iso_8601_date != '') {
		if (strlen($iso_8601_date) == 4) {
			$iso_8601_date = $iso_8601_date . '-01-01';
		}
		$date_obj = date_create($iso_8601_date);
		return date_format($date_obj, 'dMY');
	} else {
		return null;
	}
}

/**
 * @param $rootDir
 * @param array $allData
 * @return array
 */
function scan_dirs($rootDir, $allData = array())
{
	// set filenames invisible if you want
	$invisibleFileNames = array(".", "..", ".htaccess", ".htpasswd");
	// run through content of root directory
	$dirContent = scandir($rootDir);
	foreach ($dirContent as $key => $content) {
		// filter all files not accessible
		$path = $rootDir . '/' . $content;
		if (!in_array($content, $invisibleFileNames)) {
			// if content is file & readable, add to array
			if (is_file($path) && is_readable($path)) {
				// save file name with path
				$allData[] = $path;
				// if content is a directory and readable, add path and name
			} elseif (is_dir($path) && is_readable($path)) {
				// recursive callback to open new directory
				$allData = scan_dirs($path, $allData);
			}
		}
	}
	return $allData;
}

/**
 * @param string $project_id
 * @param array $fields
 * @param array $meta_fields
 * @param string $count
 * @param array $enum
 * @param string $add_to_where
 * @return array
 */
function get_vals_query($project_id, $fields, $meta_fields, $count, $enum = null, $add_to_where = null)
{
	$have_first = false;
	$have_second = false;
	$first_alias = '';
	$second_alias = '';
	$select_outer_fields = array();
	$select_inner_query = array();
	$enum_field = array();
	$return_array = array();
	foreach ($fields AS $key => $field) {
		/**
		 * build inner select statement for each field
		 */
		$join_alias = substr($key, 2);
		if (isset($field) && !in_array($key, $meta_fields)) {
			$return_array['fields_collection'][$field] = $key;
			if ($first_alias == '') {
				$first_alias = $join_alias;
			} elseif ($first_alias != '' && $second_alias == '') {
				$second_alias = $join_alias;
			}
			$select_inner_sql = "SELECT DISTINCT ";
			$select_inner_sql .= "`$join_alias`.value AS $key,\n";
			$select_inner_sql .= "`$join_alias`.event_id,\n";
			$select_inner_sql .= "`$join_alias`.project_id,\n";
			$select_inner_sql .= "`$join_alias`.record\n";
			$select_inner_sql .= "FROM redcap_data `$join_alias`\n";
			$select_inner_sql .= "WHERE `$join_alias`.field_name = '$field' ) `$join_alias`\n";
			$select_outer_fields[] = "`$join_alias`.$key";
			if (!$have_first && $count > 1) {
				$have_first = true;
				$select_inner_sql .= "LEFT OUTER JOIN (\n";
			} elseif (!$have_first && $count == 1) {
				$have_first = true;
			} elseif (!$have_second && $count == 1) {
				$have_second = true;
				$select_inner_sql .= "ON `$first_alias`.record = `$join_alias`.record\n";
				$select_inner_sql .= "AND `$first_alias`.project_id = `$join_alias`.project_id\n";
			} elseif (!$have_second) {
				$have_second = true;
				$select_inner_sql .= "ON `$first_alias`.record = `$join_alias`.record\n";
				$select_inner_sql .= "AND `$first_alias`.project_id = `$join_alias`.project_id\n";
				$select_inner_sql .= "LEFT OUTER JOIN (\n";
			} elseif ($count > 1) {
				$select_inner_sql .= "ON `$second_alias`.record = `$join_alias`.record\n";
				$select_inner_sql .= "AND `$second_alias`.event_id = `$join_alias`.event_id\n";
				$select_inner_sql .= "AND `$second_alias`.project_id = `$join_alias`.project_id\n";
				$select_inner_sql .= "LEFT OUTER JOIN (\n";
			} else {
				$select_inner_sql .= "ON `$second_alias`.record = `$join_alias`.record\n";
				$select_inner_sql .= "AND `$second_alias`.event_id = `$join_alias`.event_id\n";
				$select_inner_sql .= "AND `$second_alias`.project_id = `$join_alias`.project_id\n";
			}
			$select_inner_query[] = $select_inner_sql;
			$count--;
		} elseif (!isset($field) && !in_array($key, $meta_fields)) {
			$select_outer_fields[] = "null AS $key";
			$count--;
		} elseif (isset($field) && in_array($key, $meta_fields)) {
			$select_outer_fields[] = "'$field' AS $key";
		} elseif (isset($enum)) {
			if (!isset($field) && in_array($key, $meta_fields)) {
				foreach ($enum AS $enum_set => $enum_array) {
					if (array_key_exists($fields['lbtestcd'], $enum_array)) {
						$enum_field = $enum_set;
					}
				}
				if (in_array($fields['lbtestcd'], array_keys($enum[$enum_field]))) {
					$select_outer_fields[] = "'{$enum[$enum_field][$fields['lbtestcd']]}' AS $key";
					$fields['lbtest'] = $enum[$enum_field][$fields['lbtestcd']];
				} elseif (!in_array($fields['lbtestcd'], array_keys($enum[$enum_field]))) { //it's an _oth field
					$select_outer_fields[] = "'{$fields['lbtestcd']}' AS $key";
					$fields['lbtest'] = $fields['lbtestcd'];
					$fields['lbtestcd'] = 'OTHER';
				}
			}
		}
	}
	/**
	 * Build outer select clause with parts from above
	 */
	$select_outer = implode(', ', $select_outer_fields);
	/**
	 * construct query
	 */
	$vals_query = "SELECT DISTINCT $select_outer\n";
	$vals_query .= "FROM (";
	$vals_query .= implode("\n", $select_inner_query);
	$vals_query .= "WHERE $first_alias.project_id = '$project_id'\n";
	if (isset($enum) && $fields['lbtest'] == $enum[$enum_field][$fields['lbtestcd']]) {
		$vals_query .= "AND (SELECT value FROM redcap_data WHERE project_id = $second_alias.project_id AND event_id = $second_alias.event_id AND record = $second_alias.record AND field_name = '$enum_field') = '{$fields['lbtestcd']}'\n";
	} elseif ($fields['lbtestcd'] == 'OTHER') {
		$oth_field = str_replace('_', "_oth_", $enum_field);
		$vals_query .= "AND (SELECT value FROM redcap_data WHERE project_id = $second_alias.project_id AND event_id = $second_alias.event_id AND record = $second_alias.record AND field_name = '$oth_field') = '{$fields['lbtest']}'\n";
	}
	if (isset($add_to_where)) {
		$vals_query .= $add_to_where;
	}
	$vals_query .= "ORDER BY abs($first_alias.record) ASC";
	$return_array['query'] = $vals_query;
	//show_var($return_array);
	return $return_array;
}

/**
 * @param $project_id
 * @param $subject_id
 * @param $event_id
 * @param $event
 * @param $debug
 * @param bool $recode
 */
function code_cm($project_id, $subject_id, $event_id, $event, $debug, $recode = false)
{
	if (isset($event['cm_cmtrt']) && $event['cm_cmtrt'] != '') {
		$med = array();
		$med_result = db_query("SELECT DISTINCT drug_name FROM _whodrug_mp_us WHERE drug_name = '" . prep($event['cm_cmtrt']) . "'");
		if ($med_result) {
			$med = db_fetch_assoc($med_result);
			if ($recode) {
				if (isset($med['drug_name']) && $med['drug_name'] != '') {
					update_field_compare($subject_id, $project_id, $event_id, $med['drug_name'], $event['cm_cmdecod'], 'cm_cmdecod', $debug);
				}
			} else {
				if ($event['cm_cmdecod'] == '' && isset($med['drug_name']) && $med['drug_name'] != '') {
					update_field_compare($subject_id, $project_id, $event_id, $med['drug_name'], $event['cm_cmdecod'], 'cm_cmdecod', $debug);
				}
			}
		}
	} else {
		update_field_compare($subject_id, $project_id, $event_id, '', $event['cm_cmdecod'], 'cm_cmdecod', $debug);
	}
}

/**
 * @param $project_id
 * @param $subjid
 * @param $event_id
 * @param $aeterm
 * @param $oth_aeterm
 * @param $aedecod
 * @param $decod_field
 * @param $debug
 * @param $recode boolean
 */
function code_llt($project_id, $subjid, $event_id, $aeterm, $oth_aeterm, $aedecod, $decod_field, $debug, $recode = false, $message)
{
	if (isset($aeterm) && $aeterm != '') {
		$ae_aeterm = fix_case($aeterm);
		$ae_oth_aeterm = isset($oth_aeterm) ? fix_case($oth_aeterm) : $oth_aeterm;
		$llt_query = "SELECT DISTINCT llt_name FROM _meddra_low_level_term WHERE llt_name = '" . prep($ae_aeterm) . "'";
		if (isset($ae_oth_aeterm)) {
			$llt_query .= " OR llt_name = '" . prep($ae_oth_aeterm) . "'";
		}
		$llt_result = db_query($llt_query);
		if ($llt_result) {
			$llt = db_fetch_assoc($llt_result);
			$xlate_llt_result = db_query("SELECT llt_pref_name FROM _target_xlate_llt WHERE llt_name = '" . prep($aeterm) . "' OR llt_name = '" . prep($llt['llt_name']) . "'");
			if ($xlate_llt_result) {
				$xlate_llt = db_fetch_assoc($xlate_llt_result);
				if (isset($xlate_llt['llt_pref_name'])) {
					$llt['llt_name'] = $xlate_llt['llt_pref_name'];
					if ($debug) {
						error_log("INFO: Translating AE $aeterm to " . $xlate_llt['llt_pref_name'] . " for subject $subjid");
					}
				}
			}
			if ($recode) {
				if (isset($llt['llt_name']) && $llt['llt_name'] != '') {
					update_field_compare($subjid, $project_id, $event_id, $llt['llt_name'], $aedecod, $decod_field, $debug, $message);
				}
			} else {
				if ($aedecod == '' && isset($llt['llt_name']) && $llt['llt_name'] != '') {
					update_field_compare($subjid, $project_id, $event_id, $llt['llt_name'], $aedecod, $decod_field, $debug, $message);
				}
			}
		}
	} else {
		update_field_compare($subjid, $project_id, $event_id, '', $aedecod, $decod_field, $debug, $message);
	}
}

/**
 * @param $aeterm
 * @param $oth_aeterm
 * @return null
 */
function get_llt($aeterm, $oth_aeterm = null)
{
	if (isset($aeterm) && $aeterm != '') {
		$ae_aeterm = fix_case($aeterm);
		$ae_oth_aeterm = isset($oth_aeterm) ? fix_case($oth_aeterm) : $oth_aeterm;
		$llt_query = "SELECT DISTINCT llt_name FROM _meddra_low_level_term WHERE llt_name = '" . prep($ae_aeterm) . "'";
		if (isset($ae_oth_aeterm)) {
			$llt_query .= " OR llt_name = '" . prep($ae_oth_aeterm) . "'";
		}
		$llt_result = db_query($llt_query);
		if ($llt_result) {
			$llt = db_fetch_assoc($llt_result);
			$xlate_llt_result = db_query("SELECT llt_pref_name FROM _target_xlate_llt WHERE llt_name = '" . prep($aeterm) . "' OR llt_name = '" . prep($llt['llt_name']) . "'");
			if ($xlate_llt_result) {
				$xlate_llt = db_fetch_assoc($xlate_llt_result);
				if (isset($xlate_llt['llt_pref_name'])) {
					$llt['llt_name'] = $xlate_llt['llt_pref_name'];
				}
			}
			return $llt['llt_name'];
		} else {
			return null;
		}
	} else {
		return null;
	}
}

/**
 * @param $project_id
 * @param $subjid
 * @param $event_id
 * @param $aeterm
 * @param $oth_aeterm
 * @param $aedecod
 * @param $decod_field
 * @param $debug
 * @param $recode boolean
 */
function code_pt($project_id, $subjid, $event_id, $aemodify, $aedecod, $decod_field, $debug, $recode = false, $message)
{
	if (isset($aemodify) && $aemodify != '') {
		$ae_aemodify = fix_case($aemodify);
		$pt_query = "SELECT pt.aedecod AS aedecod FROM
		(SELECT DISTINCT llt.llt_name AS aellt,
		llt.llt_code AS aelltcd,
		llt.pt_code
		FROM _meddra_low_level_term llt
		) llt
		LEFT OUTER JOIN
		(SELECT DISTINCT pt.pt_name AS aedecod,
		pt.pt_code AS aeptcd,
		pt.pt_soc_code AS aebdsycd
		FROM _meddra_pref_term pt
		) pt
		ON llt.pt_code = CONVERT(pt.aeptcd USING utf8) COLLATE utf8_unicode_ci WHERE llt.aellt = '" . prep($ae_aemodify) . "'";
		$pt_result = db_query($pt_query);
		if ($pt_result) {
			$pt = db_fetch_assoc($pt_result);
			if (strtolower($ae_aemodify) == 'not specified') {
				$pt['aedecod'] = 'Product used for unknown indication';
			}
			if ($recode) {
				if (isset($pt['aedecod']) && $pt['aedecod'] != '') {
					update_field_compare($subjid, $project_id, $event_id, $pt['aedecod'], $aedecod, $decod_field, $debug, $message);
				}
			} else {
				if ($aedecod == '' && isset($pt['aedecod']) && $pt['aedecod'] != '') {
					update_field_compare($subjid, $project_id, $event_id, $pt['aedecod'], $aedecod, $decod_field, $debug, $message);
				}
			}
		}
	} else {
		update_field_compare($subjid, $project_id, $event_id, '', $aedecod, $decod_field, $debug, $message);
	}
}

/**
 * @param $aemodify
 * @return array|null
 */
function get_pt($aemodify)
{
	if (isset($aemodify) && $aemodify != '') {
		$ae_aemodify = fix_case($aemodify);
		$pt_query = "SELECT pt.aedecod AS aedecod FROM
		(SELECT DISTINCT llt.llt_name AS aellt,
		llt.llt_code AS aelltcd,
		llt.pt_code
		FROM _meddra_low_level_term llt
		) llt
		LEFT OUTER JOIN
		(SELECT DISTINCT pt.pt_name AS aedecod,
		pt.pt_code AS aeptcd,
		pt.pt_soc_code AS aebdsycd
		FROM _meddra_pref_term pt
		) pt
		ON llt.pt_code = CONVERT(pt.aeptcd USING utf8) COLLATE utf8_unicode_ci WHERE llt.aellt = '" . prep($ae_aemodify) . "'";
		$pt_result = db_query($pt_query);
		if ($pt_result) {
			$pt = db_fetch_assoc($pt_result);
			return $pt['aedecod'];
		} else {
			return null;
		}
	} else {
		return null;
	}
}

/**
 * @param $project_id
 * @param $subjid
 * @param $event_id
 * @param $aedecod
 * @param $aebodsys
 * @param $bodsys_field
 * @param $debug
 * @param $recode boolean
 */
function code_bodsys($project_id, $subjid, $event_id, $aedecod, $aebodsys, $bodsys_field, $debug, $recode = false, $message)
{
	if (isset($aedecod) && $aedecod != '') {
		$meddra_query = "SELECT soc.aesoc AS aebodsys, soc.soc_code AS aebdsycd
		FROM
		(SELECT DISTINCT llt.llt_name AS aellt,
		llt.llt_code AS aelltcd,
		llt.pt_code
		FROM _meddra_low_level_term llt
		) llt
		LEFT OUTER JOIN
		(SELECT DISTINCT pt.pt_name AS aedecod,
		pt.pt_code AS aeptcd,
		pt.pt_soc_code AS aebdsycd
		FROM _meddra_pref_term pt
		) pt
		ON llt.pt_code = CONVERT(pt.aeptcd USING utf8) COLLATE utf8_unicode_ci
		LEFT OUTER JOIN
		(SELECT DISTINCT hlt.hlt_name AS aehlt,
		hlt.hlt_code AS aehltcd,
		hlt_pt.pt_code
		FROM _meddra_hlt_pref_term hlt
		LEFT OUTER JOIN _meddra_hlt_pref_comp hlt_pt
		ON hlt.hlt_code = hlt_pt.hlt_code
		) hlt
		ON llt.pt_code = CONVERT(hlt.pt_code USING utf8) COLLATE utf8_unicode_ci
		LEFT OUTER JOIN
		(SELECT DISTINCT hlgt.hlgt_name AS aehlgt,
		hlgt.hlgt_code AS aehlgtcd,
		hlgt_hlt.hlt_code
		FROM _meddra_hlgt_pref_term hlgt
		LEFT OUTER JOIN _meddra_hlgt_hlt_comp hlgt_hlt
		ON hlgt.hlgt_code = hlgt_hlt.hlgt_code
		) hlgt
		ON hlt.aehltcd = CONVERT(hlgt.hlt_code USING utf8) COLLATE utf8_unicode_ci
		LEFT OUTER JOIN
		(SELECT soc.soc_name AS aesoc,
		soc.soc_code
		FROM _meddra_soc_term soc
		) soc
		ON pt.aebdsycd = CONVERT(soc.soc_code USING utf8) COLLATE utf8_unicode_ci";
		$aedecod = fix_case($aedecod);
		$bodsys_result = db_query($meddra_query . " WHERE llt.aellt = '" . prep($aedecod) . "'");
		if ($bodsys_result) {
			$bodsys = db_fetch_assoc($bodsys_result);
			if ($recode) {
				if (isset($bodsys['aebodsys']) && $bodsys['aebodsys'] != '') {
					update_field_compare($subjid, $project_id, $event_id, $bodsys['aebodsys'], $aebodsys, $bodsys_field, $debug, $message);
				}
			} else {
				if ($aebodsys == '' && isset($bodsys['aebodsys']) && $bodsys['aebodsys'] != '') {
					update_field_compare($subjid, $project_id, $event_id, $bodsys['aebodsys'], $aebodsys, $bodsys_field, $debug, $message);
				}
			}
		}
	} else {
		update_field_compare($subjid, $project_id, $event_id, '', $aebodsys, $bodsys_field, $debug);
	}
}

/**
 * @param $aedecod
 * @return string or null
 */
function get_bodsys($aedecod)
{
	if (isset($aedecod) && $aedecod != '') {
		$meddra_query = "SELECT soc.aesoc AS aebodsys, soc.soc_code AS aebdsycd
		FROM
		(SELECT DISTINCT llt.llt_name AS aellt,
		llt.llt_code AS aelltcd,
		llt.pt_code
		FROM _meddra_low_level_term llt
		) llt
		LEFT OUTER JOIN
		(SELECT DISTINCT pt.pt_name AS aedecod,
		pt.pt_code AS aeptcd,
		pt.pt_soc_code AS aebdsycd
		FROM _meddra_pref_term pt
		) pt
		ON llt.pt_code = CONVERT(pt.aeptcd USING utf8) COLLATE utf8_unicode_ci
		LEFT OUTER JOIN
		(SELECT DISTINCT hlt.hlt_name AS aehlt,
		hlt.hlt_code AS aehltcd,
		hlt_pt.pt_code
		FROM _meddra_hlt_pref_term hlt
		LEFT OUTER JOIN _meddra_hlt_pref_comp hlt_pt
		ON hlt.hlt_code = hlt_pt.hlt_code
		) hlt
		ON llt.pt_code = CONVERT(hlt.pt_code USING utf8) COLLATE utf8_unicode_ci
		LEFT OUTER JOIN
		(SELECT DISTINCT hlgt.hlgt_name AS aehlgt,
		hlgt.hlgt_code AS aehlgtcd,
		hlgt_hlt.hlt_code
		FROM _meddra_hlgt_pref_term hlgt
		LEFT OUTER JOIN _meddra_hlgt_hlt_comp hlgt_hlt
		ON hlgt.hlgt_code = hlgt_hlt.hlgt_code
		) hlgt
		ON hlt.aehltcd = CONVERT(hlgt.hlt_code USING utf8) COLLATE utf8_unicode_ci
		LEFT OUTER JOIN
		(SELECT soc.soc_name AS aesoc,
		soc.soc_code
		FROM _meddra_soc_term soc
		) soc
		ON pt.aebdsycd = CONVERT(soc.soc_code USING utf8) COLLATE utf8_unicode_ci";
		$aedecod = fix_case($aedecod);
		$bodsys_result = db_query($meddra_query . " WHERE llt.aellt = '" . prep($aedecod) . "'");
		if ($bodsys_result) {
			$bodsys = db_fetch_assoc($bodsys_result);
			return $bodsys['aebodsys'];
		} else {
			return null;
		}
	} else {
		return null;
	}
}

/**
 * @param string $project_id
 * @param string $subject_id
 * @param string $event_id
 * @param string $cmdecod
 * @param string $atc_name
 * @param string $atc2_name
 * @param bool $recode
 * @param bool $debug
 */
function code_atc($project_id, $subject_id, $event_id, $cmdecod, $atc_name, $atc2_name, $debug, $recode = false, $message)
{
	/**
	 * CM_SUPPCM_ATCNAME
	 * CM_SUPPCM_ATC2NAME
	 * queries to find ATC name for conmed
	 */
	if (isset($cmdecod) && $cmdecod != '') {
		$atc_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
		(SELECT drug_name, drug_rec_num FROM _whodrug_mp_us) drug
		LEFT JOIN
		(SELECT drug_rec_num, atc_code FROM _whodrug_dda_target) mp_atc ON TRIM(LEADING '0' FROM drug.drug_rec_num) = mp_atc.drug_rec_num
		LEFT JOIN
		(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
		LEFT JOIN
		(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
		$atcname_result = db_query($atc_query . " WHERE drug.drug_name = '" . prep($cmdecod) . "' AND atc.atc_name IS NOT NULL LIMIT 1");
		if ($atcname_result) {
			/**
			 * we don't want more than one result here
			 */
			while ($atcname = db_fetch_assoc($atcname_result)) {
				/**
				 * if we have atc and atc2, push to redcap
				 */
				if ($recode) {
					if (isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
					}
					if (isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
					}
				} else {
					if ($atc_name == '' && isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
					}
					if ($atc2_name == '' && isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
					}
				}
			}
			db_free_result($atcname_result);
		}
		/**
		 * in case whodrug doesn't know about your conmed, code from alt table
		 */
		$check_atc = get_single_field($subject_id, $project_id, $event_id, 'cm_suppcm_atcname', '');
		if (!isset($check_atc) || $check_atc == '') {
			$atc_alt_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
			(SELECT * FROM _whodrug_dda_alt) mp_atc
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
			$atcname_alt_result = db_query($atc_alt_query . " WHERE mp_atc.drug_name = '" . prep($cmdecod) . "' LIMIT 1");
			if ($atcname_alt_result) {
				/**
				 * we don't want more than one result here
				 */
				while ($atcname_alt = db_fetch_assoc($atcname_alt_result)) {
					/**
					 * if we have atc and atc2, push to redcap
					 */
					if ($recode) {
						if (isset($atcname_alt['atc_name']) && $atcname_alt['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
						}
						if (isset($atcname_alt['atc2_name']) && $atcname_alt['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
						}
					} else {
						if ($atc_name == '' && isset($atcname_alt['atc_name']) && $atcname_alt['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
						}
						if ($atc2_name == '' && isset($atcname_alt['atc2_name']) && $atcname_alt['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
						}
					}
				}
				db_free_result($atcname_alt_result);
			}
		}
	} else {
		update_field_compare($subject_id, $project_id, $event_id, '', $atc_name, 'cm_suppcm_atcname', $debug, $message);
		update_field_compare($subject_id, $project_id, $event_id, '', $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
	}
}

/**
 * @param string $project_id
 * @param string $subject_id
 * @param string $event_id
 * @param string $cmdecod
 * @param string $atc_name
 * @param string $atc2_name
 * @param bool $recode
 * @param bool $debug
 */
function code_atc_soc($project_id, $subject_id, $event_id, $cmdecod, $atc_name, $atc2_name, $debug, $recode = false, $message)
{
	/**
	 * CM_SUPPCM_ATCNAME
	 * CM_SUPPCM_ATC2NAME
	 * queries to find ATC name for conmed
	 */
	if (isset($cmdecod) && $cmdecod != '') {
		/**
		 * get indcd -> soc for mapping soc to atc
		 */
		$soc = get_bodsys(get_single_field($subject_id, $project_id, $event_id, 'cm_suppcm_indcod', ''));
		$map_result = db_query("SELECT atc_code, atc_code_alt FROM _target_map_soc_atc WHERE soc = '$soc'");
		if ($map_result) {
			$atc_code = db_result($map_result, 0, 'atc_code');
			$atc_code_alt = db_result($map_result, 0, 'atc_code_alt');
			if ($debug) {
				/*error_log("DEBUG: " . $atc_code);
				error_log("DEBUG: " . $atc_code_alt);*/
			}
		}
		$atc_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
		(SELECT drug_name, drug_rec_num FROM _whodrug_mp_us) drug
		LEFT JOIN
		(SELECT drug_rec_num, atc_code FROM _whodrug_dda_target) mp_atc ON TRIM(LEADING '0' FROM drug.drug_rec_num) = mp_atc.drug_rec_num
		LEFT JOIN
		(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
		LEFT JOIN
		(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
		$atc_query .= " WHERE drug.drug_name = '" . prep($cmdecod) . "' AND ";
		if (isset($atc_code) && $atc_code !== false) {
			$atc_query .= "(atc.atc_code = '" . $atc_code . "'";
			if (isset($atc_code_alt) && $atc_code_alt != '') {
				$atc_query .= " OR atc.atc_code = '" . $atc_code_alt . "'";
			}
			$atc_query .= ") AND ";
		}
		$atc_query .= "atc.atc_name IS NOT NULL LIMIT 1";
		if ($debug) {
			//error_log("DEBUG: " . $atc_query);
		}
		$atcname_result = db_query($atc_query);
		if ($atcname_result) {
			while ($atcname = db_fetch_assoc($atcname_result)) {
				/**
				 * if we have atc and atc2, push to redcap
				 */
				if ($recode) {
					if (isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
					}
					if (isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
					}
				} else {
					if ($atc_name == '' && isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
					}
					if ($atc2_name == '' && isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
					}
				}
			}
			db_free_result($atcname_result);
		}
		/**
		 * if we didn't find one that maps to soc, be more generic
		 */
		$check_atc = get_single_field($subject_id, $project_id, $event_id, 'cm_suppcm_atcname', '');
		if (!isset($check_atc) || $check_atc == '') {
			$atc_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
			(SELECT drug_name, drug_rec_num FROM _whodrug_mp_us) drug
			LEFT JOIN
			(SELECT drug_rec_num, atc_code FROM _whodrug_dda_target) mp_atc ON TRIM(LEADING '0' FROM drug.drug_rec_num) = mp_atc.drug_rec_num
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
			$atcname_result = db_query($atc_query . " WHERE drug.drug_name = '" . prep($cmdecod) . "' AND atc.atc_name IS NOT NULL LIMIT 1");
			if ($atcname_result) {
				/**
				 * we don't want more than one result here
				 */
				while ($atcname = db_fetch_assoc($atcname_result)) {
					/**
					 * if we have atc and atc2, push to redcap
					 */
					if ($recode) {
						if (isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
						}
						if (isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
						}
					} else {
						if ($atc_name == '' && isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
						}
						if ($atc2_name == '' && isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
						}
					}
				}
				db_free_result($atcname_result);
			}
		}
		/**
		 * in case whodrug doesn't know about your conmed, code from alt table
		 */
		$check_atc = get_single_field($subject_id, $project_id, $event_id, 'cm_suppcm_atcname', '');
		if (!isset($check_atc) || $check_atc == '') {
			$atc_alt_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
			(SELECT * FROM _whodrug_dda_alt) mp_atc
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
			$atcname_alt_result = db_query($atc_alt_query . " WHERE mp_atc.drug_name = '" . prep($cmdecod) . "' LIMIT 1");
			if ($atcname_alt_result) {
				/**
				 * we don't want more than one result here
				 */
				while ($atcname_alt = db_fetch_assoc($atcname_alt_result)) {
					/**
					 * if we have atc and atc2, push to redcap
					 */
					if ($recode) {
						if (isset($atcname_alt['atc_name']) && $atcname_alt['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
						}
						if (isset($atcname_alt['atc2_name']) && $atcname_alt['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
						}
					} else {
						if ($atc_name == '' && isset($atcname_alt['atc_name']) && $atcname_alt['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc_name'], $atc_name, 'cm_suppcm_atcname', $debug, $message);
						}
						if ($atc2_name == '' && isset($atcname_alt['atc2_name']) && $atcname_alt['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc2_name'], $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
						}
					}
				}
				db_free_result($atcname_alt_result);
			}
		}
	} else {
		update_field_compare($subject_id, $project_id, $event_id, '', $atc_name, 'cm_suppcm_atcname', $debug, $message);
		update_field_compare($subject_id, $project_id, $event_id, '', $atc2_name, 'cm_suppcm_atc2name', $debug, $message);
	}
}

/**
 * @param string $project_id
 * @param string $subject_id
 * @param string $event_id
 * @param string $cmdecod
 * @param string $atc_name
 * @param string $atc2_name
 * @param bool $recode
 * @param bool $debug
 */
function code_atc_xfsn($project_id, $subject_id, $event_id, $cmdecod, $atc_name, $atc2_name, $debug, $recode = false, $message)
{
	/**
	 * CM_SUPPCM_ATCNAME
	 * CM_SUPPCM_ATC2NAME
	 * queries to find ATC name for conmed
	 */
	if (isset($cmdecod) && $cmdecod != '') {
		$atc_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
		(SELECT drug_name, drug_rec_num FROM _whodrug_mp_us) drug
		LEFT JOIN
		(SELECT drug_rec_num, atc_code FROM _whodrug_dda_target) mp_atc ON TRIM(LEADING '0' FROM drug.drug_rec_num) = mp_atc.drug_rec_num
		LEFT JOIN
		(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
		LEFT JOIN
		(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
		$atcname_result = db_query($atc_query . " WHERE drug.drug_name = '" . prep($cmdecod) . "' AND atc.atc_name IS NOT NULL LIMIT 1");
		if ($atcname_result) {
			/**
			 * we don't want more than one result here
			 */
			while ($atcname = db_fetch_assoc($atcname_result)) {
				/**
				 * if we have atc and atc2, push to redcap
				 */
				if ($recode) {
					if (isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'xfsn_suppcm_atcname', $debug, $message);
					}
					if (isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'xfsn_suppcm_atc2name', $debug, $message);
					}
				} else {
					if ($atc_name == '' && isset($atcname['atc_name']) && $atcname['atc_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc_name'], $atc_name, 'xfsn_suppcm_atcname', $debug, $message);
					}
					if ($atc2_name == '' && isset($atcname['atc2_name']) && $atcname['atc2_name'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, $atcname['atc2_name'], $atc2_name, 'xfsn_suppcm_atc2name', $debug, $message);
					}
				}
			}
			db_free_result($atcname_result);
		}
		/**
		 * in case whodrug doesn't know about your conmed, code from alt table
		 */
		$check_atc = get_single_field($subject_id, $project_id, $event_id, 'xfsn_suppcm_atcname', '');
		if (!isset($check_atc) || $check_atc == '') {
			$atc_alt_query = "SELECT DISTINCT atc.atc_name, atc1.atc_name AS atc2_name FROM
			(SELECT * FROM _whodrug_dda_alt) mp_atc
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
			LEFT JOIN
			(SELECT atc_code, atc_name FROM _whodrug_atc) atc1 ON SUBSTRING(mp_atc.atc_code,1,3) = atc1.atc_code";
			$atcname_alt_result = db_query($atc_alt_query . " WHERE mp_atc.drug_name = '" . prep($cmdecod) . "' LIMIT 1");
			if ($atcname_alt_result) {
				/**
				 * we don't want more than one result here
				 */
				while ($atcname_alt = db_fetch_assoc($atcname_alt_result)) {
					/**
					 * if we have atc and atc2, push to redcap
					 */
					if ($recode) {
						if (isset($atcname_alt['atc_name']) && $atcname_alt['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc_name'], $atc_name, 'xfsn_suppcm_atcname', $debug, $message);
						}
						if (isset($atcname_alt['atc2_name']) && $atcname_alt['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc2_name'], $atc2_name, 'xfsn_suppcm_atc2name', $debug, $message);
						}
					} else {
						if ($atc_name == '' && isset($atcname_alt['atc_name']) && $atcname_alt['atc_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc_name'], $atc_name, 'xfsn_suppcm_atcname', $debug, $message);
						}
						if ($atc2_name == '' && isset($atcname_alt['atc2_name']) && $atcname_alt['atc2_name'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, $atcname_alt['atc2_name'], $atc2_name, 'xfsn_suppcm_atc2name', $debug, $message);
						}
					}
				}
				db_free_result($atcname_alt_result);
			}
		}
	} else {
		update_field_compare($subject_id, $project_id, $event_id, '', $atc_name, 'xfsn_suppcm_atcname', $debug, $message);
		update_field_compare($subject_id, $project_id, $event_id, '', $atc2_name, 'xfsn_suppcm_atc2name', $debug, $message);
	}
}

/**
 * @param $cm_cmdecod string
 * @return string
 */
function get_conmed_mktg_status($cm_cmdecod)
{
	$mktg_status = '99';
	$mktg_status_array = array('1' => 'PRESCRIPTION', '2' => 'OTC', '99' => 'UNKNOWN');
	$mktg_status_query = "(";
	$mktg_status_query .= "SELECT * FROM `_drugsatfda_product` WHERE drugname = '$cm_cmdecod' AND ProductMktStatus IN (1,2) ORDER BY ProductMktStatus ASC LIMIT 1";
	$mktg_status_query .= ") UNION (";
	$mktg_status_query .= "SELECT * FROM `_drugsatfda_product_hcvt` WHERE drugname = '$cm_cmdecod' AND ProductMktStatus IN (1,2) ORDER BY ProductMktStatus ASC LIMIT 1";
	$mktg_status_query .= ")";
	$mktg_status_result = db_query($mktg_status_query);
	if (isset($cm_cmdecod) && $cm_cmdecod != '') {
		if ($mktg_status_result) {
			while ($mktg_status_row = db_fetch_assoc($mktg_status_result)) {
				$mktg_status = $mktg_status_row['ProductMktStatus'];
			}
		}
		if ($mktg_status == '99') {
			$mktg_status_fuzzy = "(";
			$mktg_status_fuzzy .= "SELECT * FROM `_drugsatfda_product` WHERE drugname LIKE '%$cm_cmdecod%' AND ProductMktStatus IN (1,2) ORDER BY ProductMktStatus ASC LIMIT 1";
			$mktg_status_fuzzy .= ") UNION (";
			$mktg_status_fuzzy .= "SELECT * FROM `_drugsatfda_product_hcvt` WHERE drugname LIKE '%$cm_cmdecod%' AND ProductMktStatus IN (1,2) ORDER BY ProductMktStatus ASC LIMIT 1";
			$mktg_status_fuzzy .= ")";
			$mktg_status_fuzzy_result = db_query($mktg_status_fuzzy);
			if ($mktg_status_fuzzy_result) {
				while ($mktg_status_row = db_fetch_assoc($mktg_status_fuzzy_result)) {
					$mktg_status = $mktg_status_row['ProductMktStatus'];
				}
			}
		}
	}
	/**
	 * 1 = Prescription
	 * 2 = OTC
	 * 99 = UNKNOWN
	 */
	return $mktg_status_array[$mktg_status];
}

/**
 * @param $subject array
 * @param $fragment string
 * @param $tx_start string
 * @return array|mixed
 */
function get_baseline_date($subject, $fragment, $tx_start)
{
	$this_data = array();
	if (isset($tx_start) || $tx_start != '') {
		foreach ($subject AS $lab_event => $lab_date_set) {
			if (isset($lab_date_set[$fragment . '_lbdtc']) && $lab_date_set[$fragment . '_lbdtc'] != '') {
				$this_date_ob = new DateTime($lab_date_set[$fragment . '_lbdtc']);
				$tx_start_ob = new DateTime($tx_start);
				$interval = $tx_start_ob->diff($this_date_ob, false);
				$int_start_to_this = $interval->format('%r%a');
				if ($int_start_to_this <= 0) {
					$this_data[$int_start_to_this]['interval'] = $int_start_to_this;
					$this_data[$int_start_to_this]['event_id'] = $lab_event;
					foreach ($lab_date_set AS $this_key => $this_val) {
						if ($this_key != $fragment . '_im_lbdtc') {
							$this_data[$int_start_to_this][$this_key] = $this_val;
						}
					}
				}
			}
			if (isset($lab_date_set[$fragment . '_im_lbdtc']) && $lab_date_set[$fragment . '_im_lbdtc'] != '') {
				$this_date_ob = new DateTime($lab_date_set[$fragment . '_im_lbdtc']);
				$tx_start_ob = new DateTime($tx_start);
				$interval = $tx_start_ob->diff($this_date_ob, false);
				$int_start_to_this = $interval->format('%r%a');
				if ($int_start_to_this <= 0) {
					$this_data[$int_start_to_this]['interval'] = $int_start_to_this;
					$this_data[$int_start_to_this]['event_id'] = $lab_event;
					foreach ($lab_date_set AS $this_key => $this_val) {
						if ($this_key != $fragment . '_lbdtc') {
							$this_data[$int_start_to_this][$this_key] = $this_val;
						}
					}
				}
			}
		}
		if (count($this_data) > 0 && $this_data != '') {
			$this_sort = new FieldSorter('interval');
			usort($this_data, array($this_sort, "cmp"));
			$this_data = array_pop($this_data);
		}
	}
	return $this_data;
}

/**
 * @param $record
 * @param $event_id
 * @param $field
 * @return array
 */
function getFieldDataResHistoryTarget($record, $event_id, $field)
{
	// Set subquery for rule_id/field
	$sub_sql = "";
	if ($field != '') {
		// Field-level (can include PD rules too)
		$sub_sql = "and s.field_name = '" . prep($field) . "'";
	}
	// Query table for history
	$drw_history = array();
	$sql = "select r.*, s.status, s.exclude, s.query_status, s.assigned_user_id
				from redcap_data_quality_status s, redcap_data_quality_resolutions r
				where s.project_id = " . PROJECT_ID . " and s.event_id = $event_id
				and s.record = '" . prep($record) . "' and s.status_id = r.status_id
				$sub_sql order by r.res_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Add row to array
		$drw_history[] = $row;
	}
	// Return the array
	return $drw_history;
}

/**
 * @param $form_name string
 * @param $project_id string
 * @param $subject string
 * @param $event_name string
 * @param $debug boolean
 */
function standardize_lab_form($form_name, $project_id, $subject = null, $event_name = null, $debug)
{
	global $no_conversion, $counts_conversion, $gdl_conversion, $iul_conversion, $bili_conversion, $creat_conversion, $gluc_conversion;
	Kint::enabled($debug);
	switch ($form_name) {
		case 'cbc':
			$labs_array = array(
				'wbc' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'neut' => array('units' => '%', 'conversion' => $no_conversion),
				'anc' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'lymce' => array('units' => '%', 'conversion' => $no_conversion),
				'lym' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'plat' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'hemo' => array('units' => 'g/dL', 'conversion' => $gdl_conversion)
			);
			$stresn = '_lbstresn';
			$stresu = '_lbstresu';
			break;
		case 'chemistry':
			$labs_array = array(
				'alt' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'ast' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'alp' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'tbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
				'dbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
				'alb' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
				'creat' => array('units' => 'mg/dL', 'conversion' => $creat_conversion),
				'gluc' => array('units' => 'mg/dL', 'conversion' => $gluc_conversion),
				'k' => array('units' => 'mmol/L', 'conversion' => $no_conversion),
				'sodium' => array('units' => 'mmol/L', 'conversion' => $no_conversion)
			);
			$stresn = '_lbstresn';
			$stresu = '_lbstresu';
			break;
		case 'hcv_rna_results':
			$labs_array = array(
				'hcv' => array('units' => 'IU/mL', 'conversion' => $counts_conversion)
			);
			$stresn = '_lbstresn';
			$stresu = '_lbstresu';
			break;
		case 'inr':
			$labs_array = array(
				'inr' => array('units' => '', 'conversion' => $no_conversion)
			);
			$stresn = '_lbstresn';
			$stresu = '_lbstresu';
			break;
		case 'cbc_imported':
			$labs_array = array(
				'wbc_im' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'neut_im' => array('units' => '%', 'conversion' => $no_conversion),
				'anc_im' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'lymce_im' => array('units' => '%', 'conversion' => $no_conversion),
				'lym_im' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'plat_im' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'hemo_im' => array('units' => 'g/dL', 'conversion' => $gdl_conversion)
			);
			$stresn = '_im_lbstresn';
			$stresu = '_im_lbstresu';
			break;
		case 'chemistry_imported':
			$labs_array = array(
				'alt_im' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'ast_im' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'alp_im' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'tbil_im' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
				'dbil_im' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
				'alb_im' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
				'creat_im' => array('units' => 'mg/dL', 'conversion' => $creat_conversion),
				'gluc_im' => array('units' => 'mg/dL', 'conversion' => $gluc_conversion),
				'k_im' => array('units' => 'mmol/L', 'conversion' => $no_conversion),
				'sodium_im' => array('units' => 'mmol/L', 'conversion' => $no_conversion)
			);
			$stresn = '_im_lbstresn';
			$stresu = '_im_lbstresu';
			break;
		case 'hcv_rna_imported':
			$labs_array = array(
				'hcv_im' => array('units' => 'IU/mL', 'conversion' => $counts_conversion)
			);
			$stresn = '_im_lbstresn';
			$stresu = '_im_lbstresu';
			break;
		case 'inr_imported':
			$labs_array = array(
				'inr_im' => array('units' => '', 'conversion' => $no_conversion)
			);
			$stresn = '_im_lbstresn';
			$stresu = '_im_lbstresu';
			break;
		case 'clinical_and_lab_data':
		case 'clinical_lab_followup':
			$labs_array = array(
				'hcv' => array('units' => 'IU/mL', 'conversion' => $counts_conversion),
				'hiv' => array('units' => 'IU/mL', 'conversion' => $counts_conversion),
				'alt' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'ast' => array('units' => 'IU/L', 'conversion' => $iul_conversion),
				'tbil' => array('units' => 'mg/dL', 'conversion' => $bili_conversion),
				'alb' => array('units' => 'g/dL', 'conversion' => $gdl_conversion),
				'creat' => array('units' => 'mg/dL', 'conversion' => $creat_conversion),
				'inr' => array('units' => '', 'conversion' => $no_conversion),
				'plat' => array('units' => '10^3/uL', 'conversion' => $counts_conversion),
				'hemo' => array('units' => 'g/dL', 'conversion' => $gdl_conversion)
			);
			$stresn = '_lbstresn';
			$stresu = '_lbstresu';
			break;
		default:
			$labs_array = array();
			$stresn = '';
			$stresu = '';
			break;
	}
	if (!empty($labs_array)) {
		$fields = array();
		foreach ($labs_array AS $lab_prefix => $lab_params) {
			$fields[] = $lab_prefix . "_lborres";
			$fields[] = $lab_prefix . "_lborresu";
			$fields[] = $lab_prefix . "_lbstresn";
			$fields[] = $lab_prefix . "_lbstresu";
		}
		/**
		 * get working data
		 */
		$data = REDCap::getData('array', $subject, $fields, $event_name);
		foreach ($data AS $subjid => $subject) {
			if ($debug) {
				error_log("SUBJID: " . $subjid);
			}
			foreach ($subject AS $event_id => $event) {
				$pairs = array();
				/**
				 * rotate REDCap::getData array into value/unit pairs and store in an array
				 */
				foreach ($event AS $field => $value) {
					$pairs[substr($field, 0, strpos($field, '_'))][substr($field, strrpos($field, '_') + 1)] = trim($value);
				}
				foreach ($pairs AS $prefix => $this_lab) {
					d($this_lab);
					$has_valid_units = false;
					/**
					 * if $this_lab has both value and units:
					 */
					if ($this_lab['lborres'] != '' && $this_lab['lborresu'] != '') {
						/**
						 * get preferred $units for this test
						 */
						$units = $labs_array[$prefix]['units'];
						/**
						 * assume the entry is correct
						 */
						$lbstresn = $this_lab['lborres'];
						$lbstresu = $this_lab['lborresu'];
						/**
						 * if preferred $units were not entered:
						 */
						if (strtolower($this_lab['lborresu']) != strtolower($units)) {
							if ($debug) {
								error_log($this_lab['lborresu'] . ': ' . $units, " $prefix UNITS MISMATCH");
							}
							/**
							 * check _units_view for equivalents to lborresu
							 */
							$units_result = db_query("SELECT unit_name FROM _units_view WHERE unit_value = '$units'");
							if ($units_result) {
								while ($units_row = db_fetch_assoc($units_result)) {
									/**
									 * if we have a matching equivalent, use the preferred units
									 */
									if (strtolower($units_row['unit_name']) == strtolower($this_lab['lborresu'])) {
										$lbstresu = $units;
										if ($debug) {
											error_log($lbstresu . ': FOUND EQV');
										}
										$has_valid_units = true;
										continue;
									}
								}
							}
							if ($debug && !$has_valid_units) {
								error_log($lbstresu . ': NO EQV');
							}
							/**
							 * do units conversion if necessary
							 */
							$conv = $labs_array[$prefix]['conversion'];
							if (count($conv) > 0) {
								foreach ($conv AS $unit => $formula) {
									$units_result = db_query("SELECT unit_value FROM _units_view WHERE unit_name = '$lbstresu'");
									if ($units_result) {
										while ($units_row = db_fetch_assoc($units_result)) {
											if (strtolower($units_row['unit_value']) == strtolower($unit)) {
												$lbstresu = $units_row['unit_value'];
												$has_valid_units = true;
												continue;
											}
										}
									}
									/**
									 * if the entered units are to be converted:
									 */
									if (strtolower($this_lab['lborresu']) == strtolower($unit) || strtolower($lbstresu) == strtolower($unit)) {
										if ($debug) {
											error_log($formula . ': CONVERSION');
										}
										/**
										 * convert to standard units
										 */
										if ($formula == 'exp') {
											$lbstresn = (string)round(pow(10, $this_lab['lborres']));
										} else {
											$lbstresn = (string)round(eval("return (" . $this_lab['lborres'] . $formula . ");"), 3);
										}
										if ($debug) {
											error_log($lbstresn . ': CORRECTED VALUE');
										}
										$lbstresu = $units;
										$has_valid_units = true;
									}
								}
							}
						} else {
							$lbstresu = $units;
							$has_valid_units = true;
						}
						if ($has_valid_units) {
							update_field_compare($subjid, $project_id, $event_id, $lbstresn, $this_lab['lbstresn'], $prefix . $stresn, $debug);
							update_field_compare($subjid, $project_id, $event_id, $lbstresu, $this_lab['lbstresu'], $prefix . $stresu, $debug);
						}
					} elseif ($this_lab['lborresu'] == '' && $this_lab['lborres'] != '') {
						/**
						 * NO BLANK UNITS
						 */
						update_field_compare($subjid, $project_id, $event_id, '', $this_lab['lbstresn'], $prefix . $stresn, $debug);
						update_field_compare($subjid, $project_id, $event_id, '', $this_lab['lbstresu'], $prefix . $stresu, $debug);
					} else {
						if ($this_lab['lborres'] == '' && $this_lab['lbstresn'] != '') {
							update_field_compare($subjid, $project_id, $event_id, $this_lab['lborres'], $this_lab['lbstresn'], $prefix . $stresn, $debug);
						}
						if ($this_lab['lborresu'] == '' && $this_lab['lbstresu'] != '') {
							update_field_compare($subjid, $project_id, $event_id, $this_lab['lborresu'], $this_lab['lbstresu'], $prefix . $stresu, $debug);
						}
					}
				}
				/**
				 * @TODO: set form_complete
				 */
				//update_field_compare($subjid, $project_id, $event_id, '2', get_single_field($subjid, $project_id, $event_id, $form_name . '_complete', null), $form_name . '_complete', $debug);
			}
		}
	}
}

/**
 * @param $prefix string
 * @param $lab_fields array 'Must include _supplb_lbdtbl, _lbblfl, _lborres or _lbstresn without prefix'
 * @param $date_field array 'Date field for this form / fields'
 * @param $debug
 * UNDER CONSTRUCTION
 */
function derive_baseline_from_fields($prefix, $lab_fields, $date_field, $debug)
{
	$project = new Project();
	$initial_event = $project->firstEventId;
	$lab_fields = array_merge($date_field, $lab_fields);
	if ($debug) {
		/*show_var($lab_fields);*/
	}
	/**
	 * find baselines for each lab test
	 */
	$data = REDCap::getData('array', '', $lab_fields);
	foreach ($data AS $subjid => $subject) {
		$lab_subject = array();
		if ($debug) {
			show_var($subjid, 'FIELD SUBJID', 'blue');
		}
		/**
		 * get refstdtc for this subject
		 */
		$tx_start_fields = array("dm_rfstdtc");
		$tx_start_data = REDCap::getData('array', $subjid, $tx_start_fields);
		$rfstdtc = $tx_start_data[$subjid][$initial_event]['dm_rfstdtc'];
		if ($debug) {
			show_var($subject, 'BEFORE', 'red');
		}
		/**
		 * if treatment has started
		 */
		if (isset($rfstdtc) || $rfstdtc != '') {
			/**
			 * iterate the lab events for this prefix
			 */
			foreach ($subject AS $lab_event_id => $lab_event) {
				/**
				 * if we have a value for the orres, then add to candidate events array
				 */
				if ($lab_event[$prefix . "_lborres"] != '') {
					$lab_subject[$lab_event_id] = $subject[$lab_event_id];
				}
			}
			if ($debug) {
				show_var($lab_subject, "AFTER {$prefix}", 'green');
			}
			/**
			 * if we have candidate events
			 */
			if (count($lab_subject) > 0) {
				/**
				 * fetch the baseline date and set baseline / flag pair
				 */
				$baseline_date = '';
				$this_data = get_baseline_date($lab_subject, $fragment, $rfstdtc);
				/**
				 * if the nearest date is prior or equal to rfstdtc, it's a baseline date
				 */
				if ($debug) {
					show_var($lab_subject, 'FIELD SUBJECT DATA', 'blue');
					show_var($this_data, 'FIELD BASELINE DATA', 'red');
					show_var($rfstdtc, 'TX start', 'red');
				}
				if ($this_data[$fragment . '_lbdtc'] != '' && $this_data[$fragment . '_lbdtc'] <= $rfstdtc) {
					$baseline_date = $this_data[$fragment . '_lbdtc'];
					/**
					 * Baseline date belongs in Baseline event
					 */
					update_field_compare($subjid, $project_id, $initial_event, $baseline_date, get_single_field($subjid, $project_id, $initial_event, $prefix . "_supplb_lbdtbl", null), $prefix . "_supplb_lbdtbl", $debug);
					if ($debug) {
						show_var($baseline_date, 'FIELD BASELINE DATE', 'red');
					}
					/**
					 * Now reset all other flags that have changed
					 */
					$flag_reset_data = REDCap::getData('array', $subjid, $prefix . "_lbblfl");
					$this_baseline_flag = get_single_field($subjid, $project_id, $this_data['event_id'], $prefix . "_lbblfl", null);
					foreach ($flag_reset_data AS $reset) {
						if ($debug) {
							show_var($reset, 'FIELD RESET FLAGS', 'red');
						}
						foreach ($reset AS $reset_event_id => $reset_event) {
							foreach ($reset_event as $reset_field => $reset_val) {
								if ($reset_event_id != $this_data['event_id']) {
									update_field_compare($subjid, $project_id, $reset_event_id, '', $reset_val, $prefix . "_lbblfl", $debug);
								}
							}
						}
					}
					/**
					 * Baseline flag belongs in the event where the date occurs
					 */
					if ($baseline_date != '') {
						update_field_compare($subjid, $project_id, $this_data['event_id'], 'Y', $this_baseline_flag, $prefix . "_lbblfl", $debug);
					}
				}
			}
		}
	}
}

/**
 * @param $folder string
 * @param $zipFile object
 * @param $subfolder string
 * @return bool
 */
function folderToZip($folder, &$zipFile, $subfolder = null)
{
	if ($zipFile == null) {
		// no resource given, exit
		return false;
	}
	// we check if $folder has a slash at its end, if not, we append one
	$folder .= end(str_split($folder)) == "/" ? "" : "/";
	$subfolder .= end(str_split($subfolder)) == "/" ? "" : "/";
	// we start by going through all files in $folder
	$handle = opendir($folder);
	while (false !== ($f = readdir($handle))) {
		if ($f != "." && $f != "..") {
			if (is_file($folder . $f)) {
				// if we find a file, store it
				// if we have a subfolder, store it there
				if ($subfolder != null)
					$zipFile->addFile($folder . $f, $subfolder . $f);
				else
					$zipFile->addFile($folder . $f);
			} elseif (is_dir($folder . $f)) {
				// if we find a folder, create a folder in the zip
				$zipFile->addEmptyDir($f);
				// and call the function again
				folderToZip($folder . $f, $zipFile, $f);
			}
		}
	}
	closedir($handle);
}

/**
 * @param $file string
 */
function target_delete_repository_file($file)
{
	global $edoc_storage_option, $wdc, $webdav_path;
	if ($edoc_storage_option == '1') {
		// Webdav
		$wdc->delete($webdav_path . $file);
	} elseif ($edoc_storage_option == '2') {
		// S3
		global $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;
		$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
		$s3->deleteObject($amazon_s3_bucket, $file);
	} else {
		// Local
		@unlink(EDOC_PATH . $file);
	}
}

/**
 * RECURSIVELY DELETE FOLDER AND ITS CONTENTS
 * @param $dir string
 */
function rrmdir($dir)
{
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir . "/" . $object) == "dir") {
					rrmdir($dir . "/" . $object);
				} else {
					unlink($dir . "/" . $object);
				}
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

/**
 * @param $arg_t
 * @param bool $arg_ra
 * @return string
 */
function benchmark_timing($arg_t, $arg_ra = false)
{
	$tttime = round((end($arg_t) - $arg_t['start']) * 1000, 4);
	if ($arg_ra) $ar_aff['total_time'] = $tttime;
	else $aff = "total time : " . $tttime . " ms<br />";
	$prv_cle = 'start';
	$prv_val = $arg_t['start'];

	foreach ($arg_t as $cle => $val) {
		if ($cle != 'start') {
			$prcnt_t = round(((round(($val - $prv_val) * 1000, 4) / $tttime) * 100), 1);
			if ($arg_ra) $ar_aff[$prv_cle . ' -> ' . $cle] = $prcnt_t;
			$aff .= $prv_cle . ' -> ' . $cle . ' : ' . $prcnt_t . "%<br />";
			$prv_val = $val;
			$prv_cle = $cle;
		}
	}
	if ($arg_ra) return $ar_aff;
	return $aff;
}

function quote_wrap($var)
{
	if (isset($var)) {
		return '"' . $var . '"';
	} else {
		return $var;
	}
}

/**
 * @param array $headers
 * @param string $header
 * @return array
 */
function add_to_header($headers = array(), $header)
{
	if (!in_array($header, $headers)) {
		$headers[] = quote_wrap($header);
	}
	return $headers;
}

/**
 * @param $array
 * @return array
 */
function array_unique_recursive($array)
{
	$result = array_map("unserialize", array_unique(array_map("serialize", $array)));

	foreach ($result as $key => $value) {
		if (is_array($value)) {
			$result[$key] = array_unique_recursive($value);
		}
	}

	return $result;
}

/**
 * @param $needle
 * @param $haystack
 * @return bool|int|string
 */
function array_search_recursive($needle, $haystack)
{
	foreach ($haystack as $key => $value) {
		$current_key = $key;
		if ($needle === $value OR (is_array($value) && array_search_recursive($needle, $value) !== false)) {
			return $current_key;
		}
	}
	return false;
}

/**
 * @param $needle
 * @param $haystack
 * @return bool
 */
function array_key_exists_recursive($needle, $haystack)
{
	$result = array_key_exists($needle, $haystack);
	if ($result)
		return $result;
	foreach ($haystack as $v) {
		if (is_array($v) || is_object($v))
			$result = array_key_exists_recursive($needle, $v);
		if ($result)
			return $result;
	}
	return $result;
}

function get_visits($fields_result, $data, $first_event_id)
{
	$vals_array = array();
	while ($fields = db_fetch_assoc($fields_result)) {
		foreach ($data AS $subject_id => $subject) {
			$dsstdtc = $subject[$first_event_id]['consent_dssstdtc'];
			$rfstdtc = $subject[$first_event_id]['dm_rfstdtc'];
			$usubjid = $subject[$first_event_id]['dm_usubjid'];
			foreach ($subject AS $event_id => $event) {
				$inner_vals = array();
				/**
				 * events with date for this field, and the date is not prior to baseline
				 */
				if ($event[$fields['svstdtc']] != '' && ($event[$fields['svstdtc']] >= $rfstdtc || $event[$fields['svstdtc']] >= $dsstdtc)) {
					/**
					 * set up date objects and interval
					 */
					$rfstdtc_ob = new DateTime($rfstdtc);
					$visit_date_ob = new DateTime($event[$fields['svstdtc']]);
					$interval_ob = $rfstdtc_ob->diff($visit_date_ob, false);
					$interval = $interval_ob->format('%r%a');
					/**
					 * Because TARGET is observational/standard of care, and such things are not defined by the protocol,
					 * we can't call them planned visits, so they're defined as 'expected'
					 */
					$expected_visits = array(28 => 'WEEK 4', 56 => 'WEEK 8', 84 => 'WEEK 12', 168 => 'WEEK24');
					$visit_data = array();
					$visit_week = NULL;
					/**
					 * iterate the expected visits
					 */
					foreach ($expected_visits AS $day => $week) {
						/**
						 * test fit of date to expected visits
						 */
						if (add_date($rfstdtc, $day) == $event[$fields['svstdtc']]) {
							/**
							 * exact fit
							 * the tested date is exactly $day days after $rfstdtc
							 */
							$visit_week = $week;
							$visit_data['visitdy'] = $day;
							/**
							 * if a previous date was fit to this $week, delete it and use this one
							 */
							$have_week_index = array_search_recursive($week, $vals_array[$usubjid]);
							if (false !== $have_week_index) {
								unset($vals_array[$usubjid][$have_week_index]);
							}
						} elseif ((($day - 6) < $interval) && ($interval <= $day + 7) && false === array_search_recursive($week, $vals_array[$usubjid])) {
							/**
							 * loose fit
							 * the tested date is within the $week, and we haven't already assigned this $week to another date
							 */
							$visit_week = $week;
							$visit_data['visitdy'] = $day;
						}
					}
					$visit_data['visit'] = $visit_week;
					$visit_data['svstdy'] = $interval;
					/**
					 * assign attributes to this visit
					 */
					switch ($fields['svstdtc']) {
						case 'consent_dssstdtc':
							/**
							 * handle consent_dssstdtc exception
							 */
							$inner_vals['visit'] = 'CONSENT';
							$inner_vals['svstdtc'] = $event[$fields['svstdtc']];
							$inner_vals['svstdy'] = $visit_data['svstdy'];
							$inner_vals['visitdy'] = $visit_data['svstdy'];
							break;
						case 'dm_rfstdtc':
							/**
							 * handle dm_rfstdtc exception
							 */
							$inner_vals['visit'] = 'DAY 1';
							$inner_vals['svstdtc'] = $event[$fields['svstdtc']];
							$inner_vals['svstdy'] = 1;
							$inner_vals['visitdy'] = 1;
							break;
						default:
							/**
							 * all other fields
							 */
							if (false === array_search_recursive($event[$fields['svstdtc']], $vals_array[$usubjid])) {
								$inner_vals['visit'] = $visit_data['visit'];
								$inner_vals['svstdtc'] = $event[$fields['svstdtc']];
								$inner_vals['svstdy'] = $visit_data['svstdy'];
								$inner_vals['visitdy'] = $visit_data['visitdy'];
								if (!isset($visit_data['visit'])) {
									switch ($fields['svstdtc']) {
										case 'ae_aestdtc':
											$inner_vals['svupdes'] = 'Adverse event';
											break;
										case 'chem_lbdtc':
										case 'chem_im_lbdtc':
										case 'cbc_lbdtc':
										case 'cbc_im_lbdtc':
										case 'hcv_lbdtc':
										case 'hcv_im_lbdtc':
										case 'inr_lbdtc':
										case 'inr_im_lbdtc':
											$inner_vals['svupdes'] = 'Labs collected';
											break;
										default:
											break;
									}
								}
							}
							break;
					}
					$vals_array[$usubjid][] = $inner_vals;
				}
			}
			/**
			 * sort $vals_array[$usubjid] by date
			 */
			$sorter = new FieldSorter('svstdtc');
			usort($vals_array[$usubjid], array($sorter, "cmp"));
		}
	}
	return $vals_array;
}

/**
 * @param $this_event
 * @param $this_form
 * @return bool
 */
function getNextEventId($this_event, $this_form = null)
{
	global $Proj;
	if (!isset($Proj->eventsForms[$this_event])) return false;
	$events = array_keys($Proj->eventsForms);
	$nextEventIndex = array_search($this_event, $events) + 1;
	if (isset($this_form)) {
		return (isset($events[$nextEventIndex]) && $Proj->validateFormEvent($this_form, $events[$nextEventIndex])) ? $events[$nextEventIndex] : false;
	} else {
		return (isset($events[$nextEventIndex])) ? $events[$nextEventIndex] : false;
	}
}

/**
 * @param array $subject_regimen_data
 * @param null|string $dsterm
 * @param null|string $txstat
 * @return array
 */
function get_regimen($subject_regimen_data, $dsterm = null, $txstat = null)
{
	global $tx_to_arm, $intended_regimens;
	$actarm_array = array();
	$actarmcd_array = array();
	$arm = NULL;
	$armcd = NULL;
	$actarm = NULL;
	$actarmcd = NULL;
	foreach ($subject_regimen_data AS $regimen_event) {
		foreach ($regimen_event AS $reg_key => $reg_val) {
			if ($reg_key != 'reg_suppcm_regimen') { // actual ARM
				if ($reg_val != '') {
					$this_regimen = strtoupper(substr($reg_key, 0, strpos($reg_key, '_')));
					foreach ($tx_to_arm[$this_regimen] AS $arm_order => $arm_pair) {
						foreach ($arm_pair as $actarmcd => $arm_name) {
							$actarm_array[$arm_order] = $arm_name;
							$actarmcd_array[$arm_order] = $actarmcd;
						}
					}
				}
			} else { // planned ARM
				if ($reg_val != '') {
					$planned_array = $intended_regimens[$reg_val];
					foreach ($planned_array as $key => $val) {
						$arm = $val;
						$armcd = $key;
					}
				}
			}
		}
	}
	/**
	 * Viekira relabeling
	 */
	/*if (strpos($arm, 'Viekira') !== false) {
		if (strpos($arm, 'Dasabuvir') !== false) {
			$arm = 'Viekira Pak';
			$armcd = 'VPK';
		} else {
			$arm = str_replace('Viekira', 'Technivie', $arm);
			$armcd = str_replace('VPK', 'TCN', $armcd);
		}
	}*/
	d('before', $actarmcd_array);
	d($actarm_array);
	if (array_search('VPK', $actarmcd_array) !== false) {
		if (array_search('DBV', $actarmcd_array) !== false) {
			unset($actarmcd_array[7], $actarm_array[7]);
			$actarm_array = array_replace($actarm_array, array(array_search('Viekira', $actarm_array) => 'Viekira Pak'));
		} else {
			$actarmcd_array = array_replace($actarmcd_array, array(array_search('VPK', $actarmcd_array) => 'TCN'));
			$actarm_array = array_replace($actarm_array, array(array_search('Viekira', $actarm_array) => 'Technivie'));
		}
	}
	if (!empty($actarm_array)) {
		ksort($actarm_array);
		ksort($actarmcd_array);
		$actarm = implode('/', array_unique($actarm_array));
		//$actarm = strpos($actarm, 'Viekira/Dasabuvir') !== false ? str_replace('Viekira/Dasabuvir', 'Viekira Pak', $actarm) : $actarm;
		$actarmcd = implode('/', array_unique($actarmcd_array));
		//$actarmcd = strpos($actarmcd, 'VPK/DBV') !== false ? str_replace('VPK/DBV', 'VPK', $actarmcd) : $actarmcd;
		/**
		 * undocumented in JMP Clinical:
		 * if subject ARM or ARMCD are missing, the subject will be SCREEN FAIL.
		 * Yeah. Sweeet.
		 */
		$arm = !isset($arm) ? $actarm : $arm;
		$armcd = !isset($armcd) ? $actarmcd : $armcd;
	} else {
		if ($dsterm == 'SCREEN_FAILURE') {
			$actarm = fix_case($dsterm);
			$actarmcd = 'SCRNFAIL';
		} elseif ($txstat == 'N') {
			$actarm = 'Not Treated';
			$actarmcd = 'NOTTRT';
		}
	}
	$return_array['arm'] = $arm;
	$return_array['armcd'] = $armcd;
	$return_array['actarm'] = $actarm;
	$return_array['actarmcd'] = $actarmcd;
	return $return_array;
}

function get_visit_num($table, $usubjid, $visit_date)
{
	$result = db_query("SELECT VISITNUM FROM `$table` WHERE USUBJID = '$usubjid' AND SVSTDTC = '$visit_date' LIMIT 1");
	$ret_val = null;
	if ($result) {
		$visit_row = db_fetch_assoc($result);
		$ret_val = $visit_row['VISITNUM'];
	}
	return $ret_val;
}

function get_study_day($subjid, $iso_8601_date)
{
	global $Proj;
	$study_day = null;
	if (isset($iso_8601_date) && $iso_8601_date != '') {
		if (strlen($iso_8601_date) == 4) {
			$iso_8601_date = $iso_8601_date . '-01-01';
		}
		$date_obj = New DateTime($iso_8601_date);
		$rfstdtc = New DateTime(get_single_field($subjid, $Proj->project, $Proj->firstEventId, 'dm_rfstdtdc', ''));
		$study_day = $rfstdtc->diff($date_obj)->days;
	}
	return $study_day;
}

function blanks()
{
	return quote_wrap('--');
}

function get_element_label($field_name)
{
	global $Proj;
	return label_decode($Proj->metadata[$field_name]['element_label'], false);
}

/**
 * @param $record
 * @param $instrument
 * @param $redcap_event_name
 * @return bool
 * is this form locked?
 * If the form is locked, the save that triggered this script represents the
 * action of locking the form, not a change of data within the form.
 * Best used during redcap_save_record hook to make sure we're not acting on the lock, but the save.
 */
function is_form_locked($record, $instrument, $redcap_event_name)
{
	$form_is_locked = false;
	if (isset($record, $instrument, $redcap_event_name)) {
		global $Proj;
		$event_id = $Proj->getEventIdUsingUniqueEventName($redcap_event_name);
		$locked_result = db_query("SELECT ld_id FROM redcap_locking_data WHERE project_id = '$Proj->project_id' AND record = '$record' AND event_id = '$event_id' AND form_name = '$instrument'");
		if ($locked_result) {
			$locked = db_fetch_assoc($locked_result);
			if (isset($locked['ld_id'])) {
				$form_is_locked = true;
			}
		}
	}
	return $form_is_locked;
}

function get_end_of_array(&$array)
{
	if (!is_array($array)) {
		$return_val = &$array;
	} elseif (!count($array)) {
		$return_val = null;
	} else {
		end($array);
		$return_val = &$array[key($array)];
	}
	return $return_val;
}

function get_first_of_array(&$array)
{
	if (!is_array($array)) {
		$return_val = &$array;
	} elseif (!count($array)) {
		$return_val = null;
	} else {
		reset($array);
		$return_val = &$array[key($array)];
	}
	return $return_val;
}

/**
 * @param $record
 * @param $debug
 */
function set_tx_data($record, $debug)
{
	global $Proj, $project_id, $tx_prefixes, $dm_array, $tx_array, $endt_fields, $regimen_fields;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$fields = array_merge($dm_array, $tx_array, $endt_fields, array('trt_suppcm_txstat'));
	$data = REDCap::getData('array', $record, $fields);
	$regimen_data = REDCap::getData('array', $record, $regimen_fields, $baseline_event_id);
	foreach ($data AS $subject_id => $subject) {
		$start_stack = array();
		$tx_start_date = null;
		$stop_date = null;
		$age_at_start = null;
		$end_values = array();
		foreach ($subject AS $event_id => $event) {
			/**
			 * build dm_rfstdtc array
			 */
			foreach ($tx_array AS $tx_start) {
				if ($event[$tx_start] != '') {
					$start_stack[] = $event[$tx_start];
				}
			}
			/**
			 * build entdtc array
			 */
			foreach ($endt_fields AS $endt_field) {
				if ($event[$endt_field] != '') {
					$end_values[$event_id][$endt_field] = $event[$endt_field];
				}
			}
		}
		/**
		 * SUBJECT LEVEL
		 */
		rsort($start_stack);
		$tx_start_date = get_end_of_array($start_stack);
		/**
		 * dm_rfstdtc
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $tx_start_date, $subject[$baseline_event_id]['dm_rfstdtc'], 'dm_rfstdtc', $debug);
		/**
		 * dependent on TX start
		 */
		if (isset($tx_start_date)) {
			/**
			 * Age at start of treatment
			 * age_suppvs_age
			 */
			if ($subject[$baseline_event_id]['dm_brthyr'] != '') {
				$birth_year = $subject[$baseline_event_id]['dm_brthyr'];
			} elseif ($subject[$baseline_event_id]['dm_brthdtc'] != '') {
				$birth_year = substr($subject[$baseline_event_id]['dm_brthdtc'], 0, 4);
			} else {
				$birth_year = '';
			}
			if (isset($birth_year) && $birth_year != '') {
				$tx_start_year = substr($tx_start_date, 0, 4);
				$age_at_start = ($tx_start_year - $birth_year) > 0 ? $tx_start_year - $birth_year : null;
			}
			update_field_compare($subject_id, $project_id, $baseline_event_id, $age_at_start, $subject[$baseline_event_id]['age_suppvs_age'], 'age_suppvs_age', $debug);
			/**
			 * Date of last dose of HCV treatment or Treatment stop date
			 * dis_suppfa_txendt
			 */
			$stack = array();
			if (array_search_recursive('ONGOING', $end_values) === false) {
				foreach ($tx_prefixes AS $endt_prefix) {
					foreach ($end_values AS $event) {
						if ($event[$endt_prefix . '_cmendtc'] != '' && ($event[$endt_prefix . '_suppcm_cmtrtout'] == 'COMPLETE') || $event[$endt_prefix . '_suppcm_cmtrtout'] == 'PREMATURELY_DISCONTINUED') {
							$stack[] = $event[$endt_prefix . '_cmendtc'];
							d('PREFIX ' . $endt_prefix, $event);
						}
					}
				}
			}
			sort($start_stack);
			sort($stack);
			$last_date_in_start_stack = get_end_of_array($start_stack);
			$last_date_in_stack = get_end_of_array($stack);
			$stop_date = $last_date_in_stack < $last_date_in_start_stack ? null : $last_date_in_stack;
			d($end_values);
			d($start_stack);
			d($stack);
			d($last_date_in_start_stack);
			d($last_date_in_stack);
			d($stop_date);
			update_field_compare($subject_id, $project_id, $baseline_event_id, $stop_date, $subject[$baseline_event_id]['dis_suppfa_txendt'], 'dis_suppfa_txendt', $debug);
			/**
			 * HCV Treatment duration
			 */
			if (isset($stop_date)) {
				$tx_start_date_obj = new DateTime($tx_start_date);
				$tx_stop_date_obj = new DateTime($stop_date);
				$tx_duration = $tx_start_date_obj->diff($tx_stop_date_obj);
				$dis_dsstdy = $tx_duration->format('%R%a') + 1;
				update_field_compare($subject_id, $project_id, $baseline_event_id, $dis_dsstdy, $subject[$baseline_event_id]['dis_dsstdy'], 'dis_dsstdy', $debug);
			}
		}
		/**
		 * update treatment regimen
		 */
		$txstat = isset($tx_start_date) ? 'Y' : 'N';
		$regimen = get_regimen($regimen_data[$subject_id], $subject[$baseline_event_id]['eot_dsterm'], $txstat);
		update_field_compare($subject_id, $project_id, $baseline_event_id, $regimen['actarm'], $subject[$baseline_event_id]['dm_actarm'], 'dm_actarm', $debug);
		update_field_compare($subject_id, $project_id, $baseline_event_id, $regimen['actarmcd'], $subject[$baseline_event_id]['dm_actarmcd'], 'dm_actarmcd', $debug);
		/**
		 * treatment started flag
		 */
		update_field_compare($subject_id, $project_id, $baseline_event_id, $txstat, $subject[$baseline_event_id]['trt_suppcm_txstat'], 'trt_suppcm_txstat', $debug);
	}
}

/**
 * @param $record
 * @param $debug
 */
function set_cbc_flags($record, $debug)
{
	global $Proj, $project_id, $daa_array, $plat140_fields;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$fields = array_merge(array('dm_rfstdtc'), $daa_array, $plat140_fields);
	$data = REDCap::getData('array', $record, $fields);
	if ($debug) {
		error_log(print_r($data, true));
	}
	foreach ($data AS $subject_id => $subject) {
		if ($subject[$baseline_event_id]['dm_rfstdtc'] != '') {
			$daa_stack = array();
			$has_low_platelets = false;
			$plat_values = array();
			/**
			 * event level
			 */
			foreach ($subject AS $event_id => $event) {
				/**
				 * build daa start array
				 */
				foreach ($daa_array AS $daa_start) {
					if ($event[$daa_start] != '') {
						$daa_stack[] = $event[$daa_start];
					}
				}
				/**
				 * build $plat_values
				 */
				foreach ($plat140_fields AS $plat140_field_name) {
					if ($event[$plat140_field_name] != '') {
						$plat_values[$event_id][$plat140_field_name] = $event[$plat140_field_name];
					}
				}
			}
			/**
			 * subject level
			 * Platelets < 140K at any time while on DAA?
			 */
			rsort($daa_stack);
			$daa_start_date = get_end_of_array($daa_stack);
			if (isset($daa_start_date)) {
				$daa_start_obj = new DateTime($daa_start_date);
				$daa_lower_date = new DateTime($daa_start_date);
				$daa_interval = new DateInterval('P6M');
				$daa_lower_date->sub($daa_interval);
				$daa_lower_date_fmt = $daa_lower_date->format('Y-m-d');
				$daa_start_date_fmt = $daa_start_obj->format('Y-m-d');
				foreach ($plat_values AS $plat_event_id => $plat_event) {
					if ($plat_event['plat_lbstresn'] != '' && $plat_event['cbc_lbdtc'] != '' && !$has_low_platelets) {
						if ($daa_lower_date_fmt <= $plat_event['cbc_lbdtc'] && $plat_event['cbc_lbdtc'] <= $daa_start_date_fmt && $plat_event['plat_lbstresn'] < '140') {
							$has_low_platelets = true;
						}
					}
					if ($plat_event['plat_im_lborres'] != '' && $plat_event['cbc_im_lbdtc'] != '' && !$has_low_platelets) {
						if ($daa_lower_date_fmt <= $plat_event['cbc_im_lbdtc'] && $plat_event['cbc_im_lbdtc'] <= $daa_start_date_fmt && $plat_event['plat_im_lborres'] < '140') {
							$has_low_platelets = true;
						}
					}
				}
			}
			$plat_lt_140k = $has_low_platelets ? 'Y' : 'N';
			update_field_compare($subject_id, $project_id, $baseline_event_id, (string)$plat_lt_140k, $subject[$baseline_event_id]['plt_suppfa_faorres'], 'plt_suppfa_faorres', $debug);
		}
	}

}

/**
 * @param $record
 * @param $debug
 */
function set_bmi($record, $debug)
{
	global $Proj, $project_id, $bmi_array;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$data = REDCap::getData('array', $record, $bmi_array, $baseline_event_id);
	if ($debug) {
		error_log(print_r($data, TRUE));
	}
	foreach ($data AS $subject_id => $subject) {
		/**
		 * height, weight, BMI
		 */
		$height = $data[$subject_id][$baseline_event_id]['height_vsorresu'] == 'IN' ? round(2.54 * $data[$subject_id][$baseline_event_id]['height_vsorres'], 2) : $data[$subject_id][$baseline_event_id]['height_vsorres'];
		update_field_compare($subject_id, $project_id, $baseline_event_id, $height, $data[$subject_id][$baseline_event_id]['height_suppvs_htcm'], 'height_suppvs_htcm', $debug);
		$weight = $data[$subject_id][$baseline_event_id]['weight_vsorresu'] == 'LB' ? round(.454 * $data[$subject_id][$baseline_event_id]['weight_vsorres'], 1) : $data[$subject_id][$baseline_event_id]['weight_vsorres'];
		update_field_compare($subject_id, $project_id, $baseline_event_id, $weight, $data[$subject_id][$baseline_event_id]['weight_suppvs_wtkg'], 'weight_suppvs_wtkg', $debug);
		$bmi = round($weight * 10000 / pow($height, 2));
		update_field_compare($subject_id, $project_id, $baseline_event_id, $bmi, $data[$subject_id][$baseline_event_id]['bmi_suppvs_bmi'], 'bmi_suppvs_bmi', $debug);
	}
}

/**
 * @param $record
 * @param $debug
 */
function set_svr_dates($record, $debug)
{
	global $Proj, $project_id, $dm_array, $hcv_fields;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$fields = array_merge($dm_array, $hcv_fields);
	$data = REDCap::getData('array', $record, $fields);
	if ($debug) {
		error_log(print_r($data, TRUE));
	}
	foreach ($data AS $subject_id => $subject) {
		$stop_date = $subject[$baseline_event_id]['dis_suppfa_txendt'] != '' ? $subject[$baseline_event_id]['dis_suppfa_txendt'] : null;
		$hcv_values = array();
		if (isset($stop_date)) {
			foreach ($subject AS $event_id => $event) {
				/**
				 * build HCVDT array
				 */
				foreach ($hcv_fields AS $hcv_field) {
					$hcv_values[$event_id][$hcv_field] = $event[$hcv_field];
				}
			}
			d($hcv_values);
			/**
			 * SVR 12 and 24 dates
			 * hcv_supplb_hcvdtct, hcv_lbdtc, ifn_cmendtc, hcv_suppfa_svr12dt
			 */
			$svr_classes = array(63 => 'hcv_suppfa_svr12dt', 147 => 'hcv_suppfa_svr24dt');
			$svr_range = 83;
			foreach ($svr_classes as $svr_class => $svr_field) {
				$hcv_dates_array = array();
				$lower_date = new DateTime($stop_date);
				$lower_date->add(new DateInterval("P" . $svr_class . "D"));
				$lower_svr_date = $lower_date->format('Y-m-d');
				$upper_date = new DateTime($stop_date);
				$upper_date->add(new DateInterval("P" . ($svr_class + $svr_range) . "D"));
				$upper_svr_date = $upper_date->format('Y-m-d');
				d($svr_field, $upper_svr_date);
				foreach ($hcv_values as $event) {
					if ($event['hcv_lbdtc'] != '') {
						$date_obj = new DateTime($event['hcv_lbdtc']);
						$svr_date = $date_obj->format('Y-m-d');
						if ($lower_svr_date <= $svr_date && $svr_date <= $upper_svr_date) {
							$hcv_dates_array[] = $svr_date;
						}
					}
					if ($event['hcv_im_lbdtc'] != '' && empty($hcv_dates_array)) {
						$date_obj = new DateTime($event['hcv_im_lbdtc']);
						$svr_date = $date_obj->format('Y-m-d');
						if ($lower_svr_date <= $svr_date && $svr_date <= $upper_svr_date) {
							$hcv_dates_array[] = $svr_date;
						}
					}
				}
				rsort($hcv_dates_array);
				$hcv_date = get_end_of_array($hcv_dates_array);
				update_field_compare($subject_id, $project_id, $baseline_event_id, $hcv_date, $subject[$baseline_event_id][$svr_field], $svr_field, $debug);
			}
		}
	}
}

/**
 * @param $record
 * @param $debug
 */
function set_cirrhosis($record, $debug)
{
	global $Proj, $project_id, $dm_array, $cirr_fields;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$fields = array_merge($dm_array, $cirr_fields);
	$data = REDCap::getData('array', $record, $fields, $baseline_event_id);
	if ($debug) {
		error_log(print_r($data, TRUE));
	}
	foreach ($data AS $subject_id => $subject) {
		/**
		 * Cirrhosis
		 */
		$cirrhotic = 'N';
		$fibrosis_scale = null;
		$secondary_indications = array();
		$force_cirrhotic = isset($subject[$baseline_event_id]['cirr_suppfa_cirrovrd']) && $subject[$baseline_event_id]['cirr_suppfa_cirrovrd'] == 'Y' ? true : false;
			$had_biopsy = isset($subject[$baseline_event_id]['livbp_mhoccur']) && $subject[$baseline_event_id]['livbp_mhoccur'] == 'Y' ? true : false;
			if ($subject[$baseline_event_id]['livbp_facat'] != '' && $subject[$baseline_event_id]['livbp_faorres'] != '') {
				/**
				 * if ishak or unknown scales were used, adjust the scale value
				 */
				if ($subject[$baseline_event_id]['livbp_facat'] == 'ISHAK' || $subject[$baseline_event_id]['livbp_facat'] == 'UNKNOWN') {
					if ($subject[$baseline_event_id]['livbp_faorres'] >= '5') {
						$fibrosis_scale = '4';
					} elseif ($subject[$baseline_event_id]['livbp_faorres'] == '3' || $subject[$baseline_event_id]['livbp_faorres'] == '4') {
						$fibrosis_scale = '3';
					}
				} else {
					$fibrosis_scale = $subject[$baseline_event_id]['livbp_faorres'];
				}
			} else {
				$fibrosis_scale = null;
			}
			/**
			 * secondary indications
			 */
			if ($subject[$baseline_event_id]['fib_lborres'] != '' && $subject[$baseline_event_id]['fib_lbtest'] != '') {
				switch ($subject[$baseline_event_id]['fib_lbtest']) {
					case 'FBRTST':
						$equiv_lo = .75;
						break;
					case 'FBRSPCT':
						/**
						 * what corresponds to an F4 when the threshold for F2-F4 is at 42?
						 */
						$equiv_lo = 69;
						break;
					case 'ELFG':
						$equiv_lo = '';
						break;
					case 'HEPASCR':
						/**
						 * score >= 0.5 corresponds to F2 - F4
						 */
						$equiv_lo = .84;
						break;
					case 'FBRMTR':
						$equiv_lo = '';
						break;
					case 'OTHER':
						$equiv_lo = '';
						break;
				}
				if ($subject[$baseline_event_id]['fib_lborres'] >= $equiv_lo) {
					$secondary_indications[] = 'Y';
				}
			}
			/**
			 * Fibroscan > 8.5 corresponds to F3
			 */
			if ($subject[$baseline_event_id]['fibscn_lborres'] >= 14.0) {
				$secondary_indications[] = 'Y';
			}
			if ($subject[$baseline_event_id]['asc_mhoccur'] == 'Y') {
				$secondary_indications[] = $subject[$baseline_event_id]['asc_mhoccur'];
			}
			if ($subject[$baseline_event_id]['pht_faorres'] == 'Y') {
				$secondary_indications[] = $subject[$baseline_event_id]['pht_faorres'];
			}
			if ($subject[$baseline_event_id]['egd_faorres'] == 'Y') {
				$secondary_indications[] = $subject[$baseline_event_id]['egd_faorres'];
			}
			if ($subject[$baseline_event_id]['plt_suppfa_faorres'] == 'Y') {
				$secondary_indications[] = $subject[$baseline_event_id]['plt_suppfa_faorres'];
			}
			d($had_biopsy);
			d($fibrosis_scale);
			d($secondary_indications);
			/**
			 * determine whether subject is cirrhotic, based upon protocol standard
			 */
		if ($had_biopsy && (isset($fibrosis_scale) && $fibrosis_scale >= '4')
		|| ($had_biopsy && (isset($fibrosis_scale) && $fibrosis_scale == '3') && count($secondary_indications) >= 1)
		|| (count($secondary_indications) >= 2)
		|| $force_cirrhotic) {
				$cirrhotic = 'Y';
		/*} elseif ($had_biopsy && (isset($fibrosis_scale) && $fibrosis_scale == '3') && count($secondary_indications) >= 1) {
				$cirrhotic = 'Y';
			} elseif (count($secondary_indications) >= 2) {
				$cirrhotic = 'Y';
		} elseif ($force_cirrhotic) {
			$cirrhotic = 'Y';*/
		}
		update_field_compare($subject_id, $project_id, $baseline_event_id, $cirrhotic, $subject[$baseline_event_id]['cirr_suppfa_cirrstat'], 'cirr_suppfa_cirrstat', $debug);
	}
}

/**
 * @param $record
 * @param $debug
 */
function set_deltas($record, $debug)
{
	global $Proj, $project_id;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$thresholds = array();
	$fields = array();
	$baselines = array();
	$all_deltas = array();
	$prefix_result = db_query("SELECT DISTINCT prefix, threshold FROM _target_lab_deltas WHERE project_id = '$project_id'");
	if ($prefix_result) {
		while ($prefixes = db_fetch_assoc($prefix_result)) {
			$thresholds[$prefixes['prefix']]['threshold'] = $prefixes['threshold'];
		}
		db_free_result($prefix_result);
	}
	foreach ($thresholds AS $key => $value) {
		$fields[] = $key . "_lbstresn";
		$fields[] = $key . "_im_lbstresn";
		$fields[] = $key . "_lbblfl";
		$fields[] = $key . "_im_lbblfl";
	}
	$data = REDCap::getData('array', $record, $fields);
	d($data);
	foreach ($data AS $subject_id => $subject) {
		foreach ($subject AS $event_id => $event) {
			foreach (array_keys($thresholds) AS $lab_prefix) {
				$all_deltas[$subject_id][$lab_prefix] = false;
				if ($event[$lab_prefix . '_lbblfl'] == 'Y' && $event[$lab_prefix . '_lbstresn'] != '') {
					$baselines[$subject_id][$lab_prefix] = $event[$lab_prefix . '_lbstresn'];
				}
				if ($event[$lab_prefix . '_im_lbblfl'] == 'Y' && $event[$lab_prefix . '_im_lbstresn'] != '') {
					$baselines[$subject_id][$lab_prefix] = $event[$lab_prefix . '_im_lbstresn'];
				}
			}
		}
		d($baselines);
		foreach ($subject AS $event_id => $event) {
			foreach (array_keys($thresholds) AS $lab_prefix) {
				$threshold = $thresholds[$lab_prefix]['threshold'];
				$baseline = $baselines[$subject_id][$lab_prefix];
				if (isset($baseline) && $baseline != '' && isset($threshold) && $threshold != '' && !$all_deltas[$subject_id][$lab_prefix]) {
					if (isset($event[$lab_prefix . '_lbstresn']) && $event[$lab_prefix . '_lbstresn'] != '') {
						if ($event[$lab_prefix . '_lbstresn'] - $baseline > $threshold) {
							$all_deltas[$subject_id][$lab_prefix] = true;
						}
					}
					if (isset($event[$lab_prefix . '_im_lbstresn']) && $event[$lab_prefix . '_im_lbstresn'] != '') {
						if ($event[$lab_prefix . '_im_lbstresn'] - $baseline > $threshold) {
							$all_deltas[$subject_id][$lab_prefix] = true;
						}
					}
				}
			}
		}
	}
	d($thresholds);
	d($all_deltas);
	foreach ($all_deltas AS $subject_id => $lab_prefix) {
		foreach ($lab_prefix AS $prefix => $had_delta) {
			$delta = $had_delta ? 'Y' : 'N';
			update_field_compare($subject_id, $project_id, $baseline_event_id, $delta, get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_suppdm_gtdelta', ''), $prefix . '_suppdm_gtdelta', $debug);
		}
	}
}

/**
 * @param $record
 * @param $debug
 */
function set_treatment_exp($record, $debug)
{
	global $Proj, $project_id;
	$trt_exp_array = array('simsof_mhoccur', 'simsofrbv_mhoccur', 'pegifn_mhoccur', 'triple_mhoccur', 'nopegifn_mhoccur', 'dm_suppdm_trtexp');
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	$data = REDCap::getData('array', $record, $trt_exp_array, $baseline_event_id);
	if ($debug) {
		error_log(print_r($data, TRUE));
	}
	foreach ($data AS $subject_id => $subject) {
		/**
		 * Are you experienced?
		 */
		$experienced = false;
		foreach ($subject AS $event_id => $event) {
			if ($event['simsof_mhoccur'] == 'Y' || $event['simsofrbv_mhoccur'] == 'Y' || $event['pegifn_mhoccur'] == 'Y' || $event['triple_mhoccur'] == 'Y' || $event['nopegifn_mhoccur'] == 'Y') {
				$experienced = true;
			}
		}
		$trt_exp = $experienced ? 'Y' : 'N';
		update_field_compare($subject_id, $project_id, $baseline_event_id, $trt_exp, $subject[$baseline_event_id]['dm_suppdm_trtexp'], 'dm_suppdm_trtexp', $debug);
	}
}

/**
 * @param $record
 * @param null $save_event_id
 * @param string $formtype 'abstracted', 'imported' or 'both'
 * @param $debug
 */
function set_egfr($record, $save_event_id = null, $formtype = 'both', $debug)
{
	global $Proj, $project_id, $egfr_fields, $egfr_im_fields;
	$baseline_event_id = $Proj->firstEventId;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$save_event_id = isset($save_event_id) && $save_event_id != $baseline_event_id ? array($baseline_event_id, $save_event_id) : $save_event_id;
	$dm_array = array('dm_rfstdtc', 'dm_race', 'dm_sex', 'age_suppvs_age', 'dis_dsstdy', 'creat_supplb_lbdtbl');
	switch ($formtype) {
		case 'both':
			$egfr_fields = array_merge($egfr_fields, $egfr_im_fields);
			break;
		case 'imported':
			$egfr_fields = $egfr_im_fields;
			break;
		default:
			break;
	}
	$fields = array_merge($dm_array, $egfr_fields);
	$data = REDCap::getData('array', $record, $fields, $save_event_id);
	foreach ($data AS $subject_id => $subject) {
		/**
		 * SUBJECT-LEVEL vars
		 */
		$tx_start_date = $subject[$baseline_event_id]['dm_rfstdtc'];
		$race = $subject[$baseline_event_id]['dm_race'];
		$sex = $subject[$baseline_event_id]['dm_sex'];
		$age = $subject[$baseline_event_id]['age_suppvs_age'];
		$creat_bl_date = $subject[$baseline_event_id]['creat_supplb_lbdtbl'];
		$race_factor = $race == 'BLACK_OR_AFRICAN_AMERICAN' ? 1.212 : 1;
		$sex_factor = $sex == 'F' ? 0.742 : 1;
		$chem_values = array();
		/**
		 * EVENT LEVEL ACTIONS
		 */
		if (isset($tx_start_date) && $tx_start_date != '') {
			foreach ($subject AS $event_id => $event) {
				/**
				 * build $chem_values
				 */
				foreach ($egfr_fields AS $chem_field_name) {
					if ($event[$chem_field_name] != '') {
						$chem_values[$event_id][$chem_field_name] = $event[$chem_field_name];
					}
				}
			}
			foreach ($chem_values as $chem_event => $values) {
				if ($formtype == 'abstracted' || $formtype == 'both') {
					d($values);
					$egfr = '';
					$is_baseline = '';
					if ($values['creat_lbstresn'] != '' && is_numeric($values['creat_lbstresn'])) {
						if (isset($creat_bl_date) && $creat_bl_date != '' && $creat_bl_date == $values['chem_lbdtc']) {
							$is_baseline = 'Y';
						}
						if ($race != '' && $sex != '' && $age != '') {
							$egfr = round((175 * pow($values['creat_lbstresn'], -1.154) * pow($age, -.203) * $sex_factor * $race_factor), 2);
						}
					}
					update_field_compare($subject_id, $project_id, $chem_event, $egfr, $values['egfr_lborres'], 'egfr_lborres', $debug);
					update_field_compare($subject_id, $project_id, $chem_event, $is_baseline, $values['egfr_lbblfl'], 'egfr_lbblfl', $debug);
				}
				if ($formtype == 'imported' || $formtype == 'both') {
					d($values);
					/**
					 * for egfr from imported, we need a standardized creat, it must be numeric and the trust field must not be 'N'
					 */
					$egfr = '';
					$is_baseline = '';
					if ($values['creat_im_lbstresn'] != '' && is_numeric($values['creat_im_lbstresn']) && $values['creat_im_nxtrust'] != 'N') {
						if (isset($creat_bl_date) && $creat_bl_date != '' && $creat_bl_date == $values['chem_im_lbdtc']) {
							$is_baseline = 'Y';
						}
						if ($race != '' && $sex != '' && $age != '') {
							$egfr = round((175 * pow($values['creat_im_lbstresn'], -1.154) * pow($age, -.203) * $sex_factor * $race_factor), 2);
						}
					}
					update_field_compare($subject_id, $project_id, $chem_event, $egfr, $values['egfr_im_lborres'], 'egfr_im_lborres', $debug);
					update_field_compare($subject_id, $project_id, $chem_event, $is_baseline, $values['egfr_im_lbblfl'], 'egfr_im_lbblfl', $debug);
				}
			}
		}
	}
}

/**
 * @param $record
 * @param null $save_event_id
 * @param string $formtype 'abstracted', 'imported' or 'both'
 * @param $debug
 */
function set_crcl($record, $save_event_id = null, $formtype = 'both', $debug)
{
	/**
	 * derives CRCL for a single event if $save_event_id is set. Derives CRCL for all events if $save_event_id = null
	 */
	global $Proj, $project_id, $dm_array, $crcl_fields, $crcl_im_fields, $bmi_array;
	$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
	Kint::enabled($enable_kint);
	$baseline_event_id = $Proj->firstEventId;
	switch ($formtype) {
		case 'both':
			$crcl_fields = array_merge($crcl_fields, $crcl_im_fields);
			break;
		case 'imported':
			$crcl_fields = $crcl_im_fields;
			break;
		default:
			break;
	}
	$fields = array_merge($dm_array, $crcl_fields, $bmi_array, array('creat_supplb_lbdtbl'));
	$save_event_id = isset($save_event_id) && $save_event_id != $baseline_event_id ? array($baseline_event_id, $save_event_id) : $save_event_id;
	$data = REDCap::getData('array', $record, $fields, $save_event_id);
	if ($debug) {
		error_log(print_r($data, TRUE));
	}
	foreach ($data AS $subject_id => $subject) {
		$chem_values = array();
		$sex = isset($subject[$baseline_event_id]['dm_sex']) ? $subject[$baseline_event_id]['dm_sex'] : 'F';
		$sex_factor = $sex == 'F' ? .85 : 1;
		$creat_bl_date = $subject[$baseline_event_id]['creat_supplb_lbdtbl'];
		foreach ($subject AS $event_id => $event) {
			/**
			 * build $chem_values
			 */
			foreach ($crcl_fields AS $chem_field_name) {
				if ($event[$chem_field_name] != '') {
					$chem_values[$event_id][$chem_field_name] = $event[$chem_field_name];
				}
			}
		}
		/**
		 * Creatinine Clearance (Cockcroft-Gault Equation)
		 * IF([chem_lbdtc] = "", null, round(((140 - ([chem_lbdtc].substring(0,4) - [brthyr])) * [weight_suppvs_wtkg] * (IF([dm_sex] = "0", .85, 1)) / (72 * [creat_lbstresn])), 0))
		 */
		if ($subject[$baseline_event_id]['dm_brthyr'] != '') {
			$birth_year = $subject[$baseline_event_id]['dm_brthyr'];
		} elseif ($subject[$baseline_event_id]['dm_brthdtc'] != '') {
			$birth_year = substr($subject[$baseline_event_id]['dm_brthdtc'], 0, 4);
		} else {
			$birth_year = '';
		}
		if ($birth_year != '' && $subject[$baseline_event_id]['weight_suppvs_wtkg'] != '') {
			foreach ($chem_values as $chem_event => $values) {
				if ($formtype == 'imported' || $formtype == 'both') {
					$is_baseline = '';
					$creatinine_im_clearance = null;
					if ($values['chem_im_lbdtc'] != '' && $values['creat_im_lbstresn'] != '') {
						if (isset($creat_bl_date) && $creat_bl_date != '' && $creat_bl_date == $values['chem_im_lbdtc']) {
							$is_baseline = 'Y';
						}
						$chem_im_age = (substr($values['chem_im_lbdtc'], 0, 4)) - $birth_year;
						$creatinine_im_clearance = round(((140 - $chem_im_age) * $subject[$baseline_event_id]['weight_suppvs_wtkg'] * $sex_factor) / (72 * $values['creat_im_lbstresn']));
					}
					update_field_compare($subject_id, $project_id, $chem_event, $creatinine_im_clearance, $values['crcl_im_lborres'], 'crcl_im_lborres', $debug);
					update_field_compare($subject_id, $project_id, $chem_event, $is_baseline, $values['crcl_im_lbblfl'], 'crcl_im_lbblfl', $debug);
				}
				if ($formtype == 'abstracted' || $formtype == 'both') {
					$is_baseline = '';
					$creatinine_clearance = null;
					if ($values['chem_lbdtc'] != '' && $values['creat_lbstresn'] != '') {
						if (isset($creat_bl_date) && $creat_bl_date != '' && $creat_bl_date == $values['chem_lbdtc']) {
							$is_baseline = 'Y';
						}
						$chem_age = (substr($values['chem_lbdtc'], 0, 4)) - $birth_year;
						$creatinine_clearance = round(((140 - $chem_age) * $subject[$baseline_event_id]['weight_suppvs_wtkg'] * $sex_factor) / (72 * $values['creat_lbstresn']));
					}
					update_field_compare($subject_id, $project_id, $chem_event, $creatinine_clearance, $values['crcl_lborres'], 'crcl_lborres', $debug);
					update_field_compare($subject_id, $project_id, $chem_event, $is_baseline, $values['crcl_lbblfl'], 'crcl_lbblfl', $debug);
				}
			}
		}
	}
}

function set_dag($record, $instrument, $debug) {
	global $project_id;
	/**
	 * SET Data Access Group based upon dm_usubjid prefix
	 */
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
}

/**
 * @param $record
 * @param null $form_event
 * @param $debug
 */
function set_immunosuppressant($record, $form_event = null, $debug)
{
	global $project_id;
	/**
	 * immunosuppressive?
	 */
	$fields = array('cm_cmdecod', 'cm_suppcm_cmimmuno');
	$data = REDCap::getData('array', $record, $fields, $form_event);
	foreach ($data AS $subject_id => $subject) {
		foreach ($subject AS $event_id => $event) {
			if ($event['cm_cmdecod'] != '') {
				$immun_flag = 'N';
				$immun_meds = array();
				$immun_meds_result = db_query("SELECT * FROM _target_meds_of_interest WHERE cm_cmcat NOT IN ('steroid', 'PPI') AND cm_cmtrt = '{$event['cm_cmdecod']}'");
				if ($immun_meds_result) {
					while ($immun_meds_row = db_fetch_assoc($immun_meds_result)) {
						$immun_meds[] = $immun_meds_row['cm_cmtrt'];
					}
					db_free_result($immun_meds_result);
				}
				if (count($immun_meds) != 0) {
					$immun_flag = 'Y';
					d($immun_meds);
				}
				update_field_compare($subject_id, $project_id, $event_id, $immun_flag, $event['cm_suppcm_cmimmuno'], 'cm_suppcm_cmimmuno', $debug);
			}
		}
	}
}

/**
 * @param $record
 * @param $form_event
 * @param $debug
 */
function set_ppi($record, $form_event = null, $debug)
{
	global $project_id;
	/**
	 * Proton pump inhibitor?
	 */
	$fields = array('cm_cmdecod', 'cm_suppcm_cmppi');
	$data = REDCap::getData('array', $record, $fields, $form_event);
	foreach ($data AS $subject_id => $subject) {
		foreach ($subject AS $event_id => $event) {
			if ($event['cm_cmdecod'] != '') {
				$ppi_flag = 'N';
				$ppi_meds = array();
				$ppi_meds_result = db_query("SELECT * FROM _target_meds_of_interest WHERE cm_cmcat = 'PPI' AND cm_cmtrt = '{$event['cm_cmdecod']}'");
				if ($ppi_meds_result) {
					while ($ppi_meds_row = db_fetch_assoc($ppi_meds_result)) {
						$ppi_meds[] = $ppi_meds_row['cm_cmtrt'];
					}
					db_free_result($ppi_meds_result);
				}
				if (count($ppi_meds) != 0) {
					$ppi_flag = 'Y';
				}
				update_field_compare($subject_id, $project_id, $event_id, $ppi_flag, $event['cm_suppcm_cmppi'], 'cm_suppcm_cmppi', $debug);
			}
		}
	}
}