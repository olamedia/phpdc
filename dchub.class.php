<?php
require_once(realpath(dirname(__FILE__))."/dchub.proto.php");







class DCHub extends BSDC_Hub_Prototype {
	var $update_refresh_ts = 0;
	var $database_allow = true;
	function DCHub($hub_host = 'dc.animeforge.ru', $hub_port = 4111){
		parent::BSDC_Hub_Prototype($hub_host, $hub_port);
		//$this->register_bots();
	}
	function __destruct(){
		parent::__destruct();
	}
	function register_bots(){
		// Lain Iwakura, Hub Security
		/*
		connflag:
		1 normal
		2, 3 away
		4, 5 server
		6, 7 server away
		8, 9 fireball
		10, 11 fireball away
		*/
		/*
		Tag, addition to the <description> field
		++: indicates the client
		V: tells you the version number
		M: tells if the user is in active (A), passive (P), or SOCKS5 (5) mode
		H: tells how many hubs the user is on and what is his status on the hubs. The first number means a normal user, second means VIP/registered hubs and the last one operator hubs (separated by the forward slash ['/']).
		S: tells the number of slots user has opened
		O: shows the value of the "Automatically open slot if speed is below xx KiB/s" setting, if non-zero
		<++ V:0.673,M:P,H:0/1/0,S:50>
		<++ V:0.673,M:A,H:0/1/0,S:50>
		*/
		$Lain =& new dc_client();
		$Lain->conn = 'Satellite';
		$Lain->connflag = chr(9);
		$Lain->desc = 'Make me sad. Make me mad. Make me feel alright?<++ V:0.673,M:A,H:0/1/0,S:50>';
		$Lain->email = 'Iwakura.Lain@Infornography';
		$Lain->sharesize = '45742635964325';
		$Lain->oplisted = true;
		$this->__register_bot('Lain', $Lain);

		$Alice =& new dc_client();
		$Alice->conn = 'Cable';
		$Alice->connflag = chr(1);
		$Alice->desc = 'aliceLOVEneeds you<++ V:0.673,M:A,H:0/1/0,S:50>';
		$Alice->email = 'Mizuki Arisu';
		$Alice->sharesize = 0;
		$this->__register_bot('Alice', $Alice);

		$Chii =& new dc_client();
		$Chii->conn = 'Cable';
		$Chii->connflag = chr(8);
		//The server icon is used when the client has uptime > 2 hours, > 2 GB shared, upload > 200 MB.
		$Chii->desc = 'Chii...<++ V:0.673,M:A,H:0/1/0,S:50>';
		$Chii->email = 'chii@chii';
		$Chii->sharesize = '54635986654';
		$this->__register_bot('Chii', $Chii);

		$Mahoro =& new dc_client();
		$Mahoro->conn = 'Cable';
		$Mahoro->connflag = chr(5);
		//The fireball icon is used when the client has had an upload > 100 kB/s.
		$Mahoro->desc = 'Ecchi nano wa ikenai to omoimasu!!!<++ V:0.673,M:A,H:0/1/0,S:50>';
		$Mahoro->email = 'mahorom@tic';
		$Mahoro->sharesize = 0;
		$Mahoro->oplisted = true;
		$this->__register_bot('Mahoro', $Mahoro);
		//Robots don't share.

	}		
	function __is_registered($cid, $return_ui = false){
		if (!$this->database_allow) return false;
		$this->__debug_msg('DCHub::__is_registered() called');
		$nick = $this->client_data[$cid]->nick;
		//return false;
		//if ($nick !== 'W.Ed.') return false; else return true;
		$registered = false;
		if (!mysql_ping()) mysql_db_open();
		mysql_select_db('olamedia');
		$sql = "SELECT * FROM `ola_users` WHERE `login` = '".addslashes($nick)."'";
		$db_error = false;
		if ($uquery = mysql_query($sql)){
			if ($uinfo = mysql_fetch_array($uquery)){
				$uid = $uinfo['id'];
				$registered = true;
				if ($return_ui) return $uinfo;
			}else{
			}
		}else{
			$db_error = true;
		}
		return $registered;
	}
	function __check_password($cid){
		if (!$this->database_allow) return false;
		$this->__debug_msg('DCHub::__check_password() called');
		$uinfo = $this->__is_registered($cid, true);
		if($uinfo['hash'] == password_hash($this->client_data[$cid]->password, $uinfo['key'])) {
			$this->client_data[$cid]->id = $uinfo['id'];
			return true;
		}
		return false;
	}
	function __reg_logged_in($cid){
		if (!$this->database_allow) return false;
		$this->__debug_msg('DCHub::__reg_logged_in() called');
		// CHANGE LEVEL/CLASS++
		$nick = $this->client_data[$cid]->nick;
		$uinfo = $this->__is_registered($cid, true);
		$uid = $uinfo["id"];
		if (!mysql_ping()) mysql_db_open();
		mysql_select_db('olanet');
		$level = 0;
		if ($q = mysql_query("SELECT * FROM `lain_chat_users` WHERE `user_id` = '$uid' AND `chat_id` = '1'")){
			if ($cinfo = mysql_fetch_array($q)){
				$level = $cinfo['dc_class'];
			}
		}
		unset($this->levelnicks[$this->client_data[$cid]->level][$cid]);
		$this->client_data[$cid]->level = $level;//$uinfo['dc_class'];
		$this->client_data[$cid]->id = $uinfo['id'];
		$this->levelnicks[$this->client_data[$cid]->level][$cid] = $nick;
		//__hub_sharesize()
		// CHANGE LEVEL/CLASS--
		//, `login_count` = '".($uinfo['login_count']+1)."'
		mysql_query("UPDATE `lain_chat_users` SET `last_login_ts` = '".time()."', `dc_ip` = '".$this->client_data[$cid]->ip."', `dc_email` = '".addslashes($this->client_data[$cid]->email)."' WHERE `user_id` = '$uid' AND `chat_id` = '1'");
		$this->update_refresh_ts($cid);
	}
	
