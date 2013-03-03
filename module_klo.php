<?php

/* module_klo, a klo geh vs meetings counter */

$klo_counter = 0;
$mtg_counter = 0;

function klo_init() {
	klo_reset_counter();
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
		case 'klo+1':
			$klo_counter++;
			break;
		case 'mtg+1':
			$mtg_counter++;
			break;
	}
}

?>
