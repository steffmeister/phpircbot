<?php

/* check if config file exists */
if (!file_exists('phpircbot.conf.php')) die("No config found, please read the README!\n");

/* load phpircbot.conf.php */
require('phpircbot.conf.php');

/* internal constants */
define('IRCBOT_VERSION', '0.1');
define('USER_SHUTDOWN', '1');
define('CONNECTION_LOST', '2');
define('USER_RESTART', '3');

/* quiet mode, disable output */
$quiet_mode = false;

/* convert admin users to array */
$admin_users = explode(',', IRC_ADMIN_USERS);

/* registered commands */
$commands = array();

/* listener */
$msg_listener_global = array();
$msg_listener_private = array();

/* global modules array, contains loaded modules */
$modules = array();

/* auto load modules */
$autoload_modules = explode(',', AUTOLOAD_MODULES);
if (count($autoload_modules) > 0) {
	foreach($autoload_modules as $module_to_load) {
		load_module($module_to_load, '', 1);
	}
}

/* irc channel users */
$users = array();

/* irc resource handle */
$irc_res = false;

/* counter for connection failures */
$connection_failure = 0;

$main_quit = 0;
while(!$main_quit) {
	/* main loop, interpret data sent from irc host */

	$nick = IRC_NICK;
	$channels = explode(',', IRC_CHANNEL);
	
	$irc_res = irc_host_connect();
	
	if ($irc_res == false) {
		irc_bot_echo("Connection failure...");
		$connection_failure++;
		if ($connection_failure > IRC_HOST_RECONNECT) $main_quit = 1;
		sleep(60);
	} else {
		irc_bot_echo("Connection established...");
		$connection_failure = 0;
		$quit = 0;
		$nicked = 0;
		$joined = 0;
		$timeouts = 0;
		while($quit == 0) {
			$line = trim(fgets($irc_res));
			
			$meta_data = stream_get_meta_data($irc_res);

			if ($meta_data['timed_out']) {
				irc_bot_echo("TIMEOUT");
				$timeouts++;
				irc_bot_echo("Timeouts: $timeouts");
			} else {
				$timeouts = 0;
				irc_bot_echo("Timeouts: $timeouts");
			}
			
			if ( $meta_data['eof']){
				irc_bot_echo("EOF");
				$quit = 1;
			}

			irc_bot_echo('IRC: '.$line);
			
			/* after 6 timeouts we reconnect */
			if ($timeouts > 6) $quit = CONNECTION_LOST;
	
			/* send our nick */
			if ((preg_match ( "/(NOTICE AUTH).*(hostname)/i" , $line) == 1) && (!$nicked)) {
				irc_bot_echo('Sending nick...', 0);
				irc_send('USER '.$nick.' 0 * :phpircbot '.IRCBOT_VERSION);
				irc_send('NICK '.$nick);
				
				irc_bot_echo("ok");
				$nicked = 1;
			/* nick already in use */
			} else if (($nicked) && (strpos($line, ' 433 ') !== false)) {
				irc_bot_echo("Nick already in use :(");
				irc_bot_echo("what now?!?!");
				$nick = $nick.'_';
				irc_send('NICK '.$nick);
			/* at the end of motd message, join the channel */
			} else if ((strpos($line, ' 376 ') !== false) && (!$joined)) {
				irc_join_channel(IRC_CHANNEL);				
			/* ping - pong, kind of keepalive stuff */
			} else if (substr($line, 0, 4) == 'PING') {
				irc_send(str_replace('PING', 'PONG', $line));
				foreach($msg_listener_global as $function) {
					call_user_func($function, IRC_CHANNEL, 'PING');
				}
			/* message interpretation */
			} else if (strpos($line, ' PRIVMSG '.$nick.' ') !== false) {
				irc_bot_echo("Received private message...");
				$sender = substr($line, 1, strpos($line, '!')-1);
				irc_bot_echo("From: ".$sender);
				$msg = substr($line, strpos($line, ':', 2)+1);
				irc_bot_echo("Message: ".$msg);
				if (interpret_irc_message($sender, $msg, 1) == false) {
					foreach($msg_listener_private as $function) {
						call_user_func($function, $sender, $msg);
					}
				}
			/* general messages */
			} else if (strpos($line, ' PRIVMSG ') !== false) {
				irc_bot_echo("Received message...");
				$sender = substr($line, 1, strpos($line, '!')-1);
				irc_bot_echo("From: ".$sender);
				$msg = substr($line, strpos($line, ':', 2)+1);
				irc_bot_echo("Message: ".$msg);
				irc_bot_echo("My nick is: ".$nick);
				$result = false;
				if (strpos($msg, $nick) !== false) {
					irc_bot_echo("I was mentioned");
					if (substr($msg, 0, strlen($nick)) == $nick) {
						$msg = substr($msg, strpos($msg, ' ') + 1);
						$result = interpret_irc_message($sender, $msg, 0);
					}
				}
				if ($result == false) {
					foreach($msg_listener_global as $function) {
						call_user_func($function, $sender, $msg);
					}
				}
			/* kick message */
			} else if (strpos($line, ' KICK '.IRC_CHANNEL.' '.$nick) !== false) {
				// we were kicked :(
				irc_bot_echo("were kicked");
				$joined = 0;
				sleep(10);
				irc_bot_echo("rejoining");
				irc_join_channel(IRC_CHANNEL); // rejoin
			/* interprete names list */
			} else if (strpos($line, ' 353 '.$nick) !== false) {
				$names = substr($line, strrpos($line, ':')+1);
				$users_temp = explode(' ', $names);
				$users = array();
				foreach($users_temp as $user) {
					if (substr($user, 0, 1) == '@') $user = substr($user, 1);
					if ($user != $nick) {
						$users[] = $user;
					}
				}
				irc_bot_echo("the users are...");
				print_r($users);
			/* interpret quit message */
			} else if (strpos($line, ' QUIT ') !== false) {
				irc_bot_echo("Received quit...");
				$sender = substr($line, 1, strpos($line, '!')-1);
				irc_bot_echo("From: ".$sender);
				$usercounter = 0;
				foreach($users as $user) {
					if ($user == $sender) unset($users[$usercounter]);
					$usercounter++;
				}
				irc_bot_echo("the users are...");
				print_r($users);
			/* interpret quit message */
			} else if (strpos($line, ' JOIN ') !== false) {
				irc_bot_echo("Received join...");
				$sender = substr($line, 1, strpos($line, '!')-1);
				irc_bot_echo("From: ".$sender);
				$users[] = $sender;
				irc_bot_echo("the users are...");
				print_r($users);
			}
			
			//if (feof($irc_res)) $quit = CONNECTION_LOST;			
		}
		
		/* check what to do now... */
		switch($quit) {
			/* if we were forced to shutdown */
			case USER_SHUTDOWN: $main_quit = 1; break;
case USER_RESTART: $main_quit = 3; break;
			/* connection lost */
			case CONNECTION_LOST:
			default:
				sleep(60);
				irc_bot_echo("QUIT:$quit");
				irc_bot_echo("what next?");
			break;
		}
		/* quit message */
		if (($quit == USER_SHUTDOWN) || ($quit == USER_RESTART))	irc_send(':'.IRC_NICK.' QUIT :gotta go, fight club');

		/* close connection */
		fclose($irc_res);
	}
	
}

