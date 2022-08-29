<?php
require __DIR__ .'/config.php';

if ( ! defined('ENABLE_OUTPUT')) {
	define('ENABLE_OUTPUT', false);
}

function send_discord_notification($message, $username = null, $avatar = null, $preview_image = null, $messageID = null)
{
	$data = [
		'content' => $message,
	];

	if ($username) {
		$data['username'] = $username;
	}
	if ($avatar) {
		$data['avatar_url'] = $avatar;
	}
	if ($preview_image) {
		$data['embeds'][] = [
			'image' => [
				'url' => str_replace('-{width}x{height}', '', $preview_image),
			],
		];
	}

	$c = curl_init(DISCORD_WEBHOOK_URL . ($messageID ? "/messages/$messageID" : '') .'?wait=true');
	$o = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => 'payload_json='. urlencode(json_encode($data)),
		CURLOPT_CUSTOMREQUEST => $messageID ? 'PATCH' : 'POST',
	];
	curl_setopt_array($c, $o);
	$ret = curl_exec($c);
	curl_close($c);

	$json = json_decode($ret);
	return $json->id;
}

function get_twitch_user_data(array $userIDs)
{
	if (empty($userIDs)) return [];

	$querystring = '?id='. implode('&id=', $userIDs);
	$url = 'https://api.twitch.tv/helix/users'. $querystring;
	$c = curl_init($url);
	$o = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [
			'Authorization: Bearer '. TWITCH_TOKEN,
			'Client-Id: '. TWITCH_CLIENT_ID,
		],
	];
	curl_setopt_array($c, $o);
	$ret = curl_exec($c);
	curl_close($c);

	$data = json_decode($ret)->data;
	$data = array_combine(array_map(fn($x) => $x->id, $data), $data);

	return $data;
}

$url = 'https://api.twitch.tv/helix/streams?first=100&game_id='. TWITCH_GAME_ID;

$c = curl_init($url);
$o = [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => [
		'Authorization: Bearer '. TWITCH_TOKEN,
		'Client-Id: '. TWITCH_CLIENT_ID,
	],
];
curl_setopt_array($c, $o);
$ret = curl_exec($c);
curl_close($c);
$streams = json_decode($ret);

if (ENABLE_OUTPUT) echo ' --- '. date('Y-m-d H:i:s') ."\n";

if ( ! $streams) {
	if (ENABLE_OUTPUT) echo "ERROR: Unable to parse JSON returned from Twitch.\n";
	exit(2);
} else if ( ! empty($streams->error)) {
	if (ENABLE_OUTPUT) echo "ERROR: $streams->error ($streams->status) \"$streams->message\"\n";
	exit(1);
}

if (empty($streams->data)) {
	if (ENABLE_OUTPUT) echo "No streams :(\n";
	die();
}

$filename = __DIR__ . '/streamdata_'. TWITCH_GAME_ID .'.dat';
$prev_filedata = [];
if (file_exists($filename)) {
	$prev_filedata = explode("\n", trim(file_get_contents($filename)));
	$prev_filedata = array_map(fn($x) => explode("\t", $x), $prev_filedata);
	$prev_filedata = array_column($prev_filedata, 1, 0);
	$prev_filedata = array_map(fn($x) => explode(' ', $x), $prev_filedata);
}

$userIDs = array_map(fn($x) => $x->user_id, $streams->data);
$userdata = get_twitch_user_data($userIDs);

$filedata = '';
foreach ($streams->data as $stream) {
	$id = $stream->id;
	$user_id = $stream->user_id;
	$game = $stream->game_name;
	$userslug = $stream->user_login;
	$username = $stream->user_name;
	$stream_title = $stream->title;
	$stream_type = $stream->type;
	$viewers = $stream->viewer_count;
	$thumbnail = $stream->thumbnail_url;

	$profile_image = $userdata[$user_id]->profile_image_url ?? null;

	$already_notified = isset($prev_filedata[$id]);
	$max_viewers = $viewers;
	$prev_viewers = $prev_filedata[$id][0] ?? 0;

	if ($already_notified) {
		$max_viewers = max($max_viewers, $prev_viewers);
	}

	$msgID = $prev_filedata[$id][1] ?? null;
	if (ENABLE_OUTPUT) echo "$username is $stream_type streaming **$game** for $viewers viewers! <https://twitch.tv/$userslug> (\"$stream_title\")\n";
	if ( ! $already_notified or $max_viewers > $prev_viewers) {
		if (ENABLE_OUTPUT) echo "   notifying!\n";
		$msgID = send_discord_notification(DISCORD_MESSAGE_PREFIX ."`$username` is $stream_type streaming **$game** for a peak of $max_viewers viewers! <https://twitch.tv/$userslug> (\"$stream_title\")", $username, $profile_image, $thumbnail, $msgID);
	}


	$filedata .= "$id\t$max_viewers $msgID\n";
}

file_put_contents($filename, $filedata);
