<?php
require_once('config.php');
require_once('user.php');
$sourcePolicy = IsLogin();
HTMLStart('', ($sourcePolicy !== null ? GetUserBar($sourcePolicy[0], $sourcePolicy[1], true) : null));
echo "<p>你好, 欢迎来到 " . Title . "." . ($sourcePolicy === null ? " 请先<a href=\"login.php\">登录</a>." : '') . "</p>\n";
echo "<p>开放登录当前" . (SourcePolicy['Public']['AllowLogin'] ? '启用' : '禁用') . ", 开放注册当前" . (SourcePolicy['Public']['AllowRegister'] ? '启用' : '禁用') . ".</p>\n";
echo "<p>当前, 你可以在 "  . Title . " 上<a href=\"search.php\">搜索字体</a>, <a href=\"subtitle.php\">手动使用字幕文本</a>对字幕进行一定的处理 ,也可通过与 " . Title . " 兼容的 PT 站对字幕进行一定的处理.</p>\n";
HTMLEnd();
?>
