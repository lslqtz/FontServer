<?php
require_once('config.php');
require_once('user.php');
require_once('font.php');

$loginPolicy = IsLogin();
if ($loginPolicy === null) {
	RedirectLogin();
}
$minSearchLength = $loginPolicy[2]['MinSearchLength'];
HTMLStart('Search', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo <<<html
		<div class="searchBox">
			<form role="serach" method="POST">
				<label for="fontname">通过字体名搜索字体:</label>
				<br>
				<input type="text" id="fontname" name="fontname" minlength="{$minSearchLength}" />
				<button type="submit">搜索</button>
			</form>
		</div>
html;
if (isset($_POST['fontname'])) {
	ShowTable(SearchFonts($minSearchLength, $loginPolicy[2]['MaxSearchFontCount'], $_POST['fontname']), true, ($loginPolicy[2]['AllowDownloadFont'] ? [$loginPolicy[0], $loginPolicy[1], 0, time()] : null));
}
HTMLEnd();
?>
