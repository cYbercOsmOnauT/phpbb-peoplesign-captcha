<?php
/**
 *
 * @package       VC
 * @author        Tekin Birdüzen <t.birduezen@web-coding.eu>
 * extending from: phpbb_captcha_qa_plugin.php 10484 2010-02-08 16:43:39Z bantu $
 * @copyright (c) 2006, 2008 phpBB Group
 * @since         30.05.15
 * @version       1.0.1
 * @copyright     Tekin Birdüzen
 * @license       http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace cosmo\peoplesign\captcha;

/**
 * Peoplesign captcha with extending of the QA captcha class.
 *
 * @package VC
 */
class peoplesign extends \phpbb\captcha\plugins\qa
{
	// Setting my_peoplesign_key_override will override the value that is set in
	//  the DB and disable setting it via the ACP.
	//  use this if you really want to configure this here.
	// You must visit the ACP to adjust the challenge option string.
	private static $my_peoplesign_key_override = '';

	private static $peoplesign_location = 'phpBB3';
	private static $peoplesign_session_id = '';
	private $code = '';
	private static $ps_opt_reg_len = 250; // split challenge option string across
	private static $ps_opt_num_reg = 4; //  multiple DB entries.

	private static $peoplesign_config = array();

	/**
	 * @var \phpbb\db\driver\driver_interface
	 */
	protected $db;

	/**
	 * @var \phpbb\cache\service
	 */
	protected $cache;

	/**
	 * @var \phpbb\config\config
	 */
	protected $config;

	/**
	 * @var \phpbb\template\template
	 */
	protected $template;

	/**
	 * @var \phpbb\user
	 */
	protected $user;

	/**
	 * @var , \phpbb\request\request_interface
	 */
	protected $request;

	/**
	 * @var \phpbb\phpbb_log
	 */
	private $phpbb_log;

	/**
	 *
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\cache\service $cache
	 * @param \phpbb\config\config $config
	 * @param \phpbb\template\template $template
	 * @param \phpbb\user $user
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, \phpbb\request\request_interface $request)
	{
		$this->db = $db;
		$this->cache = $cache;
		// Only take the needed config parts
		$names = self::get_peoplesign_confignames();
		$this->config = array();
		foreach ($names AS $name)
		{
			$this->config[$name] = $config->offsetGet($name);
		}
		// Clean out the empty fields
		$this->config = array_diff($this->config, array(''));
		$this->template = $template;
		$this->user = $user;
		$this->request = $request;
		$this->phpbb_log = $GLOBALS['phpbb_container']->get('log');

		// Fill the config array
		self::$peoplesign_config = array(
			'PEOPLESIGN_PLUGIN_PHP_VERSION' => 'psPlugPHP_2.0.0',
			'PEOPLESIGN_VERSION_ID' => '1.0.15',
			'PEOPLESIGN_HOST' => 'peoplesign.com',
			'PEOPLESIGN_GET_CHALLENGE_SESSION_ID_PATH' => '/main/getChallengeSessionID',
			'PEOPLESIGN_CHALLENGE_SESSION_ID_NAME' => 'challengeSessionID',
			'PEOPLESIGN_GET_CHALLENGE_SESSION_STATUS_PATH' => '/main/getChallengeSessionStatus_v2',
			'PEOPLESIGN_CHALLENGE_RESPONSE_NAME' => 'captcha_peoplesignCRS',
			'CONNECTION_OPEN_TIMEOUT' => 5,
			'CONNECTION_READ_TIMEOUT' => 10,
			'PEOPLESIGN_IFRAME_WIDTH' => '335', // change these settings if java-disabled renderings have scroll bars
			'PEOPLESIGN_IFRAME_HEIGHT' => '380' //  Your individual CAPTCHA setting are too large to be displayed with theses settings
		);
		self::$peoplesign_config = array_merge(self::$peoplesign_config, array(
			'PEOPLESIGN_GET_CHALLENGE_SESSION_ID_URL', 'http://' . self::$peoplesign_config['PEOPLESIGN_HOST'] . self::$peoplesign_config['PEOPLESIGN_GET_CHALLENGE_SESSION_ID_PATH'],
			'PEOPLESIGN_CHALLENGE_URL' => 'http://' . self::$peoplesign_config['PEOPLESIGN_HOST'] . '/main/challenge.html',
			'PEOPLESIGN_GET_CHALLENGE_SESSION_STATUS_URL', 'http://' . self::$peoplesign_config['PEOPLESIGN_HOST'] . self::$peoplesign_config['PEOPLESIGN_GET_CHALLENGE_SESSION_STATUS_PATH']
		));
	}

	public function init($type = 1)
	{
		$this->load_language();
	}

	/**
	 *  API function
	 */
	public static function get_instance()
	{
		$instance = new phpbb_captcha_peoplesign_plugin();

		return $instance;
	}

