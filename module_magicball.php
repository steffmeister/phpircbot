<?php

$answers = array();

function magicball_init() {
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
	echo "m8b: counter".count($answers)."\n";
	$send_answer = rand(0, count($answers)-1);
	echo "m8b: $send_answer\n";
	echo "m8b: $answers[$send_answer]\n";
	irc_send_message($answers[$send_answer].'.', $target, $private);
}

?>
