<?php

/* load phpircbot.conf.php */
require('phpircbot.conf.php');

/* === nothing to be changed by the user below here... === */

/* internal constants */
define('IRCBOT_VERSION', '0.1');
define('USER_SHUTDOWN', '1');
define('CONNECTION_LOST', '2');

/* convert admin users to array */
$admin_users = explode(',', IRC_ADMIN_USERS);

/* global modules array, contains loaded modules */
$modules = array();

/* auto load modules */
$autoload_modules = explode(',', AUTOLOAD_MODULES);
if (count($autoload_modules) > 0) {
	foreach($autoload_modules as $module_to_load) {
		load_module($module_to_load, '', 1);
	}
}

$irc_res = false;


$connection_failure = 0;

$main_quit = 0;
while(!$main_quit) {
	/* main loop, interpret data sent from irc host */

	$irc_res = irc_host_connect();
	
	if ($irc_res == false) {
		echo "Connection failure...\n";
		$connection_failure++;
		if ($connection_failure > IRC_HOST_RECONNECT) $main_quit = 1;
		sleep(5000);
	} else {
		echo "Connection established...\n";
		$connection_failure = 0;
		$quit = 0;
		$nicked = 0;
		$joined = 0;
		while(!$quit) {			
			$line = trim(fgets($irc_res));
			echo 'IRC: '.$line."\n";
	
			/* send our nick */			
			#old $line == 'NOTICE AUTH :*** No ident response'
			if ((preg_match ( "/(NOTICE AUTH).*(hostname)/i" , $line) == 1) && (!$nicked)) {
				echo 'Sending nick...';
				irc_send('USER '.IRC_NICK.' 0 * :phpircbot '.IRCBOT_VERSION);
				irc_send('NICK '.IRC_NICK);
				
				echo "ok\n";
				$nicked = 1;
			/* at the end of motd message, join the channel */
			} else if ((strpos($line, ' 376 ') !== false) && (!$joined)) {
				irc_join_channel(IRC_CHANNEL);
			/* ping - pong, kind of keepalive stuff */
			} else if (substr($line, 0, 4) == 'PING') {
				irc_send(str_replace('PING', 'PONG', $line));
			/* message interpretation */
			} else if (strpos($line, ' PRIVMSG '.IRC_NICK.' ') !== false) {
				echo "Received private message...\n";
				$sender = substr($line, 1, strpos($line, '!')-1);
				echo "From: ".$sender."\n";
				$msg = substr($line, strpos($line, ':', 2)+1);
				echo "Message: ".$msg."\n";		
				interpret_irc_message($sender, $msg, 1);
			/* general messages */
			} else if (strpos($line, ' PRIVMSG '.IRC_CHANNEL.' ') !== false) {
				echo "Received message...\n";
				$sender = substr($line, 1, strpos($line, '!')-1);
				echo "From: ".$sender."\n";
				$msg = substr($line, strpos($line, ':', 2)+1);
				echo "Message: ".$msg."\n";
				if (strpos($msg, IRC_NICK) !== false) {
					echo "I was mentioned\n";
					if (substr($msg, 0, strlen(IRC_NICK)) == IRC_NICK) {
						$msg = substr($msg, strpos($msg, ' ') + 1);
						interpret_irc_message($sender, $msg, 0);
					}
				}
			/* kick message */
			} else if (strpos($line, ' KICK '.IRC_CHANNEL.' '.IRC_NICK) !== false) {
				// we were kicked :(
				sleep(5000);
				irc_join_channel(IRC_CHANNEL); // rejoin
			}
			
			if (feof($irc_res)) $quit = CONNECTION_LOST;
		
		}
		
		/* check what to do now... */
		switch(!$quit) {
			/* if we were forced to shutdown */
			case USER_SHUTDOWN: $master_quit = 1; break;
			/* connection lost */
			case CONNECTION_LOST:
			default:
				sleep(5000);
				echo "what next?\n";
			break;
		}
		/* quit message */
		irc_send(':'.IRC_NICK.' QUIT :gotta go, fight club');

		/* close connection */
		fclose($irc_res);
	}
	
}

