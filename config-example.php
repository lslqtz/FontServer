<?php
set_time_limit(300);
date_default_timezone_set('Asia/Shanghai');
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
define('LoginExpireTime', 3600);
define('DownloadExpireTime', 300);
define('FontPath', array('font'));
define('SysCacheDir', sys_get_temp_dir());
define('LanguageID', [1028, 1033, 1041, 1152, 2052, 2057, 3076, 4100, 5124]);
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
		'MaxFilesizeMB' => 2,
		'MaxDownloadFontCount' => 12,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100,
		'EmailExpireTime' => 0, // 0: Manual approval.
		'PublicUID' => 10000000 // 0: Disable public.
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
		'MaxFilesizeMB' => 6,
		'MaxDownloadFontCount' => 48,
		'MinSearchLength' => 2,
		'MaxSearchFontCount' => 100
	)
));

function GetMainFontPath(string $fontfile): string {
	return FontPath[0] . "/{$fontfile}";
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
	$userbar = ($userbar !== null ? "<p>{$userbar}</p>" : '');
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
				<h2>{$title}</h2>
				{$userbar}
			</div>
			<hr>
html;
}
function HTMLEnd() {
	echo <<<html
		</body>
	</html>
html;
}
function dieHTML(string $string, string $prefix = '') {
	HTMLStart($prefix);
	echo "<p>{$string}<p>\n";
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
function ShowTable(array $fontsResult, bool $foundFont = true, ?array $downloadFontArr = null, bool $uploadSubtitle = false) {
	echo "<p>" . ($foundFont ? '找到字体数: ' : '缺失字体数: ') . count($fontsResult) . "</p>\n";
	echo "<div class=\"searchResult\">\n<table border=\"2\">\n";
	echo "<thead>\n<tr>\n";
	echo "<th>ID</th>\n";
	echo "<th>Uploader</th>\n";
	echo "<th>Font FileName</th>\n";
	echo "<th>Font Name</th>\n";
	echo "<th>Font FullName</th>\n";
	echo "<th>Font PostScriptName</th>\n";
	echo "<th>Font SubFamily</th>\n";
	echo "<th>Font Size</th>\n";
	echo "<th>Font Created Date</th>\n";
	echo "</tr>\n</thead>\n<tbody>\n";
	foreach ($fontsResult as &$fontResult) {
		echo "<tr style=\"height: 42px; white-space: pre-line;\">\n";
		echo "<td>{$fontResult['id']}</td>\n";
		echo "<td>{$fontResult['uploader']}</td>\n";
		if ($downloadFontArr !== null && ($sign = GenerateSign($downloadFontArr[0], $downloadFontArr[1], $downloadFontArr[2], $downloadFontArr[3], $fontResult['fontfile'], ($uploadSubtitle ? 'Unknown' : sha1($fontResult['id'])))) !== null) {
			echo "<td><a href=\"download.php?source={$downloadFontArr[0]}&uid={$downloadFontArr[1]}&torrent_id={$downloadFontArr[2]}&time={$downloadFontArr[3]}&sign={$sign}&filename=" . rawurlencode($fontResult['fontfile']) . ($uploadSubtitle ? '&upload_subtitle=1' : '') . "&font_id={$fontResult['id']}\">{$fontResult['fontfile']}</a></td>\n";
		} else {
			echo "<td>{$fontResult['fontfile']}</td>\n";
		}
		echo "<td>{$fontResult['fontname']}</td>\n";
		echo "<td>{$fontResult['fontfullname']}</td>\n";
		echo "<td>{$fontResult['fontpsname']}</td>\n";
		echo "<td>{$fontResult['fontsubfamily']}</td>\n";
		echo "<td>" . round(($fontResult['fontsize'] / 1024 / 1024), 2) . " MB</td>\n";
		echo "<td>{$fontResult['created_at']}</td>\n";
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n</div>\n";
}
?>
