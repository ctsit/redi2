<?php 
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

// Need to call survey functions file to utilize a function
require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";

// Begin HTML
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<title><?php echo strip_tags(remBr(br2nl($app_title))) ?> | REDCap</title>
	<meta name="googlebot" content="noindex, noarchive, nofollow, nosnippet">
	<meta name="robots" content="noindex, noarchive, nofollow">
	<meta name="slurp" content="noindex, noarchive, nofollow, noodp, noydir">
	<meta name="msnbot" content="noindex, noarchive, nofollow, noodp">
	<meta http-equiv="Cache-Control" content="no-cache">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="expires" content="0">
	<?php print ($isIE ? '<meta http-equiv="X-UA-Compatible" content="IE=edge">' : '') ?>
	<link rel="shortcut icon" href="<?php echo APP_PATH_IMAGES ?>favicon.ico" type="image/x-icon">
	<link rel="apple-touch-icon-precomposed" href="<?php echo APP_PATH_IMAGES ?>apple-touch-icon.png">
	<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>smoothness/jquery-ui-<?php echo JQUERYUI_VERSION ?>.custom.css" media="screen,print">
	<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>style.css" media="screen,print">
	<script type="text/javascript" src="<?php echo APP_PATH_JS ?>base.js"></script>
	<script type="text/javascript" src="<?php echo APP_PATH_JS ?>underscore-min.js"></script>
	<script type="text/javascript" src="<?php echo APP_PATH_JS ?>backbone-min.js"></script>
	<script type="text/javascript" src="<?php echo APP_PATH_JS ?>RedCapUtil.js"></script>
<!--	enqueue timeline scripts    -->
	<script type='text/javascript'>
		Timeline_ajax_url = "<?php echo PLUGIN_PATH ?>includes/js/timeline_2.3.0/timeline_ajax/simile-ajax-api.js";
		Timeline_urlPrefix = "<?php echo PLUGIN_PATH ?>includes/js/timeline_2.3.0/timeline_js/";
		Timeline_parameters = "bundle=true";
	</script>
	<script type="text/javascript" src="<?php echo PLUGIN_PATH ?>includes/js/timeline_2.3.0/timeline_js/timeline-api.js?bundle=true"></script>
</head>
<body>
<noscript>
	<div class="red" style="margin-top:50px;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png" class="imgfix"> <b>WARNING: JavaScript Disabled</b><br><br>
		It has been determined that your web browser currently does not have JavaScript enabled, 
		which prevents this webpage from functioning correctly. You CANNOT use this page until JavaScript is enabled. 
		You will find instructions for enabling JavaScript for your web browser by 
		<a href="http://www.google.com/support/bin/answer.py?answer=23852" target="_blank" style="text-decoration:underline;">clicking here</a>. 
		Once you have enabled JavaScript, you may refresh this page or return back here to begin using this page.
	</div>
</noscript>
<?php

// IE CSS Hack - Render the following CSS if using IE
if ($isIE) {
	?>
	<style type="text/css">
	input[type="radio"],input[type="checkbox"] { margin: 0 }
	/* Fix IE's fieldset background issue */
	fieldset { position: relative; }
	legend {
		position:absolute;
		top: -1em;
	}
	fieldset {
		position: relative;
		margin-top:1.5em;
		padding-top:0.5em;
	}
	</style>
	<?php
}
		
// iOS CSS Hack for rendering drop-down menus with a background image
if ($isIOS) 
{
	print  '<style type="text/css">select { padding-right:14px !important;background-image:url("'.APP_PATH_IMAGES.'arrow_state_grey_expanded.png") !important; background-position:right !important; background-repeat:no-repeat !important; }</style>';
}

// Render Javascript variables needed on all pages for various JS functions
renderJsVars();

// STATS: Check if need to report institutional stats to REDCap consortium 
checkReportStats();

// Do CSRF token check (using PHP with jQuery)
createCsrfToken();

// Initialize auto-logout popup timer and logout reset timer listener
initAutoLogout();
	
// Render divs holding javascript form-validation text (when error occurs), so they get translated on the page
renderValidationTextDivs();
		
// Render hidden divs used by showProgress() javascript function
renderShowProgressDivs();

