// USAGE EXAMPLE
sl_queries={

	'Pageviews' : ' event="v" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',
	'Pageviews/Top Geo' : ' event="v" | sum(samplerate) by geo | total _sum by geo | order by _total | 1 as dummy | accum dummy by geo | if (_accum=1,1,0) as flag | accum flag as rank | where rank <= 10 | fields -dummy, _accum,_total,flag,rank',
	'Pageviews/All Geo' : ' event="v" | sum(samplerate) by geo | sort by _sum',
	'Pageviews-Imp' : ' (event="v") or (event="i")  | timeslice 1d | sum(samplerate) by _timeslice,event |  sort by _timeslice | transpose row _timeslice column event',

	'DAU' : ' event="d" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',
	'DAU/Top Geo' : ' event="d" | sum(samplerate) by geo | total _sum by geo | order by _total | 1 as dummy | accum dummy by geo | if (_accum=1,1,0) as flag | accum flag as rank | where rank <= 10 | fields -dummy, _accum,_total,flag,rank',
	'DAU/All Geo' : ' event="d" | sum(samplerate) by geo | sort by _sum',
	'DAU/Device' : ' event="d" | timeslice 1d | sum(samplerate) by _timeslice,mobile | transpose row _timeslice column mobile | sort by _timeslice',

	'Runs' : ' event="r" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',
	'Runs/Device' : ' event="r" | timeslice 1d | sum(samplerate) by _timeslice,mobile | transpose row _timeslice column mobile | sort by _timeslice',
	'Runs/Domains' : ' event="r" | sum(samplerate) by domain | sort by _sum',
	'Runs/Top Geo' : ' event="r" | sum(samplerate) by geo | total _sum by geo | order by _total | 1 as dummy | accum dummy by geo | if (_accum=1,1,0) as flag | accum flag as rank | where rank <= 10 | fields -dummy, _accum,_total,flag,rank',
	'Runs/All Geo' : ' event="r" | sum(samplerate) by geo | sort by _sum',

	'Aborts' : ' event="a" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',
	'Abort Reasons' : ' event="a" | timeslice 1d | sum(samplerate) by _timeslice,subevent | transpose row _timeslice column subevent | sort by _timeslice',
	'Abort Reasons/Device' : ' event="a" | timeslice 1d | sum(samplerate) by _timeslice,subevent,mobile | transpose row _timeslice column mobile,subevent | sort by _timeslice',
	'Vertical Timeouts' : ' event="xd" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',

	'Imp/Widget' : ' event="i" | timeslice 1d | sum(samplerate) by _timeslice,widget | transpose row _timeslice column widget | sort by _timeslice',
	'Imp/Domains' : ' event="i" | sum(samplerate) by domain | sort by _sum',
	'Imp/Rules' : ' event="i" | timeslice 1d | sum(samplerate) by _timeslice,widget,rule_id | transpose row _timeslice column widget,rule_id | sort by _timeslice',
	'Imp/Top Geo' : ' event="i" | sum(samplerate) by geo | total _sum by geo | order by _total | 1 as dummy | accum dummy by geo | if (_accum=1,1,0) as flag | accum flag as rank | where rank <= 10 | fields -dummy, _accum,_total,flag,rank',
	'Imp/All Geo' : ' event="i" | sum(samplerate) by geo | sort by _sum',	
	'Widget/Domains' : ' event="i" | timeslice 1d | sum(samplerate) by widget,domain | sort by _sum | transpose row domain column widget',

	'Clicks per Rule' : ' event="c" | timeslice 1d | sum(samplerate) by _timeslice,widget,rule_id | transpose row _timeslice column widget,rule_id | sort by _timeslice',
	'Blanks&Passbacks' : ' (event="fa" or event="na") | timeslice 1d | sum(samplerate) by _timeslice,widget,rule_id | transpose row _timeslice column widget,rule_id | sort by _timeslice',

	'All events' : ' | timeslice 1d | sum(samplerate) by _timeslice,event | transpose row _timeslice column event | sort by _timeslice',

	'% HTTPS' : ' event="r" | if (https="1",samplerate,0) as https_samplerate | sum(samplerate) as total_traffic, sum(https_samplerate) as https_traffic | concat(100*https_traffic/total_traffic,"%") as ratio | fields ratio',
	'% Adblock ' : ' event="r" | if (adblock="1",samplerate,0) as adblock_samplerate | sum(samplerate) as total_traffic, sum(adblock_samplerate) as adblock_traffic | concat(100*adblock_traffic/total_traffic,"%") as ratio | fields ratio',

// unused:
	// 'Injections' : ' event="j" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',
	// 'Injections/Top Geo' : ' event="j" | sum(samplerate) by geo | total _sum by geo | order by _total | 1 as dummy | accum dummy by geo | if (_accum=1,1,0) as flag | accum flag as rank | where rank <= 10 | fields -dummy, _accum,_total,flag,rank',
	// 'Injections/All Geo' : ' event="j" | sum(samplerate) by geo | sort by _sum',

//	'Injections/Top Geo/day' : ' event="j" | timeslice 1d | sum(samplerate) by geo,_timeslice | total _sum by geo | order by _total | 1 as dummy | accum dummy by geo | if (_accum=1,1,0) as flag | accum flag as rank | where rank <= 10 | fields -dummy, _accum,_total,flag,rank | sort by _timeslice | transpose row _timeslice column geo',
//	'Injections/All Geo/day' : ' event="j" | timeslice 1d | sum(samplerate) by _timeslice,geo | sort by _timeslice,_sum desc',
	//'New Users' : ' event="n" | timeslice 1d | sum(samplerate) by _timeslice | sort by _timeslice',
	//'Imp/Geo chart' : ' event="i" | timeslice 1d | sum(samplerate) by _timeslice,geo | sort by _timeslice | transpose row _timeslice column geo',
//	'% CTR ' : ' event="i" OR event="c" | if(event="i",samplerate,0) as impr_samplerate | if(event="c",samplerate,0) as click_samplerate | sum(click_samplerate) as click_traffic, sum(impr_samplerate) as impr_traffic | concat(100*click_traffic/impr_traffic,"%") as ratio | fields ratio,click_traffic,impr_traffic', <-- not working
};
