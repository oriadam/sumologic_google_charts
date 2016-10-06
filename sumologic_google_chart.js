////////////////////////////////////////////////////////////
// See https://github.com/oriadam/sumologic_google_charts //
////////////////////////////////////////////////////////////

/**************************************************

First, run this:
   sl_prepare(result);

To get HTML string of a table:
   var html = sl_to_html_table(result);

To get CSV string:
   var csv_string = sl_to_csv(result);

To get Google Charts compatible data:
   var data = sl_to_DataTable(result);

**************************************************/

DATE_GRANULARITY_MILLISECONDS = 24;
DATE_GRANULARITY_SECONDS = 19;
DATE_GRANULARITY_MINUTES = 16;
DATE_GRANULARITY_DAYS = 10;

// convert time number (epoch) to an ISO date string.
function time_to_date(t, date_granularity, auto_granularity) {
	var d = new Date(+t);
	if (auto_granularity && date_granularity < DATE_GRANULARITY_MILLISECONDS) {
		date_granularity = Math.max(date_granularity, t % 1000 * 60 * 60 * 24 == 0 ? 10 : t % 1000 * 60 == 0 ? 16 : t % 1000 == 0 ? 19 : 24);
	}

	var s = d.toISOString().substr(0, date_granularity)
	if (date_granularity > DATE_GRANULARITY_DAYS) {
		s = s.replace('T', ' ');
		if (date_granularity >= DATE_GRANULARITY_MILLISECONDS) {
			s = s.replace('Z', '');
		}
	}
	return s;
}

function sl_prepare(result) {

	result.dataTypes = [];
	result.idx = {}; // head name to column index

	// remember column order
	result.head.forEach(function(f, i) {
		result.idx[f] = i;
	});

	// reorder to make sure _timeslice is always first
	result.head.sort(function(a,b){
		return a == '_timeslice' ? -1 : b == '_timeslice' ? 1 : 0;
	});

	result.head.forEach(function(f, i) {
		var idx = result.idx[f];
		result.dataTypes.push(sl_detect_type(result, f, idx));
	});
}

function sl_detect_type(result, field, idx) {
	if (field == '_timeslice') {
		return 'datetime';
	} else if (field == '_sum' || field == '_count') {
		return 'number';
	}

	var rxNumber = /^(\d+(\.\d+)?|)$/;
	var rxDate = /^14\d{11}$/;
	var t = 'datetime';
	var i;
	for (i = 0; i < result.rows.length; i++) {
		var v = result.rows[i][idx];
		if (t == 'datetime' && !rxDate.test(v)) {
			t = 'number';
		}
		if (t == 'number' && !rxNumber.test(v)) {
			return 'string';
		}
	}
	return t;
}

function sl_to_DataTable(result) {
	var data = new google.visualization.DataTable();

	// add headers to DataTable
	result.head.forEach(function(f, i) {
		data.addColumn(result.dataTypes[i], f);
	});

	// add data rows to DataTable
	result.rows.forEach(function(r) {
		var row = [];
		result.head.forEach(function(f,i) {
			var idx = result.idx[f];
			var type = result.dataTypes[i];
			if (/date/.test(type)) {
				row.push(new Date(+r[idx]));
			} else if ('number' == type) {
				row.push(+r[idx] || 0);
			} else {
				row.push('' + r[idx]);
			}
		});
		data.addRow(row);
	});
	return data;
}

function sl_to_csv(result) {
	var endline = "\n";
	var sep = ",";
	var output = [];
	var auto_granularity = !result.date_granularity;
	var date_granularity = result.date_granularity || DATE_GRANULARITY_DAYS;
	var idx = result.idx;

	// header
	output.push(result.head.join(sep)
		.replace(/\b_timeslice\b/g, 'date')
		.replace(/\b(_sum|_count)\b/g, 'sum')
	);
	output.push(endline);

	// data
	result.rows.forEach(function(r) {
		result.head.forEach(function(f) {
			var i = idx[f];
			if (r[i].length === 13 && /^14\d+$/.test(r[i])) {
				var d = time_to_date(r[i], date_granularity, auto_granularity);
				if (auto_granularity && d.length > date_granularity) {
					date_granularity = d.length;
				}
				output.push(d, sep);
			} else {
				output.push(r[i], sep);
			}
		});
		output.push(endline);
	});
	return output.join('');
}

function sl_to_html_table(result, summary) {
	if (result.length < 1) {
		return 'No Data';
	}
	var auto_granularity = !result.date_granularity;
	var date_granularity = result.date_granularity || DATE_GRANULARITY_DAYS;
	var sum = [];
	var data = ['<table class="table"><thead><tr>'];
	result.head.forEach(function(f) {
		data.push('<th>');
		if (f == '_timeslice') {
			data.push('date');
		} else if (f == '_sum' || f == '_count') {
			data.push('sum');
		} else {
			data.push(f);
		}
		data.push('</th>');
		sum.push(0);
	});
	data.push('</tr></thead><tbody>');
	result.rows.forEach(function(r) {
		data.push('<tr>');
		result.head.forEach(function(f, i) {
			data.push('<td>');
			if (/date/.test(result.dataTypes[i])) {
				var d = time_to_date(r[i], date_granularity, true);
				// how much info? depends on how round the time is
				if (auto_granularity && d.length > date_granularity)
					date_granularity = d.length;
				data.push(d);
			} else {
				data.push(r[i]);
				if (typeof r[i] == 'number') {
					sum[i] += r[i];
				}
			}
			data.push('</td>');
		});
		data.push('</tr>');
	});
	data.push('</tbody>');
	if (summary) {
		data.push('<tbody>');
		sum.forEach(function(s, i) {
			data.push('<td>');
			if (!s && !i) {
				data.push('Total');
			} else if (s) {
				data.push(s);
			}
			data.push('</td>');
		});
		data.push('</tbody>');
	}
	data.push('</table>');
	return data.join('');
}
