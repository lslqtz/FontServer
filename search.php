<?php
require_once('config.php');
require_once('mysql.php');
require_once('user.php');
function SearchFonts(int $minSearchLength, int $maxSearchFontCount, string $fontname): array {
	global $db;
	if (empty($fontname) || mb_strlen(str_replace(array(' ', '%', '_'), '', $fontname)) < $minSearchLength || !ConnectDB()) {
		return [];
	}
	$fontname = '%' . str_replace(' ', '%', $fontname) . '%';
	$stmt = $db->prepare("SELECT `fonts_meta`.`id` AS `id`, `fonts_meta`.`uploader`, `fonts_meta`.`fontfile`, `fonts_meta`.`fontsize`, `fonts_meta`.`created_at` AS `created_at`, GROUP_CONCAT(DISTINCT `fonts`.`fontname` SEPARATOR '\n') AS `fontname`, GROUP_CONCAT(DISTINCT `fonts`.`fontfullname` SEPARATOR '\n') AS `fontfullname`, GROUP_CONCAT(DISTINCT `fonts`.`fontpsname` SEPARATOR '\n') AS `fontpsname`, GROUP_CONCAT(DISTINCT `fonts`.`fontsubfamily` SEPARATOR '\n') AS `fontsubfamily` FROM `fonts_meta` JOIN `fonts` ON `fonts`.`id` = `fonts_meta`.`id` WHERE `fonts_meta`.`fontfile` LIKE ? OR `fonts`.`fontname` LIKE ? OR `fonts`.`fontfullname` LIKE ? OR `fonts`.`fontpsname` LIKE ? GROUP BY `fonts_meta`.`id` ORDER BY `fonts_meta`.`created_at` DESC LIMIT {$maxSearchFontCount}");
	try {
		if (!$stmt->execute([$fontname, $fontname, $fontname, $fontname])) {
			return [];
		}
	} catch (Throwable $e) {
		return [];
	}
	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	return $result;
}
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
