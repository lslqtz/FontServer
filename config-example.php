<?php
set_time_limit(300);
date_default_timezone_set('Asia/Shanghai');
putenv('LANG=C.UTF-8');
define('Title', 'FontServer');
define('MaxMemoryMB', 1024);
define('CompressLevel', 3);
define('DBAddress', 'mysql:host=localhost;dbname=FontServer');
define('DBUsername', 'FontServer');
define('DBPassword', 'FontServer');
define('DBPersistent', true);
define('SMTPAddress', 'smtp.example.com');
define('SMTPPort', 465);
define('SMTPSSL', true);
define('SMTPUsername', 'font@example.com');
define('SMTPPassword', 'FontServer');
define('CookieName', 'FontServer-Auth');
define('MaxParallels', 2);
define('LoginExpireTime', 3600);
define('DownloadExpireTime', 300);
define('FontPath', array('font'));
define('FontPendingPath', array('font_pending'));
define('SysCacheDir', sys_get_temp_dir());
define('LanguageID', [1028, 1033, 1041, 1152, 2052, 2057, 3076, 4100, 5124]);
// Required approved uploaded fonts for levels 1 to 10. Difficulty increases progressively.
define('ContributorLevels', [1, 2, 4, 8, 16, 32, 64, 128, 256, 512]);
define('SourcePolicy', array(
	'Public' => array(
		'key' => 'FontServer',
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowRegister' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => false,
		'ProcessFontForEverySubtitle' => true, // false: High memory consumption, true: High performance consumption.
		'MaxCacheFontCount' => 0,
		'MaxSubtitleFilesizeMB' => 1,
		'MaxSubtitleFileCount' => 24,
		'MaxDownloadFontCount' => 24,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
		'EmailExpireTime' => 0, // 0: Manual approval.
		'RequireAdminApproval' => false,
		'PublicUID' => 10000000 // 0: Disable public.
	),
	'Contributor_1' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => false,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 2,
		'MaxSubtitleFilesizeMB' => 2,
		'MaxSubtitleFileCount' => 32,
		'MaxDownloadFontCount' => 32,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_2' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => false,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 4,
		'MaxSubtitleFilesizeMB' => 3,
		'MaxSubtitleFileCount' => 36,
		'MaxDownloadFontCount' => 36,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_3' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => false,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 6,
		'MaxSubtitleFilesizeMB' => 4,
		'MaxSubtitleFileCount' => 40,
		'MaxDownloadFontCount' => 40,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_4' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 8,
		'MaxSubtitleFilesizeMB' => 6,
		'MaxSubtitleFileCount' => 48,
		'MaxDownloadFontCount' => 48,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_5' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 10,
		'MaxSubtitleFilesizeMB' => 8,
		'MaxSubtitleFileCount' => 56,
		'MaxDownloadFontCount' => 56,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_6' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 12,
		'MaxSubtitleFilesizeMB' => 10,
		'MaxSubtitleFileCount' => 64,
		'MaxDownloadFontCount' => 64,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_7' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 14,
		'MaxSubtitleFilesizeMB' => 12,
		'MaxSubtitleFileCount' => 72,
		'MaxDownloadFontCount' => 72,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_8' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 16,
		'MaxSubtitleFilesizeMB' => 16,
		'MaxSubtitleFileCount' => 80,
		'MaxDownloadFontCount' => 80,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_9' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => true,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 20,
		'MaxSubtitleFilesizeMB' => 20,
		'MaxSubtitleFileCount' => 88,
		'MaxDownloadFontCount' => 88,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'Contributor_10' => array(
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => true,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true,
		'MaxCacheFontCount' => 24,
		'MaxSubtitleFilesizeMB' => 24,
		'MaxSubtitleFileCount' => 96,
		'MaxDownloadFontCount' => 96,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
	),
	'FontServer' => array(
		'key' => 'FontServer',
		'AllowLogin' => true,
		'AllowLogout' => true,
		'AllowDownloadFont' => true,
		'AllowDownloadFontArchive' => false,
		'AllowDownloadSubsetSubtitle' => true,
		'AllowDownloadSubsetSubtitleWithSeparateFont' => true,
		'ProcessFontForEverySubtitle' => true, // false: High memory consumption, true: High performance consumption.
		'MaxCacheFontCount' => 0,
		'MaxSubtitleFilesizeMB' => 6,
		'MaxSubtitleFileCount' => 100,
		'MaxDownloadFontCount' => 48,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100
	)
));

