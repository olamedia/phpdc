<?php


/* notifiche */
#define NOTIFY_ERROR		-1	// errore generico, data = NULL
//					//
#define NOTIFY_DISCONNECTED	1	// disconnesso, data = NULL
define("DC_NOTIFY_CONNECTING",	2);	// connessione in corso, data = NULL
#define NOTIFY_ERROR_RESOLV	3	// errore di risoluzione, data = NULL
#define NOTIFY_ERROR_CONNECT	4	// errore di connessione, data = NULL
#define NOTIFY_ERROR_TIMEOUT	5	// timeout di connessione, data = NULL
#define NOTIFY_CONNECTED	6	// connesso, data = NULL
#define NOTIFY_JOIN		7	// utente logginato, data = nick
#define NOTIFY_ASKPASS		8	// richiesta della password, data = NULL
#define NOTIFY_HUBNAME		9	// aggiornamento nome hub, data = hubname
#define NOTIFY_PART		10	// utente uscito, data = nick
#define NOTIFY_CHATMSG		11	// messaggio in main, data = struct message_t { <nick>, <messaggio> };
#define NOTIFY_PRIVMSG		12	// messaggio privato, data = struct message_t { <nick>, <messaggio> };


/* prefissi dei comandi */
define ("DC_PREFIX_LOCK",		"\$Lock");
define ("DC_PREFIX_KEY", "\$Key");
#define DC_PREFIX_CHATMSG	"<"
#define DC_PREFIX_HUBNAME	"$HubName"
define ("DC_PREFIX_HELLO", "\$Hello");
#define DC_PREFIX_VERSION	"$Version"
#define DC_PREFIX_VALIDATENICK	"$ValidateNick"
#define DC_PREFIX_MYINFO	"$MyINFO"
#define DC_PREFIX_QUIT		"$Quit"
#define DC_PREFIX_TO		"$To:"

/* sintassi dei comandi */
define ("DC_CMD_LOCK", DC_PREFIX_LOCK." %s Pk=%s|");	// lock string, private/public key
define ("DC_CMD_KEY", DC_PREFIX_KEY." %s|");		// key generata dalla lock
#define DC_CMD_CHATMSG		DC_PREFIX_CHATMSG "%s> %s|"	// mittente, messaggio -- messaggio in main chat
#define DC_CMD_HUBNAME		DC_PREFIX_HUBNAME " %s|"	// nome dell'hub
define("DC_CMD_HELLO", DC_PREFIX_HELLO." %s|");		// nick del nuovo utente entrato
#define DC_CMD_VERSION		DC_PREFIX_VERSION " %s|"	// versione del protocollo
#define DC_CMD_VALIDATENICK	DC_PREFIX_VALIDATENICK " %s|"	// nick -- valida il nick
#define DC_CMD_MYINFO		DC_PREFIX_MYINFO " $%s %s <%s>$ $%s%c$%s$%llu$|"
// destinatario (login: ALL), nick, tag, speed, type, email, sharesize
#define DC_CMD_QUIT		DC_PREFIX_QUIT " %s|"		// nick -- utente uscito dall'hub
#define DC_CMD_TO		DC_PREFIX_TO " %s From: %s $<%s> %s|"
// destinatario, mittente, mittente, messaggio -- messaggio privato

/** comandi da parsare **/
#define DC_CMD_VALIDATEDENIDE	"$ValidateDenide %s|"		// nick -- validazione nick negata
#define DC_CMD_HUBISFULL	"$HubIsFull|"			// l'hub Ã¨ pieno
#define DC_CMD_GETPASS		"$GetPass|"			// richiesta password
#define DC_CMD_MYPASS		"$MyPass %s|"			// password -- invia la password
#define DC_CMD_BADPASS		"$BadPass %s|"			// password errata
#define DC_CMD_LOGEDIN		"$LogedIn %s|"			// nick -- operatore logginato
#define DC_CMD_NICKLIST		"$NickList "			// lista nick -- lista utenti
#define DC_CMD_OPLIST		"$OpList "			// lista nick -- lista operatori
define("DC_CMD_GETNICKLIST", "\$GetNickList|");	// richiede la lista utenti
#define DC_CMD_OPFORCEMOVE	"$OpForceMove $Who:%s$Where:%s$Msg:%s|"
// vittima, server, motivazione -- redirige un utente su un altro hub
#define DC_CMD_FORCEMOVE	"$ForceMove %s|"		// server -- redirezione forzata
#define DC_CMD_KICK		"$Kick %s|"			// nick -- caccia un utente dall'hub
#define DC_CMD_CLOSE		"$Close %s|"			// nick -- disconnette un utente dall'hub

?>
