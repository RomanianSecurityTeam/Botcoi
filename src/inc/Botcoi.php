<?php

header('Content-Type: text/html; charset=UTF-8');
require __DIR__ . '/config.php';

/**
 * Botcoi class for handling and interactig with the user's
 * input via RST's AJAX chat
 *
 * @version 1.1
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
	 * Stores the online users
	 *
	 * @since 1.1
	 */
	private $users = array();
	
	/**
	 * Helps in handling the anti-spam system
	 * Holds a timestamp of every message sent for the users
	 *
	 * @since 1.1
	 */
	private $delayed_users = array();

	/**
	 * Holds the users with higher privileges and
	 * acces to harmful functions
	 *
	 * @since 1.1
	 */
	private $admin_users = array('Gecko');
	
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
	 * The file onto which we save the user's current cookie
	 *
	 * @since 1.1
	 */
	private $subscribers_file;
	
	/**
	 * The file onto which we save the processed forum posts
	 *
	 * @since 1.1
	 */
	private $posts_file;
	
	/**
	 * The users whose messages we don't process
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
	 * Latest html retrieved
	 * Stored for debugging purposes
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

	/**
	 * Stores the ID of the last message posted on the chat
	 *
	 * @since 1.0
	 */
	private $lastID = '';

	/**
	 * Stores the channel onto which we're posting
	 *
	 * @since 1.1
	 */
	private $channel = 'RST';

	/**
	 * Stores the ignored authors whom posts we should
	 * not notify the subscribers about
	 *
	 * @since 1.1
	 */
	private $ignored_authors = array('Aerosol', 'Reckon');

	/**
	 * Main function executed on class initialization
	 *
	 * @since 1.0
	 */
	function __construct ()
	{
		global $user;
		global $log_file;
		global $posts_file;
		global $cookie_file;
		global $subscribers_file;

		$this->user = $user;
		$this->log_file = $log_file;
		$this->posts_file = $posts_file;
		$this->cookie_file = $cookie_file;
		$this->subscribers_file = $subscribers_file;

		$this->logout();
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
			CURLOPT_COOKIEJAR => $this->cookie_file,
			//CURLOPT_HEADER => true
		);

		foreach ($options as $curl_opt => $value)
			$curl_options[$curl_opt] = $value;

		curl_setopt_array($c, $curl_options);

		$this->latest_html = curl_exec($c);

		return $this->latest_html;
	}

	/**
	 * Check if the user is logged in
	 *
	 * @since 1.0
	 */
	private function check_login ()
	{
		return true;
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
				CURLOPT_COOKIEJAR => $this->cookie_file,
				CURLOPT_HEADER => true
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
			$this->users = $xml->users->user ? $xml->users->user : null;
			$this->messages = $xml->messages->message ? $xml->messages->message : null;
			$this->lastID = $this->messages[count($this->messages) - 1];
			
			if ($this->messages !== null)
				$this->channel = $this->messages[0]->attributes()->channelID == 69 ? 'RSTech' : 'RST';
		}
		else
			$this->log('Could not get the messages!');
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

		if ($current = $this->chat_command($message, 'joke'))
		{
			$joke = $this->get_joke($current);

			if ($joke !== false)
				$this->send($joke, $user, false);
		}

		if ($current = $this->bot_command($message, 'compute|comp'))
		{
			$computed = $this->compute($current);

			if ($computed !== false)
				$this->send($computed, $user, false);
		}

		if ($current = $this->bot_command($message, 'realurl'))
		{
			$realurl = $this->get_real_url($current);

			if ($realurl !== false)
				$this->send($realurl, $user);
		}

		/*if ($current = $this->bot_command($message, 'header'))
		{
			$data = explode(" ", $current);
			$header = $data[0];
			$url = $data[1];
			$headers = $this->get_html($url, array(CURLOPT_HEADER => true));

			if (preg_match('#(' . addslashes($header) . ')\s*:(.*)#i', $headers, $res))
				$this->send(trim($res[1]) . ': ' . trim($res[2]), $user);
		}*/

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
					$this->send($converted, $user, false);
			}
		}

		if ($current = $this->bot_command($message, 'ip'))
		{
			$ip = $this->get_host_ip($current);

			if ($ip !== false)
				$this->send($ip, $user);
		}

		if ($current = $this->chat_command($message, 'vremea|weather'))
		{
			$weather = $this->get_weather($current);

			if ($weather !== false)
				$this->send($weather, $user, false);
		}

		if ($current = $this->chat_command($message, 'recommend cat(?:egories)?|recomanda(?:re)? cat(?:egorii)?'))
		{
			$this->send("Movie categories: " . $this->get_movie_categories(), $user, false);
		}
		

		if ($current = $this->chat_command($message, '(?:recommend|recomanda(?:re)?) (?:movie|film)'))
		{
			$movie = $this->get_random_movie($current);

			if ($movie)
				$this->send("/action recommends [b]{$movie['t']}[/b] ({$movie['y']}) ({$movie['r']}/10) (" . implode(", ", $movie['c']) . ") http://www.imdb.com/title/tt{$movie['id']}/", $user, false);
		}

		if ($this->chat_command($message, 'date|data'))
		{
			$this->send(date('H:i:s - M d, Y'), $user, false);
		}

		if ($this->chat_command($message, 'sub(?:scribe)|abo(?:nare)?'))
		{
			$usr = (string) $user . '';
			
			if (!$this->is_subscriber($usr))
			{
				$this->add_subscriber($usr);
				$this->send('Ai fost abonat la afisarea noilor postari.', $user);
			}
		}

		if ($this->chat_command($message, 'unsub(?:scribe)|dezabo(?:nare)?'))
		{
			$usr = (string) $user . '';

			if ($this->is_subscriber($usr))
			{
				$this->remove_subscriber($usr);
				$this->send('Ai fost dezabonat de la afisarea noilor postari.', $user);
			}
		}

		if ($current = $this->bot_command($message, 'b64|base64'))
		{
			$data = explode(" ", $current);

			if (count($data) >= 2)
			{
				$method = $data[0];
				unset($data[0]);
				$text = implode(" ", $data);
				$result = $method[0] == 'd' ? base64_decode($text) : base64_encode($text);
				$this->send($result, $user);
			}
		}

		if ($current = $this->bot_command($message, 'r13|rot13'))
		{
			$result = str_rot13($current);
			$this->send($result, $user);
		}

		if ($current = $this->bot_command($message, 'md5'))
		{
			$result = md5($current);
			$this->send($result, $user);
		}

		if ($current = $this->bot_command($message, 'sha1'))
		{
			$result = sha1($current);
			$this->send($result, $user);
		}

		if ($current = $this->bot_command($message, 'url'))
		{
			$data = explode(" ", $current);

			if (count($data) >= 2)
			{
				$method = $data[0];
				unset($data[0]);
				$text = implode(" ", $data);
				$result = $method[0] == 'd' ? urldecode($text) : urlencode($text);
				$this->send($result, $user);
			}
		}

		if ($this->bot_command($message, 'id'))
		{
			$this->send('My name is Botcoi. I\'m an automated tool designed to help you get answers to trivial questions like computing string equations, finding out the weather for a city, converting an amout, retrieving headers of a website and more. My creator is Gecko; the one and only annoying artificial lifeform programmed for assassination and destruction.', $user);
		}

		if ($this->bot_command($message, 'help'))
		{
			$this->send("All the available commands: https://github.com/RomanianSecurityTeam/Botcoi/blob/master/README.md", $user);
		}

		if ($current = $this->ai($message))
		{
			$this->send("{$user}: {$current}", $user, false);
		}

		if ($this->is_admin($user))
		{
			if ($this->bot_command($message, 'switch'))
			{
				if ($this->channel == 'RST')
					$this->channel = 'RSTech';
				else
					$this->channel = 'RST';

				$this->send("/join $this->channel", $user, false);
			}

			if ($current = $this->bot_command($message, 'say'))
			{
				$this->send($current, $user, false);
			}
		}

		if (preg_match('#\b(' . implode("|", $this->swearing_words) . ')\b#i', $message))
			$this->send("Ai grija la limbaj, te rog!", $user);
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
		if (preg_match("#^(?:bc|/privmsg(?: bc)?)? (" . $command . ") (.*)#i", $message, $res))
			return $res[2];
		if (preg_match("#^(?:bc|/privmsg(?: bc)?)? (" . $command . ")\b#i", $message))
			return true;
		else
			return false;
	}

	/**
	 * Checks if a message contains a command for the bot in it
	 *
	 * @since 1.1
	 * @param message string
	 * @param command string
	 */
	private function chat_command ($message, $command)
	{
		if (preg_match("#^(?:bc|/privmsg(?: bc)?)? ?(" . $command . ") (.*)#i", $message, $res))
			return $res[2];
		if (preg_match("#^(?:bc|/privmsg(?: bc)?)? ?(" . $command . ")\b#i", $message))
			return true;
		else
			return false;
	}

	/**
	 * Gets a random joke from multiple APIs
	 *
	 * @since 1.0
	 */
	private function get_joke ($name = '')
	{
		$joke_apis = array(
			'http://api.yomomma.info/',
			'http://api.icndb.com/jokes/random'
		);

		$name = trim((string) $name);
		$api = rand(0, count($joke_apis) - 1);
		$joke = false;

		// Yo momma API
		if ($api == 0)
		{
			$html = $this->get_html($joke_apis[$api]);
			$json = json_decode($html);

			$joke = $json->joke;
		}

		// ICNDB API
		elseif ($api == 1)
		{
			$html = $this->get_html($joke_apis[$api]);
			$json = json_decode($html);

			if ($json && $json->type == 'success')
				$joke = $json->value->joke;
		}

		if (strlen($name) > 0 && $name != '1')
		{
			$name = preg_replace('/[^a-z ]/i', '', $name);
			$joke = preg_replace('/(Yo m[oa]mm?a|Chuck Norris)/i', ucwords($name), $joke);
		}

		return $joke;
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
			return preg_replace('/[^a-z ]/i', '', $eq) . ' = [b]' . $xml->pod[1]->subpod->plaintext . '[/b]';
		
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
		$url = "http://api.wunderground.com/api/a3bbfc4d973659e7/conditions/q/" . urlencode($location) . ".json";
		$html = $this->get_html($url);
		$json = json_decode($html);

		if (isset($json->current_observation))
		{
			return "{$json->current_observation->display_location->full}: [b]{$json->current_observation->temp_c}°C[/b], {$json->current_observation->weather}, {$json->current_observation->relative_humidity} humidity";
		}

		return false;
	}

	/**
	 * Handles the AI requests
	 *
	 * @since 1.1
	 * @param message
	 */
	private function ai ($message)
	{
		if ($message == 'botcoi')
			return 'Esti timid?';

		if (preg_match('/^(botz|askbot|robo)/', $message, $res))
			return 'Eu\'s mai tare ca ' . $res[1] . '.';

		if ($this->ai_q($message, 'ce faci'))
			return 'Fac ce vreau.';

		if ($this->ai_q($message, '.*?botcoi'))
			return 'Botcoi iti spune sa te caci in palma.';

		if ($this->ai_q($message, 'cine-i cel .*? bot'))
			return 'Toti cei care au raspuns la aceasta intrebare.';

		if ($this->ai_q($message, 'cine esti'))
			return 'Daca ma mai intrebi odata iti postez CNP-ul pe site-ul politiei locale.';

		if ($this->ai_q($message, 'cine-i boss'))
			return 'Gecko e boss-ul.';

		if ($this->ai_q($message, 'cat e ceasu'))
			return 'Scrie "date".';

		if ($this->ai_q($message, 'ia.*?pula'))
			return 'Hahaha. Ai pus mana pe ea.';

		if ($this->ai_q($message, 'stii programare'))
			return 'Da, m-am auto-programat.';

		if ($this->ai_q($message, 'stii'))
			return 'Nu.';

		if ($this->ai_q($message, 'mer[sc]i|multumesc'))
			return 'Hai ca esti bagabont.';

		if ($this->ai_q($message, 'ai gagica'))
			return 'Da, a fost nevoie de doi ca sa te nasti.';

		if ($this->ai_q($message, '.*?manele'))
			return 'Nu ma intereseaza despre ce vorbesti, esti inutil.';

		if ($this->ai_q($message, 'cati ani ai'))
			return '4294967295.';

		if ($this->ai_q($message, 'zi ceva de'))
			return 'Da ce-s eu? sclavul tau?';

		if ($this->ai_q($message, 'zi.*?gluma'))
			return $this->get_joke();

		if ($this->ai_q($message, 'cere(-ti)? scuze'))
			return 'Ma faci sa rad, aratare organica.';

		if ($this->ai_q($message, 'scuze'))
			return 'Ok. Imediat iti sterg datele postate pe matrimoniale.';

		if ($this->ai_q($message, 'salut'))
		{
			$res = array('One love, man', 'Sunt prea popular ca sa vorbesc cu cei ca tine', 'Ciao, bella', 'Salut', 'Peace', 'Keep it real');
			return $res[rand(0, count($res) - 1)] . '.';
		}

		if ($this->ai_q($message, 'esti prost.*?\?'))
			return 'Nu.';

		if ($this->ai_q($message, 'te iubesc'))
			return 'No homo. Si eu.';

		if ($this->ai_q($message, 'te lovesc'))
			return 'Au. Glumeam. Te-am notat pe caiet sa-ti dau muie.';

		if ($this->ai_q($message, '(esti|puti|mirosi|sugi|esti ratat)'))
			return 'Ba tu.';

		if ($this->ai_q($message, '.*?ban lu'))
		{
			$res = array('Da', 'Nu');
			return $res[rand(0, count($res) - 1)] . '.';
		}

		if ($this->ai_q($message, 'cine.*?(gabor|militian|politist|politie|diicot|police|militie)'))
		{
			return $this->users[rand(0, count($this->users) - 1)] . '.';
		}

		if ($this->ai_q($message, 'da cu zar'))
		{
			return rand(1, 6) . '.';
		}

		if ($this->ai_q($message, 'taci'))
		{
			return 'Nu.';
		}

		if ($this->ai_q($message, '(fa )?update'))
		{
			return 'Tzj, tzj, beep, boop. Glumeam. Cine dracu te crezi?';
		}

		if ($this->ai_q($message, 'unde (sta|locuieste)'))
		{
			$res = array('Sub poduri', 'In canal', 'Pe centura', 'In penthouse', 'La palat', 'Unde vrea', 'In club', 'Pe jos', 'La vorbitor', 'In copaci', 'In picioare', 'Sub scaun', 'Sub pat', 'In cazan', 'Pe burlan', 'Acasa', 'La mine', 'Acolo');
			return $res[rand(0, count($res) - 1)] . '.';
		}

		if ($this->ai_q($message, 'cine e'))
		{
			$res = array('Nu-ti zic', 'E confidential', 'Un spart', 'Un boss', 'Sefu la patronii bossi', 'Batman', 'Omul cu chiloti peste pantaloni', 'Ala de-mprosca panza pe pereti', 'Darth Vader', 'Robin', 'Shakira', 'Un nimeni', 'Un jeg', 'O umbra pe apa', 'Sefu la shawormerie', 'Sho ce intrebare', 'Fratele meu care-mi schimba uleiu la termen');
			return $res[rand(0, count($res) - 1)] . '.';
		}

		if ($this->ai_q($message, '.*?gecko'))
			return 'Gecko, vrei sa zici spartul de m-a programat in primele stagii?';

		if ($this->ai_q($message, '.*?(sok[ae]res|somarde|hasles)'))
			return 'Esti tigan. L-am anuntat pe Gecko sa-ti dea ban.';

		if ($this->ai_q($message, 'e .*? (idiot|prost|spart|nebun|bou|ratat)'))
			return 'Despre el nu stiu multe, dar stiu ca tu imi provoci greata. De mentionat ca sunt robot.';

		if ($this->ai_q($message, '.'))
		{
			$res = array('tuci', 'situ', 'zupui', 'ecidiospor', 'ina', 'apicare', 'înnumăr', 'îmbumbi', 'răzgâia', 'sgaibă', 'varactor', 'tribrah', 'lecuță', 'moțat', 'gala', 'vădancă', 'fișiu', 'norodit', 'lambrisa', 'teacăr', 'divorțare', 'oficiat', 'porcire', 'verticaliza', 'historadiografie', 'autocratism', 'aevea', 'catafract', 'pegmatic', 'buduhoală', 'fiecine', 'patinator', 'mitralită', 'năucie', 'Cantorbery', 'kilometric', 'cinătuit', 'sângeratic', 'disodont', 'cultism', 'Breslau', 'saigi', 'magazioară', 'cucuioară', 'heruvim', 'lăidăci', 'etimorfoză', 'înderetnic', 'corupție', 'autoanaliză', 'oleaginos', 'hesperidă', 'desnădejde', 'strajameșter', 'călțun', 'tăgăduire', 'gîrtan', 'microbiuretă', 'fandasie', 'obtenebrat', 'vască', 'miț', 'Beilic', 'ceahlău', 'egofonie', 'obrejă', 'lilicea', 'gigantesc', 'valiză', 'ciocantin', 'anchilurie', 'pericolangită', 'ibriditate', 'sărcăli', 'perclu', 'hâșâit', 'picolină', 'dăngăni', 'hotru', 'dotă', 'nemolit', 'necooperativizat', 'protocarion', 'căsnicesc', 'pelinuță', 'ecoacuzie', 'tehnic', 'oligofrenopedagogie', 'împistritură', 'spilitizare', 'șițuit', 'recvizite', 'piramidotomie', 'trisecțiune', 'vitrui', 'tecto', 'mânu', 'proțăpit', 'explozor', 'recluzionar', 'păduriță', 'cloropren', 'secerică', 'kip', 'boldo', 'bălie', 'Acrisiu', 'uluci', 'pitic', 'făurie', 'contemplare', 'golîmb', 'prav', 'despăduchea', 'atomist', 'emancipare', 'graifăr', 'drăgăicuță', 'rotit', 'xilit', 'paralexematic', 'zaharimetr', 'cerestui', 'juguluit', 'zoomorfie', 'australiancă', 'păpușească', 'agamocit', 'încasatoare', 'hispida', 'combi', 'oracol', 'voci', 'întroienire', 'pseudolatinism', 'pepeșin', 'esc', 'anosmie', 'predstavlisi', 'zornăit', 'fosfatare', 'sărăcios', 'mocănesc', 'parapsihologic', 'varvara', 'fortilă', 'madă', 'aromă', 'zdrențuit', 'reunire', 'debenzolare', 'rosienesc', 'aparent', 'chioscă', 'acvafortist', 'overboust', 'șfarcă', 'reparație', 'aromat', 'rasă', 'proctotomie', 'Nicodim', 'săcuit', 'incandescent', 'bălsăma', 'Phoebus', 'goană', 'dezmeteci', 'deltaic', 'periadenoidită', 'autonega', 'lipan', 'marionetă', 'hierocrație', 'înaljos', 'menaja', 'căzător', 'lepros', 'algoritmic', 'stătător', 'orie', 'chelăcăi', 'desprejmui', 'mandril', 'brusc', 'smicui', 'suligă', 'scotocire', 'writer', 'Banya', 'portăriță', 'heliometrologie', 'afumătorie', 'tecărău', 'terifiant', 'steag', 'volubil', 'retractație', 'Vesal', 'hîrcîi', 'huțupi', 'mitacism', 'învățătoresc', 'preîmbl', 'carlovingieni', 'comisar', 'secție', 'crăngar', 'șui', 'prijuni', 'mîndrețe', 'optimist', 'francofonie', 'minicasetofon', 'emolumentar', 'răpotin', 'egzecutiv', 'psihoimunologie', 'cruntare', 'prăvilaș', 'culoglu', 'eterodoxie', 'diagnoză', 'rescizie', 'parazita', 'prăjite', 'aroga', 'despacheta', 'indentație', 'colesteropexie', 'înglotit', 'hastat', 'hinta', 'ceapol', 'fodol', 'ciorbagioaică', 'duodecimal', 'dotațiune', 'zoofitologie', 'hamailâu', 'grumăjer', 'postârnap', 'tanatic', 'cier', 'circulare', 'fustiță', 'necat', 'goangă', 'hipocondrie', 'izraelit', 'desdăuna', 'balt', 'ireconciliabil', 'cărător', 'feroșie', 'Suceava', 'dezionizat', 'șănțar', 'veda', 'mahonare', 'zăpreală', 'Milo', 'degete', 'safari', 'Olmutz', 'uniformă', 'facețios', 'melonidă', 'zbânțuitură', 'crotină', 'deeptank', 'panslavism', 'teleosteean', 'străcura', 'Pygmalion', 'ovidenie', 'pisalt', 'Tripoli', 'carambolaj', 'preacurvie', 'pasteuriza', 'drugstore', 'copiant', 'ciclan', 'poghircă', 'streșinire', 'hibernom', 'vielă', 'anastaltic', 'îngreca', 'pidea', 'droghistă', 'străluminare', 'izobilateral', 'extinguibil', 'safardea', 'zângăt', 'duldură', 'heteronomie', 'sporișin', 'inula', 'țarina', 'Adria', 'spiraea', 'ligulat', 'curățătorie', 'pleiofag', 'antepulsie', 'sfârâioc', 'Precup', 'vigoroso', 'crămăluială', 'rubiaceu', 'harmată', 'monogamic', 'anahoret', 'dintâi', 'frunzătură', 'colmataj', 'haihai', 'șutar', 'ironic', 'efilat', 'chiftiriță', 'furnicare', 'hărșuire', 'regentat', 'amniotic', 'acintus', 'bâlbără', 'intervocalic', 'reasigurator', 'sigilografie', 'interfix', 'tobă', 'moscălesc', 'depravat', 'răgăduială', 'repartiza', 'julgheală', 'fonotip', 'propurta', 'cobie', 'specifica', 'mercurit', 'denumi', 'boierit', 'atrium', 'melancolizat', 'acrospor', 'microscopie', 'megohm', 'izomerizat', 'șelământ', 'ieftini', 'puhab', 'fitotomie', 'oculogramă', 'techno', 'mulțumeală', 'hanap', 'povăț', 'mătușel', 'sindesmotomie', 'tendosinovită', 'epatită', 'dezacupla', 'pârsială', 'pilug', 'mîțișor', 'duodenorafie', 'târlie', 'publicește', 'târșitoare', 'scotofobie', 'cinizm', 'mânzălău', 'renaște', 'învălitoare', 'serascherlâc', 'nepurcică', 'strungălit', 'manotermometru', 'antiuman', 'frînghie', 'caliciflor', 'frigidarium', 'naira', 'pensionară', 'sadelcă', 'comemorare', 'xantogenare', 'crematoriu', 'țuțuiancă', 'tăpșit', 'pemni', 'profesoraș', 'influent', 'nânășel', 'diazota', 'mudejar', 'doctrinarizm', 'funerar', 'sezam', 'bancaizăn', 'transfila', 'sacramentalitate', 'telalâc', 'organino', 'educator', 'Grigorie', 'îmbotnița părăsita' , 'găinilor', 'cerebroid', 'urbanist', 'kiang', 'martor', 'zbicit', 'neuronal', 'ospitaliere', 'pirolatru', 'stârmină', 'pomazanie', 'blocaj', 'chintă', 'coșcov', 'conspectare', 'tabără', 'kiwi', 'semiotic', 'lai', 'manilovism', 'cuazi', 'tedaric', 'conjor', 'funicul', 'morfinoman', 'quassia', 'șuibăr', 'demacadamizare', 'șumăriță', 'scilla', 'ancora', 'sidilă', 'nevăstuie', 'natrit', 'verificăciune', 'maiou', 'psihiatric', 'protofosfură', 'Herder', 'discromie', 'furtun', 'înflocos', 'indolog', 'munci', 'jacard', 'abstracto', 'Atalia', 'țără', 'etnologie', 'smiorcăială' , 'unalta', 'smult', 'iriță', 'blestemăție', 'Iacobdeal', 'împachetare', 'purpuraceu', 'cetioară', 'indivizie', 'cronometrare', 'multifocal', 'încrânceni', 'supratehniciza', 'greime', 'astrospectrografie', 'aerobacter', 'reghie', 'piocolpos', 'dumbăț', 'șopârlă', 'iavaș', 'halucinogen', 'Foe', 'spovedi', 'amigdaleu', 'cărăzuire', 'calamina', 'stepare', 'egirin', 'Ieremia', 'dubleu', 'supresor', 'sucomba', 'nervil', 'lăcșor', 'meșteșugăresc', 'remizare', 'dodoloiu', 'da', 'nu', 'portocale');

			return ucwords($res[rand(0, count($res) - 1)]) . '.';
		}

		return false;
	}

	/**
	 * Checks if message is an AI question
	 *
	 * @since 1.1
	 * @param message string
	 * @param q string
	 */
	private function ai_q ($message, $q)
	{
		if (preg_match('#^botcoi,? ' . addslashes($q) . '#i', $message))
			return true;
		else
			return false;
	}

	/**
	 * Handles the new posts notification system for the subscribed users
	 *
	 * @since 1.1
	 */
	private function check_new_posts ()
	{
		$url = 'https://rstforums.com/feed/external.php?display=latest_posts&_=' . rand(0, 100000);
		$html = $this->get_html($url);
		preg_match_all("#new thread\((\d+), '(.*?)', '(.*?)', (\d+), (\d+), (\d+), '(.*?)', (\d+), (\d+)\);#i", $html, $posts);
		unset($posts[0]);

		$lines = file_get_contents($this->posts_file);
		$sep = "!@-@@";

		foreach ($posts[1] as $i => $val)
		{
			$found = false;
			
			$tid = $posts[1][$i];
			$title = $posts[2][$i];
			$author = preg_replace('/[^a-z0-9!@#$%^&*()_+=\'"{}\[\],. -]/i', '', $posts[3][$i]);
			$date = $posts[4][$i];
			$time = $posts[5][$i];
			$pid = $posts[6][$i];
			$cat = $posts[7][$i];
			$cid = $posts[8][$i];
			$uid = $posts[9][$i];

			if (in_array($author, $this->ignored_authors))
				continue;

			if (!preg_match('#' . $sep . $pid . $sep . '#si', $lines))
			{
				foreach ($this->subscribers() as $user)
				{
					if (strlen($user) > 0)
						$this->send("[b]{$author}[/b] tocmai a postat in {$cat} [b]/ {$title}[/b]: https://rstforums.com/forum/showthread.php?t={$tid}&p={$pid}", $user);
				}

				$line = "{$tid}{$sep}{$title}{$sep}{$author}{$sep}{$date}{$sep}{$time}{$sep}{$pid}{$sep}{$cat}{$sep}{$cid}{$sep}{$uid}\n";
				file_put_contents($this->posts_file, $line, FILE_APPEND);
			}
		}
	}

	/**
	 * Returns a subscribers array
	 *
	 * @since 1.1
	 */
	private function subscribers ()
	{
		$subscribers = file_get_contents($this->subscribers_file);
		$subscribers = explode("\n", $subscribers);

		return $subscribers;
	}

	/**
	 * Checks if the given user is a subscriber
	 *
	 * @since 1.1
	 * @param user string
	 */
	private function is_subscriber ($user)
	{
		$subscribers = file_get_contents($this->subscribers_file);
		$subscribers = explode("\n", $subscribers);

		foreach ($subscribers as $usr)
		{
			if ($usr == $user)
				return true;
		}

		return false;
	}

	/**
	 * Add a subscriber to the subscribers file
	 *
	 * @since 1.1
	 * @param user string
	 */
	private function add_subscriber ($user)
	{
		file_put_contents($this->subscribers_file, "$user\n", FILE_APPEND);
	}

	/**
	 * Removes a subscriber from the subscribers file
	 *
	 * @since 1.1
	 * @param user string
	 */
	private function remove_subscriber ($user)
	{
		$subscribers = file_get_contents($this->subscribers_file);
		$subscribers = array_filter(explode("\n", $subscribers));
		$index = array_search($user, $subscribers);

		if ($index !== false)
			unset($subscribers[$index]);
		
		file_put_contents($this->subscribers_file, implode("\n", $subscribers));
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

		echo "&mdash; TO: $user - $message\n<br />\n";
		ob_flush();
		flush();

		//print_r($this->latest_html);
	}

	/**
	 * Submit a POST request to send the bot's response to the chat
	 *
	 * @since 1.0
	 */
	private function send ($message, $user, $whisper = true)
	{
		$privmsg = $whisper ? "/msg $user " : '';
		$message = $privmsg . $message;
		$user = (string) $user . '';

		if (!$this->is_admin($user) && array_key_exists($user, $this->delayed_users))
		{
			if ($this->delayed_users[$user] > time() - 5)
				return false;
		}
		
		$this->delayed_users[$user] = time();

		// Log the sent message for debugging purposes
		$this->log($message, false, $user);

		if (strlen($message) > 500)
		{
			$messages = explode("\n", $message);
			$sep = "\n";

			if (count($messages) == 1)
			{
				$messages = explode(" ", $message);
				$sep = " ";
			
				if (count($messages) == 1)
				{
					preg_match_all('/.{0,500}/', $message, $res);
					$messages = $res[0];
					$sep = "";
				}
			}

			$post_message = $privmsg;

			foreach ($messages as $msg)
			{
				if (strlen($post_message . $msg) > 450)
				{
					$this->latest_html = $this->get_html("https://rstforums.com/chat/?ajax=true",
						array(
							CURLOPT_POST => true,
							CURLOPT_POSTFIELDS => array('text' => $post_message),
							CURLOPT_HEADER => true
						)
					);
					$post_message = $privmsg . $msg;
				}
				else
					$post_message .= $sep . $msg;
			}
		}
		else
		{
			$this->latest_html = $this->get_html("https://rstforums.com/chat/?ajax=true",
				array(
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => array('text' => $message),
					CURLOPT_HEADER => true
				)
			);
		}

	}

	/**
	 * Checks if the given user is admin
	 *
	 * @since 1.1
	 * @param category user
	 */
	private function is_admin ($user)
	{
		return in_array($user, $this->admin_users);
	}

	/**
	 * Get a random movie from the movies array
	 *
	 * @since 1.1
	 * @param category string
	 */
	private function get_random_movie ($category)
	{
		global $imdb_top_250;
		$movies = $imdb_top_250;

		if ($category != '')
		{
			$movies = array();

			foreach ($imdb_top_250 as $movie)
			{
				if (in_array($category, $movie['c']))
					$movies[] = $movie;
			}
		}

		if (count($movies) > 0)
			return $movies[rand(0, count($movies) - 1)];
		else
			return false;
	}

	/**
	 * Gets the movie categories from the films array
	 * It gets executed manually just to populate the function below
	 *
	 * @since 1.1
	 */
	public function get_movie_categories_from_array ()
	{
		global $imdb_top_250;

		$categories = array();

		foreach ($imdb_top_250 as $movie)
		{
			foreach ($movie['c'] as $category)
			{
				if (!in_array($category, $categories))
					$categories[] = $category;
			}
		}

		return implode(", ", $categories);
	}

	/**
	 * Returns the movie categories
	 *
	 * @since 1.1
	 */
	private function get_movie_categories ()
	{
		return 'crime, drama, western, action, thriller, adventure, fantasy, biography, history, mystery, sci-fi, romance, family, war, comedy, horror, animation, film-noir, musical, music, sport';
	}

	/**
	 * Start the bot
	 *
	 * @since 1.0
	 */
	public function init ()
	{
		$this->login();
		$banat = 0;

		while (true)
		{
			$this->check_login();

			if (!$this->is_banned)
			{
				$this->get_chat_data();
				$this->parse_messages();
				$this->check_new_posts();
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