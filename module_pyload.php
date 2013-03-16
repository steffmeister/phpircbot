<?php

/* module_pyload */

/* name of the python executable */
define('PYTHON', 'python2.5');
/* directory of pyload */
define('PYLOAD_PATH', '/share/MD0_DATA/.qpkg/pyload/');
/* my admin users */
define('MODULE_ADMINS', 'steffmeister');
/* temporary file - no need to change */
define('TEMP_FILE', 'ircpyload.tmp');

$module_admins = array();

$config_status = true;

function pyload_init() {
	global $module_admins, $config_status;
	echo "\npyload_init module\n";
	$config_status = true;
	if (!file_exists(PYLOAD_PATH.'pyLoadCli.py')) {
		echo "pyLoadCli.py not found!!\n";
		$config_status = false;
	}
	$module_admins = explode(',', MODULE_ADMINS);
}

function pyload_command($string, $target='', $private=0) {
	global $module_admins, $config_status;
	$ok = false;
	echo "my admins are\n";
	print_r($module_admins);
	foreach($module_admins as $username) {
		echo "Comparing $username with $target...\n";
		if ($username == $target) $ok = true;
	}
	if (!$ok) {
		irc_send_message('Access denied.', $target, $private);
		return false;
	}
	if (!$config_status) {
		irc_send_message('Configuration error.', $target, $private);
		return false;
	}
	//irc_send_message('default command handler', $target, $private);
	if (strpos($string, ' ') !== false) {
		$param = substr($string, strpos($string, ' ')+1);
		$string = substr($string, 0, strpos($string, ' '));
		echo "param: '$param'\n";
		echo "cmd: '$string'\n";
	}
	switch($string) {
		case 'add':
			if ($param != '') {
				if (preg_match('~^(http|ftp)(s)?\:\/\/((([a-z0-9\-]*)(\.))+[a-z0-9]*)($|/.*$)~i', $param)) {
					exec(PYTHON.' '.PYLOAD_PATH.'pyLoadCli.py add ircbotpyload '.$param.' > '.TEMP_FILE);
					if (send_temp_file($target, $private) == '') {
						irc_send_message('Added.', $target, $private);
					}
				} else {
					irc_send_message('Does not look like a valid URL.', $target, $private);
				}
			} else {
				irc_send_message('Parameter missing.', $target, $private);
			}
			break;
		case 'status':
			exec(PYTHON.' '.PYLOAD_PATH.'pyLoadCli.py status > '.TEMP_FILE);
			send_temp_file($target, $private);
			break;
			
	}
}

function send_temp_file($target, $private) {
	$content = '';
	if (file_exists(TEMP_FILE)) {
		$fh = fopen(TEMP_FILE, 'r');
		if ($fh !== false) {
			while(!feof($fh)) {
				$line = fgets($fh);
				$content .= $line;
				irc_send_message($line, $target, $private);
			}
		}
		fclose($fh);
		unlink(TEMP_FILE);
	}
	return $content;
}

?>