// Display notice that password will expire soon (if utilizing $password_reset_duration for Table-based authentication)
Authentication::displayPasswordExpireWarningPopup();

// Check if need to display pop-up dialog to SET UP SECURITY QUESTION for table-based users
Authentication::checkSetUpSecurityQuestion();


// PROJECT DELETED: If project has been scheduled for deletion, then display dialog that project can't be accessed (except by super users)
if ($date_deleted != "")
{
	// Display "project was deleted" dialog
	$deleteProjDialog = "{$lang['bottom_65']} <b>".
						DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime($date_deleted)+3600*24*PROJECT_DELETE_DAY_LAG)).
						"</b>{$lang['bottom_66']}";
	if ($super_user) {
		$deleteProjDialog .= "<br><br><b>{$lang['edit_project_77']}</b> {$lang['bottom_68']}";
	}
	// Note that the popup cannot be closed
	$deleteProjDialog .= RCView::div(array('style'=>'color:#777;margin:15px 0 20px;'), $lang['edit_project_155']);
	// "Return to My Projects" button
	$deleteProjDialog .= RCView::button(array('href'=>'javascript:;', 'onclick'=>"window.location.href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects';", 'class'=>'jqbuttonmed'), $lang['bottom_69']);
	// If a super user, show "Restore" button
	if ($super_user) {
		$deleteProjDialog .= RCView::SP . RCView::button(array('href'=>'javascript:;', 'onclick'=>"undelete_project($project_id)", 'class'=>'jqbuttonmed'), $lang['control_center_375']);
	}
	// Notice div that project was deleted
	print RCView::simpleDialog(RCView::div(array('style'=>'color:#C00000;'), $deleteProjDialog),$lang['global_03'].$lang['colon']." ".$lang['bottom_67'],"deleted_note");
	// Hidden "undelete project" div
	print RCView::simpleDialog("", $lang['control_center_378'], 'undelete_project_dialog');	
	?>
	<script type="text/javascript">
	function openDelProjDialog(){
		//simpleDialog(null,null,'deleted_note',null,'setTimeout(function(){openDelProjDialog()},10);',null,<?php echo $deleteProjRestoreBtnJs ?>,<?php echo $deleteProjRestoreBtnText ?>);
		$('#deleted_note').dialog({ bgiframe: true, modal: true, width: 500, close: function(){ setTimeout('openDelProjDialog()',10); } });
	}
	$(function(){
		openDelProjDialog();
	});
	</script>
	<?php
}


// Project status label
$statusLabel = '<div>'.$lang['edit_project_58'].'&nbsp; ';	
// Set icon/text for project status
if ($status == '1') {
	$statusLabel .= '<b style="color:green;">'.$lang['global_30'].'</b></div>';
} elseif ($status == '2') {
	$statusLabel .= '<b style="color:#800000;">'.$lang['global_31'].'</b></div>';
} elseif ($status == '3') {
	$statusLabel .= '<b style="color:#800000;">'.$lang['global_26'].'</b></div>';
} else {
	$statusLabel .= '<b style="color:#555;">'.$lang['global_29'].'</b></div>';
}


/**
 * LOGO & LOGOUT
 */
