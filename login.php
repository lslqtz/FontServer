<?php
require_once('config.php');
if (isset($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
	//if (!isset(SourcePolicy[$_GET['source']])) {
	if (!is_numeric($_GET['uid']) || !is_numeric($_GET['time'])) {
		dieHTML("坏参数!\n", 'Login');
	}
	if (IsLogin()) {
		header('HTTP/1.1 302 Found');
		header('Location: /search.php');
		die();
	}
	if (!CheckLogin($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
		dieHTML("坏签名!\n", 'Login');
	}
	$expireTime = (time() + 86400);
	setcookie(CookieName . '_Source', $_GET['source'], $expireTime);
	setcookie(CookieName . '_UID', intval($_GET['uid']), $expireTime);
	setcookie(CookieName . '_Time', intval($_GET['time']), $expireTime);
	setcookie(CookieName . '_Sign', $_GET['sign'], $expireTime);
	header('HTTP/1.1 302 Found');
	header('Location: /search.php');
	die();
} else {
	dieHTML(":(\n", 'Login');
}
?>
