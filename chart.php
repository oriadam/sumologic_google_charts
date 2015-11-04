<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once "sumologic_api.php";
if (!empty($GLOBALS['q'])) {
	$q = $GLOBALS['q'];
} else {
	if ($_GET['secret'] != $GLOBALS['SUMOLOGIC_SECRET']) {
		die('No key match. Error code: ' . __LINE__);
	}
	if (empty($_GET['q'])) {
		die('No query. Error code: ' . __LINE__);
	}
	$q = $_GET['q'];
}
?><html lang="en">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script>
	<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css" rel="stylesheet">
	<link href="/static/css/overall.css?v=15" rel="stylesheet" />
	<script type="text/javascript" src="sumologic_google_chart.js"></script>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
	<script type="text/javascript">
		google.load("visualization", "1", {packages: ["corechart"]});
	    //google.load("visualization", "1.1", {packages: ["line"]});

		//google.setOnLoadCallback(drawChart); // when data is loaded, this will be already loaded as well
		function populateChart(element, result) {
			if (!result.length) {
				$('#output_chart').html('No data.');
				return;
			}

			var chartType = "<?=$_GET['chartType']?>";
			var dataHead = sl_to_head(result);
			var data = sl_to_DataTable(result,dataHead);
			if ("" === chartType && dataHead.length > 20) {
				chartType = 'PieChart';
			}else if (dataHead[0]==='_timeslice'){
				chartType = 'LineChart';
					}

			var options = <?=$_GET['options'] ?: '{}'?>;
			options.crosshair = { trigger: "both", focused: { color: '#f88' } };
			options.interpolateNulls = true; // Whether to guess the value of missing points. If true, it will guess the value of any missing data based on neighboring points. If false, it will leave a break in the line at the unknown point.
			if (options.pointSize === undefined){
				options.pointSize = 4;
			}
			options.hAxis = options.hAxis || {};

			<?php if (strpos($q, 'timeslice by 1d') !== false) {?>
			options.hAxis.format = options.hAxis.format || 'MMMd';
			<?php } elseif (strpos($q, 'timeslice by 1h') !== false) {?>
				options.hAxis.format = options.hAxis.format || 'MMMd';
				options.hAxis.gridlines = {
					units:{
						days: {format: ['MMM dd']},
						hours: {format: ['HH:mm', 'ha']},
					},
					minorGridlines: {
						units: {
							hours: {format: ['hh:mm:ss a', 'ha']},
							minutes: {format: ['HH:mm a Z', ':mm']}
						}
					}
				};
			<?php }
?>

			var chart = new google.visualization[chartType || 'ColumnChart'](element);
			chart.draw(data, options);
		}
	</script>

	<style>
		#output_raw {
			white-space: pre;
		}
		[onclick]{
			cursor: pointer;
		}
	</style>
</head>

<body onload="$('#wait').html('INTERNAL ERROR')">
	<div id="wait">
		<i class="wait_spinner"></i> Loading chart...
	</div>
	<div id="output_chart"></div>
	<form id="csv_form" action="csv.php?filename=report <?=date('Y-m-d', from_to_time($_GET['from']))?> to <?=date('Y-m-d', from_to_time($_GET['to'] ?: 'now'))?>.csv" method="POST" target="_blank">
	<input id="csv_data" type="hidden" name="data">
	<input type="submit" class="btn btn-link" value="Download CSV"/>
	</form>
<?php
if (!empty($_GET['show_raw'])) {
	?>
	<span onclick="$('#output_raw').toggle()">Show raw response</span>
	<span id="output_raw" style="display:none"></span>
<?php }
?>
	<script>
<?php
$options = [];
$avail_options = ['collector', 'timeslice', 'timeout', 'timeZone', 'ch_options', 'show_query', 'show_raw', 'show_ch'];
foreach ($avail_options as $k) {
	if (!empty($_GET[$k])) {
		$options[$k] = $_GET[$k];
	}
}
if ($publisher_status == DEMO_STATUS) {
	$result = generate_demo_stats($q, $_GET['from'], $_GET['to'] ?: 0, $options);
} else {
	$result = sumologic_api($q, $_GET['from'], $_GET['to'] ?: 0, $options);
}
print "result=" . json_encode($result, JSON_PRETTY_PRINT) . ';';
?>

	$('#csv_data').val(sl_to_csv(result));

	$('#output_raw').html(JSON.stringify(result));
	if (result.error){
		$('#output_chart').html('ERROR:'+result.error);
	}else {
		populateChart(document.querySelector('#output_chart'),result);
	}
	$('#wait').remove();
	$('body').attr('onload',null)


	</script>
</body>

</html>
