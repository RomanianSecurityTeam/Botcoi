<?php

header('Content-Type: text/html; charset=UTF-8');
set_time_limit(0);

require __dir__ . '/config.php';

/**
 * Botcoi class for handling and interactig with the user's
 * input via RST's AJAX chat
 *
 * @version 1.0
 * @author Gecko - http://webtoil.co
 */
class Botcoi
{
	/**
	 * The object which handles the bot's
	 * credentials which are used for the login process
	 *
	 * This object will be populated with data from
	 * the inc/config.php file
	 *
	 * @since 1.0
	 */
	private $user;
	
	/**
	 * Online users array
	 *
	 * This object will be populated with data if
	 * the bot can log into the chat
	 *
	 * @since 1.0
	 */
	private $users = array();
	
	/**
	 * Chat messages object
	 * Holds the last 10 messages on the chat
	 *
	 * This object will be populated with data if
	 * the bot can log into the chat
	 *
	 * @since 1.0
	 */
	private $messages;
	
	/**
	 * The file onto which we save log information
	 *
	 * @since 1.0
	 */
	private $log_file;
	
	/**
	 * The file onto which we save the user's current cookie
	 *
	 * @since 1.0
	 */
	private $cookie_file;
	
	/**
	 * The users whose messages we don't mind
	 *
	 * @since 1.0
	 */
	private $ignored_users = array('RST', 'Botcoi');
	
	/**
	 * Some swearing words
	 *
	 * @since 1.0
	 */
	private $swearing_words = array('pula', 'pizda', 'ma-?t[ai]{1,2}', 'cacat', 'pisat', 'retardat', 'idiot', 'cretin', 'faggot');
	
	/**
	 * All the messages that we processed
	 *
	 * @since 1.0
	 */
	private $processed_messages = array();
	
	/**
	 * The current response queue to be posted to the chat
	 *
	 * @since 1.0
	 */
	private $response_queue = array();
	
	/**
	 * Latest html retrieved
	 *
	 * @since 1.0
	 */
	private $latest_html = '';

	/**
	 * Remember if the bot is currently banned
	 *
	 * @since 1.0
	 */
	private $is_banned = false;

	private $lastID = '';

	/**
	 * Main function executed on class initialization
	 *
	 * @since 1.0
	 */
	function __construct ()
	{
		global $user;
		global $log_file;
		global $cookie_file;

		$this->user = $user;
		$this->log_file = $log_file;
		$this->cookie_file = $cookie_file;
	}

	/**
	 * The cURL-based HTML source code retriever
	 *
	 * @since 1.0
	 * @param url string
	 * @param options array of additional cURL options
	 */
	private function get_html ($url, $options = array())
	{
		$c = curl_init();
		$curl_options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_REFERER => $url,
			CURLOPT_COOKIEFILE => $this->cookie_file,
			CURLOPT_COOKIEJAR => $this->cookie_file
		);

		foreach ($options as $curl_opt => $value)
			$curl_options[$curl_opt] = $value;

		curl_setopt_array($c, $curl_options);

		$this->latest_html = curl_exec($c);

