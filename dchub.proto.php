<?php
// РУССКИЙ
require_once(realpath(dirname(__FILE__))."/daemon.class.php");
require_once(realpath(dirname(__FILE__))."/dchub.lib.php");
require_once(realpath(dirname(__FILE__))."/dc.const.php");
/*
// error handler function
----------------------------------------------------------------
LDC++
----------------------------------------------------------------
$Supports UserCommand NoGetINFO NoHello UserIP2 TTHSearch GetZBlock |$Key ASD
$ValidateNick w999d|
$MyPass **********|
<w999d> Mahoro: PUBLIC MESSAGE|
$To: Mahoro From: w999d $<w999d> PRIVATE MESSAGE|
$RevConnectToMe w999d W.Ed.|
$GetINFO W.Ed. w999d|$GetINFO w999d w999d|$GetINFO Lain w999d|$GetINFO Alice w999d|$GetINFO Chii w999d|$GetINFO Mahoro w999d|


*/


class BSDC_Hub_Prototype extends RX_Daemon { // MUST SUPPORT ONLY BASIC CHAT FUNCTIONS
	public $__includes_path = '';
	// HUB INFO FOR BOTS++
	public $hub_address = 'dc.animeforge.ru'; //If the hub address is 127.0.0.1, the Hublist.org pinger will remove the hub from its database.
	public $hub_port = 4111;
	public $hub_description = '';
	public $hub_default_redirect = 'dc.animeforge.ru:4111'; // TRASH HUB
	public $hub_max_users = 6000;
	public $hub_min_share_bytes = 0;
	public $hub_min_share_redirect = 'dc.animeforge.ru:4111'; // TRASH HUB
	public $hub_min_slots = 1;
	public $hub_max_open_hubs = 5;
	public $hub_type = 'BSDC phpHUB daemon'; //Hub type gives information about the hub software and script which gave the information.
	public $hub_version = '1.2';
	public $hub_copyrights = 'BSDC phpHUB daemon version: 1.2 © 2006-2007 produced by W.Ed., Olamedia.Ru'; //Hub type gives information about the hub software and script which gave the information.
	public $hub_owner_login = 'olamedia@gmail.com'; //Hubowner login is meant to help hubowners to edit information about their hub directly from the hublist portal It is usually an email address where the account/password information should be sent.
	/*
	These commands are supported by the following:
	GeneralBot >= 0.24 (NMDCH Script)
	PtokaX
	SDCH
	Verlihub
	Yhub
	PHPDC-Hub
	DB Hub since version 0.314
	HubRules >= 1.11 (DCH++ Plugin)
	HubList >= 0.1.0 (ODC(#)H Plugin)
	*/
	// HUB INFO FOR BOTS--
	public $dc_server = null;
	public $log_filename = '/tmp/DCHub/DCHub.log';
	public $last_heartbeat = 0;
	public $watch = array(); // array of sockets
	public $clients = array(); // array of sockets
	public $writebuf = array(); //
	public $readbuf = array();
	public $client_data = array();
	public $usernicks = array(); // who have an ip
	public $botnicks = array(); // internal bots, other way to get info
	public $levelnicks = array();// $levelnicks[$level] - all nicks by levels
	public $temp_nicks_reserved = array(); // not get pass yet
	
	public $delayed = array();// Delayed disconnect support
	public $debug = false;
	public $txt_beforelogin = 'HERE WILL BE LOGIN HELP MESSAGE';
	public $txt_motd = '';
	public $txt_help = '';
	/*
	0 - unregistered
	1 - registered
	2 - vip
	3 - op
	4 - chief op
	5 - admin op
	10 - master user
	14 - external bot???
	15 - internal bot
	*/
	public $hub_name = 'HUBNAME';
	public $hub_topic = 'Anime, OST, AMV, Manga, anime-related games and other stuff';
	public $hub_security_bot_name = 'Lain';
	public $opchat_bot_name = 'Alice';
	public $spider_bot_name = 'Chii';
	public $hub_news_bot_name = 'Mahoro';
	public $bot_info = array();

	public $start_ts = 0;
	public $ai = 0;
	/*
	+  TCP (port) in: 0b  +
	+  TCP (port) out: 0b +
	+  \$MyINFO: 0         +
	+  \$Sr: 0             +
	+  \$ConnecToMe: 0     +
	+  \$RevConnecToMe: 0  +
	+  Cur/peak/max users +
	*/
	public $tcpin = 0;
	public $tcpout = 0;
	public $sr_myinfo = 0;
	public $sr_sr = 0;
	public $sr_connecttome = 0;
	public $sr_revconnecttome = 0;
	public $ss_myinfo = 0;
	public $ss_sr = 0;
	public $ss_connecttome = 0;
	public $ss_revconnecttome = 0;
	public $s_peak_users = 0;
	public $s_max_users = 0;
	
