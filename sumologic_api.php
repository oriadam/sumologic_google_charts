<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once 'sumologic_credentials.php';
// Sumologic API
// https://service.eu.sumologic.com/help/Default.htm#Search_Job_API.htm%3FTocPath%3DAPIs%7C_____3
// https://service.eu.sumologic.com/help/Search_Job_API.htm

// Field Extractor for ti
// parse "x=*&" as samplerate nodrop | parse "p=*&" as publisher nodrop | parse "e=*&" as e_category nodrop | parse "t=*&" as widget nodrop | parse "a=*&" as e_action nodrop | parse "l=*&" as e_label nodrop | parse "b=*&" as browser nodrop | parse "g=*&" as geo' | parse "s=*&" as https

function sumologic_error($query = null, $error = null, $info = null, $status = null) {
	return [
		'error' => $error,
		'info' => $info,
		'status' => $status,
		'query' => $query,
	];
}

//Run a query and immediately return a result JSON
// Using the Search API as documented here: https://github.com/SumoLogic/sumo-api-doc/wiki/Search-API
//	$query - The SL search query to execute
//	$from,$to - Period to run query, format '2015-12-31'. Default for $to: 0 (now)
//	$options:
//		endpoint - Endpoint URL. Default: 'https://api.sumologic.com/api/v1'
//		timeout - Seconds allowed to wait for query result. Default: 120
//		tz - Time Zone. Default 'UTC'
//		DataTable - return result in Google's DataTable format. Default: false
//		format - Return object format. Available: json / text. Default: json
//		ch_options - array of options for the CURL object
//		url - Do not use. Overrides all options and use specified url to access the api
//
function sumologic_search_api($query, $from, $to = 0, $options = []) {
	//$endpoint = $options['endpoint'] ?: 'https://api.sumologic.com/api/v1'; // will redirect
	$endpoint = $options['endpoint'] ?: 'https://api.eu.sumologic.com/api/v1'; // skip the redirection
	$query_url = '/logs/search';

	$RATE_LIMIT_ERROR_CODE = 429;
	$timeout = $options['timeout'] ?: 120;
	$timeZone = $options['tz'] ?: $options['tz'] ?: 'UTC';
	$totime;
	if (!empty($to)) {
		$totime = from_to_time($to);
		$totime = date(DATE_ISO8601, $totime);
	}

	$fromtime = from_to_time($from);
	if (empty($fromtime)) {
		return sumologic_error($query, 'Bad from: ' . htmlentities($from));
	} else {
		$fromtime = date(DATE_ISO8601, $fromtime);
	}

	if (empty($options['url'])) {
		$parameters = [
			'q' => $query,
			'from' => $fromtime,
		];
		if (!empty($totime)) {
			$parameters['to'] = $totime;
		}

		if (!empty($timeZone)) {
			$parameters['tz'] = $timeZone;
		}

		if ($options['format'] == 'text') {
			$parameters['format'] = $options['format'];
		}

		$url = $endpoint . $query_url . '?' . http_build_query($parameters);
	} else {
		$url = $options['url'];
		if (strpos($url, '//') === false) {
			$url = $endpoint . $query_url . $url;
		}
	}

	set_time_limit(30 + $timeout); // php time limit
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_VERBOSE, false);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_USERPWD, $GLOBALS['SUMOLOGIC_USER'] . ':' . $GLOBALS['SUMOLOGIC_PASS']);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Accept: application/json',
	));
	curl_setopt($ch, CURLOPT_ENCODING, "gzip");
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	if (!empty($options['ch_options'])) {
		curl_setopt_array($ch, $options['ch_options']);
	}

	curl_setopt($ch, CURLINFO_HEADER_OUT, true); // for debugging

	$response = curl_exec($ch);
	$error = curl_error($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$info = curl_getinfo($ch);
	// remove password from debug info:
	//$info["request_header"] = preg_replace('@(Authorization:\s*Basic\s+)[\d\w=]+@', '$1****', $info["request_header"] ?: 'undefined');
	curl_close($ch);

	if ($status == 301 || $status == 302) {
		$options['url'] = $info['redirect_url'];
		return sumologic_search_api($query, $from, $to, $options);
	}

	if (empty($response) || $status != 200) {
		return sumologic_error($query, $error ?: preg_replace('@[ \t]+@', ' ', preg_replace('@\s*\n+\s*@', "\n", strip_tags($response))) ?: 'empty response', $info, $status);
	}

	$json = json_decode($response, true);
	$error = json_last_error();
	if ($error != JSON_ERROR_NONE) {
		return sumologic_error($query, 'JSON parse error ' . $error, $info, $status);
	}

	if (@$json['status'] === $RATE_LIMIT_ERROR_CODE) {
		// rate limit? seamlessly wait a sec and try again
		sleep(0.1);
		return sumologic_search_api($query, $from, $to, $options);
	}

	// foreach ($json as $row) {
	// 	foreach ($row as $k => $v) {
	// 		if ($k == '_timeslice' || $k == '_sum' || $k == '_count') {
	// 			$row[$k] = 1 * $v;
	// 		}
	// 	}
	// }
	$result = [];
	if (!empty($options['show_query'])) {
		$result['query'] = $query;
	}
	if (!empty($options['show_raw'])) {
		$result['raw'] = $response;
	}
	if (!empty($options['show_ch'])) {
		$result['ch'] = $info;
		$result['ch_status'] = $status;
	}

	$result['rows'] = array();
	if (!empty($json[0])) {
		$result['head'] = array();
		foreach ($json[0] as $name => $row) {
			$result['head'][] = $name;
		}
		usort($result['head'], 'sumo_helper_key_cmp');

		foreach ($json as $i => $row) {
			if (is_numeric($i)) {
				$r = [];
				if ($options['all_numbers']) {
					foreach ($result['head'] as $k) {
						$v = $row[$k];
						if (empty($v)) {
							$r[] = 0;
						} else {
							$r[] = 1 * $v;
						}
					}
				} else {
					foreach ($result['head'] as $k) {
						$r[] = $row[$k];
					}
				}
				$result['rows'][] = $r;
			}
		}
	}

	return $result;
}

