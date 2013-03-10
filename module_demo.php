<?php

/* module_demo, some tests */

/* each module needs an *_init() function
   use it to set up variables and such stuff */
function demo_init() {
	echo "\ndemo_init module";
	/* this will register a custom command */
	ircbot_register_command('dm', 'dm_custom');
	/* register a callback which is called when someone writes on the channel */
	ircbot_register_for_global_listening('dm_listener_global');
	/* register a callbick which is called when a private message is received */
	ircbot_register_for_private_listening('dm_listener_private');
}

/* this function is also required by every module */
function demo_command($string, $target='', $private=0) {
	irc_send_message('default command handler', $target, $private);
}

/* our custom command callback, target is the sender, eg the channel or a username */
/* private means if the message was received privately */
function dm_custom($string, $target='', $private=0) {
	irc_send_message('registered command handler', $target, $private);
}

/* our global message callback */
function dm_listener_global($sender, $msg) {
	irc_send_message('global handler', $sender, 0);
}

/* our private message callback */
function dm_listener_private($sender, $msg) {
	irc_send_message('private handler', $sender, 1);
}

?>
