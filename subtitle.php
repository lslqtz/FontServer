<?php
require_once('config.php');
require_once('user.php');
if (($sourcePolicy = IsLogin()) === null) {
	if (!SourcePolicy['Public']['AllowLogin']) {
		dieHTML(":(\n", 'Subtitle');
	}
	header('HTTP/1.1 302 Found');
	header('Location: login.php');
	die();
}
$filename = 'Content.ass';
$filehash = 'Unknown';
$t = time();
$sign = GenerateSign($sourcePolicy[0], $sourcePolicy[1], 0, $t, $filename, $filehash);
HTMLStart('', GetUserBar($sourcePolicy[0], $sourcePolicy[1], true));
echo "<script src=\"base64.js\"></script>\n";
	echo "<script>function Convert() { let content = document.getElementById('file').value; if (content === null || content === '') { alert('坏内容!'); return false; } document.forms[0].children[0].value = Base64.encode(content); return true; }</script>\n";
echo "<label for=\"file\">ASS 字幕内容:</label>\n<br>\n";
echo "<textarea id=\"file\" spellcheck=\"false\" autocomplete=\"off\" style=\"width: 100%; height: 600px; box-sizing: border-box;\"></textarea>\n";
echo "<form id=\"subtitleForm\" method=\"POST\" onsubmit=\"return Convert();\" action=\"download.php?source={$sourcePolicy[0]}&uid={$sourcePolicy[1]}&torrent_id=0&time={$t}&sign={$sign}&filename={$filename}&upload_subtitle=1\">\n";
echo "<input type=\"hidden\" name=\"file\" value=\"\" />\n";
echo "<button type=\"submit\" style=\"margin-top: 8px;\">上传</button>\n";
echo "</form>\n";
HTMLEnd();
?>