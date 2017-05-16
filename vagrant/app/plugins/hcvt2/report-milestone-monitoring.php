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
$monitors = array('all' => 'All', 'dianne_mattingly' => 'Dianne Mattingly', 'wendy_robertson' => 'Wendy Robertson');
?>
	<h3>Milestone Monitoring Report</h3>
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
	$monitor_name = $_POST['monitor_name'];
	/**
	 * initialize variables
	 */
	$table_csv = "";
	$export_filename = "MILESTONE_MONITORING_" . strtoupper($monitor_name);
	/**
	 * get sim/sof data
	 */
	$fields = array('sim_cmstdtc', 'sof_cmstdtc');
	$data = REDCap::getData('array', '', $fields);
	/**
	 * WORKING SET OF STUDY_IDs
	 */
	$subject_sql = "SELECT data.record AS subjid, IF (demo.username IS NULL, 'N', 'Y') AS demo_locker, IF (fib.username IS NULL, 'N', 'Y') AS fibro_locker, IF (eot.username IS NULL, 'N', 'Y') AS eot_locker, dsterm.value AS eot_status FROM
	(SELECT DISTINCT record, project_id FROM `redcap_data`) data
	LEFT OUTER JOIN
	(SELECT * FROM `redcap_locking_data` WHERE form_name = 'demographics') demo
	ON data.record = demo.record AND data.project_id = demo.project_id
	LEFT OUTER JOIN
	(SELECT * FROM `redcap_locking_data` WHERE form_name = 'fibrosis_staging') fib
	ON data.record = fib.record AND data.project_id = fib.project_id
	LEFT OUTER JOIN
	(SELECT * FROM `redcap_locking_data` WHERE form_name = 'early_discontinuation_eot') eot
	ON data.record = eot.record AND data.project_id = eot.project_id
	LEFT OUTER JOIN
	(SELECT * FROM redcap_data WHERE field_name = 'eot_dsterm') dsterm
	ON data.record = dsterm.record AND data.project_id = dsterm.project_id
	WHERE data.project_id = '26'";
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
	if ($debug) {
		show_var($subject_sql);
	}
	$subject_result = db_query($subject_sql);
	if ($subject_result) {
		while ($subject_row = db_fetch_assoc($subject_result)) {
			/**
			 * initialize the working arrays
			 */
			$data_row = array();
			$data_row['Subject ID'] = '--';
			$data_row['Demographics Monitored?'] = '--';
			$data_row['Fibrosis Monitored?'] = '--';
			$data_row['EOT Monitored?'] = '--';
			$data_row['EOT Status'] = '--';
			$data_row['SIM-SOF?'] = '--';
			/**
			 * Freeze the study_id for future use and start the data row
			 */
			if (isset($subject_row['subjid'])) {
				$subjid = $subject_row['subjid'];
				$data_row['Subject ID'] = $subjid;
			}
			if (isset($subject_row['demo_locker'])) {
				$data_row['Demographics Monitored?'] = $subject_row['demo_locker'];
			}
			if (isset($subject_row['fibro_locker'])) {
				$data_row['Fibrosis Monitored?'] = $subject_row['fibro_locker'];
			}
			if (isset($subject_row['eot_locker'])) {
				$data_row['EOT Monitored?'] = $subject_row['eot_locker'];
			}
			if (isset($subject_row['eot_status'])) {
				$data_row['EOT Status'] = $subject_row['eot_status'];
			}
			/**
			 * derive whether regimen includes sim+sof
			 */
			foreach ($data[$subjid] AS $event) {
				if ($event['sim_cmstdtc'] != '' && $event['sof_cmstdtc'] != '') {
					$data_row['SIM-SOF?'] = 'Y';
				} else {
					$data_row['SIM-SOF?'] = 'N';
				}
			}
			$row_csv = implode(',', $data_row) . "\n";
			$table_csv .= $row_csv;
		}
	}
	$headers = implode(',', array_keys($data_row)) . "\n";
	if (!$debug) {
		create_download($lang, $app_title, $userid, $headers, $user_rights, $table_csv, '', $parent_chkd_flds, $project_id, $export_filename, $debug);
	} else {
		show_var($table_csv, 'TABLE', 'red');
	}
}