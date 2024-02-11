<?php
require_once('config.php');
require_once('mysql.php');
require_once('zipfile.php');
ini_set('memory_limit', '128M');
function CheckSign(string $sign, int $uid, int $timestamp, string $fontname): ?string {
	if ($sign !== sha1(SignKey . "Download/{$uid}-{$timestamp}-{$fontname}" . SignKey) || ($timestamp + DownloadExpireTime) < time()) {
		return null;
	}
	return $sign;
}
function AddFontDownloadHistory(int $uid, int $downloadID) {
	global $db;
	return $db->exec("INSERT INTO `download_history` (`user_id`, `download_id`) VALUES ({$uid}, {$downloadID}) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP()");
}
function DownloadFonts(array $fontname): array {
	global $db;
	if (count($fontname) < 1 || !ConnectDB()) {
		return [];
	}
	$fontnameInPlaceholder = (str_repeat('?,', count($fontname) - 1) . '?');
	$stmt = $db->prepare("SELECT `fonts_meta`.`id`, `fonts_meta`.`uploader`, `fonts_meta`.`fontfile`, `fonts_meta`.`fontsize`, MAX(`fonts_meta`.`created_at`) AS `created_at`, GROUP_CONCAT(DISTINCT `fonts`.`fontname` SEPARATOR '\n') AS `fontname`, GROUP_CONCAT(DISTINCT `fonts`.`fontfullname` SEPARATOR '\n') AS `fontfullname`, GROUP_CONCAT(DISTINCT `fonts`.`fontpsname` SEPARATOR '\n') AS `fontpsname`, GROUP_CONCAT(DISTINCT `fonts`.`fontsubfamily` SEPARATOR '\n') AS `fontsubfamily` FROM `fonts` JOIN `fonts_meta` ON `fonts_meta`.`id` = `fonts`.`id` WHERE `fonts`.`fontfullname` IN ({$fontnameInPlaceholder}) OR `fonts`.`fontpsname` IN ({$fontnameInPlaceholder}) GROUP BY `fonts_meta`.`id` LIMIT " . MaxDownloadFontCount);
	try {
		if (!$stmt->execute(array_merge($fontname, $fontname))) {
			var_dump($stmt->errorInfo());
			return [];
		}
	} catch (Throwable $e) {
		return [];
	}
	$result = $stmt->fetchAll();
	$stmt->closeCursor();

	return $result;
}
if (isset($_GET['sign'], $_GET['uid'], $_GET['time'], $_GET['fontname'])) {
	$fontnameArr = explode(',', $_GET['fontname']);
	if (count($fontnameArr) > MaxDownloadFontCount) {
		dieHTML("Bad Request!\n");
	}
	$uid = intval($_GET['uid']);
	$timestamp = intval($_GET['time']);
	$sign = CheckSign($_GET['sign'], $uid, $timestamp, $_GET['fontname']);
	if ($sign === null) {
		dieHTML("Bad sign!\n");
	}
	$fonts = DownloadFonts($fontnameArr);
	if (count($fonts) <= 0) {
		dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars($_GET['fontname'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n");
	}
	if (isset($_GET['download']) && $_GET['download'] == 1) {
		ob_end_clean();
		header('Content-Type: application/zip');
		header("Content-Disposition: attachment; filename=Fonts-{$uid}-{$timestamp}-{$sign}.zip");
		$tmpArr = [];
		$archive = new ZipFile();
		$archive->setDoWrite();
		foreach ($fonts as $font) {
			if (!is_file(FontPath . '/' . $font['fontfile'])) {
				continue;
			}
			if (!in_array($font['id'], $tmpArr)) {
				$tmpArr[] = $font['id'];
				AddFontDownloadHistory($uid, $font['id']);
			}
			$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
			flush();
		}
		$archive->file();
	} else {
		HTMLStart('Download');
		echo "<a href={$_SERVER['REQUEST_URI']}&download=1>Download!</a>\n";
		echo "<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars($_GET['fontname'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n";
		ShowTable($fonts);
		HTMLEnd();
	}
} else {
	dieHTML(":(\n");
}
?>
