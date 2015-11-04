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

function sl_to_DataTable(result,dataHead) {
	var rxNumber = /^\d+\.\d+$/;
	var data = new google.visualization.DataTable();

	dataHead = dataHead || sl_to_head(result);

	dataHead.forEach(function(f) {
		if (f == '_timeslice') {
			data.addColumn('datetime', f);
		} else if (f == '_sum' || f == '_count') {
			data.addColumn('number', f);
		} else {
			var v = result[0][f];
			var type = 'string'
			if (v.length === 13 && /^14\d+$/.test(v)) {
				type = 'datetime';
			} else if (rxNumber.test(v)) {
				type = 'number';
			}
			data.addColumn(type, f);
		}
	});
	result.forEach(function(r) {
		var row = [];
		dataHead.forEach(function(f, i) {
			var type = data.getColumnType(i);
			if (/date/.test(type)) {
				row.push(new Date(+r[f]));
			} else if ('number' == type) {
				row.push(+r[f]);
			} else {
				row.push(r[f].toString());
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
