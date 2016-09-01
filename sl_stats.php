<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
///////////////
// HANDLE AUTH
///////////////
$query_field = 'user_id';
$id = 150;
$query_prefix = "$query_field=$id ";

?><html>
<head>
	<?php include "../head.include.php";?>
	<link href="/static/api/style.css" rel="stylesheet"/>
	<style>
		#wait {
			position: fixed;
			top:20%;
			left:calc( 50% - 322px );
			background: rgba(0,0,0,0.7);
			border-radius: 20px;
			padding:50px 50px;
			color: #fff;
			font-size:14px;
		}
		#wait>div {
			float:left;
			height:77px;
			vertical-align: top;
		}
		#wait .wait_spinner {
			width:77px;
			height:77px;
		}
		#top_buttons {
			line-height: 34px;
		}
		#top_buttons .btn {
			height: 34px;
		}
		#top_buttons>*,#time_container>* {
			vertical-align: bottom;
		}
		#timeslice {
			width:auto;
		}
		iframe[name="result"] {
			width:90%;
			height:calc(100% - 60px);
			border:1px solid #ddd;
		}
		#ago{
			width:61px;
			padding-right:5px;
			padding-left:5px;
		}
		#time_container {
			display: inline;
		}
		#time_container>input {
			width: 140px;
			font-size: 12px;
			line-height: 12px;
		}
	</style>
	<script src="sl_queries.js?v=33a"></script>
	<script>

		function copy_to_clipboard(value, onsuccess, onfail) {
			var elem = $('<input>').val(value).appendTo('body').select();
			try {
				var success = document.execCommand('copy');
				if (success) {
					onsuccess && onsuccess();
				} else {
					console.log('Failed to copy')
					onfail && onfail();
				}
			} catch (e) {
				onfail && onfail();
				console.log('Failed to copy: ', e);
			}
			elem.remove();
			return success;
		}

	</script>
</head>
<body>
	<div id="top_buttons">
		<div id="time_container">
			<!--
			<select id="ago" class="form-control">
				<option value="3">3h</option>
				<option value="12">12h</option>
				<option value="24" selected>24h</option>
				<option value="48">48h</option>
				<option value="144">6d</option>
				<option value="720">30d</option>
			</select> ago.
			-->
			<input id="from" type="date" value="<?=date("Y-m-d")?>" class="form-control">
			to
			<input id="to"   type="date" value="<?=date("Y-m-d")?>" class="form-control">
			<select id="timeslice" class="form-control">
				<option value="1d">day</option>
				<option value="1h">hour</option>
			</select>
		</div>

		<select id="q" class="form-control" placeholder="Select query"></select>
		<script>
			$q=$('#q');
			function populate_queries(){
				var selectedIndex = Math.max(0,$q[0].selectedIndex || 0);
				$q.empty();
				Object.keys(sl_queries).forEach(function(k){
					var query = "<?=$query_prefix?>" + sl_queries[k].replace(/\b1d\b/g,$('#timeslice').val());
					query = optimize_sumo_query(query,$('#from').val(),$('#to').val());
					var option = $('<option>').val(query).html(k);
					$q.append(option);
				});
				$q[0].selectedIndex = selectedIndex;
			}
			populate_queries();
			$('#timeslice,#from,#to').change(populate_queries);
		</script>

		<div id="chart_types" class="btn-group" role="group">
			<button type="button" class="btn btn-default btn-s" val="Table"       title="Simple Table"><i class="fa fa-table"      ></i></button>
			<button type="button" class="btn btn-default btn-s" val="LineChart"   title="Line Chart"  ><i class="fa fa-line-chart" ></i></button>
			<button type="button" class="btn btn-default btn-s" val="PieChart"    title="Pie Chart"   ><i class="fa fa-pie-chart"  ></i></button>
			<button type="button" class="btn btn-default btn-s" val="ColumnChart" title="Bar Chart"   ><i class="fa fa-bar-chart"  ></i></button>
			<button type="button" class="btn btn-default btn-s" val="GeoChart"    title="World Map"   ><i class="fa fa-globe"      ></i></button>
		</div>

		<a id="go" target="result" class="btn btn-primary btn-s">Go</a>

		<span id="copy" class="btn btn-default btn-s" title="Copy query for sumologic website"></span>
	</div>

	<iframe id="frm" name="result"></iframe>
	<div id="wait">
		<div>
			<i class="wait_spinner"></i>
		</div>
		<div>
			<h3>Loading data...</h3>
	 		Queries of more than 3 days or per hour may take longer to complete
		</div>
	 </div>

	<script>
		$('#wait').hide();
		$('#frm').on('load',function(){
			$('#wait').fadeOut();
		});
		$('#go').click(function(){
			$('#wait').fadeIn();
		});
		var AUTOCLOSE_NOTIFICATION = 10;
	 	var href_tmpl = "chart.php?api_key=<?=$api_key?>&id=<?=$id?>&mode=<?=$mode?>&q=_QUERY_&chartType=_CHARTTYPE_&html_table=0&from=_FROM_&to=_TO_";
	 	// to support 'ago' remove to &to and use: &from=-_AGO_h
		$('#chart_types button').click(function(){
			$('#chart_types button.btn-primary').removeClass('btn-primary');
			$(this).addClass('btn-primary');
		}).first().click();

		$('#copy').click(function(){
			copy_to_clipboard($('#q').val(),
				function(){
					$('#copy').addClass('btn-success').text('Copied');
				},
				function(){
					$('#copy').addClass('btn-danger').text("Can't Copy");
				});
		});

		function update_go(){
			$('#copy').removeClass('btn-success btn-danger').text('Copy Query');
			var query = $('#q').val();
			$('#chart_types [val="GeoChart"]').prop('disabled',!/by geo/.test(query));
			var $go = $('#go');
			var chartType=$('#chart_types .btn-primary').attr('val');
			var timeFrom = $('#from').val() + '+00:00:00';
			var timeTo = $('#to').val() + '+23:59:59';
			$go.prop('href',href_tmpl
				.replace('_QUERY_',encodeURIComponent(query))
				.replace('_TO_',timeTo)
				.replace('_FROM_',timeFrom)
				.replace('_CHARTTYPE_',chartType)
			);
		}

		$('#q,#from,#to,#timeslice,#chart_types button').on('click change',update_go);
		update_go();
	</script>

</body>
</html>