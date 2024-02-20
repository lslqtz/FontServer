<?php
require_once('config.php');
require_once('mysql.php');
require_once('mail.php');

function IsLogin(): ?array {
	// Return sourcePolicy.
	if (isset($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')]) && CheckLoginBySign($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')])) {
		if (SourcePolicy[$_COOKIE[(CookieName . '_' . 'Source')]]['AllowLogin']) {
			return [$_COOKIE[(CookieName . '_' . 'Source')], intval($_COOKIE[(CookieName . '_' . 'UID')]), SourcePolicy[$_COOKIE[(CookieName . '_' . 'Source')]]];
		}
	}
	return null;
}
function GenerateLoginSign(string $source, int $uid, int $timestamp): string {
	return sha1(SourcePolicy[$source]['key'] . "Login/{$source}_{$uid}-{$timestamp}" . SourcePolicy[$source]['key']);
}
function CheckLoginBySign(string $source, int $uid, int $timestamp, string $sign): bool {
	if ($sign !== sha1(SourcePolicy[$source]['key'] . "Login/{$source}_{$uid}-{$timestamp}" . SourcePolicy[$source]['key']) || ($timestamp + LoginExpireTime) < time()) {
		return false;
	}
	return true;
}
function CheckLoginByUsername(string $username, string $password): int {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return 0;
	}
	$password = sha1($password);
	$stmt = $db->prepare("SELECT `id` FROM `users` WHERE `status` = 1 AND `username` = ? AND `password` = ? LIMIT 1");
	try {
		if (!$stmt->execute([$username, $password])) {
			return 0;
		}
	} catch (Throwable $e) {
		return 0;
	}
	$userID = $stmt->fetchColumn(0);
	$stmt->closeCursor();

	return ($userID !== false ? $userID : 0);
}
function RegisterUser(string $username, string $email, string $password): bool {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return false;
	}
	$stmt = $db->prepare("INSERT INTO `users` (`username`, `email`, `password`) VALUES (?, ?, ?)");
	try {
		if (!$stmt->execute([$username, $email, $password])) {
			return false;
		}
	} catch (Throwable $e) {
		return false;
	}
	$userID = $db->lastInsertId();
	$stmt->closeCursor();

	SendActivationEmail($userID, $username, $email);

	return true;
}
function ConfirmEmail(int $userID, string $email, int $timestamp, string $code): int {
	if (($timestamp + SourcePolicy['Public']['EmailExpireTime']) < time() || ($code === GetActivationCode($userID, $email, $timestamp)) <= 0) {
		return 0;
	}
	$result = $db->exec("UPDATE `users` SET `status` = 1 WHERE `status` = 0 AND `id` = {$userID} LIMIT 1");
	return ($result !== false ? $result : 0);
}
function GetActivationCode(int $userID, string $email, int $timestamp): string {
	return sha1(SourcePolicy['Public']['key'] . "{$userID}-{$email}-{$timestamp}" . SourcePolicy['Public']['key']);
}
function SendActivationEmail(int $userID, string $username, string $email) {
	$t = time();
	$activationCode = GetActivationCode($userID, $email, $t);
	SendMail($email, '账号激活邮件', "你好, {$username}. 你收到此邮件是因为你需要激活在 FontServer 注册的账号.\n\n若为本人操作, 请点击下方链接:\nhttp://font.acgvideo.cn/confirm.php?uid={$userID}&email={$email}&time={$t}&code={$activationCode}\n\n若非本人操作, 则无需采取任何行动.\n");
}
?>
