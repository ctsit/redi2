<?php
	
/**

 A DET/API Example that takes a survey response and copies the survey form to a randomized arm for the project 
 
 Based on a thread in the google groups called [REDCap] Randomization by Mark Odem.

intake_arm_1 - Public Survey
baseline_arm_2 - Group A
baseline_arm_3 - Group B
baseline_arm_4 - Group C

**/

define('LOG_FILE', '/var/log/webtools/det_random_to_arm.log');

$api_url = "https://YOUR_REDCAP_URL/api/";
$api_token = 'YOUR_TOKEN';  //
$debug = true;	// Turn off to lessen log statements

$screening_event = 'intake_arm_1';			// The name of the first event where screening occurs
$screening_form = 'intake';					// The name of the form that has the 'screening/intake' survey

$randomization_field = 'randomized_group';	// The name of the field that stores the randomization group
$randomization_form = 'randomization';		// The name of the form that contains the randomization field

// An array of 'arms-events' to randomize the intake participant to.
//  The name goes into the randomization field.
$randomized_groups = array(
	array('name'=>'A', 'event'=>'baseline_arm_2'),
	array('name'=>'B', 'event'=>'baseline_arm_3'),
	array('name'=>'C', 'event'=>'baseline_arm_4')
);


logIt('----------- Starting -------------', 'DEBUG');

// Obtain details from DET post (this is what is passed from REDCap)
$project_id = voefr('project_id');
$instrument = voefr('instrument');
$record = voefr('record');
$redcap_event_name = voefr('redcap_event_name');
$redcap_data_access_group = voefr('redcap_data_access_group');
$instrument_complete = voefr($instrument . "_complete");
if (!$record) {
	logIt('No record id parsed.');
	exit;
}

// Only fire if the screening arm/form triggered the DET
if ($redcap_event_name != $screening_event || $instrument != $screening_form) {
	logIt("Skipping det - $instrument / $redcap_event_name is not $screening_form / $screening_event","DEBUG");
	exit;
}

// Get current randomization status
$randomized = queryAPI(array(
	'content'=>'record',
	'records'=>$record,
	'events'=>array($redcap_event_name),
	'fields'=>array($randomization_field),
	'forms'=>array($randomization_form)
));
logIt('Randomized data: '.print_r($randomized,true), "DEBUG");

// Don't re-randomize a record if it has already been done
if ($randomized[0][$randomization_field] != '') {
	logIt("$record is already randomized", "DEBUG");
	exit;
}

// Get ALL data for the intake form
// (In case you're wondering why I didn't just get all the fields for this record in the first API query,
//  it is because I wanted to re-use the results from this query to import all fields on this form into
//  the randomized arm (without having to query the data dictionary and do all that logic...) 
$data = queryAPI(array(
	'content'=>'record',
	'records'=>$record,
	'events'=>array($redcap_event_name),
	'forms'=>array($screening_form)
));
logIt('Intake data: '.print_r($data,true), "DEBUG");

// Randomize the Record
$group_number = mt_rand(0, count($randomized_groups)-1);
$new_group = $randomized_groups[$group_number];
logIt("$record randomized to ".json_encode($new_group), "DEBUG");

// Upload the randomization field to the screening event
$result = queryAPI(array(
	'content'=>'record',
	'data'=>json_encode(
		array(
			array(
				'record_id'=>$record,
				'redcap_event_name'=>$data[0]['redcap_event_name'],
				$randomization_field=>$new_group['name'],
				$randomization_form.'_complete'=>2
			)
		)
	)
));
logIt('Upload 1: '. print_r($result, true), "DEBUG");

// Copy the data to the randomized event
$new_data = $data;
$new_data[0]['redcap_event_name']=$new_group['event'];
$result = queryAPI(array(
	'content'=>'record',
	'data'=>json_encode($new_data)
));
logIt('Upload 2: '. print_r($result, true), "DEBUG");





###### UTILITY FUNCTIONS

// Query the api - assumes json encoding
function queryAPI($params) {
	global $api_token, $api_url;
	
	// Assuming the following for all queries
	$params['token'] = $api_token;
	$params['format'] = 'json';
	$params['type'] = 'flat';
	logIt('Params: '.print_r($params,true), "DEBUG");
	
	$r = curl_init($api_url);
	curl_setopt($r, CURLOPT_POST, 1);
	curl_setopt($r, CURLOPT_POSTFIELDS, http_build_query($params));
	curl_setopt($r, CURLOPT_RETURNTRANSFER, 1);
	$r_result = curl_exec($r);
	$r_error = curl_error($r);
	curl_close($r);
	if ($r_error) {
		logIt("Curl call failed ($r_error) with params (".json_encode($params).")", 'ERROR');
		exit;
	}
	logIt('r_result: '.print_r($r_result,true), "DEBUG");
	$results = json_decode($r_result,true);
	return $results;
}


// Get Variable Or Empty string Fom _REQUEST
function voefr($var) {
	$result = isset($_REQUEST[$var]) ? $_REQUEST[$var] : "";
	return $result;
}

//Log to file
function logIt($msg, $level = "INFO") {
	global $project_id, $debug;
	if ($level != "DEBUG" || $debug) {
		file_put_contents( LOG_FILE,	date( 'Y-m-d H:i:s' ) . "\t" . $level . "\t" . $project_id . "\t" . $msg . "\n", FILE_APPEND );
	}
}



?>