/* connect to irc host */
function irc_host_connect() {
	//global $res;
	/* connect to irc host */
	echo 'Connecting...';
	$res = fsockopen(IRC_HOST, IRC_PORT);
	if ($res == false) {
		echo "error\n";
		//die();
		return false;
	}
	echo "ok\n";
	return $res;
}

/* join channel */
function irc_join_channel($channel) {
	global $joined;
	echo 'Joining channel...';
	irc_send(':'.IRC_NICK.' JOIN '.$channel);
	echo "ok\n";
	$joined = 1;
}

/* interpret irc messages */
function interpret_irc_message($sender, $msg, $private=0) {
	global $quit;
	$cmd = $msg;
	$params = '';
	if (strpos($msg, ' ') !== false) {
		$cmd = substr($msg, 0, strpos($msg, ' '));
		$params = substr($msg, strpos($msg, ' ')+1);
	}
	
	echo 'cmd: \''.$cmd."'\n";
	echo 'params: \''.$params."'\n";
	
	switch($cmd) {
		/* show loaded modules */
		case 'modules':
			if (count($modules) > 0) {
				$txt = '';
				foreach($modules as $loaded) {
					if ($txt != '') $txt .= ', ';
					$txt .= $loaded;
				}
				irc_send_message('Geladene Module: '.$txt, $sender, $private);
			} else {
				irc_send_message('Keine Module geladen.', $sender, $private);
			}
			break;
		/* shutdown bot */
		case 'shutdown':
			if (is_admin($sender)) $quit = USER_SHUTDOWN;
			break;
		/* load module */
		case 'load':
			if (is_admin($sender)) load_module($params, $sender, '', $private);
			break;
		/* show version */
		case 'version':
			irc_send_message('v'.IRCBOT_VERSION, $sender, $private);
			break;
		/* else (module commands) */
		default:
			echo "default\n";
			default_command($cmd, $params, $sender, $private);
			break;
	}
}

/* module commands */
function default_command($cmd, $params, $target = '', $private) {
	global $modules;
	echo "default_command($cmd, $params, $target, $private)\n";
	foreach($modules as $loaded) {
		if ($cmd == $loaded) {	
			echo "cmd == loaded\n";
			call_user_func($loaded.'_command', $params, $target, $private);
		}
	}
}

/* send message to irc host */
function irc_send($string) {
	global $irc_res;
	fwrite($irc_res, $string."\n");
}

/* send (chat) message to irc */
function irc_send_message($string, $target='', $private=1) {
	global $irc_res;
	if (($target == '') || (!$private)) $target = IRC_CHANNEL;
	if ($private && ($target == '')) $target = IRC_CHANNEL;
	$send = ':'.IRC_NICK.' PRIVMSG '.$target.' :'.$string."\n";
	fwrite($irc_res, $send);
}

/* load a module */
function load_module($module, $target='', $quiet=0, $private=0) {
	global $modules;
	$already_loaded = 0;
	foreach($modules as $loaded) {
		if ($loaded == $module) $already_loaded = 1;
	}
	if ($already_loaded == 0) {
		if (file_exists('module_'.$module.'.php')) {
			require('module_'.$module.'.php');
			call_user_func($module.'_init');
			$modules[] = $module;
			if (!$quiet) irc_send_message('Modul geladen.', $target, $private);
		} else {
			if (!$quiet) irc_send_message('Modul nicht gefunden.', $target, $private);
		}
	} else {
		if (!$quiet) irc_send_message('Modul bereits geladen.', $target, $private);
	}
}

/* check if user is admin */
function is_admin($user) {
	global $admin_users;
	foreach($admin_users as $admin) {
		if ($admin == $user) return true;
	}
	echo "user is not admin...\n";
	return false;
}


?>
