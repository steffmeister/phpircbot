<?php

/* module_linky */

$link_stats = array();

$link_watcher = array();
$link_watcher[] = 'imgur.com';
$link_watcher[] = 'xkcd.com';

$imgur_history = array();

$ping_counter = 0;

$autoimgurpaste = 1;

function linky_init() {
	global $autoimgurpaste;
	irc_bot_echo("linky_init module");
	$autoimgurpaste = 1;
	ircbot_register_for_global_listening('linky_listener_global');
}

function linky_command($string, $target='', $private=0) {
	//irc_send_message('default command handler', $target, $private);
	global $link_stats, $imgur_history, $autoimgurpaste;
	switch($string) {
		case 'show':
			if (count($link_stats) > 0) {
				foreach($link_stats as $host=>$amount) {
					irc_send_message($host.' : '.$amount, $target, $private);
				}
			} else {
				irc_send_message('Noch keine Links gesammelt.', $target, $private);
			}
			break;
		case 'imgur':
			$link = imgur_get_random_link();
			if ($link != false) {
				irc_send_message($link, $target, $private);
			} else {
				irc_send_message("Sorry, keinen gültigen Link erhalten :(", $target, $private);
			}
			break;
		case 'autoimgurpaste':
			if ($autoimgurpaste) {
				$autoimgurpaste = 0;
			} else {
				$autoimgurpaste = 1;
			}
			irc_send_message('autoimgurpaste is now: '.$autoimgurpaste, $target, $private);
			break;
		case 'help':
			irc_send_message('linky module commands:', $target, $private);
			irc_send_message(' imgur          ... get random imgurl link', $target, $private);
			irc_send_message(' autoimgurpaste ... show random imgurl link pasting mode (currently '.$autoimgurpaste.')', $target, $private);
			irc_send_message(' show           ... show some stats about pasted links', $target, $private);
			break;
	}
}

function imgur_get_random_link() {
	global $imgur_history;
	// erzeuge einen neuen cURL-Handle
	$ch = curl_init();

	// setze die URL und andere Optionen
	curl_setopt($ch, CURLOPT_URL, "http://imgur.com/gallery/random");
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// führe die Aktion aus und gebe die Daten an den Browser weiter
	$result = curl_exec($ch);

	// schließe den cURL-Handle und gebe die Systemresourcen frei
	curl_close($ch);
	irc_bot_echo($result);
	$link_begin = strpos($result, 'Location:')+strlen('Location: ');
	$link_end = strpos($result, "\n", $link_begin);
	$link = trim(substr($result, $link_begin, $link_end-$link_begin));
	irc_bot_echo("$link_begin - $link_end, string: '".$link."'");
	if (substr($link, 0, 4) == 'http') {
		$imgur_history[] = $link;
		return $link;
	}
	return false;
}


function linky_listener_global($sender, $msg) {
	global $link_stats, $ping_counter, $imgur_history;
	if ($msg == 'PING') {
		$ping_counter++;
		irc_bot_echo("Received ping...$ping_counter");
		if ($ping_counter > 10) {
			$link = imgur_get_random_link();
			if ($link != false) irc_send_message('Random IMGUR anyone? '.$link, IRC_CHANNEL, 0);
			$ping_counter = 0;
		}
	} else {
		$pos = strpos($msg, 'http');
		if ($pos !== false) {
			$end = strpos($msg, ' ', $pos);
			if ($end !== false) {
				$full_link = substr($msg, $pos, $end);
			} else {
				$full_link = substr($msg, $pos);
			}
			irc_bot_echo("link detected as: ".$full_link);
			$url = parse_url($full_link);
			irc_bot_echo("host of this is: ".$url['host']);
			if (!isset($link_stats[$url['host']])) {
				$link_stats[$url['host']] = 0;
			}
			if ($url['host'] == 'imgur.com') {				
				foreach($imgur_history as $url) {
					if ($url == $full_link) {
						irc_send_message('Den Link hatten wir schon.', IRC_CHANNEL, 0);
						break;
					}					
				}
				$imgur_history[] = $full_link;
			}
			$link_stats[$url['host']]++;
		}
	}
}


?>
