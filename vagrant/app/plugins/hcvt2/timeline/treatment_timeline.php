<?php
/**
 * Created by HCV-TARGET.
 * User: kbergqui
 * Date: 4/9/14
 * Time: 11:55 AM
 */
/**
 * includes
 */
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/includes/timeline_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
define("PAGE", basename(dirname(PAGE_FULL)) . "/" . basename(PAGE_FULL));
require_once OVERRIDE_PATH . 'ProjectGeneral/header_timeline.php';
/**
 * restrict access to one or more pids
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * vars
 */
$webroot = APP_PATH_WEBROOT_FULL;
$subjid = $_GET['record'];
$project_id = !isset($project_id) ? $_GET['pid'] : $project_id;
/**
 * get treatment start and end (or today) to center timeline
 */
$fields = array("dm_rfstdtc", "eot_dsstdtc");
$data = REDCap::getData('array', $subjid, $fields);
foreach ($data AS $subject) {
	foreach ($subject AS $event) {
		if ($event['dm_rfstdtc'] != '') {
			$start_obj = new DateTime($event['dm_rfstdtc']);
			if ($event['eot_dsstdtc'] != '') {
				$end_obj = new DateTime($event['eot_dsstdtc']);
				$endlabel = 'EOT';
			} else {
				$end_obj = new DateTime('now');
				$endlabel = 'Ongoing';
			}
			$interval_obj = $end_obj->diff($start_obj);
			$midpoint_days = $interval_obj->format('%a');
			$midpoint_obj = $start_obj->add(new DateInterval('P' . (int)($midpoint_days / 2) . 'D'));
			$midpoint = $midpoint_obj->format('Y-m-d');
			/**
			 * format dates so we can use them on timeline
			 */
			$start_date = get_gregorian_date($event['dm_rfstdtc']);
			$end_date = get_gregorian_date($end_obj->format('Y-m-d'));
			$tx_4_weeks_start = get_gregorian_date(add_date($event['dm_rfstdtc'], 21));
			$tx_4_weeks_end = get_gregorian_date(add_date($event['dm_rfstdtc'], 28));
			$tx_12_weeks_start = get_gregorian_date(add_date($event['dm_rfstdtc'], 77));
			$tx_12_weeks_end = get_gregorian_date(add_date($event['dm_rfstdtc'], 84));
			$tx_24_weeks_start = get_gregorian_date(add_date($event['dm_rfstdtc'], 161));
			$tx_24_weeks_end = get_gregorian_date(add_date($event['dm_rfstdtc'], 168));
			$svr12_weeks_start = get_gregorian_date(add_date($event['eot_dsstdtc'], 63));
			$svr12_weeks_end = get_gregorian_date(add_date($event['eot_dsstdtc'], 84));
			$svr24_weeks_start = get_gregorian_date(add_date($event['eot_dsstdtc'], 147));
			$svr24_weeks_end = get_gregorian_date(add_date($event['eot_dsstdtc'], 168));
		}
	}
}
/**
 * enqueue javascript
 */
