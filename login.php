<?php
require_once('config.php');
if (isset($_GET['sign'], $_GET['uid'], $_GET['time'])) {
	if (IsLogin()) {
		header('HTTP/1.1 302 Found');
		header('Location: /search.php');
		die();
	}
	if (!CheckLogin($_GET['sign'], $_GET['uid'], $_GET['time'])) {
		dieHTML("Bad sign!\n");
	}
	$expireTime = (time() + 86400);
	setcookie(CookieName . '_Sign', $_GET['sign'], $expireTime);
	setcookie(CookieName . '_UID', $_GET['uid'], $expireTime);
	setcookie(CookieName . '_Time', $_GET['time'], $expireTime);
	header('HTTP/1.1 302 Found');
	header('Location: /search.php');
	die();
} else {
	dieHTML(":(\n");
}
?>
