<?php

/* module_klo, a klo geh vs meetings counter */

$klo_counter = 0;
$mtg_counter = 0;

function klo_init() {
	klo_reset_counter();
	klo_listen();
}

function klo_reset_counter() {
	global $klo_counter;
	global $mtg_counter;
	$klo_counter = 0;
	$mtg_counter = 0;
}

function klo_command($string, $target='', $private=0) {
	global $klo_counter;
	global $mtg_counter;
	echo "klo_command($string)\n";
	switch($string) {
		case 'reset':
			klo_reset_counter();
			irc_send_message("Zähler zurückgesetzt.", $target, $private);
			break;
		case 'show':
			irc_send_message("Klo ".$klo_counter." : ".$mtg_counter." Meetings", $target, $private);
			break;
		case 'k++':
		case 'klo+1':
			$klo_counter++;
			irc_send_message("Klo ".$klo_counter." : ".$mtg_counter." Meetings", $target, $private);
			break;
		case 'm++':
		case 'mtg+1':
			$mtg_counter++;
			irc_send_message("Klo ".$klo_counter." : ".$mtg_counter." Meetings", $target, $private);
			break;
	}
}

function klo_listen(){
	$line = trim(fgets($res));

	if (strpos($line, ' PRIVMSG '.IRC_CHANNEL.' ') !== false) {
		echo "Received message...\n";
		$sender = substr($line, 1, strpos($line, '!')-1);
		echo "From: ".$sender."\n";
		$msg = substr($line, strpos($line, ':', 2)+1);
		echo "Message: ".$msg."\n";
		if (strpos($msg, IRC_NICK) !== false) {
			echo "I was mentioned\n";
			if (substr($msg, 0, strlen(IRC_NICK)) == IRC_NICK) {
				$msg = substr($msg, strpos($msg, ' ') + 1);
				echo $msg;
				#interpret_irc_message($sender, $msg, 0);
			}
		}
	}
}

?>
