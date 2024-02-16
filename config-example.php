<?php
set_time_limit(300);
define('Title', 'MDU-FontServer');
define('DBAddress', 'mysql:host=localhost;dbname=FontServer');
define('DBUsername', 'FontServer');
define('DBPassword', 'FontServer');
define('DBPersistent', true);
define('AllowDownloadFont', false);
define('AllowDownloadSubsetSubtitle', true);
define('AllowDownloadSubsetSubtitleWithSeparateFont', true);
define('ProcessFontForEverySubtitle', true); // false: High memory consumption, true: High performance consumption.
define('MaxMemoryMB', 1024);
define('MaxFilesizeMB', 6);
define('MinSearchLength', 2);
define('MaxCacheFontCount', 6);
define('MaxDownloadFontCount', 48);
define('MaxSearchFontCount', 100);
define('SignKey', array('FontServer' => 'FontServer'));
define('CookieName', 'FontServer-Auth');
define('LoginExpireTime', 3600);
define('DownloadExpireTime', 300);
define('FontPath', array('../font', '../fontoss/xz'));
define('SysCacheDir', sys_get_temp_dir());

function GetMainFontPath(string $fontfile): string {
	return FontPath[0] . "/{$fontfile}";
}
function GetFontPath(string $fontfile): string {
	foreach (FontPath as $fontPath) {
		if (is_file("{$fontPath}/{$fontfile}")) {
			return "{$fontPath}/{$fontfile}";
		}
	}
	return null;
}
function CheckLogin(string $source, int $uid, int $timestamp, string $sign): bool {
	if ($sign !== sha1(SignKey[$source] . "Login/{$source}_{$uid}-{$timestamp}" . SignKey[$source]) || ($timestamp + LoginExpireTime) < time()) {
		return false;
	}
	return true;
}
function IsLogin(): bool {
	if (isset($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')]) && CheckLogin($_COOKIE[(CookieName . '_' . 'Source')], $_COOKIE[(CookieName . '_' . 'UID')], $_COOKIE[(CookieName . '_' . 'Time')], $_COOKIE[(CookieName . '_' . 'Sign')])) {
		return true;
	}
	return false;
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
	foreach ($fontsResult as $fontResult) {
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
