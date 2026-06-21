<?php
require_once('config.php');
require_once('user.php');
require_once('fontFile.php');

$loginPolicy = IsLogin();
if ($loginPolicy === null || ($loginPolicy[0] === 'Public' && $loginPolicy[1] === SourcePolicy['Public']['PublicUID'])) {
	RedirectLogin();
}

$userID = $loginPolicy[1];
$isAdmin = IsAdmin($userID);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fontfile'])) {
	if ($_FILES['fontfile']['error'] === UPLOAD_ERR_OK) {
		$tmpName = $_FILES['fontfile']['tmp_name'];
		$originalName = $_FILES['fontfile']['name'];
		$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $originalName);
		if (empty($safeName) || strpos($safeName, '.') === false) {
			$safeName = "upload_" . time() . ".ttf";
		}
		$newTmpName = dirname($tmpName) . '/upload_' . time() . '_' . $safeName;
		move_uploaded_file($tmpName, $newTmpName);

		$status = $isAdmin ? 'approved' : 'pending';
		
		list($errCode, $errMsg, $additionalMsgList) = AddFontFile($newTmpName, false, $userID, $status, $safeName);
		if (is_file($newTmpName)) {
			@unlink($newTmpName);
		}
		
		HTMLStart('贡献字体', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
		if ($errCode === 0) {
			echo "<p>上传成功!" . ($isAdmin ? " 字体已自动过审." : " 请等待管理员审核.") . "</p>";
		} else {
			echo "<p>上传失败: {$errMsg}</p>";
		}
		echo "<ul>";
		foreach ($additionalMsgList as $msg) {
			echo "<li>{$msg[1]}</li>";
		}
		echo "</ul>";
		echo "<p><a href=\"upload.php\">继续上传</a></p>";
		HTMLEnd();
		exit;
	} else {
		dieHTML("上传错误代码: " . $_FILES['fontfile']['error'], '贡献字体');
	}
}

HTMLStart('贡献字体', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo <<<html
	<div class="upload">
		<form role="form" method="POST" enctype="multipart/form-data">
			<label for="fontfile">选择字体文件 (.ttf, .ttc, .otf):</label><br>
			<input type="file" id="fontfile" name="fontfile" accept=".ttf,.ttc,.otf" required />
			<br><br>
			<button type="submit">上传</button>
		</form>
	</div>
html;
HTMLEnd();
?>
