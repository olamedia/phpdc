<?php 
/**
* @package binarychoice.system.unix 
* @author Michal 'Seth' Golebiowski <seth at binarychoice dot pl>
* @copyright Copyright 2005 Seth
* @since 1.0.3 
* @link http://www.phpclasses.org/browse/file/8958.html
* @license GPL
*/ 

// user defined error handling function
function RXDaemonErrorHandler($errno, $errmsg, $filename, $linenum, $vars) {
	$logfile = (defined("RXDaemonLogFilename")?RXDaemonLogFilename:'/tmp/RXDaemon.error.log');
	// timestamp for the error entry
	$dt = @date("d.m.Y H:i:s ");

	// define an assoc array of error string
	// in reality the only entries we should
	// consider are E_WARNING, E_NOTICE, E_USER_ERROR,
	// E_USER_WARNING and E_USER_NOTICE
	$errortype = array (
	E_ERROR              => 'Error',
	E_WARNING            => 'Warning',
	E_PARSE              => 'Parsing Error',
	E_NOTICE             => 'Notice',
	E_CORE_ERROR         => 'Core Error',
	E_CORE_WARNING       => 'Core Warning',
	E_COMPILE_ERROR      => 'Compile Error',
	E_COMPILE_WARNING    => 'Compile Warning',
	E_USER_ERROR         => 'User Error',
	E_USER_WARNING       => 'User Warning',
	E_USER_NOTICE        => 'User Notice',
	E_STRICT             => 'Runtime Notice',
	E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
	);
	// set of errors for which a var trace will be saved
	$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
	
	$err = $dt . $errno .' '. $errortype[$errno] .': '. $errmsg .' in '. $filename  . " at line ".$linenum."\r\n";

	/*if (in_array($errno, $user_errors)) {
		$err .= "\t<vartrace>" . wddx_serialize_value($vars, "Variables") . "</vartrace>\n";
	}*/
	
	// for testing
	// echo $err;

	// save to the error log, and e-mail me if there is a critical user error
	if (!in_array($errno, array(E_STRICT, E_NOTICE))){
		error_log($err, 3, $logfile);
	}
	/* if ($errno == E_USER_ERROR) {
		mail("phpdev@example.com", "Critical User Error", $err);
	}*/
}



// Log message levels
define('DLOG_TO_CONSOLE', 1);
define('DLOG_NOTICE', 2);
define('DLOG_WARNING', 4);
define('DLOG_ERROR', 8);
define('DLOG_CRITICAL', 16);

class RX_Daemon{
	var $userID = 99;
	var $groupID = 99;
	var $requireSetIdentity = false;//Terminate daemon when set identity failure ? 
	var $__win32 = false;
	
	var $__consolize = false;
	
	var $__service_allowed = false;
	var $__daemon_allowed = false;
	var $__console_allowed = true;
	var $__running_as_service = false;
	var $__running_as_daemon = false;
	var $__running_in_console = true;
	
	var $pidFileLocation = '/tmp/daemon.pid';
	var $homePath = '/';
	var $__pid = 0;
	var $__isChildren = false;
	var $__isRunning = false;
	
	var $__file = '';
	