exit($main_quit);

/* connect to irc host */
function irc_host_connect() {
	//global $res;
	/* connect to irc host */
	irc_bot_echo('Connecting...', 0);
	$res = fsockopen(IRC_HOST, IRC_PORT);
	
	if ($res == false) {
		irc_bot_echo("error");
		//die();
		return false;
	}
	irc_bot_echo("ok");
	return $res;
}

/* join channel */
function irc_join_channel($channel) {
	global $joined;
	global $nick;
	irc_bot_echo('Joining channel...', 0);
	irc_send(':'.$nick.' JOIN '.$channel);
	irc_bot_echo("ok");
	$joined = 1;
}

/* interpret irc messages */
function interpret_irc_message($sender, $msg, $private=0) {
	global $quit;
	global $nick;
	global $commands;
	global $modules, $quiet_mode;
	$cmd = $msg;
	$params = '';
	if (strpos($msg, ' ') !== false) {
		$cmd = substr($msg, 0, strpos($msg, ' '));
		$params = substr($msg, strpos($msg, ' ')+1);
	}
	
	irc_bot_echo('cmd: \''.$cmd."'");
	irc_bot_echo('params: \''.$params."'");
	
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
		/* show registered commands */
		case 'commands':
			if (count($commands) > 0) {
				$txt = '';
				foreach($commands as $registered=>$func) {
					if ($txt != '') $txt .= ', ';
					$txt .= $registered;
				}
				irc_send_message('Registrierte Befehle: '.$txt, $sender, $private);
			} else {
				irc_send_message('Keine registrierten Befehle.', $sender, $private);
			}
			break;
		/* shutdown bot */
		case 'shutdown':
			if (is_admin($sender)) $quit = USER_SHUTDOWN;
			break;
case 'restart':
if (is_admin($sender)) $quit = USER_RESTART;
break;
		/* rename bot */
		case 'nick':
			if (is_admin($sender)) {
				irc_bot_echo('new nick: '.$params);
				$nick = $params;
				irc_send('NICK '.$nick);
			}
			break;
		/* load module */
		case 'load':
			if (is_admin($sender)) load_module($params, $sender, '', $private);
			break;
		/* switch quiet mode */
		case 'quietmode':
			if (is_admin($sender)) {
				if ($quiet_mode) {
					$quiet_mode = 0;
				} else {
					$quiet_mode = 1;
				}
				irc_send_message('quiet_mode is now: '.$quiet_mode, $sender, $private);
			}
			break;
		/* show version */
		case 'version':
			irc_send_message('v'.IRCBOT_VERSION, $sender, $private);
			break;
		/* help */
		case 'help':
			irc_send_message('Es gibt keine Hilfe!', $sender, $private);
			break;
		/* else (module commands) */
		default:
			irc_bot_echo("default");
			if (default_command($cmd, $params, $sender, $private)) {
				return true;
			} else {
				irc_send_message('Me no understandy!', $sender, $private);
			}
			break;
	}
	return true;
}