$logoHtml = "<div id='menu-div'>
				<div class='menubox' style='text-align:center;padding:7px 10px 0px 7px;'>
					<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects" . (($auth_meth == "none" && $auth_meth != $auth_meth_global && $auth_meth_global != "shibboleth") ? "&logout=1" : "") . "'><img src='".APP_PATH_IMAGES."redcaplogo_small.gif' title='REDCap' style='height:54px;'></a>
					<div style='text-align:left;font-size:10px;font-family:tahoma;color:#888;margin:10px -10px 5px -7px;border-top:1px solid #ddd;padding:0 0 6px 5px;'>
						<img src='".APP_PATH_IMAGES."lock_small_disable.gif' class='imgfix' style='top:5px;'> 
						{$lang['bottom_01']} <span style='font-weight:bold;color:#555;'>$userid</span>
						" . ($auth_meth == "none" ? "" : ((strlen($userid) < 14 && $auth_meth != "none") ? " &nbsp;|&nbsp; <span>" : "<br><span style='padding:1px 0 0;'><img src='".APP_PATH_IMAGES."cross_small_circle_gray.png' class='imgfix' style='top:5px;'> ")."<a href='".PAGE_FULL."?".$_SERVER['QUERY_STRING']."&logout=1' style='font-size:10px;font-family:tahoma;'>{$lang['bottom_02']}</a>") . "
					</div>
					<div class='hang'>
						<img src='".APP_PATH_IMAGES."redcap_icon.gif' class='imgfix2'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects" . (($auth_meth == "none" && $auth_meth != $auth_meth_global && $auth_meth_global != "shibboleth") ? "&logout=1" : "") . "'>{$lang['bottom_03']}</a><br>
					</div>
					<div class='hang'>
						<img src='".APP_PATH_IMAGES."house.png' class='imgfix'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."index.php?pid=$project_id'>{$lang['bottom_44']}</a><br>
					</div>
					<div class='hang'>
						<img src='".APP_PATH_IMAGES."clipboard_task.png' class='imgfix'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."ProjectSetup/index.php?pid=$project_id'>{$lang['app_17']}</a><br>
					</div>
					<div style='text-align:left;font-size:10px;font-family:tahoma;color:#666;padding:5px 0 3px 23px;'>
						$statusLabel
					</div>
				</div>
			</div>";


// ONLY for DATA ENTRY FORMS, get record information
list ($fetched, $hidden_edit, $entry_num) = getRecordAttributes();


