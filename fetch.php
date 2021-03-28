<?php
require __DIR__ .'/config.php';

function send_discord_notification($message, $messageID = null)
{
	$c = curl_init('https://discord.com/api/webhooks/'. DISCORD_WEBHOOK_ID .'/'. DISCORD_WEBHOOK_TOKEN . ($messageID ? "/messages/$messageID" : '') .'?wait=true');
	$o = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => "content=$message",
		CURLOPT_CUSTOMREQUEST => $messageID ? 'PATCH' : 'POST',
	];
	curl_setopt_array($c, $o);
	$ret = curl_exec($c);
	curl_close($c);

	$json = json_decode($ret);
	return $json->id;
}

$url = 'https://api.twitch.tv/helix/streams?first=100&game_id='. TWITCH_GAME_ID;

$c = curl_init($url);
$o = [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => [
		'Authorization: Bearer '. TWITCH_OAUTH_SECRET,
		'Client-Id: '. TWITCH_CLIENT_ID,
	],
];
curl_setopt_array($c, $o);
$ret = curl_exec($c);
curl_close($c);
$streams = json_decode($ret);

echo ' --- '. date('Y-m-d H:i:s') ."\n";

if (empty($streams->data)) {
	echo "No streams :(\n";
}

$filename = 'streamdata_'. TWITCH_GAME_ID .'.dat';
$prev_filedata = [];
if (file_exists($filename)) {
	$prev_filedata = explode("\n", trim(file_get_contents($filename)));
	$prev_filedata = array_map(fn($x) => explode("\t", $x), $prev_filedata);
	$prev_filedata = array_column($prev_filedata, 1, 0);
	$prev_filedata = array_map(fn($x) => explode(' ', $x), $prev_filedata);
}

$filedata = '';
foreach ($streams->data as $stream) {
	$id = $stream->id;
	$game = $stream->game_name;
	$userslug = $stream->user_login;
	$username = $stream->user_name;
	$stream_title = $stream->title;
	$stream_type = $stream->type;
	$viewers = $stream->viewer_count;

	$already_notified = isset($prev_filedata[$id]);
	$max_viewers = $viewers;
	$prev_viewers = $prev_filedata[$id][0] ?? 0;

	if ($already_notified) {
		$max_viewers = max($max_viewers, $prev_viewers);
	}

	$msgID = $prev_filedata[$id][1] ?? null;
	echo "$username is $stream_type streaming **$game** for $viewers viewers! <https://twitch.tv/$userslug> (\"$stream_title\")\n";
	if ( ! $already_notified or $max_viewers > $prev_viewers) {
		echo "   notifying!\n";
		$msgID = send_discord_notification(DISCORD_MESSAGE_PREFIX ."`$username` is $stream_type streaming **$game** for a peak of $max_viewers viewers! <https://twitch.tv/$userslug> (\"$stream_title\")", $msgID);
	}


	$filedata .= "$id\t$max_viewers $msgID\n";
}

file_put_contents($filename, $filedata);
