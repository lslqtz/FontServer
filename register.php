<?php
require_once('config.php');
require_once('user.php');
if (!SourcePolicy['Public']['AllowRegister']) {
	dieHTML(":(", 'Register');
}
if (($loginPolicy = IsLogin()) !== null && $loginPolicy[0] !== 'Public') {
	RedirectIndex();
}
if (!empty($_POST['username']) && !empty($_POST['email']) && !empty($_POST['password'])) {
	if (!RegisterUser($_POST['username'], $_POST['email'], $_POST['password'])) {
		dieHTML("注册失败!", 'Register');
	}
	dieHTML("注册成功! 最后一步: 请查收邮箱中的确认邮件.", 'Register');
}
HTMLStart('Register');
echo <<<html
		<div class="register">
			<form role="register" method="POST">
				<label for="username">账号:</label>
				<input type="text" id="username" name="username" autocomplete="username" />
				<br>
				<label for="email">Email:</label>
				<input type="text" id="email" name="email" autocomplete="email" />
				<br>
				<label for="password">密码:</label>
				<input type="password" id="password" name="password" autocomplete="new-password" />
				<br>
				<button type="submit" style="margin-top: 8px;">注册</button>
			</form>
		</div>
html;
HTMLEnd();
?>