// Build data entry form list
if ($status < 2 && !empty($user_rights))
{
	$dataEntry = "<div class='menubox' style='padding-right:0px;'>";
	// Set text for Invite Participants link
	$invitePart = "";
	if ($surveys_enabled && $user_rights['participants']) {
		$invitePart = "<div class='hang' style='position:relative;left:-8px;'><img src='".APP_PATH_IMAGES."survey_participants.gif' class='imgfix'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."Surveys/invite_participants.php?pid=$project_id'>".$lang['app_22']."</a></div>";
		if ($status < 1) {		
			$invitePart .=  "<div class='menuboxsub'>- ".$lang['invite_participants_01']."</div>";
		}
	}
	// Set panel title text
	if ($status < 1 && $user_rights['design']) {
		$dataEntryTitle = "<table cellspacing='0' width='100%'>
							<tr>
								<td>{$lang['bottom_47']}</td>
								<td id='menuLnkEditInstr' class='opacity50' style='text-align:right;padding-right:10px;'>"
									. RCView::img(array('src'=>'pencil_small2.png','class'=>'imgfix1 '.($isIE ? 'opacity50' : '')))
									. RCView::a(array('href'=>APP_PATH_WEBROOT."Design/online_designer.php?pid=$project_id",'style'=>'font-family:arial;font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'), $lang['bottom_70']) . "
								</td>
							</tr>
						   </table>";
	} else {
		$dataEntryTitle = $lang['bottom_47'];
	}
	
	## DATA COLLECTION SECTION
	// Invite Participants
	$dataEntry .= $invitePart;
	
	// Scheduling
	if ($repeatforms && $scheduling) {
		$dataEntry .= "<div class='hang' style='position:relative;'><img src='".APP_PATH_IMAGES."calendar_plus.png' class='imgfix'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."Calendar/scheduling.php?pid=$project_id'>".$lang['global_25']."</a></div>";
		if ($status < 1) {		
			$dataEntry .=  "<div class='menuboxsub'>- ".$lang['bottom_19']."</div>";
		}
	}
	
	## DATA STATUS GRID
	$dataEntry .= "<div class='hang' style='position:relative;'><img src='".APP_PATH_IMAGES."application_view_icons.png' class='imgfix'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."DataEntry/record_status_dashboard.php?pid=$project_id'>{$lang['global_91']}</a></div>";
	if ($status < 1) {		
		$dataEntry .=  "<div class='menuboxsub' style='position:relative;'>- ".$lang['bottom_60']."</div>";
	}
	
	## Display link for manage page if using multiple time-points (Longitudinal Module)
	$addEditRecordPage = "";
	// If user is on grid page or data entry page and record is selected, make grid icon a link back to grid page 
	$gridlink = "<img src='".APP_PATH_IMAGES."blog_pencil.gif' class='imgfix'>";
	if (!$longitudinal) {
		// Point to first form that user has access to
		foreach (array_keys($Proj->forms) as $this_form) {
			if ($user_rights['forms'][$this_form] == '0') continue;
			$addEditRecordPage = "DataEntry/index.php?pid=$project_id&page=$this_form";
			break;
		}
	} else {
		$addEditRecordPage = "DataEntry/grid.php?pid=$project_id";
	}
	if ($addEditRecordPage != "") {
		$dataEntry .=  "<div class='hang' style='position:relative;'>
						$gridlink&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."$addEditRecordPage' style='color:#800000'>".
							(($user_rights['record_create'] && ($user_rights['forms'][$Proj->firstForm] == '1' || $user_rights['forms'][$Proj->firstForm] == '3')) ? $lang['bottom_62'] : $lang['bottom_72'])."</a>
						</div>";
		if ($status < 1) {		
			$dataEntry .=  "<div class='menuboxsub' style='position:relative;'>- ".
							(($user_rights['record_create'] && ($user_rights['forms'][$Proj->firstForm] == '1' || $user_rights['forms'][$Proj->firstForm] == '3')) ? $lang['bottom_64'] : $lang['bottom_73'])."</div>";
		}
	}
	
	//Get all info for determining which forms to show on menu
	$visit_forms = array();
	if ($longitudinal && isset($fetched)) 
	{
		foreach ($Proj->eventsForms[$_GET['event_id']] as $this_form) {
			$visit_forms[$this_form] = "";
		}
	}
	
	// If showing Scheduling OR Invite Participant links OR viewing a record in longitudinal...
	if ((isset($_GET['id']) && PAGE == "DataEntry/grid.php") 
		|| (isset($fetched) && PAGE == "DataEntry/index.php")
		|| !$longitudinal) 
	{
		// Show record name on left-hand menu (if a record is pulled up)
		$record_label = "";
		if ((isset($_GET['id']) && PAGE == "DataEntry/grid.php")
			|| (isset($fetched) && PAGE == "DataEntry/index.php" && isset($_GET['event_id']) && is_numeric($_GET['event_id'])))
		{
			require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
			if (PAGE == "DataEntry/grid.php") {
				$fetched = $_GET['id'];
			}
			$record_display = RCView::b(RCView::escape($_GET['id']));
			// Get Custom Record Label and Secondary Unique Field values (if applicable)
			$this_custom_record_label_secondary_pk = Records::getCustomRecordLabelsSecondaryFieldAllRecords(addDDEending($fetched), false, getArm(), true);
			if ($this_custom_record_label_secondary_pk != '') {
				$record_display .= "&nbsp; $this_custom_record_label_secondary_pk";
			}
			// DISPLAY RECORD NAME: Set full string for record name with prepended label (e.g., Study ID 202)
			if ($longitudinal) {
				// Longitudinal project: Display record name as link and "select other record" link
				$record_label = RCView::div(array('style'=>'padding:0 0 4px;color:#800000;font-size:12px;'), 
									RCView::div(array('style'=>'float:left;'),
										RCView::a(array('style'=>'color:#800000;','href'=>APP_PATH_WEBROOT."DataEntry/grid.php?pid=$project_id&id=$fetched&arm=".getArm()), 
											RCView::img(array('src'=>'application_view_tile.png','class'=>'imgfix')) .
											strip_tags(label_decode($table_pk_label)) . " " . $record_display
										)
									) .
									RCView::div(array('style'=>'float:right;line-height:18px;'),
										RCView::a(array('id'=>'menuLnkChooseOtherRec','class'=>'opacity50','style'=>'color:#000066;vertical-align:middle;text-decoration:underline;font-size:10px;','href'=>APP_PATH_WEBROOT."DataEntry/grid.php?pid=$project_id"), 
											$lang['bottom_63']
										)
									) .
									RCView::div(array('class'=>'clear'), '')
								);
			} else {
				// Classic project: Display record name and "select other record" link
				$record_label = RCView::div(array('style'=>'padding:0 0 4px;color:#800000;font-size:12px;'), 
									RCView::div(array('style'=>'float:left;font-family:arial;'),
										strip_tags(label_decode($table_pk_label)) . " " . $record_display
									) .
									RCView::div(array('style'=>'float:right;'),
										RCView::a(array('id'=>'menuLnkChooseOtherRec','class'=>'opacity50','style'=>'color:#000066;vertical-align:middle;text-decoration:underline;font-size:10px;','href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&page={$_GET['page']}"), 
											$lang['bottom_63']
										)
									) .
									RCView::div(array('class'=>'clear'), '')
								);
			}
		}
		
		// Get event description for this event
		$event_label = "";
		if ($longitudinal && isset($_GET['event_id']) && is_numeric($_GET['event_id'])) 
		{
			$event_label = "<div style='padding:1px 0 5px;'>
								{$lang['bottom_23']}&nbsp;
								<span style='color:#800000;font-weight:bold;'>".RCView::escape(strip_tags($Proj->eventInfo[$_GET['event_id']]['name_ext']))."</span>
							</div>";
		}
		
		if ($addEditRecordPage != "") {
			$dataEntry .=  "<div class='menuboxsub' style='margin:12px 0 2px;border-top:1px dashed #aaa;text-indent:0;padding-top:5px;font-size:10px;'>
								$record_label
								$event_label
								" . ((!$longitudinal || (PAGE == "DataEntry/index.php" && $longitudinal)) ? $lang['global_57'] . $lang['colon'] : "") . "
							</div>";
		}
	}

	//If project is parent demographics project, then show menu as if this is child project.
	$this_app_name = $app_name;

	// Initialize
	$locked_forms = array();

	//For lock/unlock records and e-signatures, show locks by any forms that are locked (if a record is pulled up on data entry page)
	if (PAGE == "DataEntry/index.php" && isset($fetched)) 
	{
		$entry_num = isset($entry_num) ? $entry_num : "";
		// Lock records
		$sql = "select form_name, timestamp from redcap_locking_data where project_id = $project_id and event_id = {$_GET['event_id']} 
				and record = '" . prep($fetched.$entry_num). "'";
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) 
		{
			$locked_forms[$row['form_name']] = " <img id='formlock-{$row['form_name']}' src='".APP_PATH_IMAGES."lock_small.png' title='".cleanHtml($lang['bottom_59'])." " . DateTimeRC::format_ts_from_ymd($row['timestamp']) . "'>";	
		}
		// E-signatures
		$sql = "select form_name, timestamp from redcap_esignatures where project_id = $project_id and event_id = {$_GET['event_id']} 
				and record = '" . prep($fetched.$entry_num). "'";
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) 
		{
			$this_esignts = " <img id='formesign-{$row['form_name']}' src='".APP_PATH_IMAGES."tick_shield_small.png' title='" . cleanHtml($lang['data_entry_224'] . " " . DateTimeRC::format_ts_from_ymd($row['timestamp'])) . "'>";	
			if (isset($locked_forms[$row['form_name']])) {
				$locked_forms[$row['form_name']] .= $this_esignts;
			} else {
				$locked_forms[$row['form_name']] = $this_esignts;
			}
		}
	}

	## Render the form list for this project
	list ($form_count,$formString) = renderFormMenuList($this_app_name,$fetched,$locked_forms,$hidden_edit,$entry_num,$visit_forms);
	$dataEntry .= $formString;

	## LOCK / UNLOCK RECORDS
	//If user has ability to lock a record, give option to lock it for all forms (if record is pulled up on data entry page)
	if ($user_rights['lock_record_multiform'] && $user_rights['lock_record'] > 0 && PAGE == "DataEntry/index.php" && isset($fetched)) 
	{
		//Adjust if double data entry for display in pop-up
		if ($double_data_entry && $user_rights['double_data'] != '0') {
			$fetched2 = $fetched . '--' . $user_rights['double_data'];
		//Normal
		} else {
			$fetched2 = $fetched;
		}
		//Determine when to show which link
		if (count($locked_forms) == $form_count) {
			$show_unlocked_link = true;
			$show_locked_link = false;
		} elseif (count($locked_forms) == 0) {
			$show_unlocked_link = false;
			$show_locked_link = true;
		} else {
			$show_locked_link = true;
			$show_unlocked_link = true;
		}
		//Show link "Lock all forms"
		if ($show_locked_link && $hidden_edit) {
			$dataEntry .=  "<div style='text-align:left;padding: 6px 0px 2px 0px;'>
								<img src='".APP_PATH_IMAGES."lock.png' class='imgfix'> 
								<a style='color:#A86700;font-weight:bold;font-size:12px' href='javascript:;' onclick=\"
									lockUnlockForms('".cleanHtml($fetched2)."','".cleanHtml($fetched)."','{$_GET['event_id']}','0','0','lock');
									return false;
								\">{$lang['bottom_40']}</a>
							</div>";
		}
		//Show link "Unlock all forms"
		if ($show_unlocked_link && $hidden_edit) {
			$dataEntry .=  "<div style='text-align:left;padding: 6px 0px 2px 0px;'>
								<img src='".APP_PATH_IMAGES."lock_open.png' class='imgfix'> 
								<a style='color:#666;font-weight:bold;font-size:12px' href='javascript:;' onclick=\"
									lockUnlockForms('".cleanHtml($fetched2)."','".cleanHtml($fetched)."','{$_GET['event_id']}','0','0','unlock');
									return false;
								\">{$lang['bottom_41']}</a>
							</div>";
		}
		
	}

	$dataEntry .= "</div>";
}
	

