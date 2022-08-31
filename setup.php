#!/usr/bin/env php
<?php declare(strict_types=1);

require __DIR__ .'/utils.php';

if ( ! file_exists(CONFIG_FILE)) {
	echo "ERROR: Please copy config.sample.php to config.php and edit it.\n";
	exit(1);
}

require CONFIG_FILE;

if ( ! defined('TWITCH_CLIENT_ID') || empty(TWITCH_CLIENT_ID)) {
	echo "ERROR: TWITCH_CLIENT_ID has not been defined in config.php\n";
	exit(2);
}
if ( ! defined('TWITCH_CLIENT_SECRET') || empty(TWITCH_CLIENT_SECRET)) {
	echo "ERROR: TWITCH_CLIENT_SECRET has not been defined in config.php\n";
	exit(3);
}


/*********************************************************************************
 * Fetch and parse Twitch App Redirect URI
 ********************************************************************************/

$flags = getopt('', ['url:']);
if (empty($flags['url'])) {
	echo "Usage: {$argv[0]} --url <Twitch app Redirect URI>\n\n";
	echo "Example: {$argv[0]} --url localhost:1080\n";
	exit(4);
}

$redirectURL = $flags['url'];
$url = parse_url($redirectURL);

if ( ! $url) {
	echo "ERROR: Unable to parse URL\n";
	exit(5);
}

if ( ! in_array($url['scheme']??'http', ['http', 'https'])) {
	echo "ERROR: URL must be http or https\n";
	exit(6);
}

$url_host = $url['host'];
$url_port = strval($url['port'] ?? '80');


/*********************************************************************************
 * Set up socket and listen for OAuth callback
 ********************************************************************************/

$addrinfos = socket_addrinfo_lookup($url_host, $url_port);
if (false === $addrinfos) {
	echo "ERROR: Unable to lookup any address for URL\n";
	exit(7);
}

do {
	$sock = socket_addrinfo_bind($addrinfos[0]);
	array_shift($addrinfos);
} while (false === $sock && count($addrinfos));

if ( ! $sock) {
	echo "ERROR: Unable to create socket\n";
	exit(8);
}

if ( ! socket_listen($sock)) {
	echo "ERROR: Unable to listen on socket\n";
	exit(9);
}

$authURL = 'https://id.twitch.tv/oauth2/authorize?redirect_uri='. urlencode($redirectURL) .'&response_type=code&scope=&client_id='. TWITCH_CLIENT_ID;
echo "Please browse to the following URL and authorize the application:\n$authURL\n";
echo "Waiting for authorization...\n\n";


/*********************************************************************************
 * Extract auth code from OAuth callback
 ********************************************************************************/

$client = socket_accept($sock);
$request = socket_read($client, 1024);
if ( ! preg_match('#GET [^?]*\?code=([0-9a-z]+)#i', $request, $matches)) {
	$url = explode(' ', explode("\n", $request, 2)[0])[1];
	$querystring = str_contains($url, '?') ? explode('?', $url)[1] : '';
	parse_str($querystring, $querystring_params);

	$params_text = "\n";
	$params_html = '';
	foreach ($querystring_params as $key => $value) {
		$params_text .= "$key: $value\n";
		$params_html .= "<li>$key: $value</li>";
	}

	echo "ERROR: Did not receive a valid authorization code!$params_text";
	socket_write($client, "HTTP/1.1 400 Bad Request\n\n<h1>Did not receive a valid authorization code!</h1><ul>$params_html</ul>\n");
	socket_close($sock);
	exit(10);
}

socket_write($client, "HTTP/1.1 200 OK\n\n<h1>Success! You can now return to the terminal!</h1>\n");
socket_close($sock);


/*********************************************************************************
 * Request auth & refresh tokens
 ********************************************************************************/

$code = $matches[1];
$c = curl_init('https://id.twitch.tv/oauth2/token');
$o = [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_POSTFIELDS => [
		'client_id' => TWITCH_CLIENT_ID,
		'client_secret' => TWITCH_CLIENT_SECRET,
		'code' => $code,
		'grant_type' => 'authorization_code',
		'redirect_uri' => $redirectURL,
	],
];
curl_setopt_array($c, $o);
$ret = curl_exec($c);
$httpcode = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
curl_close($c);

if (200 != $httpcode) {
	echo "ERROR: Unable to get auth tokens from Twitch:\n$ret\n";
	exit(11);
}

$json = json_decode($ret);
if ( ! $json) {
	echo "ERROR: Unable to parse JSON to get Twitch auth tokens: ". json_last_error_msg() ."\n";
	exit(12);
}


/*********************************************************************************
 * Write tokens to config
 ********************************************************************************/

try {
	upsert_config_constants([
		'TWITCH_TOKEN' => $json->access_token,
		'TWITCH_REFRESH_TOKEN' => $json->refresh_token,
	]);
} catch (\RuntimeException $e) {
	echo "ERROR: Unable to upsert config file: {$e->getMessage()}\n";
	exit(13);
}
