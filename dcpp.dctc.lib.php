<?php

function get_dctc_link() {
	$socket_dir = '/tmp/.dctc';
	$dir_name  = $socket_dir."/running";
	$dir      = opendir($dir_name);
	$basename  = basename($dir_name);
	$fileArr  = array();
	while($file_name = readdir($dir)){
		if(!eregi(".udp|.userinfo|.done",$file_name)) {
			$fName = "$dir_name/$file_name";
			$fTime = filemtime($fName);
			$fileArr[$file_name] = $fTime;
		}
	}
	return($fName);
}

function dctc_socket() {
	if ( ! function_exists(socket_create) ) {
		die("<html><LINK REL=StyleSheet HREF='style.css.php' TYPE='text/css'><body bgcolor=#ffffff></body><b>PHP without Sockets support</b><br><i>compile with --enable-sockets option</i></html>");
	}
	$sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
	$dclink = get_dctc_link();
	if ( ! socket_connect($sock, $dclink)) {
		return false;
	}
	return $sock;
}


function dctc_send($sock,$strsend) {
	socket_write($sock, "$strsend", strlen($strsend));
	return true;
}
function have($buffer,$string) {

	if (!strncmp($buffer, $string, strlen($string))) {
		return true;
	} else {
		return false;
	}
}


?>