/**
 * APPLICATIONS MENU
 * Show function links based on rights level (Don't allow designated Double Data Entry people to see pages displaying other user's data.)
 */
$appsMenuTitle = $lang['bottom_25'];
$appsMenu = "<div class='menubox' style='padding-right:0;'>";
//Calendar
if ($status < 2 && $user_rights['calendar']) { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."date.png' class='imgfix'>&nbsp;&nbsp;<a href='".APP_PATH_WEBROOT."Calendar/index.php?pid=$project_id'>{$lang['app_08']}</a></div>";
}
// Data Exports, Reports, & Stats
if (isset($user_rights['data_export_tool']) && ($user_rights['reports'] || $user_rights['data_export_tool'] > 0 || $user_rights['graphical'])) { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."layout_down_arrow.gif' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "DataExport/index.php?pid=$project_id\">{$lang['app_23']}</a></div>";
}
//Data Import Tool
if ($status < 2 && $user_rights['data_import_tool']) { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."table_row_insert.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "DataImport/index.php?pid=$project_id\">{$lang['app_01']}</a></div>";
}
//Data Comparison Tool
if ($status < 2 && $user_rights['data_comparison_tool'] && isset($mobile_project) && $mobile_project != "2") { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."page_copy.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "DataComparisonTool/index.php?pid=$project_id\">{$lang['app_02']}</a></div>";
}
//Data Logging
if ($user_rights['data_logging']) { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."report.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "Logging/index.php?pid=$project_id\">".$lang['app_07']."</a></div>";
}
// Field Comment Log
if ($data_resolution_enabled == '1') {
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."balloons.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "DataQuality/field_comment_log.php?pid=$project_id\">{$lang['dataqueries_141']}</a></div>";
}
//File Repository
if ($user_rights['file_repository']) { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."page_white_stack.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "FileRepository/index.php?pid=$project_id\">{$lang['app_04']}</a></div>";
}
//User Rights
if ($user_rights['user_rights']) { 
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."user.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "UserRights/index.php?pid=$project_id\">{$lang['app_05']}</a>";
	if ($user_rights['data_access_groups']) {
		// Resolve Issues
		$appsMenu .= RCView::span(array('style'=>'color:#777;margin:0 6px 0 5px;'), $lang['global_43']) . 
					"<img src='".APP_PATH_IMAGES."group.png' class='imgfix' style='margin-right:2px;'>
					<a href=\"" . APP_PATH_WEBROOT . "DataAccessGroups/index.php?pid=$project_id\">{$lang['global_114']}</a>";
	}
	$appsMenu .= "</div>";
}
//Lock Record advanced setup
if ($user_rights['lock_record_customize'] > 0) {
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."lock_plus.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "Locking/locking_customization.php?pid=$project_id\">{$lang['app_11']}</a></div>";
}
//E-signature and Locking Management
if ($status < 2 && $user_rights['lock_record'] > 0) {
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."tick_shield_lock.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "Locking/esign_locking_management.php?pid=$project_id\">{$lang['app_12']}</a></div>";
}
// Randomization
if ($randomization && $status < 2 && ($user_rights['random_setup'] || $user_rights['random_dashboard'])) {
	$rpage = ($user_rights['random_setup']) ? "index.php" : "dashboard.php";
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."arrow_switch.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "Randomization/$rpage?pid=$project_id\">{$lang['app_21']}</a></div>";
}
// Data Quality
if ($status < 2 && ($user_rights['data_quality_design'] || $user_rights['data_quality_execute'] || ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] > 0))) {
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."checklist.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "DataQuality/index.php?pid=$project_id\">{$lang['app_20']}</a>";
	if ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] > 0) {
		// Resolve Issues
		$appsMenu .= RCView::span(array('style'=>'color:#777;margin:0 4px;'), $lang['global_43']) . 
					"<img src='".APP_PATH_IMAGES."balloons.png' class='imgfix'>
					<a href=\"" . APP_PATH_WEBROOT . "DataQuality/resolve.php?pid=$project_id\">{$lang['dataqueries_148']}</a>";
	}
	$appsMenu .= "</div>";
}
// API
if ($status < 2 && $api_enabled && ($user_rights['api_export'] || $user_rights['api_import'])) {
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."computer.png' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "API/project_api.php?pid=$project_id\">{$lang['setup_77']}</a></div>";
}
// Mobile app
if ($status < 2 && $mobile_app_enabled && $api_enabled && $user_rights['mobile_app'])
{
	$appsMenu .= "<div class='hang'><img src='".APP_PATH_IMAGES."redcap_app_icon.gif' class='imgfix'>&nbsp;&nbsp;<a href=\"" . APP_PATH_WEBROOT . "MobileApp/index.php?pid=$project_id\">{$lang['global_118']}</a></div>";
}
$appsMenu .= "</div>";




