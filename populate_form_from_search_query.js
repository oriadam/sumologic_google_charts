if (location.search){
	var pars=location.search.replace(/^\?/,'').split('&');
	pars.forEach(function(v){
		var kv=v.split('=');
		if (kv.length===2){
			var inp=document.querySelector('input[name="'+kv[0]+'"]');
			if (inp){
				inp.value=kv[1];
			}
		}
	})
}
var datefields=['from','to','startdate','enddate','start_date','end_date','customFrom','customTo'];
datefields.forEach(function(k){
	var elem=document.querySelector('input[name="'+k+'"]:not([value])');
	if (elem&&!elem.value){
		var d=new Date();
		elem.value=d.toISOString().replace(/T.*/,'');
	}
});