		return $this->latest_html;
	}

	/**
	 * Check if the cookie file exists yet
	 * If so, we're logged in
	 * Otherwise, we're not
	 *
	 * @since 1.0
	 */
	private function check_login ()
	{
		if (file_exists($this->cookie_file))
			return true;
		else
			$this->login();
	}

	/**
	 * Submit a POST request to log into the website
	 *
	 * @since 1.0
	 */
	private function login ()
	{
		$html = $this->get_html('https://rstforums.com/forum/login.php?do=login',
			array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => "vb_login_username=" . urlencode($this->user->name) . "&vb_login_password=" . urlencode($this->user->pass) . "&cookieuser=1&do=login",
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_COOKIEJAR => $this->cookie_file
			)
		);

		if (preg_match('#Thank you for logging in,#', $html))
		{
			$html = $this->get_html("https://rstforums.com/chat/?ajax=true",
				array(
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => array('text' => 'I\'ve been initialized. Talk to me, humans: bc help'),
					CURLOPT_HEADER => true
				)
			);

			return true;
		}
		else
			return false;
	}

	/**
	 * Erase the cookie file
	 * a.k.a. log out
	 *
	 * @since 1.0
	 */
	private function logout ()
	{
		$cookie_file = $this->cookie_file;
		unset($cookie_file);
	}

	/**
	 * Gets the XML chat data and hold the messages and online users
	 *
	 * @since 1.0
	 */
	private function get_chat_data ()
	{
		$html = $this->get_html("https://rstforums.com/chat/?ajax=true&lastID={$this->lastID}");

		if ($xml = simplexml_load_string($html, null, LIBXML_NOCDATA))
		{
			$this->messages = $xml->messages->message ? $xml->messages->message : null;
			$this->lastID = $this->messages[count($this->messages) - 1];
		}
		else
			die('Could not get the messages!');
	}

	/**
	 * Parses every message, checks to see if the user
	 * is not ignored and then applies the processor to the message
	 *
	 * @since 1.0
	 */
	private function parse_messages ()
	{
		$messages = $this->messages;

		if ($messages != null)
		{
			$this->is_banned = false;
			
			foreach ($messages as $i)
			{
				$id = (array) $i->attributes()->id;
				$id = $id[0];
				$user = $i->username;
				$message = $i->text;

				if (!in_array($user, $this->ignored_users) && !in_array($id, $this->processed_messages))
					$this->process_message($message, $user, $id);
			}

			$this->respond();
		}
		else
			$this->is_banned = true;
	}

	/**
	 * Processes the message and appends messages to the response queue
	 *
	 * @since 1.0
	 * @param message string of the user
	 * @param user string
	 * @param id integer of the message
	 */
	private function process_message ($message, $user, $id)
	{
		$this->processed_messages[] = $id;

		if (preg_match('#\b' . implode("|", $this->swearing_words) . '\b#', $message))
			$this->queue_response("Ai grija la limbaj, te rog!", $user);

		if ($this->bot_command($message, 'joke'))
		{
			$joke = $this->get_joke();

			if ($joke !== false)
				$this->queue_response($joke, $user);
		}

		if ($current = $this->bot_command($message, 'compute|comp'))
		{
			$computed = $this->compute($current);

			if ($computed !== false)
				$this->queue_response($computed, $user);
		}

		if ($current = $this->bot_command($message, 'realurl'))
		{
			$realurl = $this->get_real_url($current);

			if ($realurl !== false)
				$this->queue_response($realurl, $user);
		}

		if ($current = $this->bot_command($message, 'header'))
		{
			$data = explode(" ", $current);
			$header = $data[0];
			$url = $data[1];
			$headers = $this->get_html($url, array(CURLOPT_HEADER => true));

			if (preg_match('#(' . addslashes($header) . ')\s*:(.*)#i', $headers, $res))
				$this->queue_response(trim($res[1]) . ': ' . trim($res[2]), $user);
		}

		if ($current = $this->bot_command($message, 'convert|conv'))
		{
			$data = explode(" ", $current);

			if (count($data) >= 3)
			{
				$amount = $data[0];
				$from = $data[1];
				$to = $data[2];
				$converted = $this->convert($amount, $from, $to);

				if ($converted !== false)
					$this->queue_response($converted, $user);
			}
		}

		if ($current = $this->bot_command($message, 'ip'))
		{
			$ip = $this->get_host_ip($current);

			if ($ip !== false)
				$this->queue_response($ip, $user);
		}

		if ($current = $this->bot_command($message, 'vremea|weather'))
		{
			$weather = $this->get_weather($current);

			if ($weather !== false)
				$this->queue_response($weather, $user);
		}

		if ($current = $this->bot_command($message, 'b64|base64'))
		{
			$data = explode(" ", $current);

			if (count($data) >= 2)
			{
				$method = $data[0];
				unset($data[0]);
				$text = implode(" ", $data);
				$result = $method == 'decode' || $method == 'd' ? base64_decode($text) : base64_encode($text);
				$this->queue_response($result, $user);
			}
		}

		if ($current = $this->bot_command($message, 'r13|rot13'))
		{
			$result = str_rot13($current);
			$this->queue_response($result, $user);
		}

		if ($current = $this->bot_command($message, 'md5'))
		{
			$result = md5($current);
			$this->queue_response($result, $user);
		}

		if ($current = $this->bot_command($message, 'sha1'))
		{
			$result = sha1($current);
			$this->queue_response($result, $user);
		}

		if ($current = $this->bot_command($message, 'url'))
		{
			$data = explode(" ", $current);

			if (count($data) >= 2)
			{
				$method = $data[0];
				unset($data[0]);
				$text = implode(" ", $data);
				$result = $method == 'decode' || $method == 'd' ? urldecode($text) : urlencode($text);
				$this->queue_response($result, $user);
			}
		}

		if ($this->bot_command($message, 'id self|identify'))
		{
			$this->queue_response('My name is Botcoi. I\'m an automated tool designed to help you get answers to trivial questions like computing string equations, finding out the weather for a city, converting an amout, retrieving headers of a website and more. My creator is Gecko; the one and only annoying artificial lifeform programmed for assassination and destruction. i.e.', $user);
		}

		if ($this->bot_command($message, 'help'))
		{
			$this->queue_response("Here are all the commands:
[b]bc joke[/b]:
[b]bc compute|comp EXPRESSION[/b]: EXPRESSION can be a complex equation; a world event, statistic or much more that you consider it could be stored somewhere as important data. i.e. bc comp fastest man
[b]bc realurl SHORT_URL[/b]: Returns the final URL of SHORT_URL. Can be used on bit.ly or goo.gl links, for example.", $user);
			$this->queue_response("
[b]bc header HEADER_NAME WEBSITE_URL[/b]: Returns the value of the HEADER_NAME from the WEBSITE_URL req. i.e. bc header content-type http://google.com
[b]bc convert|conv AMOUNT A B[/b]: Converts a monetary amount from A to B. A and B must be a the 3-letter abbreviation, you can find them here: https://www.google.com/finance/converter. i.e. bc conv 100 eur ron
[b]bc ip WEBSITE_URL[/b]: Returns the IP address of the WEBSITE_URL", $user);
			$this->queue_response("
[b]bc b64|base64 e|encode|d|decode STRING[/b]: Encodes/decodes STRING using Base64. i.e. bc b64 e Gecko
[b]bc r13|rot13 STRING[/b]: Applies Rot13 shift cipher to STRING.
[b]bc md5 STRING[/b]: Computes the MD5 hash of STRING.
[b]bc sha1 STRING[/b]: Computes the SHA1 hash of STRING.", $user);
			$this->queue_response("
[b]bc url e|encode|d|decode[/b]: Encodes/decodes STRING from/to URL valid format
[b]bc vremea|weather CITY, COUNTRY[/b]: Returns the weather for the gigen CITY, the COUNTRY is optional (if the returned data is not ok).", $user);
		}
	}

	/**
	 * Checks if a message contains a command for the bot in it
	 *
	 * @since 1.0
	 * @param message string
	 * @param command string
	 */
	private function bot_command ($message, $command)
	{
		if (preg_match("#^bc (" . $command . ") (.*)#i", $message, $res))
			return $res[2];
		if (preg_match("#^bc " . $command . "\b#i", $message))
			return true;
		else
			return false;
	}

	/**
	 * Gets a random joke from multiple APIs
	 *
	 * @since 1.0
	 */
	private function get_joke ()
	{
		$joke_apis = array(
			'http://api.yomomma.info/',
			'http://api.icndb.com/jokes/random'
		);

		$api = rand(0, count($joke_apis) - 1);

		// Yo momma API
		if ($api == 0)
		{
			$html = $this->get_html($joke_apis[$api]);
			$json = json_decode($html);

			return $json->joke;
		}

		// ICNDB API
		elseif ($api == 1)
		{
			$html = $this->get_html($joke_apis[$api]);
			$json = json_decode($html);

			if ($json && $json->type == 'success')
				return $json->value->joke;
		}

		return false;
	}

	/**
	 * Computes an equation based on wolfram alpha's API
	 *
	 * @since 1.0
	 * @param eq string
	 */
	private function compute ($eq)
	{
		$html = $this->get_html('http://api.wolframalpha.com/v2/query?appid=98EL5U-AHGJLYRWH6&input=' . urlencode($eq) . '&format=plaintext');
		$xml = simplexml_load_string($html);

		if ($xml->attributes()->success && isset($xml->pod) && count($xml->pod) > 0 && isset($xml->pod[1]->subpod) && isset($xml->pod[1]->subpod->plaintext))
			return $eq . ' = [b]' . $xml->pod[1]->subpod->plaintext . '[/b]';
		
		return false;
	}

	/**
	 * Gets a URL's final destination
	 *
	 * @since 1.0
	 * @param url string
	 */
	private function get_real_url ($url)
	{
		$headers = $this->get_html($url, array(CURLOPT_HEADER => true));

		if (preg_match('#Location\s*:(.*)#i', $headers, $res))
			return trim($res[1]);

		return false;
	}

	/**
	 * Gets a website's IP
	 *
	 * @since 1.0
	 * @param host string
	 */
	private function get_host_ip ($host)
	{
		if (preg_match('#^http#', $host))
		{
			preg_match('#http.*?//([^/]+)#', $host, $res);
			$host = $res[1];
		}

		elseif (preg_match('#/#', trim($host)))
		{
			$host = explode("/", trim($host));
			$host = $host[0];
		}

		if (!preg_match('#^http#', $host))
			$host = 'http://' . $host;

		$host = @dns_get_record($host);

		if ($host && count($host) > 0 && isset($host[0]['ip']))
			return $host[0]['ip'];
		else
			return false;
	}

	/**
	 * Logs the given message and appends it to the response queue
	 *
	 * @since 1.0
	 * @param response string
	 */
	private function queue_response ($response, $user = '')
	{
		$this->log($response, false, $user);
		$this->response_queue[] = $user . '_!:!_' . $response;
	}

	/**
	 * Converts amounts
	 *
	 * @since 1.0
	 * @param amount string
	 * @param from string
	 * @param to string
	 */
	private function convert ($amount, $from, $to)
	{
		$url = "https://www.google.com/finance/converter?a={$amount}&from={$from}&to={$to}";
		$html = $this->get_html($url);

		if (preg_match('#>(.*?) = <span.*?>([^<]+)#i', $html, $res))
			return $res[1] . ' = ' . $res[2];

		return false;
	}

	/**
	 * Returns the weather for the given location
	 *
	 * @since 1.0
	 * @param location string
	 */
	private function get_weather ($location)
	{
		$url = "http://api.openweathermap.org/data/2.5/weather?q=" . urlencode($location) . "&units=metric";
		$html = $this->get_html($url);
		$json = json_decode($html);

		if (isset($json->cod) && $json->cod == 200)
			return $json->name . ': ' . $json->main->temp . '°C';

		return false;
	}

	/**
	 * Writes data to the output buffer and into a log file
	 *
	 * @since 1.0
	 * @param message string
	 * @param debug bool
	 */
	private function log ($message, $debug = false, $user = '')
	{
		if (!$debug)
			file_put_contents($this->log_file, date('[m/d/y H:i:s] ') . $message . "\n", FILE_APPEND);

		echo "&mdash; $message\n<br />\n";
		ob_flush();
		flush();

		//print_r($this->latest_html);
	}

	/**
	 * Submit a POST request to send the bot's response to the chat
	 *
	 * @since 1.0
	 */
	private function respond ()
	{
		foreach ($this->response_queue as $response)
		{
			$data = explode("_!:!_", $response);
			$message = "/msg {$data[0]} {$data[1]}";

			$html = $this->get_html("https://rstforums.com/chat/?ajax=true",
				array(
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => array('text' => $message),
					CURLOPT_HEADER => true
				)
			);
		}

		$this->response_queue = array();
	}

	/**
	 * Start the bot
	 *
	 * @since 1.0
	 */
	public function begin ()
	{
		$this->logout();
		$banat = 0;

		while (true)
		{
			$this->check_login();

			if (!$this->is_banned)
			{
				$this->get_chat_data();
				$this->parse_messages();
			}
			else
			{
				$this->log('Sunt banat!', true);
				$this->logout();
			
				// If still banned after 10 tries
				if ($banat++ == 10)
					die(print_r($this->latest_html));
			}

			sleep(1);
		}

		die('Botcoi went out of the while (true) loop.');
	}
}