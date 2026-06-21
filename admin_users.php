<?php
require_once('config.php');
require_once('user.php');
require_once('mysql.php');

$loginPolicy = IsLogin();
if ($loginPolicy === null || $loginPolicy[0] === 'Public') {
	RedirectLogin();
}

$userID = $loginPolicy[1];
if (!IsAdmin($userID)) {
	dieHTML("权限不足!", '用户管理');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['target_user'])) {
	if (!ConnectDB()) {
		dieHTML("无法连接数据库!", '用户管理');
	}
	$targetUser = intval($_POST['target_user']);
	if ($targetUser === $userID) {
		dieHTML("不能操作自己!", '用户管理');
	}
	
	if ($_POST['action'] === 'toggle_role') {
		$db->exec("UPDATE `users` SET `role` = IF(`role` = 'admin', 'user', 'admin') WHERE `id` = {$targetUser}");
	} else if ($_POST['action'] === 'toggle_status') {
		$db->exec("UPDATE `users` SET `status` = IF(`status` = 1, 0, 1) WHERE `id` = {$targetUser}");
	}
	header("Location: admin_users.php");
	exit;
}

HTMLStart('用户管理', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo "<h2>用户管理</h2>";

if (ConnectDB()) {
	$stmt = $db->query("SELECT `id`, `username`, `email`, `role`, `status`, `created_at` FROM `users` ORDER BY `id` ASC");
	if ($stmt) {
		$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo "<table border=\"2\">";
		echo "<thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created At</th><th>Actions</th></tr></thead>";
		echo "<tbody>";
		foreach ($users as $u) {
			$statusStr = $u['status'] == 1 ? 'Active' : ($u['status'] == 2 ? 'Pending Admin' : 'Pending/Banned');
			echo "<tr>";
			echo "<td>{$u['id']}</td>";
			echo "<td>" . htmlspecialchars($u['username']) . "</td>";
			echo "<td>" . htmlspecialchars($u['email']) . "</td>";
			echo "<td>{$u['role']}</td>";
			echo "<td>{$statusStr}</td>";
			echo "<td>{$u['created_at']}</td>";
			echo "<td>";
			if ($u['id'] !== $userID) {
				echo "<form method=\"POST\" style=\"display:inline;\">";
				echo "<input type=\"hidden\" name=\"target_user\" value=\"{$u['id']}\" />";
				echo "<button type=\"submit\" name=\"action\" value=\"toggle_role\">切换权限</button> ";
				echo "<button type=\"submit\" name=\"action\" value=\"toggle_status\">切换状态</button>";
				echo "</form>";
			}
			echo "</td>";
			echo "</tr>";
		}
		echo "</tbody></table>";
	}
}

HTMLEnd();
?>
