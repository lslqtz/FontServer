<?php
require_once('config.php');
require_once('mysql.php');
require_once('mail.php');

function GetUserApprovedFontCount(int $userID): int {
	global $db;
	if (!ConnectDB()) {
		return 0;
	}
	$stmt = $db->prepare("SELECT COUNT(*) FROM `fonts_meta` WHERE `uploader` = ? AND `status` = 'approved'");
	try {
		if ($stmt->execute([$userID])) {
			$count = intval($stmt->fetchColumn(0));
			$stmt->closeCursor();
			return $count;
		}
	} catch (Throwable $e) {}
	return 0;
}

function GetUserContributorLevel(int $userID): int {
	$count = GetUserApprovedFontCount($userID);
	$levels = defined('ContributorLevels') ? ContributorLevels : [1, 2, 4, 8, 16, 32, 64, 128, 256, 512];
	$level = 0;
	for ($i = 0; $i < 10; $i++) {
		if ($count >= $levels[$i]) {
			$level = $i + 1;
		} else {
			break;
		}
	}
	return $level;
}

function GetSourcePolicy(string $source, int $uid): array {
	if (!isset(SourcePolicy[$source])) {
		return [];
	}
	$policy = SourcePolicy[$source];
	if ($source === 'Public' && $uid !== SourcePolicy['Public']['PublicUID']) {
		$level = GetUserContributorLevel($uid);
		for ($l = $level; $l >= 1; $l--) {
			if (isset(SourcePolicy["Contributor_{$l}"])) {
				$policy = SourcePolicy["Contributor_{$l}"];
				break;
			}
		}
	}
	return $policy;
}

function IsLogin(): ?array {
	// Return loginPolicy.
	if (isset($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')]) && CheckLoginBySign($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')])) {
		$source = $_COOKIE[(CookieName . '_' . 'Source')];
		$uid = intval($_COOKIE[(CookieName . '_' . 'UID')]);
		$policy = GetSourcePolicy($source, $uid);
		if (!empty($policy) && $policy['AllowLogin']) {
			return [$source, $uid, $policy];
		}
	}
	if (SourcePolicy['Public']['PublicUID'] > 0) {
		// Public users use the public policy, but the public policy is the default policy, so it is not only used by public users.
		$publicSourcePolicy = SourcePolicy['Public'];
		$publicSourcePolicy['AllowLogout'] = false;
		return ['Public', SourcePolicy['Public']['PublicUID'], $publicSourcePolicy];
	}
	return null;
}
function GetUserBar(string $source, int $userID, bool $allowLogout = false): string {
	$role = GetUserRole($userID);
	$links = "";
	if ($source !== 'Public' || $userID !== SourcePolicy['Public']['PublicUID']) {
		$links .= "&nbsp;<a href=\"upload.php\">贡献字体</a>";
	}
	if ($role === 'admin') {
		$links .= "&nbsp;|&nbsp;<a href=\"admin_users.php\">用户管理</a>";
		$links .= "&nbsp;|&nbsp;<a href=\"admin_fonts.php\">字体审核</a>";
	}
	if ($allowLogout) {
		$links .= "&nbsp;|&nbsp;<a href=\"login.php?logout=1\">登出</a>";
	}
	if ($source === 'Public') {
		if (($username = GetUsernameByID($userID)) !== null) {
			$levelStr = "";
			if ($userID !== SourcePolicy['Public']['PublicUID']) {
				$level = GetUserContributorLevel($userID);
				if ($level > 0) {
					$levelStr = ", Level {$level} 贡献者";
				}
			}
			return "你好, {$username} (UID: {$userID}{$levelStr}){$links}";
		}
	}
	return "你好, {$source} 用户 (UID: {$userID}){$links}";
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
function GetUsernameByID(int $userID): ?string {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return null;
	}
	$stmt = $db->prepare("SELECT `username` FROM `users` WHERE `status` = 1 AND `id` = ? LIMIT 1");
	try {
		if (!$stmt->execute([$userID])) {
			return null;
		}
	} catch (Throwable $e) {
		return null;
	}
	$username = $stmt->fetchColumn(0);
	$stmt->closeCursor();

	return ($username !== false ? $username : null);
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
	$password = sha1($password);
	$status = (SourcePolicy['Public']['EmailExpireTime'] < 0 && empty(SourcePolicy['Public']['RequireAdminApproval'])) ? 1 : 0;
	$stmt = $db->prepare("INSERT INTO `users` (`username`, `email`, `password`, `status`) VALUES (?, ?, ?, ?)");
	try {
		if (!$stmt->execute([$username, $email, $password, $status])) {
			return false;
		}
	} catch (Throwable $e) {
		return false;
	}
	$userID = $db->lastInsertId();
	$stmt->closeCursor();

	if ($userID === false || $userID <= 0) {
		return false;
	}

	if ($userID == 1) {
		$db->exec("UPDATE `users` SET `role` = 'admin', `status` = 1 WHERE `id` = 1 LIMIT 1");
	}

	if (SourcePolicy['Public']['EmailExpireTime'] > 0) {
		SendActivationEmail($userID, $username, $email);
	}

	return true;
}
function GetUserRole(int $userID): string {
	global $db;
	if (!ConnectDB()) {
		return 'user';
	}
	$stmt = $db->prepare("SELECT `role` FROM `users` WHERE `status` = 1 AND `id` = ? LIMIT 1");
	try {
		if (!$stmt->execute([$userID])) {
			return 'user';
		}
	} catch (Throwable $e) {
		return 'user';
	}
	$role = $stmt->fetchColumn(0);
	$stmt->closeCursor();

	return ($role !== false ? $role : 'user');
}
function IsAdmin(int $userID): bool {
	return (GetUserRole($userID) === 'admin');
}
function ConfirmEmail(int $userID, string $email, int $timestamp, string $code): int {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return 0;
	}
	if (SourcePolicy['Public']['EmailExpireTime'] <= 0 || ($timestamp + SourcePolicy['Public']['EmailExpireTime']) < time()) {
		return 0;
	}
	if ($code !== GetActivationCode($userID, $email, $timestamp)) {
		return 0;
	}
	$newStatus = !empty(SourcePolicy['Public']['RequireAdminApproval']) ? 2 : 1;
	$result = $db->exec("UPDATE `users` SET `status` = {$newStatus} WHERE `status` = 0 AND `id` = {$userID} LIMIT 1");
	return ($result !== false ? $result : 0);
}
function GetActivationCode(int $userID, string $email, int $timestamp): string {
	return sha1(SourcePolicy['Public']['key'] . "{$userID}-{$email}-{$timestamp}" . SourcePolicy['Public']['key']);
}
function SendActivationEmail(int $userID, string $username, string $email) {
	$t = time();
	$activationCode = GetActivationCode($userID, $email, $t);
	SendMail($email, '账号激活邮件', "你好, {$username}. 你收到此邮件是因为你需要激活在 " . Title . " 注册的账号.\n\n若为本人操作, 请点击下方链接:\nhttp://font.acgvideo.cn/confirm.php?uid={$userID}&email=" . rawurlencode($email) . "&time={$t}&code={$activationCode}\n\n若非本人操作, 则无需采取任何行动.\n");
}
?>
