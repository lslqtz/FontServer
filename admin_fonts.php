<?php
require_once('config.php');
require_once('user.php');
require_once('mysql.php');
require_once('font.php');

$loginPolicy = IsLogin();
if ($loginPolicy === null || $loginPolicy[0] === 'Public') {
	RedirectLogin();
}

$userID = $loginPolicy[1];
if (!IsAdmin($userID)) {
	dieHTML("权限不足!", '字体审核');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['font_id'])) {
	if (!ConnectDB()) {
		dieHTML("无法连接数据库!", '字体审核');
	}
	$fontID = intval($_POST['font_id']);
	
	if ($_POST['action'] === 'approve') {
		if (!empty($_POST['old_id'])) {
			$oldID = intval($_POST['old_id']);
			$oldStmt = $db->prepare("SELECT `fontfile` FROM `fonts_meta` WHERE `id` = ? LIMIT 1");
			$oldStmt->execute([$oldID]);
			$oldFontfile = $oldStmt->fetchColumn(0);
			if ($oldFontfile) {
				$oldPath = GetMainFontPath($oldFontfile);
				if (is_file($oldPath)) unlink($oldPath);
				DeleteFontByID($oldID);
			}
		}

		$stmt = $db->prepare("SELECT `fontfile` FROM `fonts_meta` WHERE `id` = ? LIMIT 1");
		$stmt->execute([$fontID]);
		$fontfile = $stmt->fetchColumn(0);
		if ($fontfile) {
			$pendingPath = GetPendingFontPath($fontfile);
			$mainPath = GetMainFontPath($fontfile);
			if (is_file($pendingPath)) {
				rename($pendingPath, $mainPath);
			}
		}
		$db->exec("UPDATE `fonts_meta` SET `status` = 'approved' WHERE `id` = {$fontID}");
	} else if ($_POST['action'] === 'reject') {
		// optionally delete the file
		$stmt = $db->prepare("SELECT `fontfile` FROM `fonts_meta` WHERE `id` = ? LIMIT 1");
		$stmt->execute([$fontID]);
		$fontfile = $stmt->fetchColumn(0);
		if ($fontfile !== false) {
			$path = GetPendingFontPath($fontfile);
			if (is_file($path)) {
				unlink($path);
			}
			DeleteFontByID($fontID);
		}
	}
	header("Location: admin_fonts.php");
	exit;
}

HTMLStart('字体审核', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo "<h2>字体审核</h2>";

if (ConnectDB()) {
	// join with users to get username
	$stmt = $db->query("SELECT f.`id`, f.`fontfile`, f.`fontsize`, f.`created_at`, u.`username` 
						FROM `fonts_meta` f 
						LEFT JOIN `users` u ON f.`uploader` = u.`id` 
						WHERE f.`status` = 'pending' 
						ORDER BY f.`created_at` ASC");
	if ($stmt) {
		$fonts = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (count($fonts) === 0) {
			echo "<p>当前没有需要审核的字体。</p>";
		} else {
			echo "<table border=\"2\">";
			echo "<thead><tr><th>ID</th><th>Uploader</th><th>Font Info (Pending)</th><th>Old Font (Approved)</th><th>Actions</th></tr></thead>";
			echo "<tbody>";
			foreach ($fonts as $f) {
				echo "<tr>";
				echo "<td>{$f['id']}</td>";
				echo "<td>" . htmlspecialchars($f['username'] ?? 'Unknown') . "</td>";
				
				$pendingPath = GetPendingFontPath($f['fontfile']);
				$pendingHash = is_file($pendingPath) ? sha1_file($pendingPath) : 'N/A';
				$pendingSize = round($f['fontsize'] / 1024 / 1024, 2) . " MB";
				
				// Get font name info for duplicate detection
				$fstmt = $db->query("SELECT `fontname`, `fontfullname`, `fontsubfamily` FROM `fonts` WHERE `id` = {$f['id']} LIMIT 1");
				$fontInfoRow = $fstmt ? $fstmt->fetch(PDO::FETCH_ASSOC) : false;
				
				$oldHTML = "<span style='color:green'>无重复 (New Font)</span>";
				$oldID = 0;
				
				if ($fontInfoRow && !empty($fontInfoRow['fontfullname'])) {
					$ext = pathinfo($f['fontfile'], PATHINFO_EXTENSION);
					$dupe = DetectDuplicateFont($ext, $fontInfoRow['fontname'], $fontInfoRow['fontfullname'], $fontInfoRow['fontsubfamily'], false, 'approved');
					if ($dupe[0] > 0) {
						$oldID = $dupe[0];
						$oldPath = GetMainFontPath($dupe[1]);
						$oldHash = is_file($oldPath) ? sha1_file($oldPath) : 'N/A';
						$oldSize = is_file($oldPath) ? round(filesize($oldPath) / 1024 / 1024, 2) . " MB" : 'N/A';
						$oldHTML = "ID: {$oldID}<br>File: " . htmlspecialchars($dupe[1]) . "<br>Size: {$oldSize}<br>SHA1: <span style='color:" . ($oldHash === $pendingHash ? "gray" : "red") . "'>{$oldHash}</span>";
					}
				}
				
				$newHTML = "File: " . htmlspecialchars($f['fontfile']) . "<br>Size: {$pendingSize}<br>SHA1: {$pendingHash}";
				
				echo "<td>{$newHTML}</td>";
				echo "<td>{$oldHTML}</td>";
				
				echo "<td>";
				echo "<form method=\"POST\" style=\"display:inline;\">";
				echo "<input type=\"hidden\" name=\"font_id\" value=\"{$f['id']}\" />";
				if ($oldID > 0) {
					echo "<input type=\"hidden\" name=\"old_id\" value=\"{$oldID}\" />";
					echo "<button type=\"submit\" name=\"action\" value=\"approve\">Approve (替换旧版)</button><br><br>";
				} else {
					echo "<button type=\"submit\" name=\"action\" value=\"approve\">Approve (通过)</button><br><br>";
				}
				echo "<button type=\"submit\" name=\"action\" value=\"reject\">Reject (拒绝并删除)</button>";
				echo "</form>";
				echo "</td>";
				echo "</tr>";
			}
			echo "</tbody></table>";
		}
	}
}

HTMLEnd();
?>
