<?php

/**
 * @package "FancyBox 4 ElkArte" Addon for Elkarte
 * @author Spuds
 * @copyright (c) 2011-2014 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * @version 1.0
 *
 */

$url = (isset($_GET['url'])) ? $_GET['url'] : exit;

$referer = (isset($_SERVER['HTTP_REFERER'])) ? strtolower($_SERVER['HTTP_REFERER']) : false;
$is_allowed = $referer && strpos($referer, strtolower($_SERVER['SERVER_NAME'])) !== false;

// You have to be able to use this proxy
$string = ($is_allowed) ? utf8_encode(file_get_contents($url)) : exit;

$json = json_encode($string);
$callback = (isset($_GET['callback'])) ? $_GET['callback'] : false;

if ($callback)
{
	$jsonp = "$callback($json)";
	header('Content-Type: application/javascript');

	echo $jsonp;
	exit;
}

echo $json;