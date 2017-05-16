<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 2014-07-16
 */
/**
 * TESTING
 */
$debug = false;
$timer_start = microtime(true);
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
$project = new Project();
$baseline_event_id = $project->firstEventId;
$monitors = array('all' => 'All', 'dianne_mattingly' => 'Dianne Mattingly', 'wendy_robertson' => 'Wendy Robertson');
?>
<h3>Transplant Monitoring Report</h3>
<div class="blue" style="max-width: 700px;">
<p>This report contains records from subjects who have:</p>
	<ul><li>Derived treatment start at least 24 weeks prior to the day the report is run</li>
	<li>Derived treatment stop</li>
	<li>Liver transplant in their medical history</li></ul>
</div>
<br />
	<form action="<?php echo $_SERVER['PHP_SELF'] . '?pid=' . $project_id; ?> " method="post">
		<div class="data" style='max-width:700px;'>
			<select class="x-form-text x-form-field"
		        name="monitor_name"
		        style="height: 22px; padding-right: 0;"
		        id="monitor_name">
				<option value>-- Select Monitor --</option>
				<?php foreach($monitors AS $key => $value) {
					if ($_POST['monitor_name'] == $key) {
						echo "<option value='$key' selected>$value</option>";
					} else {
						echo "<option value='$key'>$value</option>";
					}
				} ?>
			</select>
				&nbsp;&nbsp;
			<input type='submit' value='Create Report'/>
		</div>
	</form>
<?php
if (isset($_POST['monitor_name'])) {
	/**
	 * initialize variables
	 */
	$timer_start = microtime(true);
	$monitor_name = $_POST['monitor_name'];
	$table_csv = "";
	$export_filename = "TRANSPLANT_MONITORING_" . strtoupper($monitor_name);
	/**
	 * get sim/sof data
	 */
	$fields = array('sim_cmstdtc', 'sof_cmstdtc');
	$data = REDCap::getData('array', '', $fields);
	/**
	 * get TX history data
	 */
	$hist_fields = array("dm_rfstdtc", "dis_suppfa_txendt", "livr_mhoccur");
	$hist_data = REDCap::getData('array', '', $hist_fields, $baseline_event_id);
	$subject_sql = "SELECT data.record AS subjid, IF (demo.username IS NULL, 'N', 'Y') AS demo_locker, IF (eot.username IS NULL, 'N', 'Y') AS eot_locker, dsterm.value AS eot_status, geno.value AS genotype FROM
				(SELECT DISTINCT record, project_id FROM `redcap_data`) data
				LEFT OUTER JOIN
				(SELECT * FROM `redcap_locking_data` WHERE form_name = 'demographics') demo
				ON data.record = demo.record AND data.project_id = demo.project_id
				LEFT OUTER JOIN
				(SELECT * FROM `redcap_locking_data` WHERE form_name = 'early_discontinuation_eot') eot
				ON data.record = eot.record AND data.project_id = eot.project_id
				LEFT OUTER JOIN
				(SELECT * FROM redcap_data WHERE field_name = 'eot_dsterm') dsterm
				ON data.record = dsterm.record AND data.project_id = dsterm.project_id
				LEFT OUTER JOIN
				(SELECT * FROM redcap_data WHERE field_name = 'hcvgt_lborres') geno
				ON data.record = geno.record AND data.project_id = geno.project_id
				WHERE data.project_id = '$project_id'";
	switch ($monitor_name) {
		case 'wendy_robertson':
			$subject_sql .= " AND MOD(data.record,2)=0";
			break;
		case 'dianne_mattingly':
			$subject_sql .= " AND MOD(data.record,2)=1";
			break;
		default:
			break;
	}
	$subject_sql .= " ORDER BY abs(data.record) ASC";
	$subject_result = db_query($subject_sql);
	if ($subject_result) {
		while ($subject_row = db_fetch_assoc($subject_result)) {
			$subject_array[$subject_row['subjid']] = array();
			foreach ($subject_row AS $key => $value) {
				if ($key != 'subjid') {
					$subject_array[$subject_row['subjid']][$key] = $value;
				}
			}
		}
	}
	foreach ($hist_data AS $subject_id => $subject) {
		if ($debug) {
			show_var($subject_id, 'dm_usubjid', 'blue');
		}
		foreach ($subject AS $event_id => $event) {
			if (($event['dm_rfstdtc'] != '' && $event['dm_rfstdtc'] < add_date(date("Y-m-d"), -168)) && $event['dis_suppfa_txendt'] != '' && $event['livr_mhoccur'] == 'Y') {
				if ($debug) {
					show_var($event, 'EVENT');
				}
				if ($debug) {
					//show_var($subject_sql);
				}
				/**
				 * initialize the working arrays
				 */
				$data_row = array();
				$data_row['Subject ID'] = $subject_id;
				$data_row['Demographics Monitored?'] = '--';
				$data_row['EOT Monitored?'] = '--';
				$data_row['EOT Status'] = '--';
				$data_row['SIM-SOF Containing Regimen?'] = '--';
				$data_row['HCV Genotype'] = '--';
				if ($debug) {
					show_var($subject_array[$subject_id], 'locking');
				}
				/**
				 * Add data to row
				 */
				if (isset($subject_array[$subject_id]['demo_locker'])) {
					$data_row['Demographics Monitored?'] = $subject_array[$subject_id]['demo_locker'];
				}
				if (isset($subject_array[$subject_id]['eot_locker'])) {
					$data_row['EOT Monitored?'] = $subject_array[$subject_id]['eot_locker'];
				}
				if (isset($subject_array[$subject_id]['eot_status'])) {
					$data_row['EOT Status'] = $subject_array[$subject_id]['eot_status'];
				}
				/**
				 * derive whether regimen includes sim+sof
				 */
				foreach ($data[$subject_id] AS $reg_event) {
					if ($reg_event['sim_cmstdtc'] != '' && $reg_event['sof_cmstdtc'] != '') {
						$data_row['SIM-SOF Containing Regimen?'] = 'Y';
					} else {
						$data_row['SIM-SOF Containing Regimen?'] = 'N';
					}
				}
				if (isset($subject_array[$subject_id]['genotype'])) {
					$data_row['HCV Genotype'] = $subject_array[$subject_id]['genotype'];
				}
				$row_csv = implode(',', $data_row) . "\n";
				$table_csv .= $row_csv;
			}
		}
	}
	$headers = implode(',', array_keys($data_row)) . "\n";
	if (!$debug) {
		create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, $export_filename, $debug);
	} else {
		show_var($table_csv, 'TABLE', 'red');
	}
	$timer_stop = microtime(true);
	$timer_time = number_format(($timer_stop - $timer_start), 2);
	echo 'This page loaded in ', $timer_time / 60, ' minutes';
}