
// Initialize datepicker for .cal2
function initCal2Datepicker() {
	$('.cal2').datepicker({yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
}

//Set new date for Auto Generated Schedule and check Day Offset Range min and max
function offsetRangeCheck(event_id,min,max,origDate,checkRange) {
	//Get new day of week when setting date in Scheduling module
	var newDateValOrig = $('#date_'+event_id).val();
	var newDateVal = newDateValOrig
	if (newDateVal.length < 1) {
		$('#weekday_'+event_id).html('');
		return;
	}
	myDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
	// Convert date to YMD format
	newDateVal = newDateVal.replace(user_date_format_delimiter,'-').replace(user_date_format_delimiter,'-');
	if (user_date_format_validation == 'mdy') {
		newDateVal = date_mdy2ymd(newDateVal);
	} else if (user_date_format_validation == 'dmy') {
		newDateVal = date_dmy2ymd(newDateVal);
	}
	var newDateValArray = newDateVal.split('-');
	myDate = new Date(newDateValArray[0], newDateValArray[1]-1, newDateValArray[2], 0, 0, 0);
	var newDate = myDays[myDate.getDay()];
	if (newDate == 'Sunday' || newDate == 'Saturday') {
		$('#weekday_'+event_id).html('<span style="color:red;">'+newDate+'</span>');
	} else {
		$('#weekday_'+event_id).html(newDate);
	}
	//Make sure new date is not too far out of range of min and max
	if (checkRange) {
		var dt1 = origDate.split("-");
		var dat1 = new Date(origDate).valueOf();
		var dat2 = myDate.valueOf();
		var daydiff = (dat2-dat1)/(1000*86400);
		var minOrigDate = new Date(parseInt(dt1[0],10),parseInt(dt1[1],10)-1,parseInt(dt1[2],10)-min);
		minOrigDate = minOrigDate.getFullYear()+"-"+('0'+(minOrigDate.getMonth()+1))+"-"+('0'+minOrigDate.getDate()).slice(-2);
		var maxOrigDate = new Date(parseInt(dt1[0],10),parseInt(dt1[1],10)-1,parseInt(dt1[2],10)+max);
		maxOrigDate = maxOrigDate.getFullYear()+"-"+('0'+(maxOrigDate.getMonth()+1)).slice(-2)+"-"+('0'+maxOrigDate.getDate()).slice(-2);
		var minmaxtxt = "";
		var alertText = "";
		$("#alert_text").dialog('destroy');
		// Convert dates back to user-preferred format
		if (user_date_format_validation == 'mdy') {
			origDate = date_ymd2mdy(origDate).replace('-',user_date_format_delimiter).replace('-',user_date_format_delimiter);
			minOrigDate = date_ymd2mdy(minOrigDate).replace('-',user_date_format_delimiter).replace('-',user_date_format_delimiter);
			maxOrigDate = date_ymd2mdy(maxOrigDate).replace('-',user_date_format_delimiter).replace('-',user_date_format_delimiter);
		} else if (user_date_format_validation == 'dmy') {
			origDate = date_ymd2dmy(origDate).replace('-',user_date_format_delimiter).replace('-',user_date_format_delimiter);	
			minOrigDate = date_ymd2dmy(minOrigDate).replace('-',user_date_format_delimiter).replace('-',user_date_format_delimiter);
			maxOrigDate = date_ymd2dmy(maxOrigDate).replace('-',user_date_format_delimiter).replace('-',user_date_format_delimiter);		
		}
		if (daydiff < (-1*min) && min > 0) {
			//Less than min
			document.getElementById('rangetext_'+event_id).style.color = 'red';
			document.getElementById('rangetext_'+event_id).style.fontWeight = 'bold';
			minmaxtxt = (max > 0) ? (minOrigDate+" - "+maxOrigDate) : ("minimum "+minOrigDate);
			alertText = "The date selected (<b>"+newDateValOrig+"</b>) is below the suggested Offset Range Minimum of <b>"+min+" days</b> "
					  + "from the originally generated date (<b>"+origDate+"</b>). You may wish to modify this new value so that it is within the suggested range (<b>"+minmaxtxt+"</b>).";
		} else if (daydiff > max && max > 0) {
			//Greater than max
			document.getElementById('rangetext_'+event_id).style.color = 'red';
			document.getElementById('rangetext_'+event_id).style.fontWeight = 'bold';
			minmaxtxt = (min > 0) ? (minOrigDate+" - "+maxOrigDate) : ("maximum "+maxOrigDate);
			alertText = "The date selected (<b>"+newDateValOrig+"</b>) is above the suggested Offset Range Maximum of <b>"+max+" days</b> "
					  + "from the originally generated date (<b>"+origDate+"</b>). You may wish to modify this new value so that it is within the suggested range (<b>"+minmaxtxt+"</b>).";
		} else {
			//Not out of range
			if (document.getElementById('rangetext_'+event_id) != null) {
				document.getElementById('rangetext_'+event_id).style.color = '#777';
				document.getElementById('rangetext_'+event_id).style.fontWeight = 'normal';
			}
		}
		//Show alert dialog box, if needed
		if (alertText != "") {
			$("#alert_text").html(alertText).dialog({ bgiframe: true, modal: true, width: 450, buttons: { Ok: function() {$(this).dialog('close');highlightTableRow("row_"+event_id,2000);} } });
		}
	}
}

//Run when click Generate Schedule button
function generateSched() {
	if (document.getElementById('idnumber').value.length < 1) {
		var idnumber = document.getElementById('idnumber2').value = trim(document.getElementById('idnumber2').value);
		var newid = 1;
	} else if (document.getElementById('idnumber2').value.length < 1) {
		var idnumber = trim(document.getElementById('idnumber').value);
		var newid = 0;
	}
	if (document.getElementById('arm').value.length > 0 && (document.getElementById('idnumber').value.length > 0 || document.getElementById('idnumber2').value.length > 0) && document.getElementById('startdate').value.length > 0) {
		document.getElementById('progress').style.visibility='visible';
		document.getElementById('table').style.display = 'none';
		document.getElementById('genbtn').disabled = true;
		$.get('/plugins/Calendar/scheduling_ajax.php', { pid: pid, action: 'generate_sched', newid: newid, idnumber: idnumber, arm: document.getElementById('arm').value, startdate: document.getElementById('startdate').value },
			function(data) {
				$('#table').html(data);
				document.getElementById('genbtn').disabled = false;
				document.getElementById('progress').style.visibility='hidden';
				initCal2Datepicker();
				$('.time').timepicker({hour: currentTime('h'), minute: currentTime('m'), timeFormat: 'hh:mm'});
				$('#table').show('blind',{},500);
			}
		);
	} else {
		alert('Please provide the record name, a Start Date, and (if applicable) an Arm');
	}
}

//Delete a record's scheduled calendar event after clicking cross
function delCalEv(cal_id,record,arm) {
	if (confirm('Are you sure you wish to delete this calendar event?')) {
		highlightTableRow('row_'+cal_id,2000);
		$.get('/plugins/Calendar/scheduling_ajax.php', { pid: pid, action: 'del_single', record: record, arm: arm, cal_id: cal_id },
			function(data) {
				document.getElementById('table').innerHTML = data;
				initCal2Datepicker();
				simpleDialog('The calendar event has been deleted.');
			}
		);
	}
}
//Begin editing a record's scheduled calendar event after clicking pencil
function beginEditCalEv(cal_id,record,arm) {
	$(document.getElementById('row_'+cal_id).cells[0]).html("<img src='"+app_path_images+"progress_circle.gif' class='imgfix2'>");
	$.get('/plugins/Calendar/scheduling_ajax.php', { pid: pid, action: 'edit_sched', record: record, arm: arm, cal_id: cal_id },
		function(data) {
			document.getElementById('table').innerHTML = data;
			initCal2Datepicker();
			$('.time').timepicker({hour: currentTime('h'), minute: currentTime('m'), timeFormat: 'hh:mm'});
		}
	);
}
//Save edits for a record's single scheduled calendar event
function saveEditCalEv(cal_id,record,arm) {
	//Determine if date changed, and if so, ask if we need to adjust all dates
	if ($('#date_'+cal_id).val() != $('#origdate_'+cal_id).val()) {
		//Determine that this is not the last row (otherwise this is pointless)
		var tbl = document.getElementById('edit_sched_table');
		var rows = tbl.tBodies[0].rows;
		var collect_id = false;
		var row_ids = "";
		var tmp_arr;
		for (var i=0; i<rows.length; i++) {
			//Collect all ids (cal_ids) after present event
			if (collect_id && rows[i].getAttribute("evstat") != "") {
				tmp_arr = rows[i].getAttribute("id").split("_");
				row_ids += ","+tmp_arr[1];
			}		
			//Is the current row the row we're modifying?
			if (rows[i].getAttribute("id") == "row_"+cal_id) {
				var rowIndex = i;
				collect_id = true;
			}
		}
		//Get difference of changed date in days, if current was changed
		var dt1 = $('#date_'+cal_id).val().replace(user_date_format_delimiter,'-').replace(user_date_format_delimiter,'-');
		var dt2 = $('#origdate_'+cal_id).val().replace(user_date_format_delimiter,'-').replace(user_date_format_delimiter,'-');
		if (user_date_format_validation == 'mdy') {
			dt1 = date_mdy2ymd(dt1);
			dt2 = date_mdy2ymd(dt2);
		} else if (user_date_format_validation == 'dmy') {
			dt1 = date_dmy2ymd(dt1);
			dt2 = date_dmy2ymd(dt2);
		}
		var dt1arr = dt1.split("-");
		var dt2arr = dt2.split("-");		
		var dat1 = new Date(dt1arr[0],(dt1arr[1]-1),dt1arr[2]).valueOf();		
		var dat2 = new Date(dt2arr[0],(dt2arr[1]-1),dt2arr[2]).valueOf();
		var daydiff = (dat1-dat2)/(1000*86400);
		if (rowIndex < rows.length-1) {
			//Not on last row, so give dialog
			$("#daydiff").html(daydiff);
			$("#adjustDatesDialog").dialog({
				bgiframe: true,
				width: 500,
				modal: true,
				title: 'Adjust ALL following events by '+daydiff+' days?',
				buttons: {
					'NO, just this one': function() {
						$(this).dialog('destroy');
						saveEditCalEv2(cal_id,record,arm,"","");
					},
					'YES, adjust ALL dates': function() {
						$(this).dialog('destroy');
						saveEditCalEv2(cal_id,record,arm,row_ids,daydiff);
					}
				}
			});
		} else {
			saveEditCalEv2(cal_id,record,arm,"","");
		}
	} else {
		saveEditCalEv2(cal_id,record,arm,"","");
	}
}
// Runs second part of function saveEditCalEv, which saves current cal event values
function saveEditCalEv2(cal_id,record,arm,other_rows,daydiff) {
	$(document.getElementById('row_'+cal_id).cells[0]).html("<img src='"+app_path_images+"progress_circle.gif' class='imgfix2'>");
	document.getElementById('time_'+cal_id).disabled = true;
	document.getElementById('date_'+cal_id).disabled = true;
	document.getElementById('notes_'+cal_id).disabled = true;
	document.getElementById('status_'+cal_id).disabled = true;
	//AJAX call
	$.get('/plugins/Calendar/scheduling_ajax.php', { pid: pid, action: 'edit_single', record: record, arm: arm, cal_id: cal_id, event_time: $('#time_'+cal_id).val(), event_date: $('#date_'+cal_id).val(), event_status: $('#status_'+cal_id).val(), notes: $('#notes_'+cal_id).val(), other_rows: other_rows, daydiff: daydiff },
		function(data) {
			document.getElementById('table').innerHTML = data;			
			initCal2Datepicker();
			//Highlight this row
			highlightTableRow("row_"+cal_id,2000);
			//Deal with other rows affected
			if (other_rows != "") {
				//Highlight all other rows affected, if adjusting multiple dates
				all_cal_ids = other_rows.split(",");
				for (var i=1; i<all_cal_ids.length; i++) {
					highlightTableRow("row_"+all_cal_ids[i],2000);
				}
			}
		}
	);
}

// Displays rest of Note text for scheduled event if Note is truncated
function showEvNote(cal_id) {
	$('#notes_ellip_'+cal_id).css({'display': 'none'});
	$('#notes_'+cal_id).append($('#notes_invis_'+cal_id).html());
	$('#notes_invis_'+cal_id).html('');
}

//Gather dates, event_ids, and times in table to create new schedule, then submit via AJAX
function createSched(idnumber,newid) {
	var this_ev_id;
	var all_ev_ids = '';
	var all_dates = '';
	var all_times = '';
	var rows = document.getElementById('projected_sched').tBodies[0].rows;
	for (var i=1; i<rows.length; i++) {
		this_ev_id = rows[i].getAttribute('ev_id');
		if ($('#row_'+this_ev_id).css('display') != 'none') {
			all_ev_ids += this_ev_id + ',';
			all_times  += $('#time_'+this_ev_id).val() + ',';
			all_dates  += $('#date_'+this_ev_id).val() + ',';
			//If has an empty date field, alert them
			if ($('#date_'+this_ev_id).val().length < 1) {
				simpleDialog('Please enter a date for each event');
				return;
			}
		}
	}
	if (all_ev_ids == '') {
		simpleDialog('You cannot create a schedule with no dates.','ERROR!');
		return;
	}
	all_times  = all_times.substring(0,all_times.length-1);
	all_dates  = all_dates.substring(0,all_dates.length-1);
	all_ev_ids = all_ev_ids.substring(0,all_ev_ids.length-1);
	var arm = (document.getElementById('arm') != null) ? $('#arm').val() : '';
	//Remove the idnumber from the drop-down on the page (since it's been scheduled)
	var selectbox = document.getElementById('idnumber');
	for (var i=selectbox.options.length-1; i>=0; i--) {
		if (selectbox.options[i].selected && selectbox.options[i].value != '') selectbox.remove(i);
	}
	//Disable/reset fields after submission
	$('#idnumber').val('');
	$('#idnumber2').val('');
	document.getElementById('createbtn').disabled = true;
	document.getElementById('cancelbtn').disabled = true;
	document.getElementById('progress2').style.visibility = 'visible';
	$.post('/plugins/Calendar/scheduling_ajax.php?pid='+pid+'&arm='+arm+'&newid='+newid+'&action=adddates&idnumber='+idnumber+'&baseline_date='+$('#startdate').val(), 
		{ times: all_times, dates: all_dates, event_ids: all_ev_ids },
		function(data) {
			$('#table').html(data);
		}
	);
}

// Save the date when changed in the Calendar Pop-up window 
function saveDateCalPopup(cal_id) {
	if ($('#newdate').val().length > 0) {
		document.getElementById('savebtndatecalpopup').disabled = true;
		$.get('/plugins/Calendar/calendar_popup_ajax.php', { pid: pid, view: 'date', action: 'edit_date', event_date: $('#newdate').val(), cal_id: cal_id },
			function(data) {
				$('#td_event_date').html(data);	
				$('#newdate').datepicker({buttonText: 'Click to select a date',yearRange: '-100:+10',changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
				window.opener.location.reload();
				setTimeout(function(){
					if (document.getElementById('msg_saved_date') != null) document.getElementById('msg_saved_date').style.visibility='hidden';
				},2000);
			}
		);						
	} else {
		alert('Please enter a date');
	}
}
// Save the time when changed in the Calendar Pop-up window 
function saveTimeCalPopup(cal_id) {
	if (document.getElementById('event_time').value.length > 0) {
		document.getElementById('savebtntimecalpopup').disabled = true;
		$.get('/plugins/Calendar/calendar_popup_ajax.php', { pid: pid, view: 'time', action: 'edit_time', cal_id: cal_id, event_time: $('#event_time').val() },
			function(data) {
				$('#td_event_time').html(data);	
				var popupwindow = window;
				window.opener.location.reload();
				popupwindow.focus();
				if (isIE) { // IE returns focus back to main window, so make sure pop-up gets focus back
					setTimeout(function(){
						popupwindow.focus();
					},500);
					setTimeout(function(){
						popupwindow.focus();
					},1000);
					setTimeout(function(){
						popupwindow.focus();
					},2000);
					setTimeout(function(){
						popupwindow.focus();
					},3000);
				}
				setTimeout(function(){
					if (document.getElementById('msg_saved_time') != null) document.getElementById('msg_saved_time').style.visibility='hidden';
				},2000);
				$('.time').timepicker({hour: currentTime('h'), minute: currentTime('m'), timeFormat: 'hh:mm'});
			}
		);
	} else {
		alert('Please enter a time');
	}
}
// Save the status when changed in the Calendar Pop-up window 
function saveStatusCalPopup(cal_id) {
	document.getElementById('savebtnstatuscalpopup').disabled = true;
	$.get('/plugins/Calendar/calendar_popup_ajax.php', { pid: pid, view: 'status', action: 'edit_status', cal_id: cal_id, event_status: $('#event_status').val() },
		function(data) {
			$('#td_change_status').html(data);	
			window.opener.location.reload();
			setTimeout(function(){
				if (document.getElementById('msg_saved_status') != null) document.getElementById('msg_saved_status').style.visibility='hidden';
			},2000);
		}
	);
}
//Calendar functions
function popupCal(cal_id,width) {
	window.open('/plugins/Calendar/calendar_popup.php?pid='+pid+'&width='+width+'&cal_id='+cal_id,'myWin','width='+width+', height=250, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');
}
function popupCalNew(day,month,year,record) {
	window.open('/plugins/Calendar/calendar_popup.php?pid='+pid+'&width=600&month='+month+'&year='+year+'&day='+day+'&record='+record,'myWin','width=600, height=290, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');
}
function calNewOver(day_num) {
	document.getElementById('link'+day_num).style.color='#FFF';
	document.getElementById('new'+day_num).bgColor='green';
}
function calNewOut(day_num) {
	document.getElementById('link'+day_num).style.color='#999';
	document.getElementById('new'+day_num).bgColor='';
}
function overCal(this_link,divnum) {
	var xdiv = document.getElementById('divcal'+divnum);
	var ydiv = document.getElementById('mousecaldiv');	
	var image = xdiv.style.backgroundImage.substring(4,xdiv.style.backgroundImage.length-1)
	ydiv.innerHTML = '<div style=\"padding:0 1px 0 17px;background-position: 0px -1px;background-repeat:no-repeat;background-image:url(' + image + ');\"> ' + this_link.innerHTML + '</div>';
	ydiv.style.color = this_link.style.color;
	ydiv.style.display = 'block';	
	ydiv.style.top = (xdiv.offsetTop+0)+'px';	
	ydiv.style.left = (xdiv.offsetLeft+90)+'px';
}
function outCal(this_link) {
	document.getElementById('mousecaldiv').innerHTML = '';
	document.getElementById('mousecaldiv').style.display='none';
}