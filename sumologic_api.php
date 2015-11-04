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

function sumologic_api($query, $from, $to = 0, $options = []) {
	$base_url = $options['endpoint'] ?: 'https://api.sumologic.com/api/v1'; // redirection
	//$base_url = $options['endpoint'] ?: 'https://api.eu.sumologic.com/api/v1'; // skip the redirection
	$base_url .= '/logs/search';

	$RATE_LIMIT_ERROR_CODE = 429;
	$timeout = $options['timeout'] ?: 61;
	$timeZone = $options['timeZone'] ?: $options['timezone'] ?: 'UTC';
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

	if (!empty($options['format'])) {
		$parameters['format'] = $options['format'];
	}

	$url = $base_url . '?' . http_build_query($parameters);
	if (!empty($options['url'])) {
		$url = $options['url'];
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

	//curl_setopt($ch, CURLINFO_HEADER_OUT, true);

	$response = curl_exec($ch);
	$error = curl_error($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$info = curl_getinfo($ch);
	// remove password from debug info:
	$info["request_header"] = preg_replace('@(Authorization:\s*Basic\s+)[\d\w=]+@', '$1****', $info["request_header"]);
	curl_close($ch);

	if ($status == 301 || $status == 302) {
		$options['url'] = $info['redirect_url'];
		return sumologic_api($query, $from, $to, $options);
	}

	if (empty($response) || $status != 200) {
		return sumologic_error($query, $error ?: preg_replace('@[ \t]+@', ' ', preg_replace('@\s*\n+\s*@', "\n", strip_tags($response))) ?: 'empty response', $info, $status);
	}

	$json = json_decode($response);
	$error = json_last_error();
	if ($error != JSON_ERROR_NONE) {
		return sumologic_error($query, 'JSON parse error ' . $error, $info, $status);
	}

	if (@$json['status'] === $RATE_LIMIT_ERROR_CODE) {
		// rate limit? seamlessly wait and try again
		sleep(1);
		return sumologic_api($query, $from, $to, $options);
	}
	if (!empty($options['show_query'])) {
		$json['query'] = $query;
	}
	if (!empty($options['show_raw'])) {
		$json['raw'] = $response;
	}
	if (!empty($options['show_ch'])) {
		$json['ch'] = $info;
		$json['ch_status'] = $status;
	}
	return $json;
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