	var $act_ts = 0;
	var $act_waiting = array();
	var $search_waiting = array();
	var $leak = array();
	function BSDC_Hub_Prototype($hub_host = 'dc.animeforge.ru', $hub_port = 4111){
		register_shutdown_function(array(&$this, 'shutdownhub'));
		$this->init($hub_host, $hub_port);
		//$this->register_bots();
		$this->__logMessage('BSDC_Hub_Prototype Created!', DLOG_TO_CONSOLE);
	}
	function __TRASHER(){ // Free Memory
		foreach ($this->clients as $cid => $client){
			if (!isset($this->watch[$cid])){
				$this->__disconnect($cid);
			}
		}
	}
	function leak($l){
		$this->leak[] = str_repeat("W", $l);
	}
	function __disconnect($cid){ // DISCONNECT CLIENT (SILENTLY)
		@socket_shutdown($this->clients[$cid], 2);
		@socket_close($this->clients[$cid]);
		$this->__debug_msg('[_DISCONNECT_] '.$this->clients[$cid]->nick.' '.$cid);
		unset($this->clients[$cid]);
		unset($this->writebuf[$cid]);
		unset($this->readbuf[$cid]);
		unset($this->client_data[$cid]);
		if (isset($this->usernicks[$cid])){
			$quitnick = $this->usernicks[$cid];
			unset($this->usernicks[$cid]);
			foreach ($this->usernicks as $xcid => $nick) {
				$this->write($xcid, "\$Quit ".$quitnick."|");
			}
		}
		unset($this->levelnicks[$this->client_data[$cid]->level][$cid]);
		unset($this->delayed[$cid]); // delayed disconnects cancel
		unset($this->act_waiting[$cid]); // delayed act
		unset($this->search_waiting[$cid]); // delayed search
		unset($this->watch[$cid]);
	}
	function init($hub_host = 'dc.animeforge.ru', $hub_port = 4111){
		define("RXDaemonLogFilename", '/tmp/DCHub/'.$hub_host.'.'.$hub_port.'.error.log');
		$this->__logMessage('BSDC_Hub_Prototype::init()', DLOG_TO_CONSOLE);
		parent::RX_Daemon();
	/*	$this->__logMessage('FATALERROR::start(^_^)', DLOG_TO_CONSOLE);
		try {
		// undefined constant, generates a warning
		$t = I_AM_NOT_DEFINED;
		// define some "vectors"
		$a = array(2, 3, "foo");
		$b = array(5.5, 4.3, -1.6);
		$c = array(1, -3);

		$d = scale_by_log($a, -2.5);// ^_^
		} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

		$this->__logMessage('FATALERROR::finish(^_^)', DLOG_TO_CONSOLE);*/
		$this->__includes_path = realpath(dirname($this->__file).'/../').'/';
		$this->__logMessage('BSDC_Hub_Prototype init() starting', DLOG_TO_CONSOLE);
		$this->hub_address = $hub_host;
		$this->hub_port = $hub_port;
		if (!is_dir('/tmp')) mkdir('/tmp');
		if (!is_dir('/tmp/DCHub')) mkdir('/tmp/DCHub');
		chmod('/tmp/DCHub', 0777);
		if (!is_dir('/tmp/DCHub')) die();
		$fp = fopen($this->log_filename, 'a');
		fclose($fp);
		chmod($this->log_filename, 0777);
		$this->pidFileLocation = '/tmp/DCHub/dchub.pid';
		//$this->register_bots();
		$this->__logMessage('BSDC_Hub_Prototype init() finished', DLOG_TO_CONSOLE);
	}
	function update_refresh_ts($cid){
		return false;// disable
	}
	function client_desc($cid){
		$desc = '';
		$desc .= '[guest] ';
		$desc .= $this->client_data[$cid]->desc;
		return $desc;
	}
	function user_hub_commands($cid, $message){
		return false;
	}	
	function __stop(){
		$this->__logMessage('BSDC_Hub_Prototype::__stop()', DLOG_TO_CONSOLE);
		$this->__isRunning = false;
		$this->__running_in_console = true;
		if (is_object($this->dc_server)) $this->dc_server->close(); // closing accept socket
		$this->__disconnect_all(); // closing client sockets
		parent::__release_RXDaemon();
	}
	function __destruct(){
		$this->__logMessage('Destruct Called!!!', DLOG_TO_CONSOLE);
		$this->__logMessage('BSDC_Hub_Prototype::__destruct()', DLOG_TO_CONSOLE);
		$this->__stop();
	}
	function shutdownhub(){
		$this->__logMessage('Shutdown Called!!!', DLOG_TO_CONSOLE);
		$this->__logMessage('BSDC_Hub_Prototype::shutdownhub()', DLOG_TO_CONSOLE);
		$this->__stop();
	}
	function restarthub(){$this->shutdownhub();}
	
	
	
	
	function __doTask(){
		static $i = 0;
		//usleep(2000000); = 2 sec
		usleep(10000); 
		$i++;
		// MAIN LOOP
		if ($this->dc_server->connected){ // for outline, socket reads
			$this->__listen();
			$this->__heartbeat_test();
			foreach ($this->delayed as $dcid){ // Delayed disconnects
				$this->__check_delayed($dcid);
			}
			$this->__act_test();
		}else{
			$this->__logMessage('[HUB NOT CONNECTED]', DLOG_TO_CONSOLE);
			if ($this->dc_server == null){
				$this->dc_server =& new dc_hub_server($this);
				$this->dc_server->hubport = $this->hub_port;
				$this->__logMessage('Starting hub...', DLOG_TO_CONSOLE);
				if ($this->dc_server->connect()){
					$this->__logMessage('Started!', DLOG_TO_CONSOLE);
					$this->start_ts = time();
				}else{
					$this->__logMessage('[HUB START ERROR] Trying bind to port '.$this->dc_server->hubport, DLOG_TO_CONSOLE);
					$this->__logMessage('[HUB START ERROR] Can\'t start! '.socket_strerror(socket_last_error()), DLOG_TO_CONSOLE);
					$this->__logMessage('Can\'t start! [HUB START ERROR] '.socket_strerror(socket_last_error()), DLOG_TO_CONSOLE);
					/*echo '<pre>';
					system('netstat -lpn');
					echo '</pre>';*/
					exit(0);
				}
			}
		}
	}
	function __socket_error($socket, $cid){
		@$this->__debug_msg('[SOCKETS DEBUG] '.socket_strerror(socket_last_error($socket)));
		$this->__disconnect($cid);
	}
	function __listen(){ // MAIN READ-WRITE LOOP, ACT(), PARSE() CALLS
		$read = array($this->dc_server->socket);
		if ($num_changed = socket_select($read, $write = null, $except = null, 0, 10)) {
			// HUB SOCKET INCOMING CONNECTION
			$this->__debug_msg('[ACCEPT] ');
			$this->__accept_connection();
		}
		if (count($this->watch)){
			$read = $this->watch;
			$except = $this->watch;
			$write = $this->watch;
			if ($num_changed = socket_select($read, $write, $except, 0, 10)) {
				//$this->__logMessage('[ RWE '.count($read).' '.count($write).' '.count($except).' ] '.$num_changed, DLOG_ERROR);
				//$this->__logMessage('[SOCKETS DEBUG] '.socket_strerror(socket_last_error()), DLOG_ERROR);
				if (count($read)){
					foreach ($read as $socket) {
						$cid = array_search($socket, $this->clients);
						// CLIENT SOCKET
						//$this->__logMessage('[CLIENT R '.$socket.'] ', DLOG_ERROR);
						if ($data = socket_read($socket, 1024, PHP_BINARY_READ)) {
							if ($data !== ''){
								//$this->__logMessage('[CLIENT R '.$socket.' DATA] '.$data, DLOG_ERROR);
								$this->__c_read($cid, $data);
								$this->tcpin += strlen($data);
								$this->__parse($cid);
							}else{
								//$this->__disconnect($cid);
							}
						}else{
							//$this->__disconnect($cid);
						}
						if (!is_resource($socket) || socket_last_error($socket)) $this->__socket_error($socket, $cid);
					}
				}
				if (count($write)){
					foreach ($write as $socket) {
						$cid = array_search($socket, $this->clients);
						/// We're ready to send commands
						$this->__act_shedule($cid);
						if (!$this->__write_flush($cid)){
							//socket_write($socket, '|', 1);
						}
						if (!is_resource($socket) || socket_last_error($socket)) $this->__socket_error($socket, $cid);
					}
					
				}
				if (count($except)){
					foreach ($except as $socket) {
						$cid = array_search($socket, $this->clients);
						$this->__logMessage('[CLIENT E '.$socket.'] ', DLOG_ERROR);
						$this->__disconnect($cid);
					}
					if (!is_resource($socket) || socket_last_error($socket)) $this->__socket_error($socket, $cid);
				}
			}
		}
	}
	function __act_shedule($cid){
		if (!isset($this->client_data[$cid])) return false;
		// Next check if act is needed at all ))
		if (!$this->client_data[$cid]->logged_in ||	!$this->client_data[$cid]->myinfo_checked){
			$this->act_waiting[$cid] = $cid;
		}
	}
	function __act_test(){
		if (!count($this->act_waiting)) return false;
		if (microtime_float(true) < $this->act_ts) return false;
		$this->act_ts = microtime_float(true) + 1; // not less than 0.5 second!
		//$this->leak(10000000);
		foreach($this->act_waiting as $cid){
			$this->__act($cid);
			unset($this->act_waiting[$cid]);
		}
	}
	function __act($cid){ // SOME REQUIRED ACTIONS
		if (!isset($this->client_data[$cid])) return false;
		if (!($this->client_data[$cid]->lock_sended)){
			$this->__s2c_lock($cid);
		}elseif (!($this->client_data[$cid]->key_sended)){
			// WAITING FOR KEY
			if ((time() - $this->client_data[$cid]->lock_ts) > 60){ // timeout after $Lock = 1 minute
				// On Timeout
				$buf = '<'.$this->hub_security_bot_name.'> Error 401: "Key" Timeout.|';
				$this->write($cid, $buf);
				$this->__write_flush($cid);
				$this->__disconnect($cid);
			}
		}elseif (!($this->client_data[$cid]->know_hubname)){
			$this->__s2c_hubname($cid);
			$this->__s2c_hubtopic($cid);
			$this->__s2c_login_help($cid);
			$this->client_data[$cid]->know_hubname = true;
			$this->write($cid, '<'.$this->hub_security_bot_name.'> '.$this->hub_copyrights.'|');//.$buf
		}elseif (!$this->client_data[$cid]->logged_in){
			$this->__login($cid);
		}elseif(!$this->client_data[$cid]->myinfo_checked){
			if ($this->client_data[$cid]->myinfo_sended){
				$this->__check_myinfo($cid);
			}
		}else{
		}
	}
	function __accept_connection(){ // ACCEPTING INCOMING CONNECTION
		if ($socket = socket_accept($this->dc_server->socket)){
			$this->ai++;
			$cid = $this->ai;//md5(uniqid(null, true));//$this->ai;
			socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
			socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_set_timeout($socket, 2);
			socket_set_nonblock($socket);
			$this->watch[$cid] = $socket;
			$this->clients[$cid] = $socket;
			$this->writebuf[$cid] = '';
			$this->readbuf[$cid] = '';
			$this->client_data[$cid] =& new dc_client();
			$this->__delayed_disconnect($cid, 60*10); // Shedule disconnect, if not providing password
			$this->__debug_msg('[SERVER CHILD SOCKET RECEIVED] ');
			socket_getpeername($socket, $this->client_data[$cid]->ip, $this->client_data[$cid]->port);
			$ip = $this->client_data[$cid]->ip;
			ob_start();
			var_dump($this->watch);
			$dump = ob_get_contents();
			ob_end_clean();
			$ip = ' var '.$ip.' at '.__LINE__.'';
			//$this->__debug_msg('[SERVER $this->watch DUMP] '.$dump);
			$this->__debug_msg('[SERVER CLIENT ADDED (S='.count($this->watch).' C='.count($this->clients).' A='.$cid.')] '.$socket.' ('.$ip.')');
			$this->__s2c_lock($cid); // Hub Welcome message
		}
		return $cid;
	}
	function __disconnect_all(){ // DISCONNECT ALL CLIENTS (ON SHUTDOWN)
		foreach ($this->usernicks as $cid => $nick){
			$this->remove_client($cid);
		}
	}
	function __delayed_disconnect($cid, $timeout = 3){
		$this->client_data[$cid]->disconnect_after = time()+$timeout;// disconnect after 10 seconds
		$this->client_data[$cid]->kicked = true;
		$this->delayed[$cid] = $cid;
	}
	function __delayed_disconnect_reset($cid){
		$this->client_data[$cid]->disconnect_after = 0;// disconnect after 10 seconds
		$this->client_data[$cid]->kicked = false;
		unset($this->delayed[$cid]);
	}
	function __check_delayed($cid){
		if ($this->client_data[$cid]->kicked){
			if ($this->client_data[$cid]->disconnect_after < time()){
				$this->__disconnect($cid);
			}
		}
	}
	function __debug_msg($msg){
		if ($this->debug) $this->__write_to_minlevel(10, $this->hub_security_bot_name, $msg);
		$this->__logMessage($msg."\r\n");
	}
	function __c_read($cid, $buf){ // LOW-LEVEL READ
		$this->readbuf[$cid] .= $this->__c2s($cid, $buf);
	}
	function __c_write($cid, $buf){ // LOW-LEVEL WRITE
		$this->writebuf[$cid] .= $this->__s2c($cid, $buf);
	}
	function __s2c_lock($cid){ // $Lock
		if ($this->client_data[$cid]->lock_sended) return false;
		/*
		$Lock EXTENDEDPROTOCOL_verlihub Pk=version0.9.8c|
		<Lain> This Hub is running version 0.9.8c (Tue Mar  8 11:00:00 CET 2005) of VerliHub (RunTime:5hours 27min ).|
		*/
		$buf = '$Lock EXTENDEDPROTOCOL_olamedia Pk=version0.9.8c|';
		$this->write($cid, $buf);
		$this->__plus_hubstats($cid);
		$this->client_data[$cid]->lock_ts = time();
		$this->client_data[$cid]->lock_sended = true;
		$buf = '<'.$this->hub_news_bot_name.'> Chii.... |';
		$this->write($cid, $buf);
	}
	function __plus_hubstats($cid){
		$buf = '<'.$this->hub_security_bot_name.'> This Hub is running version '.$this->hub_version.' of '.$this->hub_type.' '.html_entity_decode('&copy;').' 2006-2007 W.Ed. Olamedia.Ru (Uptime: '.(time() - $this->start_ts).' sec).|';
		$buf .= '<'.$this->hub_security_bot_name.'> '.$this->hub_copyrights.'|';
		$buf .= '<'.$this->hub_security_bot_name.'> Current user count: '.count($this->clients).' after '.seconds_format(time() - $this->start_ts).' uptime|';
		$buf .= '<'.$this->hub_security_bot_name.'> Current sharesize is: '.bytes2size($this->__hub_sharesize()).'|';
		$this->write($cid, $buf);
	}
	function __s2c_hubname($cid){ // $HubName
		$buf = "\$HubName ".$this->hub_name."|";
		$this->write($cid, $buf);
	}
	function __s2c_hubtopic($cid){ // $HubTopic
		$buf = "\$HubTopic ".$this->hub_topic."|";
		$this->write($cid, $buf);
	}
	function __s2c_motd($cid){ // +motd
		$buf = '<'.$this->hub_news_bot_name.'> '.$this->charset_msg($cid, $this->txt_motd).'|';
		$this->write($cid, $buf);
	}
	function __s2c_help($cid){ // +motd
		$buf = '<'.$this->hub_security_bot_name.'> '.$this->charset_msg($cid, $this->txt_help).'|';
		$this->write($cid, $buf);
	}
	function __s2c_login_help($cid){ // +loginhelp
		$buf = '';
		$buf .= '<'.$this->hub_security_bot_name.'> '.$this->charset_msg($cid, $this->txt_beforelogin).'|';
		$this->write($cid, $buf);
	}
	function __s2c_nicklist($cid){ // $NickList
		$this->write($cid, "\$NickList ".implode("\$\$", array_merge($this->usernicks, $this->botnicks))."\$\$"."|");
		// Next is for NoHello support
		/*
		This indicates that the client doesn't need either $Hello or $NickList to be sent to it when connecting to a hub.
		To populate its user list, a $MyINFO for each user is enough.
		$Hello is still accepted, for adding bots to the user list.
		DC++ still sends a $GetNickList to indicate that it is interested in the user list.
		During login, hubs must still send $Hello after $ValidateNick to indicate that the nick was accepted.
		*/
		foreach ($this->usernicks as $ocid => $othernick) {
			$desc = $this->client_desc($ocid);
			$buf = '$MyINFO $ALL '.$othernick.' '.$desc.'$ $'.$this->client_data[$ocid]->conn.''.$this->client_data[$ocid]->connflag.'$'.$this->client_data[$ocid]->email.'$'.$this->client_data[$ocid]->sharesize.'$|';
			$this->write($cid, $buf);
		}
	}
	function __s2c_oplist($cid){ // $OpList
		for ($level = 3; $level < 16; $level++){
			if (isset($this->levelnicks[$level]) && is_array($this->levelnicks[$level])){
				$this->write($cid, "\$OpList ".implode("\$\$", $this->levelnicks[$level])."\$\$"."|");
			}
		}
	}
	function __s2c_botlist($cid){ // $BotList
		//$BotList <bot1>$$<bot2>$$<bot3>$$...|
		$this->write($cid, "\$BotList ".implode("\$\$", $this->botnicks)."\$\$"."|");
		foreach ($this->botnicks as $ocid => $othernick) {
			/// Interface to bots desc
			$bot_nick = $othernick;
			if (isset($this->bot_info[$bot_nick])){
				$buf = '$MyINFO $ALL '.$bot_nick.' '.$this->bot_info[$bot_nick]->desc.'$ $'.$this->bot_info[$bot_nick]->conn.''.$this->bot_info[$bot_nick]->connflag.'$'.$this->bot_info[$bot_nick]->email.'$'.$this->bot_info[$bot_nick]->sharesize.'$|';
				$this->write($cid, $buf);
			}
		}
	}	
	function __s2c_hubinfo($cid){ // $HubINFO
		//$HubINFO <hub name>$<hub address:port>$<hub description>$<max users>$
		//<min share in bytes>$<min slots>$<max hubs>$<hub type>$<hubowner login>|
		$buf = "\$HubINFO ".$this->hub_name."\$".$this->hub_address.":".$this->hub_port."\$".$this->hub_description."\$".
		"".$this->hub_max_users."\$".$this->hub_min_share_bytes."\$".$this->hub_min_slots."\$".$this->hub_max_open_hubs."\$".$this->hub_type."\$".$this->hub_owner_login."|";
		$this->write($cid, $buf);
		$this->client_data[$cid]->know_hubname = true;
	}
	function __s2c_hello($cid, $nick){
		$this->write($cid, "\$Hello ".$nick."|");
	}
	function __s2c_validatedenide($cid, $nick, $reason){
		$this->write($cid, "\$ValidateDenide ".$nick."|");
		$this->write($cid, "<".$this->hub_security_bot_name."> ".$reason."|");
		$this->client_data[$cid]->nick = ''; // Clear nick
	}
	function __s2c_badpass($cid){
		$this->write($cid, "\$BadPass|");
		$this->write($cid, "<".$this->hub_security_bot_name."> Incorrect password!|");
		$this->client_data[$cid]->server_waiting_mypass = false;
		$this->__s2c_getpass($cid);
	}
	function __s2c_getpass($cid){
		if ($this->client_data[$cid]->server_waiting_mypass) return false;
		$nick = $this->client_data[$cid]->nick;
		$this->write($cid, "<".$this->hub_security_bot_name."> This nick is registered: ".$nick.", password needed|");
		$this->write($cid, "\$GetPass|");
		$this->client_data[$cid]->server_waiting_mypass = true;
		$this->client_data[$cid]->password_requested = true;
	}
	function __s2c_forcemove($cid, $sender_nick, $hub_uri, $reason = 'not specified'){
		/*
		$OpForceMove $Who:<victimNick>$Where:<newIp>$Msg:<reasonMsg>
		$ForceMove <newIp>
		$To: <victimNick> From: <senderNick> $<<senderNick>> You are being re-directed to <newHub> because: <reasonMsg>
		*/
		//echo 'ForceMove';
		$nick = $this->client_data[$cid]->nick;
		$buf = '$ForceMove '.$hub_uri.'|';
		$buf .= '$To: '.$nick.' From: '.$sender_nick.' $<'.$sender_nick.'> You are being re-directed to '.$hub_uri.' because: '.$reason.'|';
		$this->write($cid, $buf);
		//$this->__disconnect($cid);
	}	
	function __c2s_key($cid, $argstring){ // $Key
		$this->client_data[$cid]->key = $argstring;
		$this->client_data[$cid]->key_sended = true;
		$this->supports($cid);
	}
	function __c2s_supports($cid, $argstring){ // $Supports
		// argstring - extensions splitted with space
		$exts = explode(" ", $argstring);
		foreach ($exts as $ext) {
			$this->client_data[$cid]->supports[$ext] = true;
		}
	}
	function __c2s_version($cid, $argstring){ // $Version
		// argstring - client version
		$this->client_data[$cid]->client_version = $argstring;
	}
	function __c2s_getnicklist($cid, $argstring){ // $GetNickList
		$this->__s2c_nicklist($cid);
		$this->__s2c_oplist($cid);
		$this->__s2c_botlist($cid);
	}
	function __c2s_validatenick($cid, $argstring){ // $ValidateNick
		if ($this->client_data[$cid]->logged_in){
			$this->write($cid, "<".$this->hub_security_bot_name."> You MUST QUIT first!|");
		}else{
			$this->client_data[$cid]->nick = $argstring;
		}
	}
	function __c2s_mypass($cid, $argstring){ // $MyPass
		//echo '(MyPass:'.$argstring.")\n";
		if ($argstring == ''){ // Empty passwords not allowed!!
			$this->__s2c_badpass($cid);//$this->client_data[$cid]->password_requested
		}else{
			$this->client_data[$cid]->password = trim($argstring);
			$this->client_data[$cid]->server_waiting_mypass = false;
		}
	}
	function __c2s_myinfo($cid, $argstring){ // $MyINFO
		//$buf .= '$MyINFO $ALL '.$this->nick.' '.$desc.'$ $Cable$whoknow@answers.com$1000000000$|';
		if (preg_match("#^\\\$ALL\s+(\S+)\s+([^\\\$]*)\\\$([^\\\$]*)\\\$([^\\\$]*)\\\$([^\\\$]*)\\\$([^\\\$]*)\\\$$#ims", $argstring, $asubs)){
			// 1-nick 2-desc 3--- 4-Conn 5-email 6-sharesize
			// +ext+ nick ignored anywhere since logged id
			//$this->client_data[$cid]->desc = $asubs[1]; - nick
			$this->client_data[$cid]->desc = $asubs[2];
			$this->client_data[$cid]->conn = $asubs[4];// && connflag
			$this->client_data[$cid]->connflag = '';// in conn
			$this->client_data[$cid]->email = $asubs[5];
			$this->client_data[$cid]->sharesize = $asubs[6];//floor(floatval(
			$desc = $this->client_desc($cid);
			$buf = '$MyINFO $ALL '.$this->usernicks[$cid].' '.$desc.'$ $'.$this->client_data[$cid]->conn.''.$this->client_data[$cid]->connflag.'$'.$this->client_data[$cid]->email.'$'.$this->client_data[$cid]->sharesize.'$|';
			$this->sr_myinfo++;
			$this->ss_myinfo+= count($this->usernicks);
			$this->main_chat_system_write($buf);
			$this->__fake_check_myinfo($cid);
			$this->client_data[$cid]->myinfo_sended = true;
			if ($this->client_data[$cid]->logged_in){
				$this->__delayed_disconnect_reset($cid); // BUG!!!!
			}
		}
	}
	function __check_myinfo($cid){
		//level
		if ($this->client_data[$cid]->level > 1) {
			//<-YnHub-> A GOD hath descended from the highest of heavens, bow down wretched user, and obey the command of thy Lord w999d!
			if ($this->client_data[$cid]->level > 9){
				$this->__hub_news_main_chat("A GOD hath descended from the highest of heavens, bow down wretched user, and obey the command of thy Lord ".$this->client_data[$cid]->nick."!");
			}
		}else{
			$this->__check_limits($cid);
		}
		$this->client_data[$cid]->myinfo_checked = true;
	}
	function __check_limits($cid){
		$op_nick = $this->hub_security_bot_name;
		if ($this->client_data[$cid]->sharesize < $this->hub_min_share_bytes){
			$this->__s2c_forcemove($cid, $op_nick, $this->hub_min_share_redirect, 'Min Share is '.bytes2size($this->hub_min_share_bytes).' (Минимальный размер шары - '.bytes2size($this->hub_min_share_bytes).'');
		}
	}
	function __fake_check_myinfo($cid){
		return false;
		$desc = $this->client_data[$cid]->desc;
		$expr = "\[[BLURD]{1}:[0-9]{1,10}(\.([0-9]{1,10}))?\]|L:\[U:[0-9]{1,10}/D:[0-9]{1,10}\]|LDC\+\+\[%[version2]\]L[0-9]{1,10}";
		if (preg_match("#".$expr."#ims", $desc)){
			$this->__hub_security_main_chat("Cheat: Limiter Detected: ".$this->client_data[$cid]->nick);
		}
	}
	function __c2s_getinfo($cid, $argstring){ // $GetINFO
		//$GetINFO <othernick> <nick>|
		$x = explode(" ", $argstring);
		$othernick = $x[0];
		if ($ocid = array_search($othernick, $this->usernicks)){
			$desc = $this->client_desc($ocid);
			$buf = '$MyINFO $ALL '.$othernick.' '.$desc.'$ $'.$this->client_data[$ocid]->conn.''.$this->client_data[$ocid]->connflag.'$'.$this->client_data[$ocid]->email.'$'.$this->client_data[$ocid]->sharesize.'$|';
			$this->write($cid, $buf);
		}elseif ($ocid = array_search($othernick, $this->botnicks)){
			/// Interface to bots desc
			$bot_nick = $othernick;
			if (isset($this->bot_info[$bot_nick])){
				$buf = '$MyINFO $ALL '.$bot_nick.' '.$this->bot_info[$bot_nick]->desc.'$ $'.$this->bot_info[$bot_nick]->conn.''.$this->bot_info[$bot_nick]->connflag.'$'.$this->bot_info[$bot_nick]->email.'$'.$this->bot_info[$bot_nick]->sharesize.'$|';
				$this->write($cid, $buf);
			}
		}
	}
	
