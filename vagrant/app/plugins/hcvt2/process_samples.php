<?php
/**
 * Created by Ken Bergquist for HCV-TARGET.
 * User: kbergqui
 * Date: 3/25/2015
 * Time: 4:06 PMish
 * Purpose: Receive a CSV file name from select_samples.php, get a file handle for it, and fgetcsv it into the HCV-TARGET Biorepository Samples form
 */
/**
 * TESTING
 */
$debug = false;
$subjects = ''; // '' = ALL
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restrict use of this plugin to the STEADFAST Labs project
 */
REDCap::allowProjects('26');
/**
 * instantiate system-level variables
 */
global $Proj;
$first_event = $Proj->firstEventId;
$site_array = array();
$stream_array = array();
$dm_usubjid_array = array();
/**
 * if we have a numeric file id in the query string...
 */
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
	/**
	 * cast it to an int so we can use it to retrieve the file from the repository
	 */
	$id = (int)$_GET['id'];
	/**
	 * Get the document info from the database
	 */
	$sql = "SELECT d.docs_size,d.docs_type,d.export_file,d.docs_name,e.docs_id,m.stored_name,d.docs_file
	FROM redcap_docs d
	LEFT JOIN redcap_docs_to_edocs e ON e.docs_id=d.docs_id
	LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
	WHERE d.docs_id = '" . $id . "' and d.project_id = '" . $project_id . "';";
	$result = db_query($sql);
	if ($result) {
		/**
		 * get site names by site ID
		 */
		$site_result = db_query("SELECT LEFT(group_name, 3) AS site_id, SUBSTR(group_name, INSTR(group_name, ' - ')+3) AS group_name FROM `redcap_data_access_groups` WHERE project_id = '$project_id'");
		if ($site_result) {
			while ($site_row = db_fetch_assoc($site_result)) {
				$site_array[$site_row['site_id']] = $site_row['group_name'];
			}
		}
		/**
		 * get dm_subjid by dm_usubjid in array form
		 */
		$dm_usubjid_result = db_query("SELECT DISTINCT value AS dm_usubjid, record FROM `redcap_data` WHERE project_id = '$project_id' AND field_name = 'dm_usubjid'");
		if ($dm_usubjid_result) {
			while ($dm_usubjid_row = db_fetch_assoc($dm_usubjid_result)) {
				$dm_usubjid_array[$dm_usubjid_row['dm_usubjid']] = $dm_usubjid_row['record'];
			}
		}
		/**
		 * get bstype enum
		 */
		$enum_array = array();
		$enum_array2 = array();
		$enum_result = db_query("SELECT DISTINCT element_enum FROM redcap_metadata WHERE project_id = '$project_id' AND field_name LIKE 'bs_%_bstype' LIMIT 1");
		if ($enum_result) {
			$enum_raw = db_result($enum_result, 'element_enum');
			$enum_array = explode(' \n ', $enum_raw);
			foreach ($enum_array AS $enum_row) {
				$enum_array2 = explode(', ', $enum_row);
				$enum[strtolower($enum_array2[1])] = $enum_array2[0];
			}
		}
		/**
		 * get data
		 */
		$subject_data = REDCap::getData('array', $subjects, array('dm_usubjid'), $first_event);
		$fields = array('bs_bsstdtc');
		$base_fields_array = array('sample_code' => 'bs_%_bscode', 'sample_type' => 'bs_%_bstype', 'sample_onhand' => 'bs_%_bsonhand');
		for ($i = 1; $i <= 20; $i++) {
			foreach ($base_fields_array AS $base_key => $base_field) {
				$fields[] = str_replace('%', $i, $base_field);
			}
		}
		if ($debug) {
			//show_var($fields, 'FIELDS');
		}
		$data = REDCap::getData('array', $subjects, $fields);
		// Get query object
		$ddata = db_fetch_object($result);

		// Get file attributes
		$export_file = $ddata->export_file;
		$name = $docs_name = $ddata->docs_name;
		$name = preg_replace("/[^a-zA-Z-._0-9]/", "_", $name);
		$name = str_replace("__", "_", $name);
		$name = str_replace("__", "_", $name);
		/**
		 * If we've been passed a CSV and it's not an export file
		 */
		if (strtolower(substr($name, -4)) == ".csv" && !$export_file) {
			if (($handle = fopen(EDOC_PATH . $ddata->stored_name, 'r')) !== FALSE) {
				$row = 0;
				$biospecimen_header = array();
				$biospecimen_data = array();
				while (($file_data = fgetcsv($handle, 1000, ",")) !== FALSE) {
					$num = count($file_data);
					if ($row == 0) {
						for ($c = 0; $c < $num; $c++) {
							$biospecimen_header[] = $file_data[$c];
						}
					} else {
						$c = 0;
						foreach ($biospecimen_header AS $biospecimen_key) {
							$biospecimen_data[$row - 1][$biospecimen_key] = $file_data[$c];
							$c++;
						}
					}
					$row++;
				}
				fclose($handle);
				/**
				 * loop through each row of the file, adding to an array of incoming stream data
				 */
				foreach ($biospecimen_data AS $biospecimen_row) {
					$this_date_array = explode('/', $biospecimen_row['Collection Date']);
					$this_iso_date = $this_date_array[2] . '-' . $this_date_array[0] . '-' . $this_date_array[1];
					$stream_array[$biospecimen_row['Patient ID']][$this_iso_date][$biospecimen_row['Specimen Bar Code']] = $biospecimen_row['Specimen Type'];
				}
				/**
				 * loop through each existing record, adding it to an array of the current state
				 */
				$sample_code = NULL;
				$sample_type = NULL;
				$sample_onhand = NULL;
				$state_array = array();
				foreach ($data as $subject_id => $subject) {
					foreach ($subject as $event_id => $event) {
						for ($i = 1; $i <= ((count($event) - 1) / 3); $i++) {
							foreach ($base_fields_array AS $field_type => $field_name) {
								$$field_type = str_replace('%', $i, $field_name);
							}
							if ($event[$sample_code] != '') {
								$state_array[$subject_id][$event_id][$event['bs_bsstdtc']][$i][$event[$sample_code]] = $event[$sample_type];
							}
						}
					}
				}
				/**
				 * inside the state, we know about inventory that's been removed, but don't know about new samples
				 */
				foreach ($state_array as $subject_id => $subject) {
					$stream_usubjid = array_search($subject_id, $dm_usubjid_array);
					if (isset($stream_usubjid)) {
						foreach ($subject AS $state_event_id => $state_event) {
							foreach ($state_event AS $state_date => $field_set) {
								$next_field = count($field_set) + 1;
								/**
								 * check for samples removed from inventory
								 */
								foreach ($field_set AS $field_id => $state_samples) {
									$sample_onhand = str_replace('%', $field_id, $base_fields_array['sample_onhand']);
									foreach ($state_samples as $code => $type) {
										/**
										 * if this sample isn't in the stream, set its onhand = N
										 */
										if (!array_key_exists($code, $stream_array[$stream_usubjid][$state_date])) {
											update_field_compare($subject_id, $project_id, $state_event_id, '', array_search('1', $data[$subject_id][$state_event_id][$sample_onhand]), $sample_onhand, $debug);
										}
									}
								}
							}
						}
					}
				}
				/**
				 * inside the stream, we know about new samples and don't know about samples removed from inventory
				 */
				foreach ($stream_array AS $dm_usubjid => $stream_event) {
					$record = $dm_usubjid_array[$dm_usubjid];
					/**
					 * if we don't have a $record, it's not a current PT in the study
					 */
					if (isset($record)) {
						$site_name = $site_array[substr($dm_usubjid, 0, 3)];
						ksort($stream_event);
						if ($debug) {
							show_var($stream_event, "$record ($dm_usubjid)", 'blue');
						}
						$event_count = 1;
						$next_event = count($state_array[$record]) + 1;
						foreach ($stream_event AS $stream_date => $stream_samples) {
							ksort($stream_samples);
							$sample_code = NULL;
							$sample_type = NULL;
							$sample_onhand = NULL;
							/**
							 * if this $stream_date is not found in the $state, it's a new event
							 */
							if (!array_key_exists_recursive($stream_date, $state_array[$record])) {
								/**
								 * new event, start with the first field group
								 */
								$field_count = 1;
								$next_event_id = $Proj->getEventIdUsingUniqueEventName($next_event);
								/**
								 * new event gets a new date
								 */
								update_field_compare($record, $project_id, $next_event_id, $stream_date, $data[$record][$next_event_id]['bs_bsstdtc'], 'bs_bsstdtc', $debug);
								/**
								 * loop over the new samples and add them to the form
								 */
								foreach ($stream_samples AS $barcode => $type) {
									if (!array_key_exists_recursive($barcode, $state_array[$record])) {
										foreach ($base_fields_array AS $field_type => $field_name) {
											$$field_type = str_replace('%', $field_count, $field_name);
										}
										update_field_compare($record, $project_id, $next_event_id, $barcode, $data[$record][$next_event_id][$sample_code], $sample_code, $debug);
										update_field_compare($record, $project_id, $next_event_id, $enum[strtolower($type)], $data[$record][$next_event_id][$sample_type], $sample_type, $debug);
										update_field_compare($record, $project_id, $next_event_id, 'Y', array_search('1', $data[$record][$next_event_id][$sample_onhand]), $sample_onhand, $debug);
										$field_count++;
									}
								}
								$next_event++;
								/**
								 * if this stream date is in the current state, we're adding new samples to an existing event
								 */
							} else {
								/**
								 * get the event_id from the event name ($event_count)
								 */
								$this_event_id = $Proj->getEventIdUsingUniqueEventName($event_count);
								/**
								 * get the next available field group on this event to accept the new samples
								 */
								$next_field = (count($state_array[$record][$this_event_id]) - 1) / 3;
								/**
								 * check for new samples on this stream date to add to state
								 */
								foreach ($stream_samples AS $barcode => $type) {
									if (!array_key_exists_recursive($barcode, $state_array[$record])) {
										foreach ($base_fields_array AS $field_type => $field_name) {
											$$field_type = str_replace('%', $next_field, $field_name);
										}
										update_field_compare($record, $project_id, $this_event_id, $barcode, $data[$record][$this_event_id][$sample_code], $sample_code, $debug);
										update_field_compare($record, $project_id, $this_event_id, $enum[strtolower($type)], $data[$record][$this_event_id][$sample_type], $sample_type, $debug);
										update_field_compare($record, $project_id, $this_event_id, 'Y', array_search('1', $data[$record][$this_event_id][$sample_onhand]), $sample_onhand, $debug);
										$next_field++;
									}
								}
								$event_count++;
							}
						}
					}
				}
			} else {
				echo "<span class='red'><h3>Unable to comply. Could not open file handle.</h3></span>";
			}
		} else {
			echo "<span class='red'><h1>Please return to the previous page and select a CSV file.</h1></span>";
		}
	}
}
$timer['main_end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;