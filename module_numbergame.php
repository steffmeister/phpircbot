<?php

/* module_demo, some tests */

$current_game = array();

function numbergame_init() {
	irc_bot_echo("numbergame_init module");
	ircbot_register_command('ng', 'ng_custom');
	ircbot_register_for_global_listening('ng_listener_global');
}

function numbergame_command($string, $target='', $private=0) {
	irc_send_message('default command handler', $target, $private);
}

function ng_custom($string, $target='', $private=0) {
	global $current_game;
	switch($string) {
		case 'new':
			$current_game['running'] = 1;
			$current_game['number'] = rand(1, 10);
			//irc_send_message('nummer:'. $current_game['number'], $target, 0);
			irc_send_message('Los gehts! Erratet die Nummer, 1-10!'.$score, $sender, 0);
			irc_bot_echo("Nummer ist ".$current_game['number']);
			break;
		case 'stop':
			$current_game['running'] = 0;
			break;
		case 'reset':
			$current_game['scores'] = array();
			break;
		case 'scores':
			foreach($current_game['scores'] as $nick=>$score) {
				irc_send_message($nick.': '.$score, $sender, 0);
			}
			break;
	}
	
	//irc_send_message('registered command handler', $target, $private);
}

function ng_listener_global($sender, $msg) {
	global $current_game;
	if ($current_game['running']) {
		if (is_numeric($msg)) {
			if ($msg == $current_game['number']) {
				$current_game['running'] = 0;
				if (!isset($current_game['scores'][$sender])) {
					$current_game['scores'][$sender] = 0;
				}
				$current_game['scores'][$sender]++;
				irc_send_message($sender.' hat die richtige Nummer erraten: '.$current_game['number'].'!', $sender, 0);
				irc_bot_echo("Nummer erraten");
			}
		}
	}
}


?>
