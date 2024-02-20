<?php
require_once('config.php');
require_once('user.php');
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
	if (!CheckLoginBySign($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
		dieHTML("坏签名!\n", 'Login');
	}
	$expireTime = (time() + LoginExpireTime);
	setcookie(CookieName . '_Source', $_GET['source'], $expireTime);
	setcookie(CookieName . '_UID', intval($_GET['uid']), $expireTime);
	setcookie(CookieName . '_Time', intval($_GET['time']), $expireTime);
	setcookie(CookieName . '_Sign', $_GET['sign'], $expireTime);
	header('HTTP/1.1 302 Found');
	header('Location: /search.php');
	die();
} else if (SourcePolicy['Public']['AllowLogin']) {
	if (!empty($_POST['username']) && isset($_POST['password'])) {
		if (($userID = CheckLoginByUsername($_POST['username'], $_POST['password'])) <= 0) {
			dieHTML("坏账号!\n", 'Login');
		}
		$source = 'Public';
		$userID = intval($userID);
		$t = time();
		$expireTime = ($t + LoginExpireTime);
		setcookie(CookieName . '_Source', $source, $expireTime);
		setcookie(CookieName . '_UID', $userID, $expireTime);
		setcookie(CookieName . '_Time', $t, $expireTime);
		setcookie(CookieName . '_Sign', GenerateLoginSign($source, $userID, $t), $expireTime);
		header('HTTP/1.1 302 Found');
		header('Location: /');
	} else if (IsLogin() !== null) {
		dieHTML("已登录!\n", 'Login');
	}
}
if (!SourcePolicy['Public']['AllowLogin']) {
	dieHTML(":(\n", 'Login');
}
HTMLStart('Login');
echo <<<html
		<div class="login">
			<form role="login" method="POST">
				<label for="username">账号:</label>
				<input type="text" name="username" autocomplete="username" />
				<br>
				<label for="password">密码:</label>
				<input type="password" name="password" autocomplete="current-password" />
				<br>
				<button type="submit" style="margin-top: 8px;">登录</button>
			</form>
		</div>
html;
HTMLEnd();
?>
