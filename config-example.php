<?php
set_time_limit(300);
date_default_timezone_set('Asia/Shanghai');
define('Title', 'MDU-FontServer');
define('DBAddress', 'mysql:host=localhost;dbname=FontServer');
define('DBUsername', 'FontServer');
define('DBPassword', 'FontServer');
define('DBPersistent', true);
define('LanguageID', [1028, 1033, 1041, 1152, 2052, 2057, 3076, 4100, 5124]);
define('MaxMemoryMB', 1024);
define('CompressLevel', 3);
define('SourcePolicy', array(
	'FontServer' => array(
		'key' => 'FontServer',
		'AllowLogin' => true,
		'AllowDownloadFont' => false,
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
define('CookieName', 'FontServer-Auth');
define('LoginExpireTime', 3600);
define('DownloadExpireTime', 300);
define('FontPath', array('../font', '../fontoss/xz'));
define('SysCacheDir', sys_get_temp_dir());

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
function CheckLogin(string $source, int $uid, int $timestamp, string $sign): bool {
	if ($sign !== sha1(SourcePolicy[$source]['key'] . "Login/{$source}_{$uid}-{$timestamp}" . SourcePolicy[$source]['key']) || ($timestamp + LoginExpireTime) < time()) {
		return false;
	}
	return true;
}
function IsLogin(): ?array {
	// Return sourcePolicy.
	if (isset($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')]) && CheckLogin($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')])) {
		if (SourcePolicy[$_COOKIE[(CookieName . '_' . 'Source')]]['AllowLogin']) {
			return SourcePolicy[$_COOKIE[(CookieName . '_' . 'Source')]];
		}
	}
	return null;
}
function HTMLStart(string $prefix = '') {
	$title = Title;
	if ($prefix !== '') {
		$title = "{$prefix} - {$title}";
	}
	echo <<<html
	<!DOCTYPE html>
	<html>
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<link rel="stylesheet" href="dark.css">
			<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
			<title>{$title}</title>
		</head>
		<body>
			<h2 style="margin: 0;">{$title}</h2>
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
function ShowTable(array $fontsResult, bool $foundFont = true) {
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
		echo "<td>{$fontResult['fontfile']}</td>\n";
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
