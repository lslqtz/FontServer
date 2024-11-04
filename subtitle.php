<?php
require_once('config.php');
require_once('user.php');
$loginPolicy = IsLogin();
if ($loginPolicy === null) {
	RedirectLogin();
}
if (!$loginPolicy[2]['AllowDownloadFontArchive'] && !$loginPolicy[2]['AllowDownloadSubsetSubtitle'] && !$loginPolicy[2]['AllowDownloadSubsetSubtitleWithSeparateFont']) {
	dieHTML(":(", 'Subtitle');
}
$filename = 'Content.ass';
$filehash = 'Unknown';
$t = time();
$sign = GenerateSign($loginPolicy[0], $loginPolicy[1], 0, $t, $filename, $filehash);
HTMLStart('Subtitle', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo "<script src=\"base64.js\"></script>\n";
	echo "<script>function Convert() { let content = document.getElementById('file').value; if (content === null || content === '') { alert('坏内容!'); return false; } document.forms[0].children[0].value = Base64.encode(content); return true; }</script>\n";
echo "<label for=\"file\">ASS 字幕内容:</label>\n<br>\n";
echo "<textarea id=\"file\" spellcheck=\"false\" autocomplete=\"off\" style=\"width: 100%; height: 600px; box-sizing: border-box;\"></textarea>\n";
echo "<form id=\"subtitleForm\" method=\"POST\" onsubmit=\"return Convert();\" action=\"download.php?source={$loginPolicy[0]}&uid={$loginPolicy[1]}&torrent_id=0&time={$t}&sign={$sign}&filename={$filename}&upload_subtitle=1\">\n";
echo "<input type=\"hidden\" name=\"file\" value=\"\" />\n";
echo "<button type=\"submit\" style=\"margin-top: 8px;\">上传</button>\n";
echo "</form>\n";
HTMLEnd();
?>
