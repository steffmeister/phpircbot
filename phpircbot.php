<?php

/* check if config file exists */
if (!file_exists('phpircbot.conf.php')) die("No config found, please read the README!\n");

/* load phpircbot.conf.php */
require('phpircbot.conf.php');

/* internal constants */
define('IRCBOT_VERSION', '0.1');
define('USER_SHUTDOWN', '1');
define('CONNECTION_LOST', '2');

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
		echo "Connection failure...\n";
		$connection_failure++;
		if ($connection_failure > IRC_HOST_RECONNECT) $main_quit = 1;
		sleep(60);
	} else {
		echo "Connection established...\n";
		$connection_failure = 0;
		$quit = 0;
		$nicked = 0;
		$joined = 0;
		$timeouts = 0;
		while($quit == 0) {
			$line = trim(fgets($irc_res));
			
			$meta_data = stream_get_meta_data($irc_res);

			if ($meta_data['timed_out']) {
				echo "TIMEOUT\n";
				$timeouts++;
				echo "Timeouts: $timeouts\n";
			} else {
				$timeouts = 0;
				echo "Timeouts: $timeouts\n";
			}
			
			if ( $meta_data['eof']){
				echo "EOF\n";
				$quit = 1;
			}

			echo 'IRC: '.$line."\n";
			
			/* after 6 timeouts we reconnect */
			if ($timeouts > 6) $quit = CONNECTION_LOST;
	
			/* send our nick */
			if ((preg_match ( "/(NOTICE AUTH).*(hostname)/i" , $line) == 1) && (!$nicked)) {
				echo 'Sending nick...';
				irc_send('USER '.$nick.' 0 * :phpircbot '.IRCBOT_VERSION);
				irc_send('NICK '.$nick);
				
				echo "ok\n";
				$nicked = 1;
			/* nick already in use */
			} else if (($nicked) && (strpos($line, ' 433 ') !== false)) {
				echo "Nick already in use :(\n";
				echo "what now?!?!\n";
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
				echo "Received private message...\n";
				$sender = substr($line, 1, strpos($line, '!')-1);
				echo "From: ".$sender."\n";
				$msg = substr($line, strpos($line, ':', 2)+1);
				echo "Message: ".$msg."\n";		
				if (interpret_irc_message($sender, $msg, 1) == false) {
					foreach($msg_listener_private as $function) {
						call_user_func($function, $sender, $msg);
					}
				}
			/* general messages */
			} else if (strpos($line, ' PRIVMSG ') !== false) {
				echo "Received message...\n";
				$sender = substr($line, 1, strpos($line, '!')-1);
				echo "From: ".$sender."\n";
				$msg = substr($line, strpos($line, ':', 2)+1);
				echo "Message: ".$msg."\n";
				echo "My nick is: ".$nick."\n";
				$result = false;
				if (strpos($msg, $nick) !== false) {
					echo "I was mentioned\n";
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
				echo "were kicked\n";
				$joined = 0;
				sleep(10);
				echo "rejoining\n";
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
				echo "the users are...\n";
				print_r($users);
			}
			
			//if (feof($irc_res)) $quit = CONNECTION_LOST;			
		}
		
		/* check what to do now... */
		switch($quit) {
			/* if we were forced to shutdown */
			case USER_SHUTDOWN: $main_quit = 1; break;
			/* connection lost */
			case CONNECTION_LOST:
			default:
				sleep(60);
				echo "\nQUIT:$quit";
				echo "\nwhat next?\n";
			break;
		}
		/* quit message */
		if ($quit == USER_SHUTDOWN)	irc_send(':'.IRC_NICK.' QUIT :gotta go, fight club');

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
	global $nick;
	echo 'Joining channel...';
	irc_send(':'.$nick.' JOIN '.$channel);
	echo "ok\n";
	$joined = 1;
}

/* interpret irc messages */
function interpret_irc_message($sender, $msg, $private=0) {
	global $quit;
	global $nick;
	global $commands;
	global $modules;
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
		/* rename bot */
		case 'nick':
			if (is_admin($sender)) {
				echo 'new nick: '.$params."\n";
				$nick = $params;
				irc_send('NICK '.$nick);
			}
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
			return default_command($cmd, $params, $sender, $private);
			break;
	}
	return true;
}

function ircbot_global_handler($cmd, $params, $target = '', $private) {
}

/* module commands */
function default_command($cmd, $params, $target = '', $private) {
	global $modules, $commands;
	echo "default_command($cmd, $params, $target, $private)\n";
	foreach($commands as $command=>$func) {
		if ($cmd == $command) {
			echo "cmd == command\n";
			call_user_func($func, $params, $target, $private);
			return true;
		}
	}
	foreach($modules as $loaded) {
		if ($cmd == $loaded) {	
			echo "cmd == loaded\n";
			call_user_func($loaded.'_command', $params, $target, $private);
			return true;
		}
	}
	return false;
}

/* register command */
function ircbot_register_command($command, $function) {
	global $commands;
	echo "ircbot_register_command($command, $function)\n";
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
