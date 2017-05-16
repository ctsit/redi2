<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 7/14/15
 * Time: 5:37 PM
 */
$debug = false;
$term = '@AUTOCOMPLETE';
$term_string = "(Autocomplete)";

if ($debug) {
	error_log("Loading $term");
}
/**
 * if we haven't loaded hook_terms array, we can't proceed, so log it and move on
 */
if (!isset($hook_terms)) {
	if ($debug) {
		error_log("ERROR: hook_terms array in " . __FILE__ . " not loaded.");
	}
	return;
}
/**
 * if there are no @AUTOCOMPLETE hooks, don't proceed, log it and move on
 */
if (!isset($hook_terms[$term])) {
	if ($debug) {
		error_log("INFO: Skipping $term - none found in instrument $instrument.");
	}
	return;
}
/**
 * open script tag and ready func
 */
print "<script type=\"text/javascript\">\n";
print "$(document).ready(function () {\n";
/**
 * for each hook_terms['@AUTOCOMPLETE'] use jQuery to inject our autocomplete widget into the DOM
 */
foreach ($hook_terms[$term] AS $autocomplete_field_name => $values) {
	$sql_query = $values['params'];
	$_SESSION["query_$autocomplete_field_name"] = $sql_query;
	$query_array = explode(' ', $sql_query);
	$query_field = $query_array[(array_search('FROM', $query_array) - 1)];
	print "$(\"[name='{$autocomplete_field_name}']\").attr(\"id\", \"{$autocomplete_field_name}\");\n";
	print "$(\"#{$autocomplete_field_name}-tr td.data div.note\").text('{$term_string}');\n";
	print "$(\"#{$autocomplete_field_name}\").autocomplete({\n";
	print "     source: \"" . APP_PATH_WEBROOT_FULL . "plugins/Autocomplete/autocomplete_control_ajax.php?pid=$project_id&f=$autocomplete_field_name&a=$query_field\",\n";
	print "     minLength: 2,\n";
	print "     delay: 250\n";
	print "     });\n";
	print "if ($('#" . $autocomplete_field_name . ".autocomplete').length) {
		$('#" . $autocomplete_field_name . ".autocomplete').prepend('<div id=\"searching\" style=\"display:none;\"></div>');
	}\n";
}
/**
 * close script tag and ready func
 */
print "});";
print "</script>\n";