function GetMainFontPath(string $fontfile): string {
	return FontPath[0] . "/{$fontfile}";
}
function GetPendingFontPath(string $fontfile): string {
	return FontPendingPath[0] . "/{$fontfile}";
}
function GetFontPath(string $fontfile): ?string {
	foreach (FontPath as &$fontPath) {
		if (is_file("{$fontPath}/{$fontfile}")) {
			return "{$fontPath}/{$fontfile}";
		}
	}
	return null;
}
function GenerateSign(string $source, int $uid, int $torrentID, int $timestamp, string $filename, string $filehash): ?string {
	if (!isset(SourcePolicy[$source])) {
		return null;
	}
	return sha1(SourcePolicy[$source]['key'] . "Download/{$source}_{$uid}-{$torrentID}-{$timestamp}-{$filename}-{$filehash}" . SourcePolicy[$source]['key']);
}
function CheckSign(string $source, int $uid, int $torrentID, int $timestamp, string $sign, string $filename, string $filehash): ?string {
	if ($sign !== sha1(SourcePolicy[$source]['key'] . "Download/{$source}_{$uid}-{$torrentID}-{$timestamp}-{$filename}-{$filehash}" . SourcePolicy[$source]['key']) || ($timestamp + DownloadExpireTime) < time()) {
		return null;
	}
	return $sign;
}
function HTMLStart(string $prefix = '', string $userbar = null) {
	$title = Title;
	if ($prefix !== '') {
		$title = "{$prefix} - {$title}";
	}
	$userbar = ($userbar !== null ? "\n			<p>{$userbar}</p>" : '');
	echo <<<html
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="dark.css">
		<link rel="icon" href="favicon.ico">
		<title>{$title}</title>
	</head>
	<body>
		<div class="header">
			<h2>{$title}</h2>{$userbar}
		</div>
		<hr>
html;
	echo "\n";
}
function HTMLOutput(string $code) {
	echo "		{$code}\n";
}
function HTMLEnd() {
	echo <<<html
	</body>
</html>
html;
	echo "\n";
}
function dieHTML(string $string, string $prefix = '') {
	HTMLStart($prefix);
	HTMLOutput("<p>{$string}</p>");
	HTMLEnd();
	die();
}
function RedirectIndex() {
	header('HTTP/1.1 302 Found');
	header('Location: /');
	die();
}
function RedirectLogin() {
	header('HTTP/1.1 302 Found');
	header('Location: /login.php');
	die();
}
function Queue(): array {
	$locks = [];
	for ($p = 1; $p <= MaxParallels; $p++) {
		$pFilename = "FSLock-{$p}.lock";
		if (!is_file($pFilename)) {
			$locks[$p] = $pFilename;
		} else if (($pFileCreationTime = filectime($pFilename)) !== false) {
			$locks[$pFileCreationTime] = $pFilename;
		}
	}
	ksort($locks);
	$minLock = array_key_first($locks);
	if ($minLock === null) {
		return [-1];
	} else if ($minLock > 2333 && $minLock < (time() - 300)) {
		Unqueue([0, $locks[$minLock], null]);
	}
	$lockRes = fopen($locks[$minLock], 'w');
	$waitSec = 0;
	while (!flock($lockRes, LOCK_EX | LOCK_NB)) {
		if (($waitSec++) > 12) {
			return [-2];
		}
		sleep(1);
	}
	return [$minLock, $locks[$minLock], $lockRes];
}
function Unqueue(?array $queueInfo): bool {
	if ($queueInfo === null || $queueInfo[0] < 0) {
		return false;
	}
	if ($queueInfo[2] !== null) {
		flock($queueInfo[2], LOCK_UN);
		fclose($queueInfo[2]);
	}
	return @unlink($queueInfo[1]);
}
?>
