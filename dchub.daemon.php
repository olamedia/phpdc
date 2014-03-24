<?php
if (!defined("NX_PATH")) define ("NX_PATH", getcwd()."/");
require_once(NX_PATH."lib/global.lib.php");
require_once(NX_PATH."conf/mysql.conf.php");
require_once(NX_PATH."lib/auth.lib.php");
require_once(NX_PATH."lib/mysql.lib.php");
require_once(NX_PATH."lib/dcpp.dctc.lib.php");
require_once(NX_PATH."lib/dchub.class.php");

//$dchub =& new DCHub('chibi.animeforge.ru', 4112);
$dchub =& new BSDC_Hub_Prototype('chibi.animeforge.ru', 4112);
$dchub->hub_name = '^_^ Anime Chibi [min 2Gb] [anime manga games] [BSDC phpHUB daemon http://dc.animeforge.ru]';
$dchub->hub_min_share_bytes = 2*1024*1024*1024; // 2Gb
$dchub->pidFileLocation = '/tmp/DCHub/dchub.chibi.pid';
$dchub->log_filename = '/tmp/DCHub/DCHub.chibi.log';
$dchub->start();

?>