$script = "<script type='text/javascript'>
var tl;
function onLoad() {
	var theme = Timeline.ClassicTheme.create();
	theme.event.bubble.width = 300;
	theme.event.bubble.height = 300;
	theme.event.track.height = 0;
	theme.event.tape.height = 4;

	var condensed = Timeline.ClassicTheme.create();
	condensed.event.track.height = 0;
	condensed.event.tape.height = 2;

	var txSource = new Timeline.DefaultEventSource(0);
	var conmedSource = new Timeline.DefaultEventSource(0);
	var aeSource = new Timeline.DefaultEventSource(0);
	var labsSource = new Timeline.DefaultEventSource(0);
	var overviewSource = new Timeline.DefaultEventSource(0);
	var bandInfos = [
		Timeline.createBandInfo({
			eventSource:    txSource,
			width:          \"17%\",
			intervalUnit:   Timeline.DateTime.WEEK,
			intervalPixels: 100,
			theme:          theme
		}),
		Timeline.createBandInfo({
			eventSource:    aeSource,
			width:          \"18%\",
			intervalUnit:   Timeline.DateTime.WEEK,
			intervalPixels: 100,
			theme:          condensed
		}),
		Timeline.createBandInfo({
			eventSource:    conmedSource,
			width:          \"30%\",
			intervalUnit:   Timeline.DateTime.WEEK,
			intervalPixels: 100,
			theme:          condensed
		}),
		Timeline.createBandInfo({
			eventSource:    labsSource,
			width:          \"30%\",
			intervalUnit:   Timeline.DateTime.WEEK,
			intervalPixels: 100,
			theme:          condensed
		}),
		Timeline.createBandInfo({
			overview:       true,
			eventSource:    overviewSource,
			width:          \"5%\",
			intervalUnit:   Timeline.DateTime.MONTH,
			intervalPixels: 300,
			theme:          theme
		})
	];
	bandInfos[1].syncWith = 0;
	bandInfos[2].syncWith = 1;
	bandInfos[3].syncWith = 2;
	bandInfos[4].syncWith = 3;
	bandInfos[4].highlight = true;



    for (var i = 0; i < bandInfos.length; i++) {
	    if (i == 0 || i == 4) {
		    bandInfos[0].decorators = [
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$start_date\",
		            endDate:    \"$end_date\",
		            color:      \"blue\", // set color explicitly
		            opacity:    5,
		            startLabel: \"Start\",
		            endLabel:   \"$endlabel\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$tx_12_weeks_start\",
		            endDate:    \"$tx_12_weeks_end\",
		            color:      \"white\", // set color explicitly
		            opacity:    40,
		            startLabel: \"wk<br />12\",
		            endLabel:   \"\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$tx_4_weeks_start\",
		            endDate:    \"$tx_4_weeks_end\",
		            color:      \"white\", // set color explicitly
		            opacity:    40,
		            startLabel: \"wk<br />4\",
		            endLabel:   \"\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$tx_24_weeks_start\",
		            endDate:    \"$tx_24_weeks_end\",
		            color:      \"white\", // set color explicitly
		            opacity:    40,
		            startLabel: \"wk<br />24\",
		            endLabel:   \"\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$svr12_weeks_start\",
		            endDate:    \"$svr12_weeks_end\",
		            color:      \"yellow\", // set color explicitly
		            opacity:    20,
		            startLabel: \"svr<br />12\",
		            endLabel:   \"\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$svr24_weeks_start\",
		            endDate:    \"$svr24_weeks_end\",
		            color:      \"yellow\", // set color explicitly
		            opacity:    20,
		            startLabel: \"svr<br />24\",
		            endLabel:   \"\",
		            theme:      theme
		        })
		    ];
	    } else {
	        bandInfos[i].decorators = [
	            new Timeline.SpanHighlightDecorator({
	                startDate:  \"$start_date\",
	                endDate:    \"$end_date\",
	                color:      \"blue\", // set color explicitly
	                opacity:    5,
	                startLabel: \"\",
	                endLabel:   \"\",
	                theme:      theme
	            }),
	            new Timeline.SpanHighlightDecorator({
	                startDate:  \"$tx_12_weeks_start\",
	                endDate:    \"$tx_12_weeks_end\",
	                color:      \"white\", // set color explicitly
	                opacity:    40,
	                startLabel: \"\",
	                endLabel:   \"\",
	                theme:      theme
	            }),
	            new Timeline.SpanHighlightDecorator({
	                startDate:  \"$tx_4_weeks_start\",
	                endDate:    \"$tx_4_weeks_end\",
	                color:      \"white\", // set color explicitly
	                opacity:    40,
	                startLabel: \"\",
	                endLabel:   \"\",
	                theme:      theme
	            }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$tx_24_weeks_start\",
		            endDate:    \"$tx_24_weeks_end\",
		            color:      \"white\", // set color explicitly
		            opacity:    40,
		            startLabel: \"wk<br />24\",
		            endLabel:   \"\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$svr12_weeks_start\",
		            endDate:    \"$svr12_weeks_end\",
		            color:      \"yellow\", // set color explicitly
		            opacity:    20,
		            startLabel: \"\",
		            endLabel:   \"\",
		            theme:      theme
		        }),
		        new Timeline.SpanHighlightDecorator({
		            startDate:  \"$svr24_weeks_start\",
		            endDate:    \"$svr24_weeks_end\",
		            color:      \"yellow\", // set color explicitly
		            opacity:    20,
		            startLabel: \"\",
		            endLabel:   \"\",
		            theme:      theme
		        })
	        ];
	    }
    }

   tl = Timeline.create(document.getElementById(\"timeline\"), bandInfos);
   tl.loadJSON(\"treatment_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       txSource.loadJSON(json, url);
   });
   tl.loadJSON(\"conmeds_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       conmedSource.loadJSON(json, url);
   });
   tl.loadJSON(\"ae_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       aeSource.loadJSON(json, url);
   });
   tl.loadJSON(\"labs_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       labsSource.loadJSON(json, url);
   });
   tl.loadJSON(\"labs_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       overviewSource.loadJSON(json, url);
   });
   tl.loadJSON(\"ae_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       overviewSource.loadJSON(json, url);
   });
   tl.loadJSON(\"conmeds_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       overviewSource.loadJSON(json, url);
   });
   tl.loadJSON(\"treatment_json.php?pid=$project_id&record=$subjid\", function(json, url) {
       overviewSource.loadJSON(json, url);
   });
 }
var resizeTimerID = null;
function onResize() {
	if (resizeTimerID == null) {
		resizeTimerID = window.setTimeout(function() {
			resizeTimerID = null;
			tl.layout();
			centerTimeline('$midpoint');
		}, 500);
	}
}
function centerTimeline(midpoint) {
	tl.getBand(0).setCenterVisibleDate(Timeline.DateTime.parseGregorianDateTime(midpoint));
}
</script>\n";
echo $script;
/**
 * construct html for page
 */
if (isset($subjid)) {
	$html = "<link rel='stylesheet' href='../../includes/timeline.css' type='text/css' />";
	$html .= RCView::table(array("width" => "99%", "style" => "border: 1px solid #aaa;"),
		RCView::tr('',
			RCView::td(array("height" => "17%", "width" => "1%"), RCView::div(array("class" => "rotate"), 'Treatment')) .
			RCView::td(array("rowspan" => 5, "width" => "99%"), RCView::div(array("id" => "timeline", "style" => "height: 870px; width:100%; border: 1px solid #aaa;"), ''))
		) .
		RCView::tr('',
			RCView::td(array("height" => "18%"), RCView::div(array("class" => "rotate"), 'AEs / CEs'))
		) .
		RCView::tr('',
			RCView::td(array("height" => "30%"), RCView::div(array("class" => "rotate"), 'ConMeds'))
		) .
		RCView::tr('',
			RCView::td(array("height" => "30%"), RCView::div(array("class" => "rotate"), 'Labs'))
		) .
		RCView::tr('',
			RCView::td(array("height" => "5%"), RCView::div(array("class" => "rotate"), 'Overview'))
		)
	);
	$html .= "<script type='text/javascript'>
	$(window).load(function(){
	/*alert('Loaded');*/
	    onLoad();
	    centerTimeline('$midpoint');
	});
	$(window).resize(function(){
	    onResize();
	});
	</script>\n";
	echo $html;
} else {
	echo(RCView::h1('', 'Please select any form within the patient grid before running this plugin.'));
}