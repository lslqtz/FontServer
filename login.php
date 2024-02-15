<?php
require_once('config.php');
if (isset($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
	//if (!isset(SignKey[$_GET['source']])) {
	if ($_GET['source'] !== 'MDU') {
		dieHTML("Bad source!\n", 'Login');
	}
	if (IsLogin()) {
		header('HTTP/1.1 302 Found');
		header('Location: /search.php');
		die();
	}
	if (!CheckLogin($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
		dieHTML("Bad sign!\n", 'Login');
	}
	$expireTime = (time() + 86400);
	setcookie(CookieName . '_Source', $_GET['source'], $expireTime);
	setcookie(CookieName . '_UID', $_GET['uid'], $expireTime);
	setcookie(CookieName . '_Time', $_GET['time'], $expireTime);
	setcookie(CookieName . '_Sign', $_GET['sign'], $expireTime);
	header('HTTP/1.1 302 Found');
	header('Location: /search.php');
	die();
} else {
	dieHTML(":(\n", 'Login');
}
?>
