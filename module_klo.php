<?php

/* module_klo, a klo geh vs meetings counter */

function klo_init() {
	echo "\ninit klo_module";
	klo_reset_counter();
	ircbot_register_for_global_listening( 'klo_listener_global' );
}

function klo_command( $string, $target='', $private=0 ) {

	echo "klo_command($string)\n";
	$user='';
	$input = explode(' ', $string);	
	$string=$input[0];
	switch ( $string ) {
		case 'reset':
			$user = isset($input[1]) ? $input[1] : 'all' ;
			klo_reset_counter($user);
			irc_send_message( "Zähler zurückgesetzt.", $target, $private );
			break;
		case 'show':
			$user = isset($input[1]) ? $input[1] : 'all' ;
			klo_print_stats($user, $target, $private );
			break;
		case 'k++':
		case 'klo+1':
			klo_increase_counter( 'k' , $target);
			klo_print_stats( 'all' , $target, $private );
			break;
		case 'm++':
		case 'mtg+1':
			klo_increase_counter( 'm' , $target);
			klo_print_stats( 'all', $target, $private );
			break;
		case 'help':
		case 'man':
		default:
			klo_print_help( $target, $private );
	}
}

/*
	interpret messages from chat and count klos or meetings
*/
function klo_listener_global( $sender, $msg ) {
	if ( preg_match( '/[kK]lo/', $msg ) ) {
		klo_increase_counter( 'k', $sender );
	}
	if ( preg_match( '/[mM]eeting/', $msg ) ) {
		klo_increase_counter( 'm', $sender );
	}
}

/*
	reset all individual counters
*/
function klo_reset_counter($user='all') {
	echo "\nreset all counters";
	
	if ($user === 'all') {
		foreach ( $GLOBALS['counter'] as $key => $value ) {
			foreach ( $value as $k => $v ) {
				$GLOBALS['counter'][$key][$k] = 0;
			}
		}
	}else{
		foreach ( $GLOBALS['counter'] as $key => $value ) {
			
			$GLOBALS['counter'][$key][$user] = 0;
			
		}
	}		
}

/*
	increase individual counter
*/
function klo_increase_counter( $what , $who ) {
	$GLOBALS['counter'][$what][$who]++;
}

/*
	print stats for all or for specific user
*/
function klo_print_stats( $user='all', $target='', $private=0 ) {
	irc_send_message( "Stats for $user:", $target, $private );
	
	if ( $user==='all' ) {
		$all_counts=array();
		// count all klos and meetings
		foreach ( $GLOBALS['counter'] as $key => $value ) {
			foreach ( $value as $k => $v ) {
				$all_counts[$key]+=$v;
			}
		}
		
		irc_send_message( "Klo ".$all_counts['k']." : ".$all_counts['m']." Meetings", $target, $private );
	}else {
		irc_send_message( "Klo ".$GLOBALS['counter']['k'][$user]." : ".$GLOBALS['counter']['m'][$user]." Meetings", $target, $private );
	}
}

/*
	print available commands
*/

function klo_print_help( $target='', $private=0 ) {
	$usage = array(
		"#wtf is it?",
		"Scans stream for \"klo\" or \"meeting\" and counts how much shit gets produced.",
		"#Commands:",
		"reset 			- reset ALL the counters",
		"show [user]	- show the current stats for [user]; default is 'all'",
		"klo+1,k++ 		- increase your klo count",
		"mtg+1,m++ 		- increase your meeting count",
		"help 			- show this message",
	);

	foreach ( $usage as $key => $value ) {
		irc_send_message( $value, $target, $private );
	}
}

?>
