<?php
/**
 * This script takes a POST from form_renderer_functions.php in the render_autocomplete function
 * An SQL query is passed from the element_enum via $_SESSION['query_fragment_$field_name'].
 * Autocomplete queries the database using a two-part query. In the element_enum field of redcap_metadata,
 * with element_type autocomplete is stored a query. This allows import API to validate imported data a la sql field type.
 * This query must first be parsed into a fragment of the form:
 * 
 * SELECT value FROM redcap_data WHERE project_id='n' AND value LIKE
 *
 * The second part of the query is provided by AJAX in the form of the -query- variable, containing
 * whatever terms the user enters in the autocomplete field.
 */
/**
 * allow for testing
 */
$debug = false;
/**
 * includes
 */
require_once(dirname(dirname(dirname(__FILE__))) . "/redcap_connect.php");
/**
 * restricted use
 */
$userAuthenticated = Authentication::authenticate();
if ($userAuthenticated) {
	/**
	 * get text entered by user. This is appended to query string by jquery autocomplete
	 */
	$full_query = $_GET['q'];
	$term = prep($_GET['term']);
	/**
	 * get field name, used to make session unique
	 */
	$field_name = $_GET['f'];
	$query_field = $_GET['a'];
	/**
	 * get query fragment, passed in session variable to keep prying eyes away
	 * results should be limited by adding LIMIT 0,n to query after $term enclosure
	 * if no session variable, return an appropriate error value
	 */
	if (isset($_SESSION["query_$field_name"]) && !isset($full_query)) {
		$full_query = $_SESSION["query_$field_name"];
		$query_array = explode(' ', $full_query);
		$query_field = $query_array[(array_search('FROM', $query_array) - 1)];
		if (array_search('WHERE', $query_array) !== false) {
			$like_query = $full_query . " AND $query_field LIKE";
		} else {
			$like_query = $full_query . " WHERE $query_field LIKE";
		}
		$sql_query = "(" . $like_query . " '" . $term . "%' ORDER BY " . $query_field . " ASC LIMIT 0,20)";
		$sql_query .= "UNION";
		$sql_query .= "(" . $like_query . " '%" . $term . "%' ORDER BY " . $query_field . " ASC LIMIT 0,50)";
	} elseif ($debug) {
		$sql_query = "(" . $like_query . " '" . $term . "%' LIMIT 0,20)";
		$sql_query .= "UNION";
		$sql_query .= "(" . $like_query . " '%" . $term . "%' LIMIT 0,50)";
	} else {
		$sql_query = "SELECT 'ERROR: no SQL QUERY present'";
		error_log('Autocomplete Error: no SQL Query present. Dumping $_SERVER:');
		error_log(print_r($_SERVER, true));
	}
	$result = db_query($sql_query);
	if ($result) {
		while ($row = db_fetch_array($result, MYSQLI_NUM)) {
			$row_set[] = $row[0];
			$data_set[] = $row[1];
		}
	}
	/**
	 * format the array into expected format, which the jquery autocomplete documentation calls json but isn't, strictly speaking...
	 */
	$json = json_encode($row_set);
	if ($debug) {
		error_log($json);
	}
	echo $json;
}