/*
 ** REPORTS
 */
//Check to see if custom reports are specified for this project. If so, print the appropriate links.
//Build menu item for each separate report
$reportsListTitle = $lang['app_06'];
if ($user_rights['reports']) {
	$reportsListTitle = "<table cellspacing='0' width='100%'>
						<tr>
							<td>{$lang['app_06']}</td>
							<td id='menuLnkEditReports' class='opacity50' style='text-align:right;padding-right:10px;'>"
								. RCView::img(array('src'=>'pencil_small2.png','class'=>'imgfix1 '.($isIE ? 'opacity50' : '')))
								. RCView::a(array('href'=>APP_PATH_WEBROOT."DataExport/index.php?pid=$project_id",'style'=>'font-family:arial;font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'), $lang['bottom_71']) . "
							</td>
						</tr>
					   </table>";
}
// Reports built in Reports & Exports module
$reportsList = DataExport::outputReportPanel();


/**
 * HELP MENU
 */
$helpMenuTitle = '<div style="margin-top:-3px;"><img src="'.APP_PATH_IMAGES.'help.png" class="imgfix"> <span style="color:#3E72A8;">'.$lang['bottom_42'].'</span></div>';
$helpMenu = "<div class='menubox' style='font-size:11px;color:#444;'>
				
				<!-- Help & FAQ -->
				<div class='hang'>
					<img src='" . APP_PATH_IMAGES . "bullet_toggle_minus.png' class='imgfix'>
					<a style='color:#444;' href='" . APP_PATH_WEBROOT_PARENT . "index.php?action=help'>".$lang['bottom_27']."</a>
				</div>
				
				<!-- Video Tutorials -->
				<div class='hang'>
					<img src='" . APP_PATH_IMAGES . "bullet_toggle_plus.png' class='imgfix'>
					<a style='color:#444;' href='javascript:;' onclick=\"
						$('#menuvids').toggle('blind',{},500,
							function(){
								var objDiv = document.getElementById('west');
								objDiv.scrollTop = objDiv.scrollHeight;
							}
						);
					\">".$lang['bottom_28']."</a>
				</div>
				
				<div id='menuvids' style='display:none;line-height:1.2em;padding:2px 0 0 16px;'>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('redcap_overview_brief01.flv')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['bottom_58']."</a>
					</div>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('redcap_overview03.mp4')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['bottom_57']."</a>
					</div>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('project_types01.flv')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['training_res_71']."</a>
					</div>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('redcap_survey_basics02.flv')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['bottom_51']."</a>
					</div>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('data_entry_overview_01.flv')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['bottom_56']."</a>
					</div>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('form_editor_upload_dd02.flv')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['bottom_31']."</a>
					</div>
					<div class='menuvid'>
						&bull; <a onclick=\"popupvid('redcap_db_applications_menu02.flv')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>".$lang['bottom_32']."</a>
					</div>
				</div>
				
				<!-- Suggest a New Feature -->
				<div class='hang'>
					<img src='" . APP_PATH_IMAGES . "star_small.png' class='imgfix'>
					<a style='color:#444;' target='_blank' href='https://redcap.vanderbilt.edu/enduser_survey_redirect.php?redcap_version=$redcap_version&server_name=".SERVER_NAME."'>".$lang['bottom_52']."</a>
				</div>
				
				<div style='padding-top:10px;'>
					".$lang['bottom_38']." <a href='mailto:$project_contact_email' style='color:#333;font-size:11px;text-decoration:underline;'>".$lang['bottom_39']."</a>".$lang['period']."
				</div>
				
			</div>";
			