	/**
	 * Determines weather or not the captcha is available and ready.
	 * Peoplesign requires a key issued from peoplesign.com
	 **/
	public function is_available()
	{
		// load language file for pretty display in the ACP dropdown
		$this->load_language();
		return !((!array_key_exists('peoplesign_key', $this->config) || ('' === $this->config['peoplesign_key']))) && ('' === self::$my_peoplesign_key_override);
	}

	public function uninstall()
	{
	}

	// The argument has to be there for qa compatibility
	public function garbage_collect($type = 0)
	{

	}

	public function install()
	{
	}

	/**
	 *  API function
	 */
	public function has_config()
	{
		return true;
	}

	/**
	 *  API function
	 */
	static public function get_name()
	{
		return 'CAPTCHA_PEOPLESIGN';
	}

	/**
	 *  API function - send the question to the template
	 */
	public function get_template()
	{
		$this->load_code();

		$this->template->assign_vars(array(
			'CODE' => $this->code,
			'S_CONFIRM_CODE' => true, // required for max login attempts
		));

		return '@cosmo_peoplesign/captcha_peoplesign.html';
	}

	public function get_demo_template()
	{
		$this->load_code();

		$this->template->assign_vars(array(
			'CODE' => $this->code,
		));

		return '@cosmo_peoplesign/captcha_peoplesign_acp_demo.html';
	}

	/**
	 * Check the captcha to determine if the user can pass
	 * returns:
	 *    false - success, user passes
	 *    true  - failure, user does not pass
	 **/
	public function validate()
	{
		$this->request_session_id();

		if ($this->solved)
		{
			return false;
		}

		$response = $this->process_peoplesign_response(self::$peoplesign_session_id, '', self::$peoplesign_location, $this->get_peoplesign_key());

		if ($response)
		{
			$this->solved = true;
			return false;
		}
		return $this->user->lang['ERROR_WRONG_ANSWER']; // evaluates to true (error), and displays a message to the user that there was a problem
	}

	public function reset()
	{
		$key = $this->get_peoplesign_key();
		if ('' === $key)
		{
			return '';
		}

		// Display the CAPTCHA to the user in the same language as the rest of the board.
		$language = $this->request->variable('lang', $this->user->lang_name);
		$options = $this->get_options_string();
		$options = self::set_captcha_language($options, $language);

		$peoplesign_wrapper_version = 'phpBB3_' . self::$peoplesign_config['PEOPLESIGN_VERSION_ID'];

		$response = $this->get_peoplesign_session_id(
			$this->get_peoplesign_key(),
			$this->user->ip,
			$options,
			self::$peoplesign_location,
			$peoplesign_wrapper_version,
			self::$peoplesign_session_id
		);
		self::$peoplesign_session_id = $response;
		$this->solved = false;
	}

