<?php
//echo dcspecialchars("test");

function seconds_format($s){
	$d = floor($s/(24*60*60));
	$s = $s - ($d*24*60*60);
	$h = floor($s/(60*60));
	$s = $s - ($h*60*60);
	$m = floor($s/60);
	$s = $s - ($m*60);
	return $d.'d '.lzero($h).':'.lzero($m).':'.lzero($s);
}
function microtime_float(){ // PHP4, in PHP5 use microtime(true)
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
function lzero($i, $c = 2){
	$s = strlen($i);
	//echo 'STRLEN OF '.$i." = ".$s."\n";
	if ($c > $s) return str_repeat("0", $c - $s).$i; else return $i;
}
function bytes2size($b, $l = 0){
    $s = array('b', 'Kb', 'Mb', 'Gb', 'Tb');
    if (($b > 1024) && isset($s[$l+1])) return bytes2size($b/1024, $l+1);
    return number_format($b, 2, '.', ' ').' '.$s[$l];
}

function array_delete( $value, $array)
{
	$array = array_diff( $array, array($value) );
	return $array;
}
function multicharset_msg($utfmsg){
	$msg = "\r\nutf-8:".$utfmsg;
	$msg .= "\r\nwindows-1251:".iconv('utf-8', 'cp1251', $utfmsg);
	return $msg;
}
function dcspecialchars($s){
	//return(($c == 0) || ($c == 5) || ($c == 124) || ($c == 96) || ($c == 126) || ($c == 36));
	return preg_replace(
	array("#\\x0#ims","#\\x5#ims","#\\x124#ims","#\\x96#ims","#\\x126#ims","#\\x136#ims"), 
	array('&#0;','&#5;','&#124;','&#96;','&#126;','&#136;'), 
	$s
	);
}
class dc_client{
	var $logged_in = false;
	var $kicked = false;
	var $disconnect_after = 0;
	var $password_requested = false;
	var $server_waiting_mypass = false;
	var $nick = '';
	var $password = '';
	var $ip;
	var $port;
	var $id = 0;


	var $lock_sended = false;
	var $myinfo_sended = false;
	var $myinfo_checked = false;
	var $lock_ts = 0;
	var $write_ts = 0; // stays for a timeout check
	var $key_sended = false; // $Key sended
	var $key = false;
	var $hello = false;
	var $level = 0; // normal user
	var $client_version = '';
	//2-desc 3--- 4-Conn 5-email 6-sharesize
	var $desc = '';
	var $conn = '';
	var $connflag = 1;
	var $email = '';
	var $sharesize = 0;
	var $supports = array();
	var $oplisted = false;
	var $know_hubname = false;

	var $charset = 'CP1251';

}
class dc_hub_server{
	var $socket;	/* ++ socket */
	var $hubserver = '0.0.0.0';	/* ++ hub server hostname/ip, 0.0.0.0 will listen on all interfaces */
	var $hubport = 4111;	/* ++ hub server port 411 - standard DC 4111 default for Verlihub*/
	var $maxsockets = 10000;
	var $connected = false;
	var $parent = null;
	function dc_hub_server(&$parent = null){
		$this->parent =& $parent;
	}
	function connect(){
		if (!$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)){
			echo '[SERVER NOT CREATED] '.socket_strerror(socket_last_error());
			$this->parent->__logMessage('[SERVER NOT CREATED] '.socket_strerror(socket_last_error()) , DLOG_ERROR);
			return false;
		}
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1); // BEFORE BIND !!!!
		socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1);
		if (!(@socket_bind($this->socket, $this->hubserver, $this->hubport))){
			echo '[SERVER BIND ERROR] '.socket_strerror(socket_last_error());
			$this->close();
			$this->parent->__logMessage('[SERVER BIND ERROR] '.socket_strerror(socket_last_error()) , DLOG_ERROR);
			return false;
		}
		socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1);
		//$this->socket
		socket_set_nonblock($this->socket);
		if (!(@socket_listen($this->socket, $this->maxsockets))){
			echo '[SERVER LISTEN ERROR] '.socket_strerror(socket_last_error());
			$this->close();
			$this->parent->__logMessage('[SERVER LISTEN ERROR] '.socket_strerror(socket_last_error()) , DLOG_ERROR);
			return false;
		}
		$this->connected = true;
		return true;
	}
	function incoming_socket(){
		return socket_accept($this->socket);
	}
	function send($buf){
		return socket_write($this->socket, $buf, strlen($buf));
	}
	function buf(){
		$read = array($this->socket);
		$except = array($this->socket);
		$write = array($this->socket);
		if (socket_select($read, $write, $except, 0, 0) !== false) {
			if (in_array($this->socket, $read)) {
				if ($data = socket_read($this->socket, 1024, PHP_BINARY_READ)) {
					return $data;
				}
			}
			if (in_array($this->socket, $write)) {
				socket_write($this->socket, '', 0);
				/// We're ready to send commands
			}
			if (in_array($this->socket, $except)) {
				$this->disconnect();
			}
			$streams = array($this->socket);
		}else{
			//return "[ERROR] ".socket_strerror(socket_last_error());
		}
		return '';
	}
	function close(){
		$this->disconnect();
	}
	function disconnect(){
		if (is_resource($this->socket)) socket_close($this->socket);
		$this->connected = false;
		//sleep(5);
	}
	function __destruct(){
		
		$this->disconnect();
	}
}

function dchub_restart(){
	exec('/usr/local/bin/php '.NX_PATH.'dchub.daemon.php');
}


?>