	function update_refresh_ts($cid){
		return false;// disable
		if (!$this->database_allow) return false;
		if (time() < $this->update_refresh_ts) return false;
		$this->update_refresh_ts = time() + 60; // each minute
		$this->__debug_msg('DCHub::update_refresh_ts() called');
		//return false;
		if (!isset($this->client_data[$cid])) return false;
		$ts = time();
		$uid = $this->client_data[$cid]->id;
		$nick = iconv($this->client_data[$cid]->charset, 'utf-8', $this->client_data[$cid]->nick);
		$nick = addslashes($nick);
		$sharesize = $this->client_data[$cid]->sharesize;
		if ($nick !== ''){
			$chat_id = 1;
			if (!mysql_ping()) mysql_db_open();
			mysql_select_db('olanet');
			if ($uid){
				if ($q = mysql_query("SELECT * FROM `lain_chat_users` WHERE `user_id` = '$uid' AND `chat_id` = '$chat_id'")){
					if (mysql_num_rows($q)){
						mysql_query("UPDATE `lain_chat_users` SET `last_refresh_ts` = '$ts' WHERE `user_id` = '$uid' AND `chat_id` = '$chat_id'");
					}else{
						if ($q = mysql_query("SELECT * FROM `lain_chat_users` WHERE `login` = '$nick' AND `chat_id` = '$chat_id'")){
							if (mysql_num_rows($q)){
								mysql_query("UPDATE `lain_chat_users` SET `last_refresh_ts` = '$ts', `user_id` = '$uid' WHERE `login` = '$nick' AND `chat_id` = '$chat_id'");
							}else{
								mysql_query("INSERT INTO `lain_chat_users` SET `last_refresh_ts` = '$ts', `user_id` = '$uid', `login` = '$nick', `chat_id` = '$chat_id'");
							}
						}
					}
				}
			}else{
				if ($q = mysql_query("SELECT * FROM `lain_chat_users` WHERE `login` = '$nick' AND `chat_id` = '$chat_id'")){
					if (mysql_num_rows($q)){
						mysql_query("UPDATE `lain_chat_users` SET `last_refresh_ts` = '$ts' WHERE `login` = '$nick' AND `chat_id` = '$chat_id'");
					}else{
						mysql_query("INSERT INTO `lain_chat_users` SET `last_refresh_ts` = '$ts', `login` = '$nick', `chat_id` = '$chat_id'");
					}
				}
			}
			mysql_query("UPDATE `lain_chat_users` SET `dc_sharesize` = '$sharesize' WHERE `login` = '$nick' AND `chat_id` = '$chat_id'");
			//mysql_query("UPDATE `lain_chat_users` SET `dc_ip` = '".$this->client_data[$cid]->ip."', `dc_email` = '".addslashes($this->client_data[$cid]->email)."' WHERE `login` = '$nick' AND `chat_id` = '$chat_id'");
		}
	}

