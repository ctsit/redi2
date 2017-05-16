<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 12/18/13
 * Time: 9:20 AM
 */
$debug = false;
$subjects = ''; // '' = ALL
$timer = array();
$timer['start'] = microtime(true);
/**
 * includes
 */
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . '/DataExport/functions.php';

// Restrict access to the desired projects
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
$project = new Project();
Kint::enabled($debug);

$query = array();
$constants = array();
$constants['STUDYID'] = strtoupper(substr($project->project['project_name'], 0, 4) . substr($project->project['project_name'], strpos($project->project['project_name'], '_') + 1, 1));
$constants['DOMAIN'] = 'SUPP';
echo "<h3>This plugin first truncates $table_name then inserts {$constants['DOMAIN']} domain values.</h3>";
/**
 * full list
 */
//$supp_domains = array('AE', 'CM', 'DM', 'DS', 'FA', 'IE', 'LB', 'MH', 'SU', 'VS');
/**
 * short list
 */
$supp_domains = array('AE', 'CM', 'FA', 'LB');
//$supp_domains = array('CM');
$timer['end_main'] = microtime(true);
$table_name = "_{$constants['STUDYID']}_{$constants['DOMAIN']}";
/**
 * end Main Loop
 */
$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name` (
	  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
	  `RDOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
	  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
	  `IDVAR` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IDVARVAL` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `QNAM` VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL,
	  `QLABEL` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
	  `QVAL` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
	  `QORIG` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
	  KEY `rdomain` (`RDOMAIN`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
$truncate_query = "TRUNCATE TABLE $table_name";
$columns = "(STUDYID, RDOMAIN, USUBJID, IDVAR, IDVARVAL, QNAM, QLABEL, QVAL, QORIG)";
$sql = "INSERT INTO $table_name
	select distinct 'HCVT2' AS `STUDYID`,
	`supp`.`RDOMAIN` AS `RDOMAIN`,
	concat('HCVT2','-',`subj`.`value`) AS `USUBJID`,
	`idvars`.`supp_idvar` AS `IDVAR`,
	if((isnull(`idvarvals`.`value`) and (`idvarvaldesc`.`element_label` is not null) AND `supp`.`rdomain` <> 'LB'),`idvarvaldesc`.`element_label`,if((`idvarvals`.`value` = 'OTHER'),`idvarothvals`.`value`,`idvarvals`.`value`)) AS `IDVARVAL`,
	`supp`.`QNAM` AS `QNAM`,
	left(`supp`.`QLABEL`,100) AS `QLABEL`,
	left(`vals`.`value`,100) AS `QVAL`,
	if((`supp`.`form_name` like 'derived%' OR `supp`.`form_name` LIKE '%coding'),'DERIVED','CRF') AS `QORIG` from
	(
	        (
	                (
	                        (
	                                (`redcap`.`redcap_data` `subj`
	                                        join (`redcap`.`_hcvt2_supp_metadata` `supp` join `redcap`.`redcap_data` `vals`) on (
	                                                ((`vals`.`field_name` = `supp`.`field_name`)
	                                                and (`subj`.`record` = `vals`.`record`)
	                                                and (`subj`.`project_id` = `vals`.`project_id`))
	                                        )
	                                )
	                                left join `redcap`.`_hcvt2_supp_map` `idvars` on (
	                                        (`idvars`.`supp_var` = convert(`supp`.`QNAM` using utf8))
	                                )
	                        )
	                        left join `redcap`.`redcap_metadata` `idvarvaldesc` on (
	                                (`idvarvaldesc`.`field_name` = (convert(lcase(concat(`supp`.`prefix`,'_',`idvars`.`supp_idvar`)) using utf8) collate utf8_unicode_ci))
	                        )
	                )
	                left join `redcap`.`redcap_data` `idvarvals` on (
	                        ((`idvarvals`.`event_id` = `vals`.`event_id`)
	                        and (`idvarvals`.`record` = `subj`.`record`)
	                        and (`idvarvals`.`field_name` = (convert(lcase(concat(`supp`.`prefix`,'_',`idvars`.`supp_idvar`)) using utf8) collate utf8_unicode_ci))
	                        and (`idvarvals`.`project_id` = `subj`.`project_id`))
	                )
	        )
	        left join `redcap`.`redcap_data` `idvarothvals` on (
	                ((`idvarothvals`.`event_id` = `vals`.`event_id`)
	                and (`idvarothvals`.`record` = `subj`.`record`)
	                and (`idvarothvals`.`field_name` = (convert(lcase(concat(`supp`.`prefix`,'_oth_',`idvars`.`supp_idvar`)) using utf8) collate utf8_unicode_ci))
	                and (`idvarothvals`.`project_id` = `subj`.`project_id`))
	        )
	)
	where ((`subj`.`field_name` = 'dm_usubjid')
	        and (`subj`.`project_id` = '$project_id')
	        and (`vals`.`value` <> 'OTHER')
	        and (`vals`.`value` IS NOT NULL )
	        and (`vals`.`value` <> ''))
	order by `subj`.`value`,`supp`.`RDOMAIN`
";
if (!$debug) {
	if (db_query($table_create_query)) {
		echo "$table_name exists<br />";
	}
	if (db_query($truncate_query)) {
		echo "$table_name has been truncated<br />";
		if (db_query($sql)) {
			echo "$table_name has been updated<br />";
		} else {
			error_log("SQL INSERT FAILED: " . db_error() . "\n");
			echo db_error() . "<br />";
		}
	} else {
		error_log("TRUNCATE FAILED: " . db_error() . "\n");
		echo db_error() . "<br />";
	}
} else {
	d($sql);
}
foreach ($supp_domains as $supp_domain) {
	$table_name_domain = "_{$constants['STUDYID']}_{$constants['DOMAIN']}{$supp_domain}";
	$table_create_query = "CREATE TABLE IF NOT EXISTS `$table_name_domain` (
	  `STUDYID` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
	  `RDOMAIN` VARCHAR(2) COLLATE utf8_unicode_ci NOT NULL,
	  `USUBJID` VARCHAR(16) COLLATE utf8_unicode_ci NOT NULL,
	  `IDVAR` VARCHAR(40) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `IDVARVAL` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
	  `QNAM` VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL,
	  `QLABEL` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
	  `QVAL` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
	  `QORIG` VARCHAR(8) COLLATE utf8_unicode_ci NOT NULL,
	  KEY `rdomain` (`RDOMAIN`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	$truncate_query = "TRUNCATE TABLE $table_name_domain";
	$sql = "INSERT INTO $table_name_domain SELECT * FROM $table_name WHERE RDOMAIN = '$supp_domain'";
	if ($supp_domain == 'LB') {
		$sql .= " AND (IDVARVAL IS NOT NULL OR IDVARVAL <> '')";
	}
	if (!$debug) {
		if (db_query($table_create_query)) {
			echo "$table_name_domain exists<br />";
		}
		if (db_query($truncate_query)) {
			echo "$table_name_domain has been truncated<br />";
			if (db_query($sql)) {
				echo "$table_name_domain has been updated<br />";
				/**
				 * prep for download
				 */
				if (defined("USERID")) {
					$userid = USERID;
				} else if (in_array(CRON_PAGE, non_auth_pages())) {
					$userid = "[CRON]";
				} else {
					$userid = '';
				}
				if (is_array($fields_collection)) {
					foreach ($fields_collection AS $field_collection) {
						foreach ($field_collection AS $key => $val) {
							$chkd_fields_array[] = $key;
						}
					}
					$chkd_fields = "'" . implode("', '", array_unique($chkd_fields_array)) . "'";
				}
				create_cdisc_download($table_name_domain, $lang, $app_title, $userid, $user_rights, $chkd_fields, '', $project_id, $constants['DOMAIN'] . $supp_domain, $debug);
			} else {
				error_log("SQL INSERT FAILED: " . db_error() . "\n");
				echo db_error() . "<br />";
			}
		} else {
			error_log("TRUNCATE FAILED: " . db_error() . "\n");
			echo db_error() . "<br />";
		}
	} else {
		d($sql);
	}
}
$timer['end'] = microtime(true);
$init_time = benchmark_timing($timer);
echo $init_time;