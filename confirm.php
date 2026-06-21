<?php
require_once('config.php');
require_once('user.php');
if (isset($_GET['uid'], $_GET['time'], $_GET['code']) && is_numeric($_GET['uid']) && is_numeric($_GET['time']) && !empty($_GET['email'])) {
	if (ConfirmEmail(intval($_GET['uid']), $_GET['email'], intval($_GET['time']), $_GET['code']) <= 0) {
		dieHTML("确认失败! 可能已确认或已失效.", 'Confirm');
	}
	if (empty(SourcePolicy['Public']['RequireAdminApproval'])) {
		dieHTML("确认成功! 请直接<a href=\"login.php\">登录</a>.", 'Confirm');
	} else {
		dieHTML("邮件确认成功! 请等待管理员审批.", 'Confirm');
	}
}
dieHTML(":(", 'Confirm');
?>
