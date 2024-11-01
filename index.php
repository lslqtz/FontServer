<?php
require_once('config.php');
require_once('user.php');
$loginPolicy = IsLogin();
HTMLStart('', ($loginPolicy !== null ? GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']) : null));
$allowLogin = SourcePolicy['Public']['AllowLogin'];
$allowRegister = SourcePolicy['Public']['AllowRegister'];
echo "<p>你好, 欢迎来到 " . Title . ".";
if ($allowLogin) {
	if ($loginPolicy === null) {
		echo " 请先<a href=\"login.php\">登录</a>";
		if ($allowRegister) {
			echo "或<a href=\"register.php\">注册</a>";
		}
		echo ".";
	} else if ($loginPolicy[0] === 'Public') {
		echo " 若已有账号, 可以<a href=\"login.php\">登录</a>";
		if ($allowRegister) {
			echo "或<a href=\"register.php\">注册</a>";
		}
		echo ".";
	}
}
echo "</p>\n";
echo "<p>开放登录当前" . ($allowLogin ? '启用' : '禁用') . ", 开放注册当前" . ($allowRegister ? (SourcePolicy['Public']['EmailExpireTime'] > 0 ? '启用' : '启用 (需要审批)') : '禁用') . ".</p>\n";
echo "<p>当前, 你可以在 "  . Title . " 上<a href=\"search.php\">搜索字体</a>, <a href=\"subtitle.php\">手动使用字幕文本</a>对字幕进行一定的处理 ,也可通过与 " . Title . " 兼容的 PT 站对字幕进行一定的处理.</p>\n";
HTMLEnd();
?>