	function __c2s_connecttome($cid, $argstring){ // $ConnectToMe
		if (!$this->client_data[$cid]->logged_in) return false;
		//Older clients, DC++ and NMDC protocol compatible clients:
		//$ConnectToMe <RemoteNick> <SenderIp>:<SenderPort>
		//NMDC v2.205 and DC:PRO v0.2.3.97A:
		//$ConnectToMe <SenderNick> <RemoteNick> <SenderIp>:<SenderPort>
		//This command starts the process for a client-client connection
		//initiated by an active mode client.
		//On receiving, the client responds
		//by connecting to the IP and port number specified,
		//and from there the client-client protocol takes over.
		//SenderNick IS ACTIVE and wants to connect to RemoteNick.
		//SenderNick sends through the hub, and starts to listen
		//for incoming connection at address Client1IP:Client1port (TCP).
		$this->sr_connecttome++;
		if (preg_match("#^\s*((\S+)\s+)?(\S+)\s+(\S+):(\S+)\s*$#ims", $argstring, $asubs)){
			$fromnick = $asubs[2];
			$remotenick = $asubs[3];
			$senderip = $asubs[4];
			$senderport = $asubs[5];
			if ($fromnick !== ''){
				$fromnick = ' '.$this->usernicks[$cid];
			}
			if (in_array($remotenick, $this->usernicks)){
				$remotecid = array_search($remotenick, $this->usernicks);
				$this->ss_connecttome++;
				$this->write($remotecid, "\$ConnectToMe$fromnick $remotenick $senderip:$senderport|");
			}
		}
	}
	function __c2s_multiconnecttome($cid, $argstring){ // $MultiConnectToMe
		if (!$this->client_data[$cid]->logged_in) return false;
		$this->write($cid, "<".$this->hub_security_bot_name."> Not supported: \$MultiConnectToMe!|");
	}
	function __c2s_revconnecttome($cid, $argstring){ // $RevConnectToMe
		if (!$this->client_data[$cid]->logged_in) return false;
		//$RevConnectToMe <nick> <remoteNick>
		//TA passive client may send this to cause a peer to send a $ConnectToMe back.
		//$RevConnectToMe <nick> <remoteNick>
		//<nick> is the sender of the message.
		//<remoteNick> is the user which should send to $ConnectToMe.
		//The server must send this message unmodified to <remoteNick>.
		//If <remoteNick> is an active client, it must send a $ConnectToMe to <nick>.
		//If not, it must ignore the message.
		$this->sr_revconnecttome++;
		if (preg_match("#^\s*(\S+)\s+(\S+)\s*$#ims", $argstring, $asubs)){
			$fromnick = $asubs[1];
			$remotenick = $asubs[2];
			$fromnick = $this->usernicks[$cid];
			if (in_array($remotenick, $this->usernicks)){
				//$fromcid = array_search($remotenick, $this->usernicks);
				$remotecid = array_search($remotenick, $this->usernicks);
				$this->ss_revconnecttome++;
				$this->write($remotecid, "\$RevConnectToMe $fromnick $remotenick|");
			}
		}
	}
	function spam_filter($cid, $message){
		return false;
	}
	function __c2s_to($cid, $argstring){
		if (!$this->client_data[$cid]->logged_in) return false;
		// $To: <othernick> From: <nick> $<<nick>> ;<message>
		// The server must pass the message unmodified to client <othernick> which must display the message to the user. (JavaDC omits the space between <<nick>> and <message>.)
		if (preg_match("#^(\S+)\s*From:\s*(\S+)\s*\\\$\s*<([^>]+)>\s*(.*)$#ims", $argstring, $asubs)){
			$fromnick = $asubs[2];
			$othernick = $asubs[1];
			$fromnick_msg = $asubs[3];
			$message = trim($asubs[4]);
			if (!$this->spam_filter($cid, $message)){
				$this->__debug_msg('[PM FROM='.$fromnick.' TO='.$othernick.' FROMNICK='.$fromnick_msg.'] '.$message);
				if ($ocid = array_search($othernick, $this->usernicks)){ // check of not to bot
					$this->user_to_user_pm($cid, $ocid, $message);
				}
			}
		}else{
			$this->__debug_msg('[CAN\'T PARSE] '.$hubcommand);
		}
	}
	function __c2s_quit($cid, $argstring){
		$this->__disconnect($cid);
	}
	function __c2s_opforcemove($cid, $argstring){ // $MultiConnectToMe
		// $OpForceMove $Who:<victimNick>$Where:<newIp>$Msg:<reasonMsg>
		// $OpForceMove $Who:<victimNick>$Where:<newIp>$Msg:<reasonMsg>
		// $Who:Mahoro$Where:serevr$Msg:reasn
		if ($this->client_data[$cid]->level < 5) return false;
		$args = explode("$", $argstring);
		$who = '';
		$where = '';
		$msg = 'undefined';
		foreach ($args as $arg){
			//echo 'arg:'.$arg."\n";
			if (preg_match("#^Who:(.*)$#ims", $arg, $wsubs)){
				$who = $wsubs[1];
			}
			if (preg_match("#^Where:(.*)$#ims", $arg, $wsubs)){
				$where = $wsubs[1];
			}
			if (preg_match("#^Msg:(.*)$#ims", $arg, $wsubs)){
				$msg = $wsubs[1];
			}
		}
		$who_cid = array_search($who, $this->usernicks);
		$sender_nick = $this->client_data[$cid]->nick;
		//echo 'who_cid='.$who_cid.' sender='.$sender_nick.' where='.$where.' msg='.$msg."\n";
		if (($who !== '') && ($where !== '') && $who_cid){
			$this->__s2c_forcemove($who_cid, $sender_nick, $where, $msg);
		}
	}
	function __c2s_kick($cid, $argstring){
		/*
		** Kicker to Hub (w999d, pm to victim): $To: W.Ed. From: w999d $<w999d> You are being kicked because: reason|
		** Kicker to Hub (w999d, to main chat): <w999d> is kicking W.Ed. because: reason|
		Kicker to Hub (w999d, command):	$Kick W.Ed.|
		** Hub to Kicker (from main chat):	<w999d> is kicking W.Ed. because: reason
		Hub to Kicker (from main chat):	<-YnHub-> W.Ed., IP address: 127.0.0.1 was kicked by w999d
		Hub to Kicker (from op chat): $To: w999d From: #[Op-Chat] $<#[Op-Chat]> [kick] W.Ed. was kicked by w999d
		** Hub to Victim (pm): $To: W.Ed. From: w999d $<w999d> You are being kicked because: reason
		** Hub to Victim (from main chat): <w999d> is kicking W.Ed. because: reason
		Hub to Kicker (command): $Quit W.Ed.
		*/
		if ($this->client_data[$cid]->level < 5) return false;
		$victim = $argstring;
		if ($victim_cid = array_search($victim, $this->usernicks)){
			// OP CHAT MESSAGE HERE !!
			// MAIN CHAT MESSAGE HERE !!
			//$this->write_flush($victim_cid);
			$this->__delayed_disconnect($victim_cid);
			//$this->write($cid, "<".$this->hub_security_bot_name."> Not supported: \$Kick!|");
		}
	}
	function __s2c_search_warning($cid){
		$this->write($cid, "<".$this->hub_security_bot_name."> Search interval - 30 seconds!|");
	}
	function __c2s_search($cid, $argstring){
		if (!$this->client_data[$cid]->logged_in) return false;
		if (isset($this->search_waiting[$cid]) && ($this->search_waiting[$cid] > time())){
			$this->__s2c_search_warning($cid);
		}else{
			$this->main_chat_write("\$Search ".$argstring."");
			$this->search_waiting[$cid] = time() + 30;
		}
		//$Search 82.179.117.162:26252 F?T?0?9?TTH:KXLGKSUA7QQIPV6JZGJOJYJVUZFBHDSQ3JYAM3Q
		//$this->_clogMessage('[search] '.$argstring, DLOG_ERROR);
	}
	function __c2s_sr($cid, $argstring){
		if (!$this->client_data[$cid]->logged_in) return false;
		if (isset($this->search_waiting[$cid]) && ($this->search_waiting[$cid] > time())){
			$this->__s2c_search_warning($cid);
		}else{
		if (preg_match("#^(.+)\x05([^\x05]+)$#ims", $argstring, $asubs)){
			$sr = $asubs[1];
			$this->sr_sr++;
			$this->ss_sr+= count($this->usernicks);
			$this->main_chat_system_write("\$SR ".$sr."");

		}
		$this->search_waiting[$cid] = time() + 30;
		}
		//DC.AnimeForge.Ru HUB [Anime/Manga/Games] (85.249.143.178:4111)
		//---- SEARCHSTRING
		//<sizerestricted>?<isminimumsize>?<size>?<datatype>?<searchpattern>
		//---- ACTIVE
		//$Search <ip>:<port> <searchstring>
		/*
							The server must forward this message unmodified to all the other users.
							Every other user with one or more matching files must send a UDP packet to <ip>:<port> containing just the message,
							$SR <nick> <searchresponse> <ip>:<port>
							<nick> is the nick of the user with the file.
							<ip>:<port> are the values sent in the $Search command.
							Can we store multiple responses in one UDP packet?
							What is the format of <searchresponse>?
							*/
		//---- PASSIVE
		//$Search Hub:<searchingNick> <searchstring>
		/*
							The server must forward this message unmodified to all the other users.
							Every other user with one or more matching files must send to the server,
							$SR <resultNick> <filepath>^E<filesize> <freeslots>/<totalslots>^E<hubname> (<hubhost>[:<hubport>])^E<searchingNick>
							$SR <resultNick> <filepath>^E
							<filesize> <freeslots>/<totalslots>^E
							<hubname> (<hubhost>[:<hubport>])^E
							<searchingNick>
							*/
	}
	function __c2s_multisearch($cid, $argstring){
		$this->write($cid, "<".$this->hub_security_bot_name."> Not supported: \$OpForceMove!|");
	}
	function __c2s_botinfo($cid, $argstring){
		//$BotINFO <website>$<filename>$<extensions seperated with , >$<Registeraddress>$<hubvote>|
		/*
							H: $Lock
							P: $Supports BotINFO NoHello
							P: $Key mykey|	 					<<may be left out
							P: $ValidateNick [TL]{Pinger}
							H: $Supports
							H: <botname> hubsoftversion + uptime
							H: $Hello [TL]{Pinger}
							P: $BotINFO <website>$<filename>$<extensions seperated with , >$<Registeraddress>$<hubvote>|
							H: $Hubinfo  <hubname>$<address:port>$<hub description>$<max users>$<Minshare in bytes>$<min slots>$<max hubs>$<hub type>$<Hubowner email>|
							P: $GetNickList|
							H: <$Myinfo stream>


							*/
		$this->__s2c_hubinfo($cid);
	}
	function __login($cid){
		$this->__debug_msg('BSDC_Hub_Prototype::__login() called');
		if ($this->client_data[$cid]->logged_in) return true;
		if ($this->__validate_nick($cid)) return true;
		return false;
	}
	function __is_registered($cid){
		$this->__debug_msg('BSDC_Hub_Prototype::__is_registered() called');
		// Override this to enable registrations
		return false;
	}
	function __check_password($cid){
		$this->__debug_msg('BSDC_Hub_Prototype::__check_password() called');
		// Override this to enable registrations
		return false;
	}
	function __reg_logged_in($cid){
		$this->__debug_msg('BSDC_Hub_Prototype::__reg_logged_in() called');
		// Override this to enable registrations
		return false;
	}
	function __validate_nick($cid){
		$this->__debug_msg('BSDC_Hub_Prototype::__validate_nick() called');
		//$this->__debug_msg('__validate_nick()');
		$nick = $this->client_data[$cid]->nick;
		if (preg_match("#([\$\|]|\s+)#ims", $nick)){ // Check Nick for special characters
			$this->__s2c_validatedenide($cid, $nick, "The nickname you've supplied contains spaces or restricted characters. Sorry, this is a protocol limitations.|");
		}elseif ($ocid = array_search($nick, $this->usernicks) || $bcid = array_search($nick, $this->botnicks) || $tcid = array_search($nick, $this->temp_nicks_reserved)){
			$this->__s2c_validatedenide($cid, $nick, "This nick is already in chat: ".$nick.". The nickname you've supplied is already in use by another user, please change it and reconnect.");
		}elseif($this->__is_registered($cid)){
			if ($this->client_data[$cid]->password_requested){
				if ($this->client_data[$cid]->password !== ''){
					if ($this->__check_password($cid)){
						$this->__any_logged_in($cid);
						$this->__reg_logged_in($cid);
					}else{
						$this->__s2c_badpass($cid);
					}
				}else{
					//$this->__debug_msg('password timeout??');
					// Timeout??
				}
			}else{
				$this->__s2c_getpass($cid);
			}
		}else{
			$this->__any_logged_in($cid);
			$this->write($cid, "<".$this->hub_security_bot_name."> Hi, this nick is not registered: ".$nick.". Feel free to use it. If you want to register it, type \"+regme\"!|");
		}
	}	
	function __any_logged_in($cid){
		$this->__debug_msg('BSDC_Hub_Prototype::__any_logged_in() called');
		$nick = $this->client_data[$cid]->nick;
		$this->client_data[$cid]->logged_in = true;
		//myinfo_sended
		if (!$this->client_data[$cid]->myinfo_sended){
			$this->__delayed_disconnect($cid, 10); // shedule disconnect because logged in but no myinfo!
		}else{
			$this->__delayed_disconnect_reset($cid); // cancel disconnect because logged in && myinfo!
		}
		$this->main_chat_to($cid, $this->hub_security_bot_name, 'You\'re logged in!');
		$this->__s2c_nicklist($cid);
		$this->__s2c_oplist($cid);
		$this->__s2c_botlist($cid);
		foreach ($this->usernicks as $ocid => $othernick) {
			$this->__s2c_hello($ocid, $nick);
		}
		$this->usernicks[$cid] = $nick;
		$this->__s2c_hello($cid, $nick);

		$this->__s2c_motd($cid);
		$this->usercommand($cid);
		$this->__users_stats_update();
	}
	function __users_stats_update(){
		$uc = count($this->usernicks);
		if ($this->s_peak_users < $uc){
			$this->s_peak_users = $uc;
		}
	}
	function __parse($cid){
		// BASIC PARSING
		$buf = $this->readbuf[$cid];
		$this->readbuf[$cid] = '';
		$this->__debug_msg('[SERVER PARSE '.$this->clients[$cid].'] '.$buf);
		if(!$cid) return false;
		if(!@$this->clients[$cid]) {
			$this->__debug_msg("read \"$buf\" from a non existant client socket ");
			return false;
		}
		$commands = explode('|', $buf);
		foreach ($commands as $cmd) {
			//$this->__debug_msg('--%'.$cmd.'%--');
			if (trim($cmd) !== ''){ // Skipping ??
				if ($this->__parse_protocol_cmd($cid, $cmd)){
					// Protocol recognized
					//$this->__debug_msg("Protocol recognized");
				}else{
					if ($this->client_data[$cid]->logged_in) $this->__parse_msg($cid, $cmd); // Other
				}
			}
		}
		return true;
	}
	function __parse_protocol_cmd($cid, $protocol_cmd){
		if (preg_match("#^".preg_quote('$', "#")."([a-z|:]+)(\s+(\S+.*)?)?$#ims", $protocol_cmd, $csubs)){
			$cmd = strtolower($csubs[1]);
			$argstring = isset($csubs[3])?trim($csubs[3]):'';
			$this->__debug_msg('DEBUG $'.$cmd.' '.$argstring);
			switch ($cmd) {
				// DC1 Spec:
			case 'key': $this->__c2s_key($cid, $argstring);	break;//$Key <responsekey>
			case 'validatenick': $this->__c2s_validatenick($cid, $argstring); break;//$ValidateNick <nick>
			case 'mypass': $this->__c2s_mypass($cid, $argstring); break;//$MyPass <password>
			case 'version': $this->__c2s_version($cid, $argstring); break;//$Version <version>
			case 'getnicklist': $this->__c2s_getnicklist($cid, $argstring); break;//$GetNickList
			case 'myinfo': $this->__c2s_myinfo($cid, $argstring); break;//$MyINFO $ALL <nick> <interest>$ $<speed>$<e-mail>$<sharesize>$
			case 'getinfo': $this->__c2s_getinfo($cid, $argstring); break;//$GetINFO <othernick> <nick>
			case 'connecttome': $this->__c2s_connecttome($cid, $argstring); break; //$ConnectToMe <remoteNick> <senderIp>:<senderPort>
			case 'multiconnecttome': $this->__c2s_multiconnecttome($cid, $argstring); break; //$MultiConnectToMe <senderIp>:<senderPort> &ltlinkedserverip>:<linkedserverport>
			case 'revconnecttome': $this->__c2s_revconnecttome($cid, $argstring); break; //$RevConnectToMe <nick> <remoteNick>
			case 'to:': $this->__c2s_to($cid, $argstring); break; //$To: <othernick> From: <nick> $<<nick>> ;<message>
			case 'quit': $this->__c2s_quit($cid, $argstring); break; //$Quit <nick>
			case 'opforcemove': $this->__c2s_opforcemove($cid, $argstring); break; //$OpForceMove $Who:<victimNick>$Where:<newIp>$Msg:<reasonMsg>
			case 'kick': $this->__c2s_kick($cid, $argstring); break; //$Kick <victimNick>
			case 'search': $this->__c2s_search($cid, $argstring); break; //$Search <ip>:<port> <searchstring>, $Search Hub:<searchingNick> <searchstring>
			case 'sr': $this->__c2s_sr($cid, $argstring); break; //$SR <resultNick> <filepath>^E<filesize> <freeslots>/<totalslots>^E<hubname> (<hubhost>[:<hubport>])^E<searchingNick>
			case 'multisearch': $this->__c2s_multisearch($cid, $argstring); break; //$MultiSearch
				// Extended Protocol
			case 'supports': $this->__c2s_supports($cid, $argstring); break; //????
			case 'botinfo': $this->__c2s_botinfo($cid, $argstring); break; //$BotINFO
			default:
				$this->__debug_msg('[BSDC PROTOCOL] UNKNOWN COMMAND '.$hubcommand);
				return false;
				break;
			}
			return true;
		}
		return false;
	}
	