	function client_desc($cid){
		$desc = '';
		if ($this->client_data[$cid]->id){
			// registered
			$desc .= '[animeforge.ru] ';
		}else{
			// guest
			$desc .= '[guest] ';
		}
		$desc .= $this->client_data[$cid]->desc;
		return $desc;
	}
	function __parse($cid){
		$this->update_refresh_ts($cid);
		parent::__parse($cid);
	}
	function spam_filter($cid, $message){
		$usernick = $this->usernicks[$cid];
		if (preg_match("#(\.zapto\.org|\.game\-host\.org)#ims", $message)){
			//$this->write("$To: ".W.Ed. From: w999d $<w999d> You are being kicked because: reason|
			$this->__delayed_disconnect($cid);
			$this->write($cid, "<".$this->hub_security_bot_name."> Spam not allowed!|");
			$this->__hub_security_main_chat("Spamer ".$usernick." killed!");
			return true;
		}
		return false;
	}
	function user_hub_commands($cid, $message){
		$usernick = $this->usernicks[$cid];
		/*
		+rules ������� ����
		+faq ����� ���������� �������
		+motd �������� ��� ���������
		+help ������� �� ��������
		+report <���������> ��������� ��������� ���������� (OP) � OP-���.
		+regme <���������> ��������� ������ �� ����������� ���� ���������� (OP) � OP-���.
		+myip ��� ip-�����
		+myinfo ���������� ��� ���
		+me <���������> �������� '+me' �� ��� ��� � ���������� ��������� � ���

		/fav - client only
		*/
		if ($this->spam_filter($cid, $message)) return true;
		
		
		if ($message == '+help'){
			$this->__s2c_help($cid);
		}elseif ($message == '+motd'){
			$this->__s2c_motd($cid);
		}elseif ($message == '+includes'){
			$this->main_chat_write_c('** '.$usernick.' '.$this->__includes_path);
			//$this->__includes_path
		}elseif ($message == '+progress'){
			$this->progress($cid);
		}elseif (preg_match("#^\+me\s+(\S.*)$#ims", $message, $msubs)){
			$this->main_chat_write_c('** '.$usernick.' '.$msubs[1]);
		}elseif ($message == '+hubname'){
			$this->hub_name($cid);
			$buf = '<Chii> Hub name: '.dcspecialchars($this->hub_name).'|';
			$this->write($cid, $buf);
		}elseif ($message == '+myip'){
			$buf = '<Chii> Your IP: '.$this->client_data[$cid]->ip.'|';
			$this->write($cid, $buf);
		}elseif ($message == '+stats'){
			/*
			[22:10:06] <b_w_johan> +stats
			[22:10:12] <b_w_johan> +hubstats
			[22:10:13] <b_w_johan> lol
			[22:10:20] <b_w_johan> you should make something for this
			[22:10:25] <b_w_johan> like amount data recieved send
			[22:10:35] <b_w_johan> current / top / max users
			[22:10:39] <b_w_johan> stuff like that
			[22:10:40] <b_w_johan> uptime
			*/
			$this->__plus_hubstats($cid);
			$buf = "<".$this->hub_security_bot_name."> 
                  STATS
-------------------------------------------
Uptime: ".seconds_format(time() - $this->start_ts)."          
".(function_exists('memory_get_usage')?'Memory usage: '.bytes2size(memory_get_usage()):'')."
".(function_exists('memory_get_usage')?'Memory real usage: '.bytes2size(memory_get_usage(true)):'')."

TCP (port) in: $this->tcpin        
TCP (port) out: $this->tcpout      

IN                       
\$MyINFO: $this->sr_myinfo               
\$Sr: $this->sr_sr                  
\$ConnecToMe: $this->sr_connecttome           
\$RevConnecToMe: $this->sr_revconnecttome
		
OUT                      
\$MyINFO: $this->ss_myinfo               
\$Sr: $this->ss_sr                   
\$ConnecToMe: $this->ss_connecttome           
\$RevConnecToMe: $this->ss_revconnecttome  
		
Cur/peak/max users :      
".count($this->usernicks)."/".$this->s_peak_users."/".$this->hub_max_users."       
-------------------------------------------
			|";
			$this->write($cid, $buf);
		}elseif ($message == '+hubstats'){
			$this->__plus_hubstats($cid);
		}elseif ($message == '+win'){
			$this->client_data[$cid]->charset = 'CP1251';
			$buf = '<Lain> Your charset is set to WINDOWS-1251|';
			$this->write($cid, $buf);
		}elseif ($message == '+utf'){
			$this->client_data[$cid]->charset = 'UTF-8';
			$buf = '<Lain> Your charset is set to UTF-8|';
			$this->write($cid, $buf);
		}elseif (preg_match("#^\+regme(\s+.*)?#ims", $message)){
			$msg = '    You can register your nickname at http://animeforge.ru/';
			$msg .= '    Вы можете зарегистрировать ваш ник на http://animeforge.ru/';
			$this->private_to($cid, $this->hub_security_bot_name, $this->charset_msg($cid, htmlentities($msg, ENT_NOQUOTES, 'utf-8')));
		/*}elseif (pregmatch("#^\+level\s+([0-9]+)\s+(\S+)$#ims", $message, $subs)){
			if ($this->client_data[$cid]->level < 10) return false;
			$newlevel = $subs[1];
			$opnick = $subs[2];
			if ($opcid = array_search($opnick, $this->usernicks)){
				if ($opuid = $this->client_data[$opcid]->id){
					if (!mysql_ping()) mysql_db_open();
					mysql_select_db('olanet');
					mysql_query("UPDATE `lain_chat_users` SET `dc_class` = '$newlevel' WHERE `user_id` = '$opuid' AND `chat_id` = '1'");
				}
				$this->client_data[$opcid]->level = $newlevel;
			}*/
		}elseif ($message == '+myinfo'){
			//
			/*
			Your info:
			Nick: W.Ed.
			Class: Master (10)
			IP: XXX.XXX.XXX.XXX

			Reg Information:
			Nick: W.Ed. Crypt:encrypted Pwd set?:yes Class:10
			LastLogin: Sun Oct 15 12:41:33 2006 LastIP:195.189.80.38
			LastError:Fri Oct 13 12:40:00 2006 ErrIP:195.189.80.38
			LoginCount: 18 ErrorCOunt: 24Protect: 0 HideKick: 0 all: 0
			HideKeys: 0
			Registered since: Thu Oct 12 11:26:49 2006 by: admin_user
			*/
			/*
			-+rules Правила хаба
			-+faq Часто задаваемые вопросы
			++motd Показать это сообщение
			++help Справка по командам
			-+report <сообщение> Отправить сообщение операторам (OP) в OP-чат.
			-+regme <сообщение> Отправить заявку на регистрацию ника операторам (OP) в OP-чат.
			++myip Мой ip-адрес
			-+myinfo Информация обо мне
			++me <сообщение> Заменяет '+me' на мой ник и отправляет сообщение в чат
			*/
		}else{
			return false;
		}
		return true;
	}
	function progress($cid){
		$buf = '

SUPPORTS (checklist):
magnet link format : magnet:?xt=urn:tree:tiger:TTTTTTTTTT&xl=999&dn=Filename.Ext		
========================		
NMDC Client-Hub Protocol	
------------------------		
<> - works
$BadPass - works
$Close - for OPs only, not needed yet ))
$ConnectToMe - works
$ForceMove - works
$GetINFO - works
$GetNickList - works
$GetPass - works
$Hello - works
$HubIsFull - not needed yet )))
$HubName - works
$Key - works, needed??? for compatibility only :: works
$Kick - works
$Lock - works
$LogedIn - works, deprecated??? ))
$MyINFO - works
$MyPass - works
$MultiConnectToMe - ? not needed yet ))
$MultiSearch - ? not needed yet ))
$NickList - works
$OpForceMove - works
$OpList - works
$Quit - works
$RevConnectToMe - works, full support :: not parsed :: *****
$Search - works, full support :: not parsed
$SR - works
$To - works
$ValidateDenide - works
$ValidateNick - works
$Version - works
===========================
DC++ extensions
---------------------------
$Supports - full support, needed to fill hub supports list
:: $Supports ChatOnly
:: $Supports $UserCommand - works 
:: $Supports NoGetINFO - works 
:: $Supports $UserIP2 (v2 of the $UserIP command)
:: $Supports NoHello - works
:: $Supports $GetZBlock 
$TTHSearch 
===========================
BCDC++ extensions
---------------------------
$GetMeta
$Meta
===========================
NMDC ZLine Extension
---------------------------
$Z
===========================
old proposal by Locate for the nmdc protocol
---------------------------
$SetviewportOptions
$GetviewportOptions
$viewportOptions
===========================
===========================
NMDC Client-Client Protocol
---------------------------
:::TCP:::
---------------------------
$Cancel
$Canceled
$Direction
$Error
$FileLength
$Get + CHUNK
$GetListLen
$Key
$Lock
$MaxedOut
$MyNick
$Send
---------------------------
:::UDP:::
---------------------------
$SR
$Ping (is not implemented by DC++) - deprecated
===========================
DC++ Extensions
---------------------------
$ADCGET
$Failed (response to $GetZBlock, $UGetBlock, $UGetZBlock)
$Supports  - see at hub commands
:: $Supports BZList 
:: $Supports GetZBlock - see at hub commands
:: $Supports GetTestZBlock
:: $Supports MiniSlots 
:: $Supports XmlBZList 
:: $Supports ADCGet 
:: $Supports TTHL (indicates support for the "tthl" namespace for $ADCGET)
:: $Supports TTHF (indicates support for the retrieving a file by its TTH through $ADCGET)
:: $Supports ZLIG 
$Sending 
$GetZBlock 
$UGetBlock 
$UGetZBlock
===================================	
Extensions
-----------------------------------
$BotINFO (to get $HubINFO) - works
$BotList - works
$CapabilitiesChat
$FeaturedNetworks
$HubTopic - works
$HubINFO (response for $BotINFO) - works
$GetListLen, $ListLen (is used to obtain the size of the remote user\'s DcLst style file list) - deprecated because of DcLst not exists now
$MCTo (YnHub, $To displayed as an ordinary chat-message.)
$SecuredExecutor (To secure and manage who can actually load and execute scripts)
$Up
$UpToo
$UserIP (sent by the client)
:: $Supports $ClientID


Registering hubs
To register a hub, connect to any hublist server. They are traditionally run on port 2501.
Hublist: $Lock ... Pk=...|
Hub: $Key ...|Hubname|Ip-address(:port)|Description|Users|Shared bytes|


http://dcpp.net/wiki/index.php/Special:Categories
http://dcpp.net/wiki/index.php/Category:NMDC_Protocol_Extensions
';
		$buf = '<Chii> '.dcspecialchars($buf).'|';
		$this->write($cid, $buf);
	}
}


?>