	/**
	 * Handle the Administration Control Panel configuration
	 **/
	public function acp_page($id, &$module)
	{
		$this->load_language();

		$module->tpl_name = '@cosmo_peoplesign/captcha_peoplesign_acp';
		$module->page_title = 'ACP_VC_SETTINGS';

		// From captcha_peoplesign.html ...
		$form_key = 'acp_captcha';
		add_form_key($form_key);

		// button options on the configure page
		$submit = $this->request->variable('submit', '');
		$preview = $this->request->variable('preview', '');

		// On preview or submit, then set the values
		if (($preview || $submit) && check_form_key($form_key))
		{
			$captcha_vars = self::get_peoplesign_confignames(false);
			foreach ($captcha_vars as $captcha_var)
			{
				$value = $this->request->variable($captcha_var, '');
				// Handle the peoplesign options specially... split them across DB entries
				if ($captcha_var == 'peoplesign_options')
				{
					$value = str_replace("&amp;", "&", $value);

					if (strlen($value) > (self::$ps_opt_reg_len * self::$ps_opt_num_reg))
					{
						// Give an error to the user. value is too large for the DB
						$this->config->set($captcha_var, $value);
					}
					else
					{
						// Store the config data across multiple DB entries, since a DB field is varchar(255)
						$value = str_pad($value, self::$ps_opt_reg_len * self::$ps_opt_num_reg);
						for ($i = 0; $i < self::$ps_opt_num_reg; $i++)
						{
							$this->config->set($captcha_var . $i, substr($value, $i * self::$ps_opt_reg_len, self::$ps_opt_reg_len));
						}
					}
				}
				else
				{
					$this->config->set($captcha_var, $value);
				}
			}
			if ($submit)
			{
				$this->phpbb_log->add('admin', 'LOG_CONFIG_VISUAL');
				trigger_error($this->user->lang['CONFIG_UPDATED'] .
					adm_back_link($module->u_action));
			}
		}
		else
		{
			if ($submit || $preview)
			{
				trigger_error($this->user->lang['FORM_INVALID'] . adm_back_link($module->u_action));
			}
		}

		$this->reset();

		$this->template->assign_vars(array(
			'PEOPLESIGN_KEY' => $this->get_peoplesign_key(),
			'PEOPLESIGN_OPTIONS' => $this->get_options_string(),
			'PEOPLESIGN_VERSION_ID' => self::$peoplesign_config['PEOPLESIGN_VERSION_ID'],
			'CAPTCHA_PREVIEW' => $this->get_demo_template($id),
			'CAPTCHA_NAME' => $this->get_service_name(),
			'U_ACTION' => $module->u_action
		));
	}

	/**
	 *  Private methods
	 */

	private function load_language()
	{
		// load our language file if needed
		if (!array_key_exists('lang', $this->user) || !array_key_exists('PEOPLESIGN_LANG', $this->user->lang))
		{
			$this->user->add_lang_ext('cosmo/peoplesign', 'captcha_peoplesign');
		}
	}

	private function load_code()
	{
		if (!$this->get_peoplesign_key())
		{
			$this->load_language();
			$this->code = $this->user->lang['PEOPLESIGN_NO_KEY'];
			return;
		}
		elseif ($this->solved)
		{
			$this->reset();
		}
		$this->request_session_id();
		if ('' !== self::$peoplesign_session_id)
		{
			$this->code = $this->get_peoplesign_javascript(self::$peoplesign_session_id);
		}
	}

	/**
	 * reconstitute the peoplesign options from xiple DB entries
	 **/
	private function get_options_string()
	{
		$ps_opts = '';
		// reconstitute the option string from the many DB entries
		for ($i = 0; $i < self::$ps_opt_num_reg; $i++)
		{
			if (!array_key_exists('peoplesign_options' . $i, $this->config))
			{
				$this->config['peoplesign_options' . $i] = ''; // initialize
			}
			$ps_opts .= $this->config['peoplesign_options' . $i];
		}
		// trim off the space padding used when storing it to the DB
		$ps_opts = trim($ps_opts);
		return $ps_opts;
	}

