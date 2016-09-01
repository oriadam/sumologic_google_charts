<?php
////////////////////////////////////////////////////////////
// See https://github.com/oriadam/sumologic_google_charts //
////////////////////////////////////////////////////////////
error_reporting(E_ERROR | E_WARNING | E_PARSE);
if (file_exists('chart_include.php')) {
	include 'chart_include.php';
}
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

$html_table = !empty($_GET['html_table']);
$chartType = @$_GET['chartType'];

?><html lang="en">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
	<!--script src="https://code.jquery.com/jquery-migrate-3.0.0.js"></script-->
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.0/jquery-ui.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.0/jquery-ui.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.0/themes/smoothness/jquery-ui.min.css">
	<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css" rel="stylesheet">
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
	<link href="style.css" rel="stylesheet" />
	<script type="text/javascript" src="sumologic_google_chart.js"></script>
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript">
		google.charts.load("current", {packages: ["corechart","table","geochart"]});
		function populateChart(element, result,chart_options) {
			if (result.rows && result.rows.length) {
				var chartType = "<?=$chartType?>";
				if (!chartType) return;
				var data = sl_to_DataTable(result);
				var defaults = {
					width:'100%',
					height:'350px',
					curveType: 'function',
					crosshair: { trigger: "both", focused: { color: '#f88' } },
					interpolateNulls: false, // Whether to guess the value of missing points. If true, it will guess the value of any missing data based on neighboring points. If false, it will leave a break in the line at the unknown point.
					pointSize: 4,
					dateFormat: 'YYYY-MM-dd',
					hAxis:{
						dateFormat: 'MM-dd',
				<?php if (strpos($q, '_timeslice') !== false) {?>
						//format: 'MM-dd',
				<?php } ?>
					},
					vAxis:{
						dateFormat: 'MM-dd',
						//format: 'MM-dd',
					},
				};
				try {
					var options = Object.assign(defaults,chart_options||{});
					var chart = new google.visualization[chartType](element);
				}catch(e){
					console.error(e);
					alert('Error with chart configuration! chartType=' + chartType + ' options=' + JSON.stringify(chart_options));
				}
				chart.draw(data, options);
			}
		}
	</script>

	<style>
		[onclick]{
			cursor: pointer;
		}
		#output_query,#output_chart,#output_error,#output_json {
			width:calc(100% - 130px);
		}

		#output_query{
			width:calc(100% - 130px);
			height:30px;
			border:1px solid gray;
		}
		#output_json {
			font-family: monospace;
			white-space: pre;
			border:1px solid gray;
		}
	</style>
	<?=$GLOBALS['in_head'] ?: ''?>
</head>

<body onload="$('#wait').html('INTERNAL ERROR')">
	<div id="wait">
		<i class="wait_spinner"></i> Loading chart...
	</div>
	<div id="output_error"></div>
	<div id="output_chart"></div>
	<?php if ($html_table) { ?>
	<div id="output_table"></div>
	<?php } ?>
	<div id="tools">
		<form id="csv_form" action="csv.php?filename=report <?=date('Y-m-d', from_to_time($_GET['from']))?> to <?=date('Y-m-d', from_to_time($_GET['to'] ?: 'now'))?>.csv" method="POST" target="_blank">
		<input id="csv_data" type="hidden" name="data">
		<input type="submit" class="btn btn-link" value="Download CSV"/>
		</form>
		<!--span class="btn btn-link" onclick="$('#output_chart').toggle()">Show Chart</span-->
		<span class="btn btn-link btn-xs" onclick="$('#output_query').toggle()">Show Query</span>
		<span class="btn btn-link btn-xs" onclick="$('#output_json').toggle()">Show JSON</span>
		<?=$GLOBALS['in_tools'] ?: ''?>
	</div>
	<textarea id="output_query"><?=$q?></textarea>
<?php
$options = [];
$api_options = ['collector', 'timeslice', 'timeout', 'tz', 'ch_options', 'show_query', 'show_raw', 'show_ch', 'all_numbers'];
foreach ($api_options as $k) {
	if (!empty($_GET[$k])) {
		$options[$k] = $_GET[$k];
	}
}
if (!empty($GLOBALS['chart_demo_data'])) {
	$result = generate_demo_stats($q, $_GET['from'], $_GET['to'] ?: 0, $options);
} else {
	$result = sumologic_search_api($q, $_GET['from'], $_GET['to'] ?: 0, $options);
}
print '<div id="output_json">' . json_encode($result, JSON_PRETTY_PRINT) . '</div>';

?>
<script>
	var result = JSON.parse($('#output_json').html());
	result.query = $('#output_query').val();

	if (typeof result_callback == 'function'){
		result = result_callback(result);
	}
	if (result.error){
		$('#output_error').html('ERROR:'+result.error);
	} else if (!result.rows || !result.rows.length) {
		$('#output_error').html('Empty result.');
		$('#output_json,#output_query,#output_chart').hide().off();
	}else {
		$('#output_error').hide();
		sl_prepare(result);
		<?php if ($html_table) { ?>
		$('#output_table').html(sl_to_html_table(result,result.rows.length>1));
		<?php } ?>
	}

	// Download CSV
	$('#csv_form').on('submit',function(){
		$('#csv_data').val(sl_to_csv(result));
	});

	<?php if ($chartType) { ?>
	function drawChart(){
		if (!window.drawChartRunOnce){
			window.drawChartRunOnce=1;
			populateChart(document.querySelector('#output_chart'),result);
		}
	}
	$('#output_chart').show();
	google.charts.setOnLoadCallback(drawChart); // when data is loaded, this will be already loaded as well
	<?php } ?>

	$('#output_json,#output_query').hide().off();
	$('#wait').remove();
	$('body').attr('onload',null)
</script>
</body>

</html>
