<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 2/25/14
 * Time: 9:02 AM
 */
function create_download_all($Proj, $lang, $project_id, $app_name, $app_title, $super_user, $userid, $headers, $headers_labels, $data_csv, $data_csv_labels, $field_names, $is_child=false, $chkd_flds, $table_pk, $longitudinal, $exportDags=false, $exportSurveyFields=false) {
	$dagEnum = '';
	$do_remove_identifiers = false;
	$do_date_shift = false;
	// Retrieve project data (raw & labels) and headers in CSV format
	//list ($headers, $headers_labels, $data_csv, $data_csv_labels, $field_names)
	//	= fetchDataCsv($chkd_flds, $parent_chkd_flds, false, $do_hash, $do_remove_identifiers, $useStandardCodes, $useStandardCodeDataConversion, $standardId, $standardCodeLookup, $useFieldNames, $exportDags, $exportSurveyFields, $exportSurveyFields);
	// Log the event
	//log_event("", "redcap_data", "data_export", "", str_replace("'", "", $chkd_flds) . (($parent_chkd_flds == "") ? "" : ", " . str_replace("'", "", $parent_chkd_flds)), "Export data");

	############################################################
	## PREPARE SYNTAX FILES FOR STATS PACKAGES

	# Initializing the syntax file strings
	$spss_string = "FILE HANDLE data1 NAME='data_place_holder_name' LRECL=90000.\n";
	$spss_string .= "DATA LIST FREE" . "\n\t";
	$spss_string .= "FILE = data1\n\t/";
	$sas_string = "DATA " . $app_name . ";\nINPUT ";
	$sas_format_string = "data redcap;\n\tset redcap;\n";
	$stata_string = "clear\n\n";
	$R_string = "#Clear existing data and graphics\nrm(list=ls())\n";
	$R_string .= "graphics.off()\n";
	$R_string .= "#Load Hmisc library\nlibrary(Hmisc)\n";
	$R_label_string = "#Setting Labels\n";
	$R_units_string = "\n#Setting Units\n";
	$R_factors_string = "\n\n#Setting Factors(will create new variable for factors)";
	$R_levels_string = "";
	$value_labels_spss = "VALUE LABELS ";


	// Get relevant metadata to use for syntax files
		$syntaxfile_sql = "SELECT field_name, element_validation_type, element_enum, element_type, element_label, field_units
						   FROM redcap_metadata where project_id = $project_id and field_name in ($chkd_flds) order by field_order";

	// Array that is prepended to $field_names array if fields need to be added, such as redcap_event_name or survey timestamp
	$field_names_prepend = array();
	$prev_form = "";
	$prev_field = "";

	// Loop through all fields that were exported
	$q = db_query($syntaxfile_sql);
	while ($row = db_fetch_assoc($q)) {
		// Create object for each field we loop through
		$ob = new stdClass();
		foreach ($row as $col => $val) {
			$col = strtoupper($col);
			$ob->$col = $val;
		}

		// Set values for this loop
		$this_form = $Proj->metadata[$ob->FIELD_NAME]['form_name'];

		// If surveys exist, as timestamp and identifier fields
		if ($exportSurveyFields && $prev_form != $this_form && $ob->FIELD_NAME != $table_pk && isset($Proj->forms[$this_form]['survey_id'])) {
			// Alter $meta_array
			$ob2 = new stdClass();
			$ob2->ELEMENT_TYPE = 'text';
			$ob2->FIELD_NAME = $this_form . '_timestamp';
			$ob2->ELEMENT_LABEL = 'Survey Timestamp';
			$ob2->ELEMENT_ENUM = '';
			$ob2->FIELD_UNITS = '';
			$ob2->ELEMENT_VALIDATION_TYPE = '';
			$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
		}


		if ($ob->ELEMENT_TYPE != 'checkbox') {
			// For non-checkboxes, add to $meta_array
			$meta_array[$ob->FIELD_NAME] = (Object)$ob;
		} else {
			// For checkboxes, loop through each choice to add to $meta_array
			$orig_fieldname = $ob->FIELD_NAME;
			$orig_fieldlabel = $ob->ELEMENT_LABEL;
			$orig_elementenum = $ob->ELEMENT_ENUM;
			foreach (parseEnum($orig_elementenum) as $this_value => $this_label) {
				unset($ob);
				// $ob = $meta_set->FetchObject();
				$ob = new stdClass();
				// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
				if (!is_numeric($this_value)) {
					$this_value = preg_replace("/[^a-z0-9]/", "", strtolower($this_value));
				}
				// Convert each checkbox choice to a advcheckbox field (because advcheckbox has equivalent processing we need)
				// Append triple underscore + coded value
				$ob->FIELD_NAME = $orig_fieldname . '___' . $this_value;
				$ob->ELEMENT_ENUM = "0, Unchecked \\n 1, Checked";
				$ob->ELEMENT_TYPE = "advcheckbox";
				$ob->ELEMENT_LABEL = "$orig_fieldlabel (choice=" . str_replace(array("'", "\""), array("", ""), $this_label) . ")";
				$meta_array[$ob->FIELD_NAME] = (Object)$ob;
			}
		}


		if ($ob->FIELD_NAME == $table_pk) {
			// If project has multiple Events (i.e. Longitudinal), add new column for Event name
			if ($longitudinal) {
				// Put unique event names and labels into array to convert to enum format
				$evtEnumArray = array();
				$evtLabels = array();
				foreach ($Proj->eventInfo as $event_id => $attr) {
					$evtLabels[$event_id] = label_decode($attr['name_ext']);
				}
				foreach ($evtLabels as $event_id => $event_label) {
					$evtEnumArray[] = $Proj->getUniqueEventNames($event_id) . ", " . label_decode($event_label);
				}
				$evtEnum = implode(" \\n ", $evtEnumArray);
				// Alter $meta_array
				$ob2 = new stdClass();
				$ob2->ELEMENT_TYPE = 'select';
				$ob2->FIELD_NAME = 'redcap_event_name';
				$ob2->ELEMENT_LABEL = 'Event Name';
				$ob2->ELEMENT_ENUM = $evtEnum;
				$ob2->FIELD_UNITS = '';
				$ob2->ELEMENT_VALIDATION_TYPE = '';
				$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
				// Add pseudo-field to array
				$field_names_prepend[] = $ob2->FIELD_NAME;
			}
			// If project has DAGs, add new column for group name
			if ($exportDags) {
				// Alter $meta_array
				$ob2 = new stdClass();
				$ob2->ELEMENT_TYPE = 'select';
				$ob2->FIELD_NAME = 'redcap_data_access_group';
				$ob2->ELEMENT_LABEL = 'Data Access Group';
				$ob2->ELEMENT_ENUM = $dagEnum;
				$ob2->FIELD_UNITS = '';
				$ob2->ELEMENT_VALIDATION_TYPE = '';
				$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
				// Add pseudo-field to array
				$field_names_prepend[] = $ob2->FIELD_NAME;
			}

			// Add survey identifier (unless we've set it to remove all identifiers - treat survey identifier same as field identifier)
			if ($exportSurveyFields && !$do_remove_identifiers) {
				// Alter $meta_array
				$ob2 = new stdClass();
				$ob2->ELEMENT_TYPE = 'text';
				$ob2->FIELD_NAME = 'redcap_survey_identifier';
				$ob2->ELEMENT_LABEL = 'Survey Identifier';
				$ob2->ELEMENT_ENUM = '';
				$ob2->FIELD_UNITS = '';
				$ob2->ELEMENT_VALIDATION_TYPE = '';
				$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
				// Add pseudo-field to array
				$field_names_prepend[] = $ob2->FIELD_NAME;
			}

			// If surveys exist, as timestamp and identifier fields
			if ($exportSurveyFields && $prev_form != $this_form && isset($Proj->forms[$this_form]['survey_id'])) {
				// Alter $meta_array
				$ob2 = new stdClass();
				$ob2->ELEMENT_TYPE = 'text';
				$ob2->FIELD_NAME = $this_form . '_timestamp';
				$ob2->ELEMENT_LABEL = 'Survey Timestamp';
				$ob2->ELEMENT_ENUM = '';
				$ob2->FIELD_UNITS = '';
				$ob2->ELEMENT_VALIDATION_TYPE = '';
				$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
			}
		}

		// Set values for next loop
		$prev_form = $this_form;
		$prev_field = $ob->FIELD_NAME;
	}

	// Now reset field_names array
	$field_names = array_keys($meta_array);


	// $spss_data_type_array = "";
	$spss_format_dates = "";
	$spss_variable_label = "VARIABLE LABEL ";
	$spss_variable_level = array();
	$sas_label_section = "\ndata redcap;\n\tset redcap;\n";
	$sas_value_label = "proc format;\n";
	$sas_input = "input\n";
	$sas_informat = "";
	$sas_format = "";
	$stata_insheet = "insheet ";
	$stata_var_label = "";
	$stata_inf_label = "";
	$stata_value_label = "";
	$stata_date_format = "";

	$first_label = true;
	$large_name_counter = 0;
	$large_name = false;


	// Use arrays for string replacement
	$orig = array("'", "\"", "\r\n", "\r", "\n", "&lt;", "<=");
	$repl = array("", "", " ", " ", " ", "<", "< =");

	//print_array($meta_array);print_array($field_names);exit;


	// Loop through all metadata fields
	for ($x = 0; $x <= count($field_names) + 1; $x++) {

		if (($x % 5) == 0 && $x != 0) {
			$spss_string .= "\n\t";
		}
		$large_name = false;

		// Set field object for this loop
		$ob = $meta_array[$field_names[$x]];

		// Remove any . or - in the field name (as a result of checkbox raw values containing . or -)
		// $ob->FIELD_NAME = str_replace(array("-", "."), array("_", "_"), (string)$ob->FIELD_NAME);

		// Convert "sql" field types to "select" field types so that their Select Choices come out correctly in the syntax files.
		/**
		 * override for autocomplete plugin code starts here
		 * add autocomplete type to this check
		 */
		if ($ob->ELEMENT_TYPE == "sql" || $ob->ELEMENT_TYPE == "autocomplete") /**
		 * end override for autocomplete plugin code
		 */ {
			// Change to select
			$ob->ELEMENT_TYPE = "select";
			// Now populate it's choices by running the query
			$ob->ELEMENT_ENUM = getSqlFieldEnum($ob->ELEMENT_ENUM);
		} elseif ($ob->ELEMENT_TYPE == "yesno") {
			$ob->ELEMENT_ENUM = YN_ENUM;
		} elseif ($ob->ELEMENT_TYPE == "truefalse") {
			$ob->ELEMENT_ENUM = TF_ENUM;
		}

		//Remove any offending characters from label
		$ob->ELEMENT_LABEL = str_replace($orig, $repl, label_decode(html_entity_decode($ob->ELEMENT_LABEL, ENT_QUOTES)));

		if ($field_names[$x] != "") {
			if (strlen($field_names[$x]) >= 31) {
				$short_name = substr($field_names[$x], 0, 20) . "_v_" . $large_name_counter;
				$sas_label_section .= "\tlabel " . $short_name . "='" . $ob->ELEMENT_LABEL . "';\n";
				$stata_var_label .= "label variable " . $short_name . ' "' . $ob->ELEMENT_LABEL . '"' . "\n";
				$stata_insheet .= $short_name . " ";
				$large_name_counter++;
				$large_name = true;
			}
			if (!$large_name) {
				$sas_label_section .= "\tlabel " . $field_names[$x] . "='" . $ob->ELEMENT_LABEL . "';\n";
				$stata_var_label .= "label variable " . $field_names[$x] . ' "' . $ob->ELEMENT_LABEL . '"' . "\n";
				$stata_insheet .= $field_names[$x] . " ";
			}
			$spss_variable_label .= $field_names[$x] . " '" . $ob->ELEMENT_LABEL . "'\n\t/";
			$R_label_string .= "\nlabel(data$" . $field_names[$x] . ")=" . '"' . $ob->ELEMENT_LABEL . '"';
			if (($ob->FIELD_UNITS != Null) || ($ob->FIELD_UNITS != "")) {
				$R_units_string .= "\nunits(data$" . $field_names[$x] . ")=" . '"' . $ob->FIELD_UNITS . '"';
			}
		}

		# Checking for single element enum (i.e. if it is coded with a number or letter)
		$single_element_enum = true;
		if (substr_count(((string)$ob->ELEMENT_ENUM), ",") > 0) {
			$single_element_enum = false;
		}

		# Select value labels are created
		if (($ob->ELEMENT_TYPE == "yesno" || $ob->ELEMENT_TYPE == "truefalse" || $ob->ELEMENT_TYPE == "select" || $ob->ELEMENT_TYPE == "advcheckbox" || $ob->ELEMENT_TYPE == "radio") && !preg_match("/\+\+SQL\+\+/", (string)$ob->ELEMENT_ENUM)) {

			//Remove any apostrophes from the Choice Labels
			$ob->ELEMENT_ENUM = str_replace($orig, $repl, label_decode($ob->ELEMENT_ENUM));

			//Place $ in front of SAS value if using non-numeric coded values for dropdowns/radios
			$sas_val_enum_num = ""; //default
			$numericChoices = true;
			foreach (array_keys(parseEnum($ob->ELEMENT_ENUM)) as $key) {
				if (!is_numeric($key)) {
					// If at least one key is not numeric, then stop looping because we have all we need.
					$sas_val_enum_num = "$";
					$numericChoices = false;
					break;
				}
			}

			if ($first_label) {
				if (!$single_element_enum) {
					$value_labels_spss .= "\n" . (string)$ob->FIELD_NAME . " ";
				}
				$R_factors_string .= "\ndata$" . (string)$ob->FIELD_NAME . ".factor = factor(data$" . (string)$ob->FIELD_NAME . ",levels=c(";
				$R_levels_string .= "\nlevels(data$" . (string)$ob->FIELD_NAME . ".factor)=c(";
				$first_label = false;
				if (!$large_name && !$single_element_enum) {
					$sas_value_label .= "\tvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
					$sas_format_string .= "\n\tformat " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_.;\n";
					if ($numericChoices) {
						$stata_inf_label .= "\nlabel values " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_\n";
						$stata_value_label = "label define " . (string)$ob->FIELD_NAME . "_ ";
					}
				} else if ($large_name && !$single_element_enum) {
					$sas_value_label .= "\tvalue $sas_val_enum_num" . $short_name . "_ ";
					$sas_format_string .= "\n\tformat " . $short_name . " " . $short_name . "_.;\n";
					if ($numericChoices) {
						$stata_value_label .= "label define " . $short_name . "_ ";
						$stata_inf_label .= "\nlabel values " . $short_name . " " . $short_name . "_\n";
					}
				}
			} else if (!$first_label) {
				if (!$single_element_enum) {
					$value_labels_spss .= "\n/" . (string)$ob->FIELD_NAME . " ";
					if (!$large_name) {
						$sas_value_label .= "\n\tvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
						$sas_format_string .= "\tformat " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_.;\n";
						if ($numericChoices) {
							$stata_value_label .= "\nlabel define " . (string)$ob->FIELD_NAME . "_ ";
							$stata_inf_label .= "label values " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_\n";
						}
					}
				}
				$R_factors_string .= "data$" . (string)$ob->FIELD_NAME . ".factor = factor(data$" . (string)$ob->FIELD_NAME . ",levels=c(";
				$R_levels_string .= "levels(data$" . (string)$ob->FIELD_NAME . ".factor)=c(";
				if ($large_name && !$single_element_enum) {
					$sas_value_label .= "\n\tvalue $sas_val_enum_num" . $short_name . "_ ";
					$sas_format_string .= "\tformat " . $short_name . " " . $short_name . "_.;\n";
					if ($numericChoices) {
						$stata_value_label .= "\nlabel define " . $short_name . "_ "; //LS inserted this line 24-Feb-2012
						$stata_inf_label .= "label values " . $short_name . " " . $short_name . "_\n";
					}
				}
			}

			$first_new_line_explode_array = explode("\\n", (string)$ob->ELEMENT_ENUM);

			// Loop through multiple choice options
			$select_is_text = false;
			$select_determining_array = array();
			for ($counter = 0; $counter < count($first_new_line_explode_array); $counter++) {
				if (!$single_element_enum) {

					// SAS: Add line break after 2 multiple choice options
					if (($counter % 2) == 0 && $counter != 0) {
						$sas_value_label .= "\n\t\t";
						$value_labels_spss .= "\n\t";
					}

					$second_comma_explode = explode(",", $first_new_line_explode_array[$counter], 2);
					$value_labels_spss .= "'" . trim($second_comma_explode[0]) . "' ";
					$value_labels_spss .= "'" . trim($second_comma_explode[1]) . "' ";
					if (!is_numeric(trim($second_comma_explode[0])) && is_numeric(substr(trim($second_comma_explode[0]), 0, 1))) {
						// if enum raw value is not a number BUT begins with a number, add quotes around it for SAS only (parsing issue)
						$sas_value_label .= "'" . trim($second_comma_explode[0]) . "'=";
					} else {
						$sas_value_label .= trim($second_comma_explode[0]) . "=";
					}
					$sas_value_label .= "'" . trim($second_comma_explode[1]) . "' ";
					if ($numericChoices) {
						$stata_value_label .= trim($second_comma_explode[0]) . " ";
						$stata_value_label .= "\"" . trim($second_comma_explode[1]) . "\" ";
					}
					$select_determining_array[] = $second_comma_explode[0];
					$R_factors_string .= '"' . trim($second_comma_explode[0]) . '",';
					$R_levels_string .= '"' . trim($second_comma_explode[1]) . '",';
				} else {
					$select_determining_array[] = $second_comma_explode[0];
					$R_factors_string .= '"' . trim($first_new_line_explode_array[$counter]) . '",';
					$R_levels_string .= '"' . trim($first_new_line_explode_array[$counter]) . '",';
				}
			}
			$R_factors_string = rtrim($R_factors_string, ",");
			$R_factors_string .= "))\n"; //pharris 09/28/05
			$R_levels_string = rtrim($R_levels_string, ",");
			$R_levels_string .= ")\n";
			if (!$single_element_enum) {
				$sas_value_label = rtrim($sas_value_label, " ");
				$sas_value_label .= ";";
			}
			if (!$single_element_enum) {
				foreach ($select_determining_array as $value) {
					if (preg_match("/([A-Za-z])/", $value)) {
						$select_is_text = true;
					}
				}
			} else {
				foreach ($first_new_line_explode_array as $value) {
					if (preg_match("/([A-Za-z])/", $value)) {
						$select_is_text = true;
					}
				}
			}


		} else if (preg_match("/\+\+SQL\+\+/", (string)$ob->ELEMENT_ENUM)) {

			$select_is_text = true;

		}
		################################################################################
		################################################################################

		# If the ELEMENT_VALIDATION_TYPE is a float the data is define as a Number
		if ($ob->ELEMENT_VALIDATION_TYPE == "float" || $ob->ELEMENT_TYPE == "calc") {
			$spss_string .= $ob->FIELD_NAME . " (F8.2) ";
			if (!$large_name) {
				$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
				$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
				$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
			} elseif ($large_name) {
				$sas_informat .= "\tinformat " . $short_name . " best32. ;\n";
				$sas_format .= "\tformat " . $short_name . " best12. ;\n";
				$sas_input .= "\t\t" . $short_name . "\n";
			}
			// $spss_data_type_array[$x] = "NUMBER";
			$spss_variable_level[] = $ob->FIELD_NAME . " (SCALE)";

		} elseif ($ob->ELEMENT_TYPE == "slider" || $ob->ELEMENT_VALIDATION_TYPE == "int") {
			$spss_string .= $ob->FIELD_NAME . " (F8) ";
			if (!$large_name) {
				$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
				$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
				$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
			} elseif ($large_name) {
				$sas_informat .= "\tinformat " . $short_name . " best32. ;\n";
				$sas_format .= "\tformat " . $short_name . " best12. ;\n";
				$sas_input .= "\t\t" . $short_name . "\n";
			}
			// $spss_data_type_array[$x] = "NUMBER";
			$spss_variable_level[] = $ob->FIELD_NAME . " (SCALE)";

			# If the ELEMENT_VALIDATION_TYPE is a DATE a treat the data as a date
		} elseif ($ob->ELEMENT_VALIDATION_TYPE == "date" || $ob->ELEMENT_VALIDATION_TYPE == "date_ymd" || $ob->ELEMENT_VALIDATION_TYPE == "date_mdy" || $ob->ELEMENT_VALIDATION_TYPE == "date_dmy") {
			$spss_string .= $ob->FIELD_NAME . " (SDATE10) ";
			$spss_format_dates .= "FORMATS " . $ob->FIELD_NAME . "(ADATE10).\n";
			if (!$large_name) {
				$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " yymmdd10. ;\n";
				$sas_format .= "\tformat " . $ob->FIELD_NAME . " yymmdd10. ;\n";
				$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
				$stata_date_format .= "\ntostring " . $ob->FIELD_NAME . ", replace";
				$stata_date_format .= "\ngen _date_ = date(" . $ob->FIELD_NAME . ",\"YMD\")\n";
				$stata_date_format .= "drop " . $ob->FIELD_NAME . "\n";
				$stata_date_format .= "rename _date_ " . $ob->FIELD_NAME . "\n";
				$stata_date_format .= "format " . $ob->FIELD_NAME . " %dM_d,_CY\n";
			} elseif ($large_name) {
				$sas_informat .= "\tinformat " . $short_name . " yymmdd10. ;\n";
				$sas_format .= "\tformat " . $short_name . " yymmdd10. ;\n";
				$sas_input .= "\t\t" . $short_name . "\n";
				$stata_date_format .= "\ntostring " . $short_name . ", replace";
				$stata_date_format .= "\ngen _date_ = date(" . $short_name . ",\"YMD\")\n";
				$stata_date_format .= "drop " . $short_name . "\n";
				$stata_date_format .= "rename _date_ " . $short_name . "\n";
				$stata_date_format .= "format " . $short_name . " %dM_d,_CY\n";
			}

			# If the ELEMENT_VALIDATION_TYPE is TIME (military)
		} elseif ($ob->ELEMENT_VALIDATION_TYPE == "time") {

			$spss_string .= $ob->FIELD_NAME . " (A500) ";
			if (!$large_name) {
				$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " time5. ;\n";
				$sas_format .= "\tformat " . $ob->FIELD_NAME . " time5. ;\n";
				$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
			} elseif ($large_name) {
				$sas_informat .= "\tinformat " . $short_name . " time5. ;\n";
				$sas_format .= "\tformat " . $short_name . " time5. ;\n";
				$sas_input .= "\t\t" . $short_name . "\n";
			}

			# If the ELEMENT_VALIDATION_TYPE is DATETIME or DATETIME_SECONDS
			// } elseif (substr($ob->ELEMENT_VALIDATION_TYPE, 0, 8) == "datetime") {


			# If the object type is select then the variable $select_is_text is checked to
			# see if it is a TEXT or a NUMBER and treated accordanly.
		} elseif ($ob->ELEMENT_TYPE == "yesno" || $ob->ELEMENT_TYPE == "truefalse" || $ob->ELEMENT_TYPE == "select" || $ob->ELEMENT_TYPE == "advcheckbox" || $ob->ELEMENT_TYPE == "radio") {
			if ($select_is_text) {
				$temp_trim = rtrim("varchar(500)", ")");
				# Divides the string to get the number of caracters
				$temp_explode_number = explode("(", $temp_trim);
				$spss_string .= $ob->FIELD_NAME . " (A" . $temp_explode_number[1] . ") ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$" . $temp_explode_number[1] . ". ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$" . $temp_explode_number[1] . ". ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . " \$\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$" . $temp_explode_number[1] . ". ;\n";
					$sas_format .= "\tformat " . $short_name . " \$" . $temp_explode_number[1] . ". ;\n";
					$sas_input .= "\t\t" . $short_name . " \$\n";
				}
				// $spss_data_type_array[$x] = "TEXT";
			} else {
				$spss_string .= $ob->FIELD_NAME . " (F3) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " best32. ;\n";
					$sas_format .= "\tformat " . $short_name . " best12. ;\n";
					$sas_input .= "\t\t" . $short_name . "\n";
				}
				// $spss_data_type_array[$x] = "NUMBER";
			}


			# If the object type is text a treat the data like a text and look for the length
			# that is specified in the database
		} elseif ($ob->ELEMENT_TYPE == "text" || $ob->ELEMENT_TYPE == "calc" || $ob->ELEMENT_TYPE == "file") {

			$spss_string .= $ob->FIELD_NAME . " (A500) ";
			if (!$large_name) {
				$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$500. ;\n";
				$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$500. ;\n";
				$sas_input .= "\t\t" . $ob->FIELD_NAME . " \$\n";
			} elseif ($large_name) {
				$sas_informat .= "\tinformat " . $short_name . " \$500. ;\n";
				$sas_format .= "\tformat " . $short_name . " \$500. ;\n";
				$sas_input .= "\t\t" . $short_name . " \$\n";
			}


			# If the object type is textarea a treat the data like a text and specify a large
			# string size.
		} elseif ($ob->ELEMENT_TYPE == "textarea") {
			$spss_string .= $ob->FIELD_NAME . " (A30000) ";
			if (!$large_name) {
				$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$5000. ;\n";
				$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$5000. ;\n";
				$sas_input .= "\t\t" . $ob->FIELD_NAME . " \$\n";
			} elseif ($large_name) {
				$sas_informat .= "\tinformat " . $short_name . " \$5000. ;\n";
				$sas_format .= "\tformat " . $short_name . " \$5000. ;\n";
				$sas_input .= "\t\t" . $short_name . " \$\n";
			}
			// $spss_data_type_array[$x] = "TEXT";
		}

	}

	// File names
	$today = date("Y-m-d_Hi");
	$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20);
	$data_file_name = $projTitleShort . "_DATA_NOHDRS_" . $today . ".csv";
	$data_file_name_WH = $projTitleShort . "_DATA_" . $today . ".csv";
	$data_file_name_labels = $projTitleShort . "_DATA_LABELS_" . $today . ".csv";
	$export_sps_file_name = $projTitleShort . "_SPSS_" . $today . ".sps";
	$export_sas_file_name = $projTitleShort . "_SAS_" . $today . ".sas";
	$export_R_file_name = $projTitleShort . "_R_" . $today . ".r";
	$export_stata_file_name = $projTitleShort . "_STATA_" . $today . ".do";

	//Finish up syntax files
	$spss_string = rtrim($spss_string);
	$spss_string .= ".\n";
	$spss_string .= "\nVARIABLE LEVEL " . implode("\n\t/", $spss_variable_level) . ".\n";
	$spss_string .= "\n" . substr_replace($spss_variable_label, ".", -3) . "\n\n";
	$spss_string .= rtrim($value_labels_spss);
	$spss_string .= ".\n\n$spss_format_dates\nSET LOCALE=en_us.\nEXECUTE.\n";

	$spss_string = str_replace("data_place_holder_name", $data_file_name, $spss_string);

	$sas_read_string .= "%macro removeOldFile(bye); %if %sysfunc(exist(&bye.)) %then %do; proc delete data=&bye.; run; "
		. "%end; %mend removeOldFile; %removeOldFile(work.redcap); data REDCAP; "; // Suggested change by Ray Balise
	//$sas_read_string .= "proc delete data=REDCAP;\nrun;\n\ndata REDCAP;"; // Added to prevent deleting all temp files
	//$sas_read_string .= "proc delete data=_ALL_;\nrun;\n\ndata REDCAP;";
	$sas_read_string .= "%let _EFIERR_ = 0; ";
	$sas_read_string .= "infile '" . $data_file_name . "'";
	$sas_read_string .= " delimiter = ',' MISSOVER DSD lrecl=32767 firstobs=1 ; ";
	$sas_read_string .= "\n" . $sas_informat;
	$sas_read_string .= "\n" . $sas_format;
	$sas_read_string .= "\n" . $sas_input;
	$sas_read_string .= ";\n";
	$sas_read_string .= "if _ERROR_ then call symput('_EFIERR_',\"1\");\n";
	$sas_read_string .= "run;\n\nproc contents;run;\n\n";
	$sas_read_string .= $sas_label_section . "\trun;\n";
	$sas_value_label .= "\n\trun;\n";
	$sas_format_string .= "\trun;\n";
	$sas_read_string .= "\n" . $sas_value_label;
	$sas_read_string .= "\n" . $sas_format_string;
	$sas_read_string .= "\nproc contents data=redcap;";
	$sas_read_string .= "\nproc print data=redcap;";
	$sas_read_string .= "\nrun;\nquit;";

	$stata_order = "order " . substr($stata_insheet, 8);
	$stata_insheet .= "using " . "\"" . $data_file_name . "\", nonames";

	$stata_string .= $stata_insheet . "\n\n";
	$stata_string .= "label data " . "\"" . $data_file_name . "\"" . "\n\n";
	$stata_string .= $stata_value_label . "\n";
	$stata_string .= $stata_inf_label . "\n\n";
	$stata_string .= $stata_date_format . "\n";
	$stata_string .= $stata_var_label . "\n";
	$stata_string .= $stata_order . "\n";
	$stata_string .= "set more off\ndescribe\n";

	$R_string .= "#Read Data\ndata=read.csv('" . $data_file_name_WH . "')\n";
	$R_string .= $R_label_string;
	$R_string .= $R_units_string;
	$R_string .= $R_factors_string;
	$R_string .= $R_levels_string;


	$today = date("Y-m-d-H-i-s");
	$docs_comment = $docs_comment_WH = "Data export file created by $userid on $today";
	$spss_docs_comment = "Spss syntax file created by $userid on $today";
	$sas_docs_comment = "Sas syntax file created by $userid on $today";
	$stata_docs_comment = "Stata syntax file created by $userid on $today";
	$R_docs_comment = "R syntax file created by $userid on $today";
	$data = prep($data);


	#########################################

	// Replace any MS Word chacters in the data
	$data_csv = replaceMSchars($data_csv);
	$data_csv_labels = replaceMSchars($data_csv_labels);

	//Add comment in last field if these are date shifted
	$doc_rights = $do_date_shift ? "'DATE_SHIFT'" : "NULL";

	// Set flag for checking if error occurs during saving of files to docs table
	$is_export_error = false;

	### Creates the STATA syntax file
	$stata_string = strip_tags($stata_string); // Do NOT use addBOMtoUTF8() on Stata because BOM causes issues in syntax file
	$docs_size = strlen($stata_string);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $export_stata_file_name . "', NULL, '" . TODAY . "','$docs_size','" . $stata_docs_comment . "','application/octet-stream',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$stata_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($export_stata_file_name, $stata_string, $stata_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}

	### Creates the R syntax file
	$R_string = addBOMtoUTF8(strip_tags($R_string));
	$docs_size = strlen($R_string);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $export_R_file_name . "', NULL, '" . TODAY . "','$docs_size','" . $R_docs_comment . "','application/octet-stream',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$r_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($export_R_file_name, $R_string, $r_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}

	### Creates the SAS syntax file
	$sas_read_string = addBOMtoUTF8(strip_tags($sas_read_string));
	$docs_size = strlen($sas_read_string);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $export_sas_file_name . "', NULL, '" . TODAY . "','$docs_size','" . $sas_docs_comment . "','application/octet-stream',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$sas_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($export_sas_file_name, $sas_read_string, $sas_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}

	### Creates the data comma separeted value file WITHOUT headers
	$data_csv_temp = addBOMtoUTF8($data_csv);
	$docs_size = strlen($data_csv_temp);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $data_file_name . "', NULL, '" . TODAY . "','$docs_size','" . $docs_comment . "','application/csv',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$data_wo_hdr_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($data_file_name, $data_csv_temp, $data_wo_hdr_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}
	unset($data_csv_temp);

	### Creates the data comma separeted value file WITH header
	$data_csv = addBOMtoUTF8($headers . $data_csv);
	$docs_size = strlen($data_csv);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $data_file_name_WH . "', NULL, '" . TODAY . "','$docs_size','" . $docs_comment_WH . "','application/csv',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$data_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($data_file_name_WH, $data_csv, $data_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}
	unset($data_csv);

	### Creates the SPSS syntax file
	$spss_string = addBOMtoUTF8(strip_tags($spss_string));
	$docs_size = strlen($spss_string);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $export_sps_file_name . "', NULL, '" . TODAY . "','$docs_size','" . $spss_docs_comment . "','application/octet-stream',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$spss_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($export_sps_file_name, $spss_string, $spss_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}

	### Creates the data comma separeted value file WITH LABELS
	$data_csv_labels = addBOMtoUTF8($headers_labels . $data_csv_labels);
	$docs_size = strlen($data_csv_labels);
	$export_sql = "INSERT INTO redcap_docs (project_id,docs_name,docs_file,docs_date,docs_size,docs_comment,docs_type,docs_rights,export_file) "
		. "VALUES ($project_id, '" . $data_file_name_labels . "', NULL, '" . TODAY . "','$docs_size','" . $docs_comment . "','application/csv',$doc_rights,1)";
	if (!db_query($export_sql)) {
		$is_export_error = true;
	} else {
		// Get insert id
		$data_labels_doc_id = db_insert_id();
		// Store the file in the file system
		if (!DataExport::storeExportFile($data_file_name_labels, $data_csv_labels, $data_labels_doc_id, $docs_size)) {
			$is_export_error = true;
		}
	}

	#########################################

	//Catch the error if the CSV data file is too large for MySQL to handle
	if ($is_export_error) {
		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		renderPageTitle("<img src='" . APP_PATH_IMAGES . "application_go.png'> {$lang['app_03']}");
		print  "<div class='red' style='margin:20px 0;'><img src='" . APP_PATH_IMAGES . "exclamation.png'>
					<b>{$lang['global_01']}:</b><br/>{$lang['data_export_tool_62']}";
		if ($super_user) {
			if ($edoc_storage_option == '1') {
				print $lang['data_export_tool_136'];
			} elseif ($edoc_storage_option == '0') {
				print $lang['data_export_tool_135'] . " (<b>" . EDOC_PATH . "</b>)" . $lang['period'];
			} else {
				print $lang['data_export_tool_135'] . " " . $lang['period'];
			}
			print " " . $lang['data_export_tool_137'];
		} else {
			print "{$lang['data_export_tool_64']} <a href='mailto:$project_contact_email' style='font-family:Verdana;'>$project_contact_name</a>
				   {$lang['data_export_tool_65']}";
		}
		print  "</div>";
		renderPrevPageLink(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	//Catch the error if there were data conversion problems
	if ($is_data_conversion_error) {
		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		renderPageTitle("<img src='" . APP_PATH_IMAGES . "application_go.png'> {$lang['app_03']}");
		print  "<div class='red' style='margin:20px 0;'><img src='" . APP_PATH_IMAGES . "exclamation.png'>
					<b>{$lang['global_01']}:</b><br/>{$lang['data_export_tool_62']}";
		if ($super_user) {
			print  $lang['data_export_tool_63'];
		} else {
			print  "{$lang['data_export_tool_64']} <a href='mailto:$project_contact_email' style='font-family:Verdana;'>$project_contact_name</a>
				{$lang['data_export_tool_65']}";
		}
		print $is_data_conversion_error_msg;
		print  "</div>";
		renderPrevPageLink(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}


	// Header
	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

	renderPageTitle("<img src='" . APP_PATH_IMAGES . "application_go.png'> {$lang['app_03']}");

	print  "<div style='text-align:center;padding-top:10px;max-width:700px;'>
				<span class='darkgreen' style='padding:8px 80px;'>
				<img src='" . APP_PATH_IMAGES . "tick.png' class='imgfix'> {$lang['data_export_tool_05']}
				</span>
			</div>
			<p><br>{$lang['data_export_tool_06']}<br><br>";

	// Button back to previous page
	$prevPage = (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : PAGE_FULL . "?pid=$project_id";
	print  "<button class='jqbutton' onclick=\"window.location.href='$prevPage';\">
				<img src='" . APP_PATH_IMAGES . "arrow_left.png' class='imgfix'>
				{$lang['config_functions_40']}
			</button>";

	//Set the CSV icon to date-shifted look if the data in these files were date shifted
	if ($do_date_shift) {
		$csv_img = "download_csvdata_ds.gif";
		$csvexcel_img = "download_csvexcel_raw_ds.gif";
		$csvexcellabels_img = "download_csvexcel_labels_ds.gif";
	} else {
		$csv_img = "download_csvdata.gif";
		$csvexcel_img = "download_csvexcel_raw.gif";
		$csvexcellabels_img = "download_csvexcel_labels.gif";
	}

	// If Send-It is not enabled for Data Export and File Repository, then hide the link to utilize Send-It
	$sendItLinkDisplay = ($sendit_enabled == '1' || $sendit_enabled == '3') ? "" : "display:none;";

	//Table header
	print  "<div style='max-width:700px;'>";
	print  "<table style='border: 1px solid #DODODO; border-collapse: collapse; width: 100%'>
			<tr class='grp2'>
				<td colspan='2' style='font-family:Verdana;font-size:12px;text-align:right;'>
				</td>
				<td style='font-family:Verdana;font-size:12px;text-align:center;'>
					{$lang['docs_58']}<br>{$lang['data_export_tool_51']}
				</td>
			</tr>";
	//Excel
	print  '<tr class="odd">
				<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-left:1px solid #D0D0D0;">
					<img src="' . APP_PATH_IMAGES . 'excelicon.gif" title="' . $lang['data_export_tool_15'] . '" alt="' . $lang['data_export_tool_15'] . '" />
				</td>
			    <td style="font-family:Verdana;font-size:11px;padding:10px;" valign="top">
					<b>' . $lang['data_export_tool_15'] . '</b><br>
					' . $lang['data_export_tool_118'] . '<br><br>
					<i>' . $lang['global_02'] . ': ' . $lang['data_export_tool_17'] . '</i>
				</td>
				<td valign="top" style="text-align:right;width:100px;padding-top:10px;">
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $data_labels_doc_id . '">
						<img src="' . APP_PATH_IMAGES . $csvexcellabels_img . '" title="' . $lang['data_export_tool_60'] . '" alt="' . $lang['data_export_tool_60'] . '"></a> &nbsp;
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $data_doc_id . '">
						<img src="' . APP_PATH_IMAGES . $csvexcel_img . '" title="' . $lang['data_export_tool_60'] . '" alt="' . $lang['data_export_tool_60'] . '"></a>
					<div style="text-align:left;padding:5px 0 1px;' . $sendItLinkDisplay . '">
						<div style="line-height:5px;">
							<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
								href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;"
								onclick=\'$("#sendit_' . $data_doc_id . '").toggle("blind",{},"fast");\'>' . $lang['data_export_tool_66'] . '</a>
						</div>
						<div id="sendit_' . $data_doc_id . '" style="display:none;padding:4px 0 4px 6px;">
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $data_labels_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_120'] . '</a>
							</div>
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $data_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_119'] . '</a>
							</div>
						</div>
					</div>
				</td>
			</tr>';
	//SPSS
	print '<tr class="even noncsv">
				<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-left:1px solid #D0D0D0;">
					<img src="' . APP_PATH_IMAGES . 'spsslogo_small.png" title="' . $lang['data_export_tool_07'] . '" alt="' . $lang['data_export_tool_07'] . '" />
				</td>
				<td style="font-family:Verdana;font-size:11px;padding:10px;" valign="top">
					<b>' . $lang['data_export_tool_07'] . '</b><br />' . $lang['global_24'] . $lang['colon'] . " " . $lang['data_export_tool_08'] . '<br>
					<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#spss_detail").toggle("fade");\'>' . $lang['data_export_tool_08b'] . '</a>
					<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="spss_detail">
						<b>' . $lang['data_export_tool_01'] . '</b><br>' .
		$lang['data_export_tool_08c'] . ' <font color="green">/folder/subfolder/</font> (e.g., /Users/administrator/documents/)<br><br>' .
		$lang['data_export_tool_08d'] . '
						<br><font color=green>FILE HANDLE data1 NAME=\'DATA.CSV\' LRECL=90000.</font><br><br>' .
		$lang['data_export_tool_08e'] . '<br>
						<font color=green>FILE HANDLE data1 NAME=\'<font color=red>/folder/subfolder/</font>DATA.CSV\' LRECL=90000.</font><br><br>' .
		$lang['data_export_tool_08f'] . '
					</div>
				</td>
				<td valign="top" style="text-align:right;width:100px;padding-top:10px;">
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $spss_doc_id . '">
						<img src="' . APP_PATH_IMAGES . 'download_spss.gif" title="' . $lang['data_export_tool_68'] . '" alt="' . $lang['data_export_tool_68'] . '">
					</a> &nbsp;
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $data_wo_hdr_doc_id . '">
						<img src="' . APP_PATH_IMAGES . $csv_img . '" title="' . $lang['data_export_tool_69'] . '" alt="' . $lang['data_export_tool_69'] . '"></a>
					<div style="padding-left:11px;text-align:left;">
						<a href="' . APP_PATH_WEBROOT . 'DataExport/spss_pathway_mapper.php?pid=' . $project_id . '"
						><img src="' . APP_PATH_IMAGES . 'download_pathway_mapper.gif" title="' . $lang['data_export_tool_70'] . '" alt="' . $lang['data_export_tool_70'] . '"></a> &nbsp;
					</div>
					<div style="text-align:left;padding:5px 0 1px;' . $sendItLinkDisplay . '">
						<div style="line-height:5px;">
							<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
								href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;" onclick=\'
									$("#sendit_' . $spss_doc_id . '").toggle("blind",{},"fast");
								\'>' . $lang['data_export_tool_66'] . '</a>
						</div>
						<div id="sendit_' . $spss_doc_id . '" style="display:none;padding:4px 0 4px 6px;">
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $spss_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_71'] . '</a>
							</div>
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $data_wo_hdr_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_72'] . '</a>
							</div>
						</div>
					</div>
				</td>
			</tr>';
	//SAS
	print '<tr class="odd noncsv">
				<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-left:1px solid #D0D0D0;">
					<img src="' . APP_PATH_IMAGES . 'saslogo_small.png" title="' . $lang['data_export_tool_11'] . '" alt="' . $lang['data_export_tool_11'] . '" />
				</td>
				<td style="font-family:Verdana;font-size:11px;padding:10px;" valign="top">
					<b>' . $lang['data_export_tool_11'] . '</b><br />' . $lang['global_24'] . $lang['colon'] . " " . $lang['data_export_tool_130'] . '<br>
					<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#sas_detail").toggle("fade");\'>' . $lang['data_export_tool_08b'] . '</a>
					<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="sas_detail">
						<b>' . $lang['data_export_tool_131'] . '</b><br>' .
		$lang['data_export_tool_132'] . ' <font color="green">/folder/subfolder/</font> (e.g., /Users/administrator/documents/)<br><br>' .
		$lang['data_export_tool_133'] . '
						<br>... <font color=green>infile \'DATA.CSV\' delimiter = \',\' MISSOVER DSD lrecl=32767 firstobs=1 ;</font><br><br>' .
		$lang['data_export_tool_08e'] . '<br>
						... <font color=green>infile \'<font color=red>/folder/subfolder/</font>DATA.CSV\' delimiter = \',\' MISSOVER DSD lrecl=32767 firstobs=1 ;</font><br><br>' .
		$lang['data_export_tool_134'] . '
					</div>
				</td>
				<td valign="top" style="text-align:right;width:100px;padding-top:10px;">
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $sas_doc_id . '">
						<img src="' . APP_PATH_IMAGES . 'download_sas.gif" title="' . $lang['data_export_tool_74'] . '" alt="' . $lang['data_export_tool_74'] . '">
					</a> &nbsp;
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $data_wo_hdr_doc_id . '">
						<img src="' . APP_PATH_IMAGES . $csv_img . '" title="' . $lang['data_export_tool_69'] . '" alt="' . $lang['data_export_tool_69'] . '"></a>
					<div style="padding-left:11px;text-align:left;">
						<a href="' . APP_PATH_WEBROOT . 'DataExport/sas_pathway_mapper.php?pid=' . $project_id . '"
						><img src="' . APP_PATH_IMAGES . 'download_pathway_mapper.gif"></a> &nbsp;
					</div>
					<div style="text-align:left;padding:5px 0 1px;' . $sendItLinkDisplay . '">
						<div style="line-height:5px;">
							<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
								href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;" onclick=\'
									$("#sendit_' . $sas_doc_id . '").toggle("blind",{},"fast");
								\'>' . $lang['data_export_tool_66'] . '</a>
						</div>
						<div id="sendit_' . $sas_doc_id . '" style="display:none;padding:4px 0 4px 6px;">
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $sas_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_71'] . '</a>
							</div>
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $data_wo_hdr_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_72'] . '</a>
							</div>
						</div>
					</div>
				</td>
			</tr>';
	//R
	print '<tr class="even noncsv">
				<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-left:1px solid #D0D0D0;">
					<img src="' . APP_PATH_IMAGES . 'rlogo_small.png" title="' . $lang['data_export_tool_09'] . '" alt="' . $lang['data_export_tool_09'] . '" />
				</td>
				<td style="font-family:Verdana;font-size:11px;padding:10px;" valign="top">
					<b>' . $lang['data_export_tool_09'] . '</b><br />' . $lang['data_export_tool_10'] . '
				</td>
				<td valign="top" style="text-align:right;width:100px;padding-top:10px;">
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $r_doc_id . '">
						<img src="' . APP_PATH_IMAGES . 'download_r.gif" title="' . $lang['data_export_tool_75'] . '" alt="' . $lang['data_export_tool_75'] . '">
					</a> &nbsp;
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $data_doc_id . '&exporttype=R">
						<img src="' . APP_PATH_IMAGES . $csv_img . '" title="' . $lang['data_export_tool_69'] . '" alt="' . $lang['data_export_tool_69'] . '"></a>
					<div style="text-align:left;padding:5px 0 1px;' . $sendItLinkDisplay . '">
						<div style="line-height:5px;">
							<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
								href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;" onclick=\'
									$("#sendit_' . $r_doc_id . '").toggle("blind",{},"fast");
								\'>' . $lang['data_export_tool_66'] . '</a>
						</div>
						<div id="sendit_' . $r_doc_id . '" style="display:none;padding:4px 0 4px 6px;">
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $r_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_71'] . '</a>
							</div>
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $data_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_72'] . '</a>
							</div>
						</div>
					</div>
				</td>
			</tr>';
	//STATA
	print '<tr class="odd noncsv">
				<td valign="top" style="text-align:center;width:60px;padding-top:10px;border:0px;border-bottom:1px solid #D0D0D0;border-left:1px solid #D0D0D0;">
					<img src="' . APP_PATH_IMAGES . 'statalogo_small.png" title="' . $lang['data_export_tool_13'] . '" alt="' . $lang['data_export_tool_13'] . '" />
				</td>
				<td style="font-family:Verdana;font-size:11px;padding:10px;border-bottom:1px solid #D0D0D0;" valign="top">
					<b>' . $lang['data_export_tool_13'] . '</b><br />' . $lang['data_export_tool_14'] . '
				</td>
				<td valign="top" style="text-align:right;width:100px;padding-top:10px;border-bottom:1px solid #D0D0D0;">
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $stata_doc_id . '">
						<img src="' . APP_PATH_IMAGES . 'download_stata.gif" title="' . $lang['data_export_tool_76'] . '" alt="' . $lang['data_export_tool_76'] . '">
					</a> &nbsp;
					<a href="' . APP_PATH_WEBROOT . 'FileRepository/file_download.php?pid=' . $project_id . '&id=' . $data_wo_hdr_doc_id . '">
						<img src="' . APP_PATH_IMAGES . $csv_img . '" title="' . $lang['data_export_tool_69'] . '" alt="' . $lang['data_export_tool_69'] . '"></a>
					<div style="text-align:left;padding:5px 0 1px;' . $sendItLinkDisplay . '">
						<div style="line-height:5px;">
							<img src="' . APP_PATH_IMAGES . 'mail_small.png" style="position: relative; top: 5px;"><a
								href="javascript:;" style="color:#666;font-size:10px;text-decoration:underline;" onclick=\'
									$("#sendit_' . $stata_doc_id . '").toggle("blind",{},"fast");
								\'>' . $lang['data_export_tool_66'] . '</a>
						</div>
						<div id="sendit_' . $stata_doc_id . '" style="display:none;padding:4px 0 4px 6px;">
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $stata_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_71'] . '</a>
							</div>
							<div>
								&bull; <a href="javascript:;" onclick="popupSendIt(' . $data_wo_hdr_doc_id . ',2);" style="font-size:10px;">' . $lang['data_export_tool_72'] . '</a>
							</div>
						</div>
					</div>
				</td>
			</tr>';

	print '</table>';
	print '</div><br><br><br><br>';
}