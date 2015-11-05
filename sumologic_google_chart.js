////////////////////////////////////////////////////////////
// See https://github.com/oriadam/sumologic_google_charts //
////////////////////////////////////////////////////////////

function sl_to_head(result) {
	var dataHead = Object.keys(result[0]);
	dataHead.sort(function(a, b) {
		if (a === '_timeslice') {
			return -1;
		}
		if (b === '_timeslice') {
			return 1;
		}
		if (a === 'type') {
			return -1;
		}
		if (b === 'type') {
			return 1;
		}
		return 0; // --> otherwise keep the same order: a < b ? -1 : 1;
	});
	return dataHead;
}

function sl_detect_type(result,field){
	var rxNumber = /^(\d+\.\d+|)$/;
	var rxDate = /^14\d{11}$/;
	var t='datetime';
	var i;
	for (i=0;i<result.length;i++){
		var v=result[i][field];
		if (t=='datetime'&&!rxDate.test(v)){
			t='number';
		}
		if (t=='number'&&!rxNumber.test(v)){
			return 'string';
		}
	}
	return t;
}

function sl_to_DataTable(result,dataHead) {
	var data = new google.visualization.DataTable();

	dataHead = dataHead || sl_to_head(result);

	dataHead.forEach(function(f) {
		if (f == '_timeslice') {
			data.addColumn('datetime', f);
		} else if (f == '_sum' || f == '_count') {
			data.addColumn('number', f);
		} else {
			data.addColumn(sl_detect_type(result,f), f);
		}
	});
	result.forEach(function(r) {
		var row = [];
		dataHead.forEach(function(f, i) {
			var type = data.getColumnType(i);
			if (/date/.test(type)) {
				row.push(new Date(+r[f]));
			} else if ('number' == type) {
				row.push(+r[f]||0);
			} else {
				row.push(''+r[f]);
			}
		});
		data.addRow(row);
	});
	return data;
}

function sl_to_csv(result,dataHead) {
	if (result.length < 1) {
		return '';
	}
	var data = [];
	var endline = "\n";
	var sep = ",";
	dataHead = dataHead || sl_to_head(result);
	dataHead.forEach(function(f) {
		if (f == '_timeslice') {
			data.push('date', sep);
		} else if (f == '_sum' || f == '_count') {
			data.push('sum', sep);
		} else {
			data.push(f, sep);
		}
	});
	data.push(endline);
	result.forEach(function(r) {
		dataHead.forEach(function(f) {
			if (r[f].length === 13 && /^14\d+$/.test(r[f])) {
				var d = new Date(+r[f]);
				data.push(d.toISOString().substr(0, 10), sep);
			} else {
				data.push(r[f], sep);
			}
		});
		data.push(endline);
	});
	return data.join('');
}

function sl_to_html_table(result,dataHead) {
	if (result.length < 1) {
		return 'No Data';
	}
	var data = ['<table class="table"><thead><tr>'];
	dataHead = dataHead || sl_to_head(result);
	dataHead.forEach(function(f) {
		data.push('<th>');
		if (f == '_timeslice') {
			data.push('date');
		} else if (f == '_sum' || f == '_count') {
			data.push('sum');
		} else {
			data.push(f);
		}
		data.push('</th>');
	});
	data.push('</tr></thead><tbody>');
	result.forEach(function(r) {
		data.push('<tr>');
		dataHead.forEach(function(f) {
			data.push('<td>');
			if (r[f].length === 13 && /^14\d+$/.test(r[f])) {
				var d = new Date(+r[f]);
				data.push(d.toISOString().substr(0, 10));
			} else {
				data.push(r[f]);
			}
			data.push('</td>');
		});
		data.push('</tr>');
	});
	data.push('</tbody></table>');
	return data.join('');
}
