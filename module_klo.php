<?php

/* module_klo, a klo geh vs meetings counter */

$klo_counter = 0;
$mtg_counter = 0;

function klo_init() {
	echo "\ninit klo_module";
	klo_reset_counter();
	klo_listen();
}

function klo_reset_counter() {
	echo "\nreset klo and meeting counters";
	global $klo_counter;
	global $mtg_counter;
	$klo_counter = 0;
	$mtg_counter = 0;
}

function klo_command($string, $target='', $private=0) {
	global $klo_counter;
	global $mtg_counter;

	$usage = array(
		"#Commands:",
		"reset 	- reset the counters",
		"show 		- show the current stats",
		"klo+1,k++ - increase klo count",
		"mtg+1,m++ - increase meeting count",
		"help 		- show this message",
		);
		
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
		case 'help':		
		case 'man':	
		default:
			foreach ($usage as $key => $value) {
				irc_send_message($value, $target, $private);
			}
			
	}
}

function klo_listen(){
	
}

?>
