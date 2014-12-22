<?php

$user = new stdClass();

$user->name = ''; 	// Your forum username
$user->pass = ''; 	// Your forum password




$tmp = __dir__ . '/../tmp';
$log_file = $tmp . '/log.txt';
$cookie_file = $tmp . '/' . preg_replace('/[^a-z0-9_-]/i', '', $user->name) . '.txt';