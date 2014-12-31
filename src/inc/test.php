<?php

	require('config.php');

	function check_new_posts ()
	{
		global $posts_file;

		$url = 'https://rstforums.com/feed/external.php?display=latest_posts&_=' . rand(0, 100000);
		$html = file_get_contents($url);
		preg_match_all("#new thread\((\d+), '(.*?)', '(.*?)', (\d+), (\d+), (\d+), '(.*?)', (\d+), (\d+)\);#i", $html, $posts);
		unset($posts[0]);

		$f = fopen($posts_file, 'a+');
		$fs = filesize($posts_file);
		$lines = fread($f, $fs == 0 ? 1 : $fs);
		$sep = "@-@@!";

		foreach ($posts[1] as $i => $val)
		{
			$found = false;
			
			$tid = $posts[1][$i];
			$title = $posts[2][$i];
			$author = $posts[3][$i];
			$date = $posts[4][$i];
			$time = $posts[5][$i];
			$pid = $posts[6][$i];
			$cat = $posts[7][$i];
			$cid = $posts[8][$i];
			$uid = $posts[9][$i];

			if (!preg_match('#\b' . $pid . $sep . '#', $lines))
			{
				echo "$pid     <br />\n";
				$line = "{$tid}{$sep}{$title}{$sep}{$author}{$sep}{$date}{$sep}{$time}{$sep}{$pid}{$sep}{$cat}{$sep}{$cid}{$sep}{$uid}\n";
				fwrite($f, $line);
			}
		}

		fclose($f);
	}

	check_new_posts();