function ircbot_global_handler($cmd, $params, $target = '', $private) {
}

/* module commands */
function default_command($cmd, $params, $target = '', $private) {
	global $modules, $commands;
	irc_bot_echo("default_command($cmd, $params, $target, $private)");
	foreach($commands as $command=>$func) {
		if ($cmd == $command) {
			irc_bot_echo("cmd == command");
			call_user_func($func, $params, $target, $private);
			return true;
		}
	}
	foreach($modules as $loaded) {
		if ($cmd == $loaded) {	
			irc_bot_echo("cmd == loaded");
			call_user_func($loaded.'_command', $params, $target, $private);
			return true;
		}
	}
	return false;
}

/* register command */
function ircbot_register_command($command, $function) {
	global $commands;
	irc_bot_echo("ircbot_register_command($command, $function)");
	$commands[$command] = $function;
	//print_r($commands);
}

/* listener registration public */
function ircbot_register_for_global_listening($function) {
	global $msg_listener_global;
	$msg_listener_global[] = $function;
}

/* listener registration private */
function ircbot_register_for_private_listening($function) {
	global $msg_listener_private;
	$msg_listener_private[] = $function;
}

/* send message to irc host */
function irc_send($string) {
	global $irc_res;
	fwrite($irc_res, $string."\n");
}

/* get array of channel users */
function ircbot_get_channel_users() {
	global $users;
	return $users;
}

/* send (chat) message to irc */
function irc_send_message($string, $target='', $private=1) {
	global $irc_res;
	global $nick;
	if (($target == '') || (!$private)) $target = IRC_CHANNEL;
	if ($private && ($target == '')) $target = IRC_CHANNEL;
	$send = ':'.$nick.' PRIVMSG '.$target.' :'.$string."\n";
	if ($string != '') {
		fwrite($irc_res, $send);
		return true;
	}
	return false;
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
	irc_bot_echo("user is not admin...");
	return false;
}

/* echo messages */
function irc_bot_echo($message, $newline=1) {
	global $quiet_mode;
	if (!$quiet_mode) {
		echo $message;
		if ($newline) echo "\n";
	}
}

?>
