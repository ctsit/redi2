<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 7/16/15
 * Time: 3:21 PM
 */
/**
 * DEBUG
 */
$debug = true;
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * if data was submitted here...
 */
if ($_GET && $_POST) {
	if ($debug) {
		show_var($_GET, 'GET');
		show_var($_POST, 'POST');
	}
	/**
	 * initialize variables
	 */
	$project = new Project();
	$next_event_id = getNextEventId($_GET['event_id'], $_GET['page']);
	$original_action = APP_PATH_WEBROOT . "DataEntry/index.php?pid={$_GET['pid']}&event_id={$_GET['event_id']}&page={$_GET['page']}";
	$redirect_url = APP_PATH_WEBROOT . "DataEntry/index.php?pid={$_GET['pid']}&event_id={$next_event_id}&page={$_GET['page']}";
	/**
	 * conditionally modify post and redirect
	 */
//	if ($_POST['submit-action'] == 'submit-btn-savenextevent' && $next_event_id !== false) {
//		$_POST['save-and-redirect'] = $redirect_url;
//	}
//	header('Location: ' . $original_action);
	/**
	 * construct and render proxy form
	 */
	$form_contents = '';
	foreach ($_POST AS $field => $value) {
		$form_contents .= RCView::input(array('type' => 'hidden', 'name' => $field, 'value' => $value));
	}
	if ($_POST['submit-action'] == 'submit-btn-savenextevent' && $next_event_id !== false) {
		$form_contents .= RCView::input(array('type' => 'hidden', 'name' => 'save-and-redirect', 'value' => $redirect_url));
	}
	$form_contents .= RCView::submit('');
	echo RCView::form(array('id' => 'proxy_form', 'action' => $original_action), $form_contents);
	if (!$debug) {
		?>
		<script type="text/javascript">
			$(document).ready(function () {
				$("#proxy_form").trigger('submit');
			});
		</script>
		<?php
	}
}

