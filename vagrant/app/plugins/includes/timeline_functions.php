<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 4/10/14
 * Time: 9:47 AM
 */

/**
 * return an array for each event
 * @param $stdtc string
 * @param $endtc string
 * @param $earliest_end string
 * @param $description string
 * @param $title string
 * @param $color string
 * @param $textcolor string
 * @param $url string
 * @param $tracknum string
 * @return array
 */
function get_event_array($stdtc, $endtc, $earliest_end, $description, $title, $color = '', $textcolor = '', $url = '', $tracknum = '')
{
	$date = explode("-", $stdtc);
	$phpmakedate = mktime(0, 0, 0, $date[1], $date[2], $date[0]);
	if ($endtc == NULL || $endtc == '0000-00-00' || $endtc == '') {
		$phpenddate = NULL;
		$durationEvent = FALSE;
	} else {
		$enddate = explode("-", $endtc);
		$phpmakeenddate = mktime(0, 0, 0, $enddate[1], $enddate[2], $enddate[0]);
		$phpenddate = date("r", $phpmakeenddate);
		$durationEvent = TRUE;
	}
	if ($earliest_end == NULL || $earliest_end == '0000-00-00' || $earliest_end == '') {
		$earliest_enddate = NULL;
	} else {
		$earliest_end_array = explode("-", $earliest_end);
		$make_earliest_enddate = mktime(0, 0, 0, $earliest_end_array[1], $earliest_end_array[2], $earliest_end_array[0]);
		$earliest_enddate = date("r", $make_earliest_enddate);
		$phpenddate = $earliest_enddate;
		$durationEvent = TRUE;
	}
	/**
	 * generate output array
	 */
	$eventAtts = array(
		'start' => date("r", $phpmakedate),
		'end' => $phpenddate,
		'earliestEnd' => $earliest_enddate,
		'durationEvent' => $durationEvent,
		'description' => $description,
		'title' => $title,
		'color' => $color,
		'textColor' => $textcolor,
		'link' => $url,
		'trackNum' => $tracknum
	);
	return $eventAtts;
}

/**
 * @param $iso8601 string
 * @return bool|string
 */
function get_gregorian_date($iso8601) {
	$date_array = explode("-", $iso8601);
	$make_date = mktime(0, 0, 0, $date_array[1], $date_array[2], $date_array[0]);
	return date("r", $make_date);
}