	function __parse_user_cmd($cid, $cmd){
		
	}
	function __parse_msg($cid, $cmd){
		//$cmd = html_entity_decode($cmd, ENT_QUOTES);
		if (preg_match("#^<([^>]*)>(.*)$#ims", $cmd, $msubs)){
			// MAIN CHAT, SEND TO ALL USERS
			$this->__debug_msg('[BSDC MSG] MAIN CHAT '.$cmd);
			$fromnick = $this->usernicks[$cid]; // $fromnick = $msubs[1];
			$message = trim($msubs[2]);
			
			// CONVERT TO INTERNAL ENCODING:
			if (strtoupper($this->client_data[$cid]->charset) !== 'UTF-8'){
				$message = mb_convert_encoding($message, 'UTF-8', $this->client_data[$cid]->charset);
			}
			
			$this->__debug_msg('[MESSAGE FROM='.$fromnick.'] '.$message);
			if ($this->client_data[$cid]->level == 10){ // master user
				if ($message == 'quit'){
					$this->shutdownhub();
				}
				if ($message == 'restart'){
					$this->restarthub();
				}
				if ($message == '+debug'){
					$this->debug = true;
				}
				if ($message == '-debug'){
					$this->debug = false;
				}
			}
			// Special hub commands???
			if (!$this->user_hub_commands($cid, $message)){
				$ts = time();
				$chat_id = 1;
				$uid = $this->client_data[$cid]->id;
				$nick = iconv($this->client_data[$cid]->charset, 'utf-8', $this->client_data[$cid]->nick);
				if ($nick !== ''){
					$chat_id = 1;
					//if (!mysql_ping()) mysql_db_open();
					//mysql_select_db('olanet');
					//mysql_query("INSERT INTO `lain_chat_messages` SET `from_dc` = '1', `color` = '000000', `bgcolor` = 'ffffff', `its` = '$ts', `from_user_id` = '$uid', `from_user` = '$nick', `chat_id` = '$chat_id', `message` = '".addslashes(iconv($this->client_data[$cid]->charset, 'utf-8', $message))."'");
				}
				$this->client_to_main_chat($cid, $message);
			}
		}else{
			$this->__debug_msg('[BSDC MSG] UNKNOWN COMMAND '.$cmd);
		}
	}
	function __hub_sharesize(){
		$s = 0;
		foreach ($this->client_data as $cid => $data){
			$s += $data->sharesize;
		}
		return $s;
	}
	function __register_bot($bot_nick, $bot_info){ // REGISTER INTERNAL BOT INFO
		$this->levelnicks[15][$bot_nick] = $bot_nick;
		$this->botnicks[$bot_nick] = $bot_nick;
		$this->bot_info[$bot_nick] = $bot_info;
		if ($bot_info->oplisted){
			//$this->nicks[$bot_nick] = $bot_nick;
		}
	}
	function __heartbeat_test(){
		$period = 15;
		if (time() - $this->last_heartbeat > $period){
			$this->__on_heartbeat();
			$this->__TRASHER();
			$this->__logMessage('[HEARTBEAT SOCKETS='.count($this->watch).' CLIENTS='.count($this->clients).']', DLOG_ERROR);
			$this->last_heartbeat = time();
		}
	}
	function __on_heartbeat(){
		$m = array(
		"#%HUBNAME%#ims",
		"#%HUBADDR%#ims",
		"#%HUBLINK%#ims",
		);
		$r = array(
		$this->hub_name,
		$this->hub_address.':'.$this->hub_port,
		'dchub://'.$this->hub_address.':'.$this->hub_port,
		);
		//if (!mysql_ping()) mysql_db_open();
		//mysql_select_db('olanet');
		//mysql_query("UPDATE `lain_dc_info` SET `value` = '".time()."' WHERE `name` = 'last_heartbeat'");
		//echo $this->__includes_path.'text/motd.txt';
		$this->txt_motd = $this->__txt('motd');// @file_get_contents($this->__includes_path.'text/motd.txt', FILE_BINARY).'EOF';
		$this->txt_help = $this->__txt('help');// @file_get_contents($this->__includes_path.'text/help.txt', FILE_BINARY).'EOF';
		$this->txt_beforelogin = $this->__txt('beforelogin');// = @file_get_contents($this->__includes_path.'text/beforelogin.txt', FILE_BINARY).'EOF';
		$this->txt_motd = preg_replace($m, $r, $this->txt_motd);
	}
	function __txt($name){
		$txt = '';
		$i = $this->__includes_path.'text/';
		$f = $i.$name.'.txt';
		$s = 'Lain';
		if (is_file($f)){
			return file_get_contents($f);
			/*$this->__write_to_minlevel($minlevel = 10, $s, $f.' exists');
			$this->__write_to_minlevel($minlevel = 10, $s, 'filesize='.filesize($f).'');
			$this->__write_to_minlevel($minlevel = 10, $s, 'file='.implode("",file($f)).'');
			$this->__write_to_minlevel($minlevel = 10, $s, 'getcontents='.file_get_contents($f).'');*/
		}else{
			$this->__write_to_minlevel($minlevel = 10, $s, $f.' not exists!');
		}
		return '';
	}
	function __logMessage($msg, $status = DLOG_NOTICE){
		if ($status & DLOG_TO_CONSOLE || $this->__running_in_console){
			print $msg."\n";
		}
		/*if ($this->logged_in){
			//$this->send_pm('W.Ed.', $msg);
		}*/
		/*if ($fp = @fopen($this->log_filename, 'a')){
			fwrite($fp, date("Y/m/d H:i:s ").$msg."\n");
			fclose($fp);
		}*/
		//if () parent::__logMessage($msg, $status);
	}
	function __clogMessage($msg, $status = DLOG_NOTICE){
		$this->__logMessage($msg, $status);
	}
	function write($cid, $buf){ // dubbing in mainchat
		$this->write_once($cid, $buf);
	}
	function write_once($cid, $buf){ // in main chat
		$this->__c_write($cid, $buf);
		//$this->writebuf[$cid] .= $buf;
	}
	function __write_flush($cid){
		if (!isset($this->writebuf[$cid])) return false;
		$this->update_refresh_ts($cid);
		$buf = $this->writebuf[$cid];
		$this->writebuf[$cid] = '';
		if ($buf !== ''){
			//$this->__logMessage('[SERVER FLUSHES TO '.$this->clients[$cid].'] '.$buf, DLOG_ERROR);
			if(!$cid) return false;
			if(!@$this->clients[$cid]) {
				$this->__logMessage("write \"$buf\" to a non existant client socket ", DLOG_ERROR);
				return false;
			}
			$done = 0;
			$len = strlen($buf);
			while($done<$len){
				if(!$n = @socket_write($this->clients[$cid],substr($buf,$done))) break;
				$done += $n;
			}
			$this->tcpout += $done;
			if ($done == $len){
				return true;
			}else{
				// write back to buf
				$this->writebuf[$cid] = substr($buf, $done, ($len - $done));
				# no more error on client disconnect
				if(socket_last_error($this->clients[$cid])!==104) $this->__debug_msg('close connection (write failed) ');
				//$this->close_connection($cid);
				return false;
			}
		}
		return true;
	}
	function remove_client($cid){ // DEPRECATED
		$this->__disconnect($cid);
	}
	function __write_to_minlevel($minlevel = 10, $from_nick, $msg){
		//$levelnicks[$level] - all nicks by levels
		for ($level = $minlevel; $level <= 10; $level++){
			if (isset($this->levelnicks[$level])){
				foreach ($this->levelnicks[$level] as $nick){
					if (in_array($nick, $this->usernicks)){ // if normal user (not bot)
						if ($to_cid = array_search($nick, $this->usernicks)){
							$this->private_to($to_cid, $from_nick, $msg);
						}
					}
				}
			}
		}
	}
	function usercommand($cid){
		//$UserCommand 255 1
		//$this->write($cid, '$UserCommand 255 7|$UserCommand 0 3|$UserCommand 1 3 Hub commands\Hubstats$&lt;%[mynick]&gt; +stats&amp;#124;|$UserCommand 1 3 Hub commands\Who was first in?$&lt;%[mynick]&gt; +firstin&amp;#124;|$UserCommand 1 3 Hub commands\Show topic$&lt;%[mynick]&gt; +showtopic&amp;#124;|');
		$this->write($cid, "\$UserCommand 255 1|");
		$this->write($cid, "\$UserCommand 1 1 MENU\\register\$<%[mynick]> +regme|");
		$this->write($cid, "\$UserCommand 1 1 MENU\\help\$<%[mynick]> +help|");
		if ($this->client_data[$cid]->level >=10){
			$this->write($cid, "\$UserCommand 0 1|");
			$this->write($cid, "\$UserCommand 1 1 MASTER\shutdown\$<%[mynick]> quit|");
		}
		$this->write($cid, "\$UserCommand 0 1|");
	}
	function client_to_main_chat($client_id, $message){
		$fromnick = $this->usernicks[$client_id];
		foreach ($this->usernicks as $to_cid => $usernick){
			$this->write_once($to_cid, '<'.$fromnick.'> '.dcspecialchars($this->charset_msg($to_cid, $message)).'|');
		}
	}
	function main_chat_write($message){
		foreach ($this->usernicks as $cid => $usernick){
			$this->write_once($cid, dcspecialchars($message).'|');
		}
	}
	function main_chat_write_c($message){
		foreach ($this->usernicks as $cid => $usernick){
			$this->write_once($cid, dcspecialchars($this->charset_msg($cid, $message)).'|');
		}
	}
	function __hub_security_main_chat($message){
		$this->main_chat_system_write("<".$this->hub_security_bot_name."> ".$message);
	}
	function __hub_news_main_chat($message){
		$this->main_chat_system_write("<".$this->hub_news_bot_name."> ".$message);
	}
	function main_chat_system_write($message){
		foreach ($this->usernicks as $cid => $usernick){
			$this->write_once($cid, $message.'|');
		}
	}
	function main_chat_to($cid, $fromnick, $message){
		$this->write($cid, "<$fromnick> $message|");
	}
	function private_to($cid, $fromnick, $message){
		$usernick = $this->client_data[$cid]->nick;
		// incorrect: $this->write($cid, "\$To: '.$usernick.' From $fromnick $<$fromnick> $message|");
		$this->write($cid, "\$To: ".$usernick." From: $fromnick $<$fromnick> $message|");
	}
	function user_to_user_pm($cid, $ocid, $message){
		$this->private_to($ocid, $this->usernicks[$cid], $message);
	}
	function supports($cid){
		$this->write($cid, "\$Supports UserCommand BotINFO NoHello NoGetINFO BotList HubTopic |");
	}

