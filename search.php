<?php
require_once('config.php');
require_once('user.php');
require_once('font.php');

$loginPolicy = IsLogin();
if ($loginPolicy === null) {
	RedirectLogin();
}
$minSearchLength = $loginPolicy[2]['MinSearchLength'];
$fontname = (isset($_POST['fontname']) ? $_POST['fontname'] : '');
$fontnameEscaped = htmlspecialchars($fontname, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
HTMLStart('Search', GetUserBar($loginPolicy[0], $loginPolicy[1], $loginPolicy[2]['AllowLogout']));
echo <<<html
		<div class="searchBox">
			<form role="serach" method="POST">
				<label for="fontname">通过字体名搜索字体:</label>
				<br>
				<input type="text" id="fontname" name="fontname" minlength="{$minSearchLength}" value="{$fontnameEscaped}" />
				<button type="submit">搜索</button>
			</form>
		</div>
html;
echo "\n";
if (!empty($fontname)) {
	ShowTable(SearchFonts($minSearchLength, $loginPolicy[2]['MaxSearchFontCount'], $fontname), true, ($loginPolicy[2]['AllowDownloadFont'] ? [$loginPolicy[0], $loginPolicy[1], 0, time()] : null));
}
HTMLEnd();
?>
