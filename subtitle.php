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
$filename_ass = 'Content.ass';
$filename_zip = 'Content.zip';
$filename_rar = 'Content.rar';
$filename_7z = 'Content.7z';
$filehash = 'Unknown';
$t = time();
$sign_ass = GenerateSign($loginPolicy[0], $loginPolicy[1], 0, $t, $filename_ass, $filehash);
$sign_zip = GenerateSign($loginPolicy[0], $loginPolicy[1], 0, $t, $filename_zip, $filehash);
$sign_rar = GenerateSign($loginPolicy[0], $loginPolicy[1], 0, $t, $filename_rar, $filehash);
$sign_7z = GenerateSign($loginPolicy[0], $loginPolicy[1], 0, $t, $filename_7z, $filehash);
HTMLStart('Subtitle', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo <<<html
		<script src="base64.js"></script>
		<script>
		const signs = {
			'ass': '{$sign_ass}',
			'zip': '{$sign_zip}',
			'rar': '{$sign_rar}',
			'7z': '{$sign_7z}'
		};
		const t = {$t};
		function Convert() { 
			let content = document.getElementById('file').value; 
			if (content === null || content === '') { 
				alert('坏内容!'); 
				return false; 
			} 
			document.forms[0].children[0].value = Base64.encode(content); 
			return true; 
		}
		function ConvertFile() {
			let fileInput = document.getElementById('archive_file');
			if (!fileInput.files || fileInput.files.length === 0) {
				alert('请选择文件!');
				return false;
			}
			let file = fileInput.files[0];
			let ext = file.name.split('.').pop().toLowerCase();
			if (!signs[ext]) {
				alert('不支持的文件扩展名!');
				return false;
			}
			let reader = new FileReader();
			reader.onload = function(e) {
				let base64 = e.target.result.split(',')[1];
				let form = document.getElementById('subtitleForm');
				form.action = `download.php?source={$loginPolicy[0]}&uid={$loginPolicy[1]}&torrent_id=0&time=\${t}&sign=\${signs[ext]}&filename=Content.\${ext}&upload_subtitle=1`;
				form.querySelector('input[name="file"]').value = base64;
				form.submit();
			};
			reader.readAsDataURL(file);
		}
		</script>
		<label for="file">ASS 字幕内容:</label>\n<br>
		<textarea id="file" spellcheck="false" autocomplete="off" style="width: 100%; height: 500px; box-sizing: border-box;"></textarea>
		<form id="subtitleForm" method="POST" onsubmit="return Convert();" action="download.php?source={$loginPolicy[0]}&uid={$loginPolicy[1]}&torrent_id=0&time={$t}&sign={$sign_ass}&filename={$filename_ass}&upload_subtitle=1">
			<input type="hidden" name="file" value="" />
			<button type="submit" style="margin-top: 8px;">上传纯文本</button>
		</form>
		<hr style="margin: 20px 0;">
		<label for="archive_file">或上传压缩包 (支持 .zip, .rar, .7z):</label>\n<br>
		<input type="file" id="archive_file" accept=".zip,.rar,.7z" style="margin-top: 8px;" />
		<br>
		<button type="button" onclick="ConvertFile()" style="margin-top: 8px;">上传压缩包</button>
html;
echo "\n";
HTMLEnd();
?>