/**
 * EXTERNAL PAGE LINKAGE
 */
if (defined("USERID") && isset($ExtRes)) {
	$externalLinkage = $ExtRes->renderHtmlPanel();
}


// Build the HTML panels for the left-hand menu
// Make sure that 'pid' in URL is defined (otherwise, we shouldn't be including this file)
if (isset($_GET['pid']) && is_numeric($_GET['pid']))
{
	$westHtml = renderPanel('', $logoHtml)
			  . renderPanel($dataEntryTitle, $dataEntry)
			  . renderPanel($appsMenuTitle, $appsMenu, 'app_panel');
	if ($externalLinkage != "") {
		$westHtml .= $externalLinkage;
	}
	if ($reportsList != "") {
		$westHtml .= renderPanel($reportsListTitle, $reportsList, 'report_panel');
	}
	$westHtml .= renderPanel($helpMenuTitle, $helpMenu, 'help_panel');
}
else
{
	// Since no 'pid' is in URL, then give warning that header/footer will not display properly
	$westHtml = renderPanel("&nbsp;", "<div style='padding:20px 15px;'><img src='".APP_PATH_IMAGES."exclamation.png' class='imgfix'> <b style='color:#800000;'>{$lang['bottom_54']}</b><br>{$lang['bottom_55']}</div>");
}


/**
 * PAGE CONTENT
 */
?>
<table border=0 cellspacing=0 style="width:100%;">
	<tr>
		<td valign="top" id="west" style="width:250px;">
			<div id="west_inner" style="width:250px;"><?php echo $westHtml ?></div>
		</td>
		<td valign="top" id="westpad">&nbsp;</td>
		<td valign="top" id="center">
			<div id="center_inner">
				<div id="subheader" class="notranslate">
					<?php if ($display_project_logo_institution) { ?>
						<?php if (trim($headerlogo) != "") echo "<img src='$headerlogo' title='".cleanHtml($institution)."' alt='".cleanHtml($institution)."' style='max-width:700px; expression(this.width > 700 ? 700 : true);'>"; ?>
						<div id="subheaderDiv1">
							<?php echo $institution . (($site_org_type == "") ? "" : "<br><span style='font-family:tahoma;font-size:13px;'>$site_org_type</span>") ?>
						</div>
					<?php } ?>
					<div id="subheaderDiv2" <?php if (!$display_project_logo_institution) echo 'style="border:0;padding-top:0;"'; ?>>
						<div style="max-width:700px;"><?php echo filter_tags($app_title) ?></div>
					</div>
				</div>