	function is_connected($cid){
		return (bool) ( @$this->clients[$cid] && @$this->watch[$cid] );
	}
	function charset_msg($cid, $msg){
		//return $msg; // s2c && c2s USED NOW
		if (mb_check_encoding($msg, 'UTF-8')){
			//echo 'Valid UTF-8. ';
			$xmsg = mb_convert_encoding($msg ,$this->client_data[$cid]->charset , "UTF-8");
		}
		return $xmsg;//"MSG:".$msg." XMSG".$xmsg;
		return iconv('UTF-8', $this->client_data[$cid]->charset."//IGNORE", $msg);
	}
	function __s2c($cid, $msg){
		return $msg;
		$sme = mb_detect_encoding($msg, "ASCII,CP1251,UTF-8", true);
		$ce = $this->client_data[$cid]->charset;
		if (mb_check_encoding($msg, $ce)){
			return '<-SERVER-> Internal:UTF-8, Detected:'.$sme.', Client: '.$ce.'|'.mb_convert_encoding($msg, $ce, "UTF-8");
		}
		return '<-S2C ERROR-> Internal:UTF-8, Detected:'.$sme.', Client: '.$ce.'|';
		if (mb_check_encoding($msg, $enc)){//'UTF-8'
			return '<-E-> '.'UTF-8'.'|'.mb_convert_encoding($msg, $ce, "UTF-8");
		}else{
			$enc = mb_detect_encoding($msg, "ASCII,CP1251,UTF-8", true);
			if (mb_check_encoding($msg, $enc)){
				return '<-E-> '.$enc.'|'.mb_convert_encoding($msg, $this->client_data[$cid]->charset, "UTF-8");
			}else{
				return '<-E-> '.$enc.' NOT-MODIFIED'.'|'.$this->out($msg);
			}
		}
	}
	function __c2s($cid, $msg){
		return $msg;
		$sme = mb_detect_encoding($msg, "ASCII,CP1251,UTF-8", true);
		$ce = $this->client_data[$cid]->charset;
		if (mb_check_encoding($msg, $ce)){
			return '<'.$this->client_data[$cid]->nick.'> C2S Internal:UTF-8, Detected:'.$sme.', Client: '.$ce.'|'.mb_convert_encoding($msg, "UTF-8", $ce);
		}
		return '<'.$this->client_data[$cid]->nick.'> C2S ERROR Internal:UTF-8, Detected:'.$sme.', Client: '.$ce.'|';
		//mb_detect_encoding($str, "auto");
		if (mb_check_encoding($msg, $this->client_data[$cid]->charset)){
			return mb_convert_encoding($msg, "UTF-8", $this->client_data[$cid]->charset);
		}else{
			return 'CLIENT CHARSET ERROR:'.$msg;
		}
	}
	function in($s){
		return html_entity_decode($s, ENT_QUOTES);
	}
	function out($s){
		return htmlentities($s, ENT_QUOTES);
	}
	
}
/*
							$SR W.Ed. ANIME_VIDEO\Naruto\Ep 102-106 (Anime only) - Tea Country Race Arc\Naruto 104.avi180312190 10/10TTH:FLX7C6XZGZJ6K7MTGCGVTBLWYHWJVGOEA6SPPXY (dc.animeforge.ru:4115)w999d|
							$SR W.Ed. ANIME_VIDEO\Naruto\Ep 102-106 (Anime only) - Tea Country Race Arc\Naruto 104.srt16090 10/10TTH:YNPXWVS45CGEJ2HYC3IKFDY3DOMT77VIVM6CLMI (dc.animeforge.ru:4115)w999d|
							$SR W.Ed. ANIME_VIDEO\Naruto\Ep 102-106 (Anime only) - Tea Country Race Arc\Naruto 105.avi179851858 10/10TTH:XZ56GVTI5FHADDFXNRUSPCLBXT6VRC2BICWNOTA (dc.animeforge.ru:4115)w999d|
							$SR W.Ed. ANIME_VIDEO\Naruto\Ep 102-106 (Anime only) - Tea Country Race Arc\Naruto 105.srt18131 10/10TTH:4YUMG4UJVB2Q7KXASSGNVRWNAKQBTCTNHMGLJYA (dc.animeforge.ru:4115)w999d|

							$SR W.Ed. ANIME_VIDEO\Naruto\Ep 102-106 (Anime only) - Tea Country Race Arc\Naruto 103.srt19210 10/10TTH:NK2FRUEN4LGJVT2XVLXDK6GPZB5O6DRMAMES2IY (dc.animeforge.ru:4115)w999d|

							*/
//$this->_clogMessage('[sr] '.$argstring, DLOG_ERROR);
// argstring - search result
//$SR Alchemist usr\dcshare\AMV\Naruto\[outlaw55]_Naruto_-_Secret_Lovers.avi15374336 50/50DC.AnimeForge.Ru HUB [Anime/Manga/Games] (85.249.143.178:4111)
//$SR Alchemist usr\dcshare\AMV\Naruto\[outlaw55]_Naruto_-_Secret_Lovers.avi
//15374336 50/50
?>