	/**
	 * populate the Peoplesign session id var
	 **/
	private function request_session_id()
	{
		if ('' === self::$peoplesign_session_id)
		{
			self::$peoplesign_session_id = $this->request->variable(self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'], '');
			$this->reset();
		}
	}

	private function get_peoplesign_key()
	{
		if ('' === self::$my_peoplesign_key_override)
		{
			if (!array_key_exists('peoplesign_key', $this->config))
			{
				$this->config['peoplesign_key'] = ''; // initialize
			}

			return $this->config['peoplesign_key'];
		}
		return self::$my_peoplesign_key_override;
	}

	/**
	 * Display the CAPTCHA using javascript
	 *
	 * @param    string    peoplesign_session_id    The session id obtained from get_peoplesign_session_id used to identify this session.
	 * @param    string    iframe_width            Optional. If a browser has javascript disabled, the peoplesign challenge will be sent in an iframe having the specified width.
	 * @param    string    iframe_height            Optional. If a browser has javascript disabled, the peoplesign challenge will be sent in an iframe having the specified height.
	 *
	 * @return    string                    The HTML that will display the CAPTCHA.
	 */
	private function get_peoplesign_javascript($peoplesign_session_id, $iframe_width = '', $iframe_height = '')
	{
		if ('' === $iframe_width)
		{
			$iframe_width = self::$peoplesign_config['PEOPLESIGN_IFRAME_WIDTH'];
		}
		if ('' === $iframe_height)
		{
			$iframe_height = self::$peoplesign_config['PEOPLESIGN_IFRAME_HEIGHT'];
		}

		// Prevent the browser from doing any caching
		$peoplesign_html =
			"<script type=text/javascript> " .
			"document.write('<script type=\"text/javascript\" src=\"" .
			self::$peoplesign_config['PEOPLESIGN_CHALLENGE_URL'] .
			"?" . self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'] .
			"=$peoplesign_session_id&addJSWrapper=true" .
			"&ts=' +(new Date()).getTime() +'\" " .
			"id=\"yeOldePeopleSignJS\"><\/script>'); </script> " .
			"<noscript>" .
			$this->get_peoplesign_iframe($peoplesign_session_id, $iframe_width, $iframe_height) .
			"</noscript>";

		return $peoplesign_html;
	}

	/**
	 * Display the CAPTCHA using iframes.
	 *
	 * @param    string peoplesign_session_id    The session id obtained from get_peoplesign_session_id used to identify this session.
	 * @param    string iframe_width            Optional. If a browser has javascript disabled, the peoplesign challenge will be sent in an iframe having the specified width.
	 * @param    string iframe_height            Optional. If a browser has javascript disabled, the peoplesign challenge will be sent in an iframe having the specified height.
	 *
	 * @return    string                    The HTML that will display the CAPTCHA.
	 */
	private function get_peoplesign_iframe($peoplesign_session_id, $iframe_width = '', $iframe_height = '')
	{
		if ('' === $iframe_width)
		{
			$iframe_width = self::$peoplesign_config['PEOPLESIGN_IFRAME_WIDTH'];
		}
		if ('' === $iframe_height)
		{
			$iframe_height = self::$peoplesign_config['PEOPLESIGN_IFRAME_HEIGHT'];
		}

		$peoplesign_html =
			'<iframe src="' . self::$peoplesign_config['PEOPLESIGN_CHALLENGE_URL'] .
			'?' . self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'] .
			"={$peoplesign_session_id}\" width=\"{$iframe_width}\" height=\"{$iframe_height}\" frameborder=\"0\" allowTransparency=\"true\"> " .
			'<p>' . $this->user->lang['NO_FRAMES_MESSAGE'] . '</p>
			</iframe>
			<input name="' . self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'] . "\" type=\"hidden\" value=\"$peoplesign_session_id\" />";

		return $peoplesign_html;
	}

	/**
	 * makes a web service call to PEOPLESIGN_HOST, returns csid
	 * No need to call this if you call get_peoplesign_html()
	 * If you don't want to use get_peoplesign_html, call this and then pass the return value to get_peoplesign_javascript or get_peoplesign_iframe
	 * A peoplesign session id is assigned to a given visitor and is valid until he/she passes a challenge
	 *
	 * @param                                              string    peoplesign_key                                The site's private key obtained from peoplesign.com
	 * @param                                              string    visitor_ip                                    The ipaddress of the visitor, this value can be set by the user.
	 * @param                                              string    peoplesign_options (optional)                The challenge option string obtained from the customize page at peoplesign.com
	 * @param                                              string    client_location                                The location id on the webiste where the CAPTCHA appears.
	 * @param    wrapper_plugin_info (optional) The version of the web framework wrapper used to call this function.
	 * @param    current_peoplesign_session_id  (optional) The existing session id if one exists.  It is validated again in this function.
	 *
	 * @return    array    status, session_id
	 */
	private function get_peoplesign_session_id($peoplesign_key, $visitor_ip, $peoplesign_options, $client_location = 'default', $wrapper_plugin_info, $current_peoplesign_session_id = '')
	{
		$this->load_language();
		// ensure private key is not the empty string
		if ('' === $peoplesign_key)
		{
			$this->print_error($this->user->lang['ERROR_EMPTY_KEY']);
			return ('');
		}

		if ('' === $visitor_ip)
		{
			$visitor_ip = $_SERVER['REMOTE_ADDR'];
		}

		// challenge option string - accept a string or an array for flexibility to the user
		if (is_string($peoplesign_options))
		{
			parse_str($peoplesign_options, $peoplesign_options);
		}

		$plugin_info = urlencode(trim($wrapper_plugin_info . ' ' . self::$peoplesign_config['PEOPLESIGN_PLUGIN_PHP_VERSION']));
		$peoplesign_key = urlencode(trim($peoplesign_key));
		$visitor_ip = urlencode(trim($visitor_ip));
		$client_location = urlencode(trim($client_location));
		$current_peoplesign_session_id = urlencode(trim($current_peoplesign_session_id));
		$peoplesign_args = '';

		// create an encoded string containing peoplesign_options
		if (is_array($peoplesign_options))
		{
			foreach ($peoplesign_options as $name => $value)
			{
				$peoplesign_args .= '&' . urlencode($name) . '=' . urlencode($value);
			}
		}

		$response = $this->http_post(80, self::$peoplesign_config['PEOPLESIGN_HOST'], self::$peoplesign_config['PEOPLESIGN_GET_CHALLENGE_SESSION_ID_PATH'], "privateKey={$peoplesign_key}&visitorIP={$visitor_ip}&clientLocation={$client_location}&pluginInfo={$plugin_info}&" . self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'] . "={$current_peoplesign_session_id}{$peoplesign_args}");

		// default value to return the empty string
		$peoplesign_session_id = '';

		if ($response)
		{
			// inspect the response for a status string and the session id
			$peoplesign_session_id = '';
			$status = $this->user->lang['CODE_SERVER_UNREACHABLE'];

			$tmp = explode("\n", $response, 2);
			$tmp_count = count($tmp);

			if ($tmp_count >= 1)
			{
				// pass the status message through to the error message.
				$status = $tmp[0];
				if ($tmp_count >= 2)
				{
					$peoplesign_session_id = $tmp[1];
				}
			}

			// The server will respond with "success"
			if ('success' !== $status)
			{
				$this->print_error($this->user->lang['ERROR_SERVER_STATUS'] . " ({$status})");
			}
		}
		else
		{
			$this->print_error($this->user->lang['ERROR_BAD_RESPONSE']);
		}

		return ($peoplesign_session_id);
	}

	/**
	 * use the return value to determine if the user's response is correct.
	 * calls get_peoplesign_session_status
	 * decides if the user's response is correct based on the status string.  Note: the response is treated as "correct" if the peoplesign server did not respond.
	 *
	 * @param    peoplesign_session_id      The value if PEOPLESIGN_CHALLENGE_SESSION_ID_NAME read when processing the form submission.
	 * @param    peoplesign_response        The value of PEOPLESIGN_CHALLENGE_RESPONSE_NAME read when processing the form submission.  if you are using get_peoplesign_iframe (rare) PEOPLESIGN_CHALLENGE_RESPONSE_NAME won't be set, and you can pass null or empty string for this value when calling get_peoplesign_session_status
	 * @param    client_location            MUST match the argument passed to get_peoplesign_html.
	 * @param    peoplesign_key             obtain your key from peoplesign.com
	 *
	 * @return    boolean                    true for pass, false for fail
	 */
	private function process_peoplesign_response($peoplesign_session_id, $peoplesign_response, $client_location = 'default', $peoplesign_key)
	{
		// If these variables were not supplied, attempt to retrieve them from post
		if (!$peoplesign_session_id)
		{
			$peoplesign_session_id = $this->get_post_var(self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'], '');
		}

		if (!$peoplesign_response)
		{
			$peoplesign_response = $this->get_post_var(self::$peoplesign_config['PEOPLESIGN_CHALLENGE_RESPONSE_NAME'], '');
		}

		$status = $this->get_peoplesign_session_status($peoplesign_session_id, $peoplesign_response, $client_location, $peoplesign_key);

		switch ($status)
		{
			case 'pass':
				$allow_pass = true;
				break;
			case 'fail':
			case 'notRequested':
			case 'awaitingResponse':
			case 'invalidChallengeSessionID':
				$allow_pass = false;
				break;
			default:
				$this->load_language();
				$this->print_error($this->user->lang['ERROR_SERVER_STATUS'] . ": {$status}");
				// if we have an unexpected status from the peoplesign web server, we can
				// assume it's having trouble and allow the user to pass.
				$allow_pass = true;
		}

		return $allow_pass;
	}

	/**
	 * Submit the request to the server
	 *
	 * @param integer $port
	 * @param string $host
	 * @param string $path
	 * @param string $encoded_payload
	 *
	 * @return
	 */
	private function http_post($port, $host, $path, $encoded_payload)
	{
		$this->load_language();
		$http_request =
			"POST $path HTTP/1.0\n" .
			"Host: $host\n" .
			"Content-Type: application/x-www-form-urlencoded\n" .
			'Content-Length: ' . strlen($encoded_payload) . "\n" .
			"User-Agent: peoplesignClient-PHP\n\n" .
			$encoded_payload;

		$http_response = '';

		// apparently default_socket_timeout does not effect dnslookups
		// It's probably unnecessary to set this because 2 specific timeouts are set
		// below: (1) timeout parameter to fsockopen (2) stream_set_timeout() for fgets() calls
		ini_set('default_socket_timeout', self::$peoplesign_config['CONNECTION_OPEN_TIMEOUT']);

		// WARNING:  timeout will not be respected if gethostbyname hangs
		//(gethostbyname may be called to resolve $host in fsockopen)

		$socket = @fsockopen($host, $port, $errno, $errstr, self::$peoplesign_config['CONNECTION_OPEN_TIMEOUT']);

		if (!$socket)
		{
			$this->print_error($this->user->lang['ERROR_NO_SOCKET'] . " ($errstr: [$errno])");
			return $this->user->lang['CODE_SERVER_UNREACHABLE'];
		}

		fwrite($socket, $http_request);

		$blockSize = 1160;
		$block = 0;
		$maxBlocks = 1024;

		stream_set_timeout($socket, self::$peoplesign_config['CONNECTION_READ_TIMEOUT']);
		while (!feof($socket))
		{
			$http_response .= fgets($socket, $blockSize);
			if ($block >= $maxBlocks)
			{
				$this->print_error($this->user->lang['ERROR_EXCESSIVE_DATA']);
				fclose($socket);
				return $this->user->lang['CODE_INVALID_SERVER_RESPONSE'];
			}
			$block++;
		}
		fclose($socket);

		$return_value = explode("\r\n\r\n", $http_response, 2);

		return $return_value[1];
	}

	private function print_error($message)
	{
		$this->load_language();
		error_log($this->user->lang['ERROR_PREAMBLE'] . $message);
	}

	/**
	 * Contacts the peoplesign server to validate the user's response.
	 *
	 * @param    string peoplesign_session_id    The value if PEOPLESIGN_CHALLENGE_SESSION_ID_NAME read when processsing the form submission.
	 * @param    string peoplesign_response        The value of PEOPLESIGN_CHALLENGE_RESPONSE_NAME read when processing the form submission.  if you are using get_peoplesign_iframe (rare) PEOPLESIGN_CHALLENGE_RESPONSE_NAME won't be set, and you can pass null or empty string for this value when calling get_peoplesign_session_status
	 * @param    string client_location        MUST match the argument passed to get_peoplesign_html.
	 * @param    string peoplesign_key            obtain your key from peoplesign.com
	 *
	 * @return    string                    pass, fail or awaitingResponse
	 */
	private function get_peoplesign_session_status($peoplesign_session_id, $peoplesign_response, $client_location = 'default', $peoplesign_key)
	{
		if (!$peoplesign_response)
		{
			$peoplesign_response = $this->get_post_var(self::$peoplesign_config['PEOPLESIGN_CHALLENGE_RESPONSE_NAME'], '');
		}

		$peoplesign_response = urlencode($peoplesign_response);
		$client_location = urlencode($client_location);
		$peoplesign_key = urlencode($peoplesign_key);

		$status = $this->http_post(80, self::$peoplesign_config['PEOPLESIGN_HOST'], self::$peoplesign_config['PEOPLESIGN_GET_CHALLENGE_SESSION_STATUS_PATH'], self::$peoplesign_config['PEOPLESIGN_CHALLENGE_SESSION_ID_NAME'] . "={$peoplesign_session_id}&" . self::$peoplesign_config['PEOPLESIGN_CHALLENGE_RESPONSE_NAME'] . "={$peoplesign_response}&privateKey={$peoplesign_key}&clientLocation={$client_location}");

		return $status;
	}

	/**
	 *  Static methods
	 */

	private static function get_peoplesign_confignames($long = true)
	{
		$names = array('peoplesign_key');
		if ($long)
		{
			for ($i = 0; $i < self::$ps_opt_num_reg; $i++)
			{
				$names[] = 'peoplesign_options' . $i;
			}
		}
		else
		{
			$names[] = 'peoplesign_options';
		}
		return $names;
	}

	private function get_post_var($variable_name, $variable_type)
	{

		$return = $this->request->variable($variable_name, $variable_type);
		$return = str_replace('&amp;', '&', $return);

		return $return;
	}

	/**
	 * Set the specified language option in the challenge option string.
	 *
	 * @param    string option_string    The challenge option string taken from the database, and specified from the ACP.
	 * @param    string language        The language specifier taken directly from the user's setting.
	 *
	 * If the specified language is not supported, or recognized by the peoplesign server (highly doubtful), the CAPTCHA language will be defaulted to english by the Peoplesign server.
	 */
	private static function set_captcha_language($option_string, $language = '')
	{
		# Do not add an empty language.
		if ('' === $language)
		{
			return $option_string;
		}

		# Only add the language if it isn't already specified.
		$pos = strpos($option_string, 'language');
		if (false === $pos)
		{
			# The option string has no language setting defined.

			# Check if the string is empty.
			if ('' === $option_string)
			{
				# There was no challenge option settings, create one.
				return 'language=' . $language;
			}
			# Add the language setting to the existing challenge option
			return $option_string . '&language=' . $language;
		}

		# If the language setting has already been specified, then honor it.
		return $option_string;
	}
}