function sumo_helper_key_cmp($a, $b) {
	if ($a=='_timeslice') return -1;
	if ($b=='_timeslice') return 1;
	if ($a=='type') return -1;
	if ($b=='type') return 1;
	if ($a=='_count') return -1;
	if ($b=='_count') return 1;
	if ($a=='_sum') return -1;
	if ($b=='_sum') return 1;
	if ($a[0]=='_' && $b[0]!='_') return -1;
	if ($b[0]=='_' && $a[0]!='_') return 1;
	return 0; //strcasecmp($a, $b);
}

function from_to_time($from) {
	$fromtime = false;
	if ($from[0] == '-') {
		$fromtime = $from; // SL has built in support for the "-15m" format
		// format -60m is not supported by strtotime
		$amount = intval(substr($from, 1));
		$interval = strtolower(preg_replace('@^-[\d\s]+@', '', $from)[0]);
		if ($interval == 's') {
			//$amount *= 1;
		} elseif ($interval == 'm') {
			$amount *= 60;
		} elseif ($interval == 'h') {
			$amount *= 60 * 60;
		} elseif ($interval == 'd') {
			$amount *= 24 * 60 * 60;
		} else {
			$amount = false;
		}
		if ($amount) {
			$fromtime = time() - $amount;
		}
	} else {
		$fromtime = strtotime($from);
	}
	return $fromtime;
}

function generate_demo_stats($q, $from, $to, $options) {
	$arr = [];
	$fromtime = from_to_time($from);
	$totime = from_to_time($to ?: 'now');
	$delta = 24 * 60 * 60; // 1d
	if ($fromtime >= $totime - $delta) { // 24h ago
		$delta = 60 * 60; // 1h
	}
	//print "from='$from'; fromtime=$fromtime; to='$to'; totime='$totime'; delta=$delta;";
	$i = 0;
	for ($time = $fromtime; $time <= $totime; $time += $delta) {
		$i++;
		$arr[] = [
			'_timeslice' => $time * 1000,
			'_sum' => 5000 + mt_rand($i * 200, 2000 + $i * 200),
		];
	}
	return $arr;
}
