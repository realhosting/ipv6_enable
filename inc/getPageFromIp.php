<?php
function getPageFromIp($url, $ip = false) {

	// Create parts from full URL and set defaults
	$splitUrl = explode('://', $url);

	if (count($splitUrl) > 1) {
		list($scheme, $path) = $splitUrl;
	} else {
		$scheme = 'http';
		$path = $url;
	}
	if (strpos($path, '/') !== false) {
		list($domain, $path) = explode('/', $path, 2);
		$url = "$scheme://$domain/$path";
	} else {
		$domain = $path;
		$url = "$scheme://$domain/";
	}
	
	// Set cURL DNS cache
	$resolve = array(
		"-$domain:80", "-$domain:443",				// remove old cache (non www)
		"-www.$domain:80", "-www.$domain:443",		// remove old cache (www)
		"$domain:80:$ip", "$domain:443:$ip",		// add new DNS cache (non www)
		"www.$domain:80:$ip", "www.$domain:443:$ip" // add new DNS cache (www)
	);
	
	// cURL options
	$options = array(
		CURLOPT_CUSTOMREQUEST  =>	'GET',
		CURLOPT_POST           =>	false,
		CURLOPT_USERAGENT      =>	'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:54.0) Gecko/20100101 Firefox/54.0', // Windows 10, latest Firefox
		CURLOPT_COOKIEFILE     =>	"cookies/cookie_$domain.txt",
		CURLOPT_COOKIEJAR      =>	"cookies/cookie_$domain.txt",
		CURLOPT_RETURNTRANSFER =>	true,			// return web page
		CURLOPT_HEADER         =>	false,			// don't return headers
		CURLOPT_FOLLOWLOCATION =>	true,   		// follow redirects
		CURLOPT_ENCODING       =>	'',				// handle all encodings
		CURLOPT_AUTOREFERER    =>	true,			// set referer on redirect
		CURLOPT_CONNECTTIMEOUT =>	8,				// timeout on connect
		CURLOPT_TIMEOUT        =>	30,				// timeout on response
		CURLOPT_MAXREDIRS      =>	10,				// stop after 10 redirects
		CURLOPT_SSL_VERIFYPEER =>	false,			// Disable SSL checks and ignore errors
		CURLOPT_SSL_VERIFYHOST =>	false,			// Disable SSL checks and ignore errors
		CURLOPT_SSL_VERIFYSTATUS =>	false,			// Disable SSL checks and ignore errors
		CURLOPT_RESOLVE        =>	$resolve,		// Look for website on this ip(s)
#		CURLOPT_IPRESOLVE      =>	CURL_IPRESOLVE_V4,	// Force using ipv4
#		CURLOPT_IPRESOLVE      =>	CURL_IPRESOLVE_V6,	// Force using ipv6
		CURLOPT_DNS_CACHE_TIMEOUT		=> 0,	// No cache
		CURLOPT_DNS_USE_GLOBAL_CACHE	=> false,
	);
	
	// cURL exec, get data and info
	$ch = curl_init($url);
	curl_setopt_array($ch, $options);
	$data['curlinfo']	= curl_getinfo($ch);
	$data['errno']		= curl_errno($ch);
	$data['errmsg']		= curl_error($ch);
	$data['source']		= curl_exec($ch);
	curl_close($ch);
	
	return $data;
}
