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

function sl_prepare(result) {

	result.dataTypes = [];
	result.head.forEach(function(field, idx) {
		result.dataTypes.push(sl_detect_type(result, field, idx));
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

	result.head.forEach(function(f, i) {
		data.addColumn(result.dataTypes[i], f);
	});

	result.rows.forEach(function(r) {
		var row = [];
		result.head.forEach(function(f, i) {
			var type = result.dataTypes[i];
			if (/date/.test(type)) {
				row.push(new Date(+r[i]));
			} else if ('number' == type) {
				row.push(+r[i] || 0);
			} else {
				row.push('' + r[i]);
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

	// header
	output.push(result.head.join(sep)
		.replace(/\b_timeslice\b/g, 'date')
		.replace(/\b(_sum|_count)\b/g, 'sum')
	);
	output.push(endline);

	// data
	result.rows.forEach(function(r) {
		result.head.forEach(function(f, i) {
			if (r[i].length === 13 && /^14\d+$/.test(r[f])) {
				var d = new Date(+r[f]);
				output.push(d.toISOString().substr(0, 10), sep);
			} else {
				output.push(r[f], sep);
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
	var sum=[];
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
				var d = new Date(+r[i]);
				data.push(d.toISOString().substr(0, 10));
			} else {
				data.push(r[i]);
				if (typeof r[i]=='number'){
					sum[i]+=r[i];
				}
			}
			data.push('</td>');
		});
		data.push('</tr>');
	});
	data.push('</tbody>');
	if (summary){
		data.push('<tbody>');
		sum.forEach(function(s,i){
			data.push('<td>');
			if (!s&&!i){
				data.push('Total');
			}else if (s){
				data.push(s);
			}
			data.push('</td>');
		});
		data.push('</tbody>');
	}
	data.push('</table>');
	return data.join('');
}