	function __shlib_dl($lib){
		return @dl("php_".$lib.'.'.PHP_SHLIB_SUFFIX);
	}
	function __release_test(){
		if (function_exists("win32_start_service")){
			$this->__service_allowed = true; // WIN32 way
		}
		if (function_exists("pcntl_fork") && function_exists("posix_getpid")){
			$this->__daemon_allowed = true;// POSIX && PCNTL way
		}
		return ($this->__daemon_allowed || $this->__service_allowed);
	}
	function RX_Daemon(){
		$this->__file = __FILE__;
		// INIT
		//error_reporting(0);
		// we will do our own error handling
		error_reporting(E_ALL ^ E_NOTICE);
		//$old_error_handler = set_error_handler("RXDaemonErrorHandler");
		$this->__logMessage('RXDaemonErrorHandler Registered!', DLOG_TO_CONSOLE);
		set_time_limit(0);
		ignore_user_abort(true);
		ob_implicit_flush();
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$this->__win32 = true;
			//echo DIRECTORY_SEPARATOR; // /
			//echo PHP_SHLIB_SUFFIX;    // so
			//echo PATH_SEPARATOR;      // :
		}else{
		}
		if (!$this->__release_test()){ // can't demonize now, trying load required extensions
			if ($this->__win32){
				$this->__shlib_dl('win32service');
			}else{
				/*
				POSIX functions are enabled by default. 
				You can disable POSIX-like functions with --disable-posix. 
				
				Process Control support in PHP is not enabled by default. 
				You have to compile the CGI or CLI version of PHP with --enable-pcntl configuration option when compiling PHP to enable Process Control support. 
				
				*/
				//$this->__shlib_dl('win32service');
			}
		}
		$this->__release_test();
		register_shutdown_function(array(&$this, '__release_RXDaemon'));
		$this->__logMessage('RXDaemon Created!', DLOG_TO_CONSOLE);
	}
	function start(){
		$this->__start();
	}
	function stop(){
		$this->__stop();
	}
	function __start(){
		$this->__logMessage('RXDaemon::__start()', DLOG_TO_CONSOLE);
		$this->__logMessage('Starting...', DLOG_TO_CONSOLE);
		$this->__logMessage('OS:'.PHP_OS, DLOG_TO_CONSOLE);
		if (!$this->__consolize){
			if ($this->__daemon_allowed){
				if (!$this->__daemonize()){
					$this->__logMessage('Could not start daemon', DLOG_TO_CONSOLE);
					if (!$this->__console_allowed) return false;
				}else{
					$this->__running_as_daemon = true;
					$this->__logMessage('Starting as daemon...', DLOG_TO_CONSOLE);
				}
			}elseif ($this->__service_allowed){
				if (!$this->__servicize()){
					$this->__logMessage('Could not start service', DLOG_TO_CONSOLE);
					if (!$this->__console_allowed) return false;
				}else{
					$this->__running_as_service = true;
					$this->__logMessage('Starting as service...', DLOG_TO_CONSOLE);
				}
			}elseif($this->__console_allowed){
			}else{
				return false;
			}
		}
		if (!$this->__running_as_daemon && !$this->__running_as_service){
			// running in console
			$this->__logMessage('Starting in console...', DLOG_TO_CONSOLE);
			$this->__running_in_console = true;
			if (!$this->__consolize()){
				exit(0);
			}
		}
		$this->__logMessage('Running...', DLOG_TO_CONSOLE);
		$this->__isRunning = true;
		while ($this->__isRunning){
			$this->__doTask();
			if ($this->__running_in_console) {
				//$this->__logMessage('__doTask()');
				//usleep(10);
				//sleep(1);
			}
		}
		return true;
	}
	function __stop(){
		$this->__logMessage('RXDaemon::__stop()', DLOG_TO_CONSOLE);
		// override this method (called by shutdown function)
		$this->__logMessage('Stoping RXDaemon', DLOG_TO_CONSOLE);
		$this->__running_in_console = true;
		$this->__isRunning = false;
		//exit();
	}
	function __doTask(){
		// override this method
	}
	function __logMessage($msg, $level = DLOG_NOTICE){
		// override this method
		//if ($this->__running_in_console) 
		echo $msg."\r\n";
	}
	function __consolize(){
		$this->__logMessage('RXDaemon::__consolize()', DLOG_TO_CONSOLE);
		ob_end_flush();
		if ($this->__isConsoleRunning()){
			// Deamon is already running. Exiting
			$this->__logMessage('RXDeamon is already running. Exiting', DLOG_TO_CONSOLE);
			return false;
		}
		if (!$fp = @fopen($this->pidFileLocation, 'w'))	{
			$this->__logMessage('Could not write to PID file', DLOG_TO_CONSOLE);
			return false;
		}else{
			fputs($fp, $this->__pid);
			fclose($fp);
		}
		$this->__isChildren = true;
		$this->__logMessage('RXDaemon::__consolize() OK', DLOG_TO_CONSOLE);
		return true;
	}
	function __servicize(){
		ob_end_flush();
		if ($this->__isDaemonRunning()){
			// Deamon is already running. Exiting
			$this->__logMessage('RXDeamon is already running. Exiting', DLOG_TO_CONSOLE);
			return false;
		}
		return false;
	}
	function __daemonize(){
		$this->__logMessage('RXDaemon::__daemonize()', DLOG_TO_CONSOLE);
		$this->__running_in_console = false;
		ob_end_flush();
		if ($this->__isDaemonRunning()){
			// Deamon is already running. Exiting
			$this->__logMessage('RXDeamon is already running. Exiting', DLOG_TO_CONSOLE);
			return false;
		}
		if (!$this->__fork()){
			// Coudn't fork. Exiting.
			$this->__logMessage('Coudn\'t fork. Exiting.', DLOG_TO_CONSOLE);
			return false;
		}
		if (!$this->__setIdentity() && $this->requireSetIdentity){
			// Required identity set failed. Exiting
			$this->__logMessage('Required identity set failed. Exiting', DLOG_TO_CONSOLE);
			return false;
		}
		if (!posix_setsid()){
			$this->__logMessage('Could not make the current process a session leader', DLOG_TO_CONSOLE);
			return false;
		}
		if (!$fp = @fopen($this->pidFileLocation, 'w'))	{
			$this->__logMessage('Could not write to PID file', DLOG_TO_CONSOLE);
			return false;
		}else{
			fputs($fp, $this->__pid);
			fclose($fp);
		}
		@chdir($this->homePath);
		umask(0);
		declare(ticks = 1);
		pcntl_signal(SIGCHLD, array(&$this, '__sigHandler'));
		pcntl_signal(SIGTERM, array(&$this, '__sigHandler'));
		return true;
	}
	function __isConsoleRunning(){
		return is_file($this->pidFileLocation);
	}
	function __isDaemonRunning(){
		$oldPid = @file_get_contents($this->pidFileLocation);
		if ($this->__running_in_console) return false;
		if ($oldPid !== false && posix_kill(trim($oldPid),0)){
			$this->__logMessage('Daemon already running with PID: '.$oldPid, DLOG_TO_CONSOLE);
			return true;
		}else{
			return false;
		}
	}
	function __fork() {
		$this->__logMessage('RXDaemon::__fork()', DLOG_TO_CONSOLE);
		$pid = pcntl_fork();
		if ($pid == -1){ // error
			$this->__logMessage('Could not fork', DLOG_TO_CONSOLE);
			return false;
		} elseif ($pid){ // parent
			$this->__logMessage('Killing parent', DLOG_TO_CONSOLE);
			exit();
		}else{ // children
			$this->__isChildren = true;
			$this->__pid = posix_getpid();
			return true;
		}
	}
	function __setIdentity()	{
		if (!posix_setgid($this->groupID) || !posix_setuid($this->userID)){
			$this->__logMessage('Could not set identity', DLOG_TO_CONSOLE);
			return false;
		}else{
			return true;
		}
	}
	function __sigHandler($sigNo){
		$this->__logMessage('__sigHandler('.$sigNo.')');
		switch ($sigNo){
		case SIGHUP:   // 1 - HUP - hang up
			$this->__logMessage('SIGHUP signal', DLOG_TO_CONSOLE);
			//$this->stop();
			break;
		case SIGINT:   // 2 - INT - interrupt ^C - non catchable?????
			$this->__logMessage('SIGINT signal', DLOG_TO_CONSOLE);
			exit(0);//$this->__stop();
			break;
		case SIGQUIT:   // 3 - QUIT - quit
			$this->__logMessage('SIGQUIT signal', DLOG_TO_CONSOLE);
			exit(0);//$this->__stop();
			break;
			// 4,5
		case SIGABRT:   // 6 - ABRT - abort
			$this->__logMessage('SIGABRT signal', DLOG_TO_CONSOLE);
			exit(0);//$this->__stop();
			break;
			//
		case SIGKILL:   // 9 - KILL - kill - non-catchable, non-ignorable
			$this->__logMessage('SIGKILL signal', DLOG_TO_CONSOLE);
			exit(0);//$this->__stop();
			break;
			//
		case SIGTERM:   // 15      TERM (software termination signal)
			$this->__logMessage('SIGTERM signal', DLOG_TO_CONSOLE);
			exit(0);//
			break;

		case SIGCHLD:   // Halt??
			$this->__logMessage('SIGCHLD signal', DLOG_TO_CONSOLE);
			while (pcntl_waitpid(-1, $status, WNOHANG) > 0);
			break;
		}
		return 0;
		/*
		1       HUP (hang up)
		2       INT (interrupt)
		3       QUIT (quit)
		6       ABRT (abort)
		9       KILL (non-catchable, non-ignorable kill)
		14      ALRM (alarm clock)
		15      TERM (software termination signal)
		*/
	}
	function __log_last_error(){
		$this->__logMessage('RXDaemon::__log_last_error()', DLOG_TO_CONSOLE);
		$le = error_get_last();
		//echo 'XXX '.$php_errormsg."\n";
		print_r($le);
		$errno = $le['type'];
		$errmsg = $le['message'];
		$filename = $le['file'];
		$linenum = $le['line'];
		RXDaemonErrorHandler($errno, $errmsg, $filename, $linenum, array());
	}
	function __release_RXDaemon(){
		$this->__logMessage('RXDaemon::__release_RXDaemon()', DLOG_TO_CONSOLE);
		//$this->__running_in_console = true;
		$this->__log_last_error();
		$this->__isRunning = false;
		if ($this->__running_as_daemon){
			$this->__releaseDaemon();
		}elseif($this->__running_as_service){
			
		}else{
			// running from console
			$this->__releaseConsole();
			//$this->__logMessage('Releasing console', DLOG_TO_CONSOLE);
		}
	}
	function __releaseConsole(){
		$this->__logMessage('RXDaemon::__releaseConsole()', DLOG_TO_CONSOLE);
		if ($this->__isChildren && file_exists($this->pidFileLocation)){
			$this->__logMessage('Releasing console UNLINK(PID)', DLOG_TO_CONSOLE);
			unlink($this->pidFileLocation);
		}
	}
	function __releaseDaemon(){
		$this->__logMessage('RXDaemon::__releaseDaemon()', DLOG_TO_CONSOLE);
		if ($this->__isChildren && file_exists($this->pidFileLocation)){
			$this->__logMessage('Releasing daemon UNLINK(PID)', DLOG_TO_CONSOLE);
			unlink($this->pidFileLocation);
		}
		//print_r(error_get_last());
	}
}
/*$RX_Daemon =& new RX_Daemon();
$RX_Daemon->start();*/
?>
