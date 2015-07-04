<?php
/**
*
* peoplesign [English]
*
* @package language
* @version $Id: captcha_peoplesign.php,v 1.0.15 2011/04/10 15:00 hookerb Exp$
* @copyright (c) 2008-2011 Myricomp LLC
* @license http://opensource.org/licenses/gpl-license.php GNU Public License, v2
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'PEOPLESIGN_LANG'				=> 'en',
	'CAPTCHA_PEOPLESIGN'			=> 'peoplesign:)',
	'PEOPLESIGN_KEY'				=> 'Peoplesign Schlüssel',
	'PEOPLESIGN_KEY_EXPLAIN'		=> 'Dies ist Dein peoplesign Schlüssel. Besuche <a href="http://www.peoplesign.com">peoplesign.com</a> um es an Dich gemailt zu bekommen.',
	'PEOPLESIGN_OPTIONS'			=> 'Peoplesign Optionen String',
	'PEOPLESIGN_OPTIONS_EXPLAIN'	=> 'Passe an wie peoplesign aussieht, indem Du Deinen eigenen peoplesign Optionen String von <a href="http://www.peoplesign.com/main/customize.html">peoplesign.com</a> erhältst (bleibt es leer, werden die Standardeinstellungen verwendet).',
	'PEOPLESIGN_NO_KEY'				=> 'Beantrage das Dein peoplesign Schlüssel von <a href="http://www.peoplesign.com">peoplesign.com</a> am Dich gemailt wird.',
	'PEOPLESIGN_VERSION'			=> 'Peoplesign Version',

	# error messages
	'ERROR_BAD_CONFIG'		=> 'Peoplesign ist nicht richtig eingerichtet: Erzeugung der Client Session fehlgeschlagen. Bitte informiere den Administrator dieser Seite.',
	'ERROR_UNAVAILABLE'		=> 'Peoplesign ist nicht verfügbar.',
	'ERROR_UNEXPECTED'		=> 'Unerwarteter Status von get_peoplesign_session_status.',
	'ERROR_EMPTY_KEY'		=> 'Privaten Schlüssel erhalten der entweder leer war oder nur aus Leerzeichen bestand.',
	'NO_FRAMES_MESSAGE'		=> 'Da es scheine, dass Dein Browser keine "iframes" unterstützt, musst Du <a href="http://www.peoplesign.com/main/challenge.html">hier</a> klicken um zu beweisen, dass Du menschlich bist.',
	'ERROR_NO_SOCKET'		=> 'Bekomme keinen Socket zum Peoplesign Host.',
	'ERROR_EXCESSIVE_DATA'	=> 'Excessive Daten wurden vom Aufruf des Peoplesign Webservice zurück gesendet.',
	'ERROR_PREAMBLE'		=> 'FEHLER: Peoplesign Client: ',
	'ERROR_VISITOR_IP'		=> 'Ungültige visitor_ip',
	'ERROR_SERVER_STATUS'	=> 'Unerwarteter Server Status.',
	'ERROR_BAD_RESPONSE'	=> 'Schlechte HTTP Antwort vom Server.',
	'ERROR_WRONG_ANSWER'	=> 'Eines deiner unteren Antworten war unkorrekt..',

	// return codes
	'CODE_INVALID_PRIVATE_KEY'		=> 'invalid_private_key',
	'CODE_SERVER_UNREACHABLE'		=> 'server_unreachable',
	'CODE_INVALID_SERVER_RESPONSE'	=> 'invalid_server_response'
));