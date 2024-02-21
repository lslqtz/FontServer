<?php
require_once('config.php');
require_once('user.php');
if (($sourcePolicy = IsLogin()) === null) {
	if (!SourcePolicy['Public']['AllowLogin']) {
		dieHTML(":(\n", 'Index');
	}
	header('HTTP/1.1 302 Found');
	header('Location: login.php');
	die();
}
HTMLStart('', GetUserBar($sourcePolicy[0], $sourcePolicy[1], ($sourcePolicy[0] === 'Public')));
echo "<p>你好, 欢迎来到 " . Title . ".</p>\n<p>当前, 你可以在 "  . Title . " 上<a href=\"search.php\">搜索字体</a>, <a href=\"subtitle.php\">手动使用字幕文本</a>对字幕进行一定的处理 ,也可通过与 " . Title . " 兼容的 PT 站对字幕进行一定的处理.</p>\n";
HTMLEnd();
?>
