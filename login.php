<?php
require_once('config.php');
require_once('user.php');
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
	if (isset($_COOKIE[(CookieName . '_' . 'Source')])) {
		setcookie(CookieName . '_Source', '', -1);
		setcookie(CookieName . '_UID', '', -1);
		setcookie(CookieName . '_Time', '', -1);
		setcookie(CookieName . '_Sign', '', -1);
	}
	RedirectIndex();
} else if (($loginPolicy = IsLogin()) !== null && $loginPolicy[0] !== 'Public') {
	RedirectIndex();
} else if (isset($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
	//if (!isset(SourcePolicy[$_GET['source']])) {
	if (!is_numeric($_GET['uid']) || !is_numeric($_GET['time'])) {
		dieHTML("坏参数!", 'Login');
	}
	if (!CheckLoginBySign($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'])) {
		dieHTML("坏签名!", 'Login');
	}
	$expireTime = (time() + LoginExpireTime);
	setcookie(CookieName . '_Source', $_GET['source'], $expireTime);
	setcookie(CookieName . '_UID', intval($_GET['uid']), $expireTime);
	setcookie(CookieName . '_Time', intval($_GET['time']), $expireTime);
	setcookie(CookieName . '_Sign', $_GET['sign'], $expireTime);
	RedirectIndex();
} else if (!SourcePolicy['Public']['AllowLogin']) {
	dieHTML(":(", 'Login');
} else if (!empty($_POST['username']) && isset($_POST['password'])) {
	if (($userID = CheckLoginByUsername($_POST['username'], $_POST['password'])) <= 0) {
		dieHTML("坏账号!", 'Login');
	}
	$source = 'Public';
	$userID = intval($userID);
	$t = time();
	$expireTime = ($t + LoginExpireTime);
	setcookie(CookieName . '_Source', $source, $expireTime);
	setcookie(CookieName . '_UID', $userID, $expireTime);
	setcookie(CookieName . '_Time', $t, $expireTime);
	setcookie(CookieName . '_Sign', GenerateLoginSign($source, $userID, $t), $expireTime);
	RedirectIndex();
}
HTMLStart('Login');
echo <<<html
		<div class="login">
			<form role="login" method="POST">
				<label for="username">账号:</label>
				<input type="text" id="username" name="username" autocomplete="username" />
				<br>
				<label for="password">密码:</label>
				<input type="password" id="password" name="password" autocomplete="current-password" />
				<br>
				<button type="submit" style="margin-top: 8px;">登录</button>
			</form>
		</div>
html;
HTMLEnd();
?>
