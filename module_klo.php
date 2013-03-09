<?php

/* module_klo, a klo geh vs meetings counter */

$klo_counter = 0;
$mtg_counter = 0;

$counter = array('k'=>0,'m'=>0);

function klo_init() {
	echo "\ninit klo_module";
	klo_reset_counter();
	ircbot_register_for_global_listening( 'klo_listener_global' );
}

function klo_command( $string, $target='', $private=0 ) {

	echo "klo_command($string)\n";
	switch ( $string ) {
		case 'reset':
			klo_reset_counter();
			irc_send_message( "Zähler zurückgesetzt.", $target, $private );
			break;
		case 'show':
			klo_print_stats( $target, $private );
			break;
		case 'k++':
		case 'klo+1':
			klo_increase_counter( 'k' );
			klo_print_stats( $target, $private );
			break;
		case 'm++':
		case 'mtg+1':
			klo_increase_counter( 'm' );
			klo_print_stats( $target, $private );
			break;
		case 'help':
		case 'man':
		default:
			klo_print_help($target, $private);
	}
}

function klo_listener_global( $sender, $msg ) {	
	if (preg_match('/[kK]lo/', $msg)) {
		klo_increase_counter('k');
	}
	if (preg_match('/[mM]eeting/', $msg)) {
		klo_increase_counter('m');	
	}
}

function klo_reset_counter() {
	echo "\nreset all counters";
	if(!empty($GLOBALS['counter'])){
		foreach ( $GLOBALS['counter'] as $k=>$v ) {
			$GLOBALS['counter'][$k] = 0;
		}	
	}	
}

function klo_increase_counter( $what ) {
	$GLOBALS['counter'][$what]++;
}

function klo_print_stats( $target='', $private=0 ) {
	irc_send_message( "Klo ".$GLOBALS['counter']['k']." : ".$GLOBALS['counter']['m']." Meetings", $target, $private );

}

function klo_print_help( $target='', $private=0) {
	$usage = array(
		"#Commands:",
		"reset 	- reset the counters",
		"show 		- show the current stats",
		"klo+1,k++ - increase klo count",
		"mtg+1,m++ - increase meeting count",
		"help 		- show this message",
	);

	foreach ( $usage as $key => $value ) {
		irc_send_message( $value, $target, $private );
	}
}

?>
