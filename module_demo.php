<?php

/* module_demo, some tests */

function demo_init() {
	echo "\ndemo_init module";
	ircbot_register_command('dm', 'dm_custom');
	ircbot_register_for_global_listening('dm_listener_global');
	ircbot_register_for_private_listening('dm_listener_private');
}

function demo_command($string, $target='', $private=0) {
	irc_send_message('default command handler', $target, $private);
}

function dm_custom($string, $target='', $private=0) {
	irc_send_message('registered command handler', $target, $private);
}

function dm_listener_private($sender, $msg) {
	irc_send_message('private handler', $sender, 1);
}

function dm_listener_global($sender, $msg) {
	irc_send_message('global handler', $sender, 0);
}


?>
