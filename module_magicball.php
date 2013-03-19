<?php

/* module_magicball, magic 8 ball */

$answers = array();

function magicball_init() {
	irc_bot_echo("magicball_init module");
	/* answers were shamelessly copied from wikipedia */
	global $answers;
	$answers[] = 'It is certain';
	$answers[] = 'It is decidedly so';
	$answers[] = 'Without a doubt';
	$answers[] = 'Yes – definitely';
	$answers[] = 'You may rely on it';
	$answers[] = 'As I see it, yes';
	$answers[] = 'Most likely';
	$answers[] = 'Outlook good';
	$answers[] = 'Yes';
	$answers[] = 'Signs point to yes';
	$answers[] = 'Reply hazy, try again';
	$answers[] = 'Ask again later';
	$answers[] = 'Better not tell you now';
	$answers[] = 'Cannot predict now';
	$answers[] = 'Concentrate and ask again';
	$answers[] = 'Don\'t count on it';
	$answers[] = 'My reply is no';
	$answers[] = 'My sources say no';
	$answers[] = 'Outlook not so good';
	$answers[] = 'Very doubtful';
}


function magicball_command($string, $target='', $private) {
	global $answers;
	$send_answer = rand(0, count($answers)-1);
	irc_send_message($answers[$send_answer].'.', $target, $private);
}

?>
