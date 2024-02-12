<?php
require_once('config.php');
require_once('mysql.php');
function GetAllFontsFilename(string $fontdir) {
	$dir = new RecursiveDirectoryIterator(
	    $fontdir,
	    RecursiveDirectoryIterator::KEY_AS_FILENAME | 
	    RecursiveDirectoryIterator::CURRENT_AS_FILEINFO
	);
	$files = new RegexIterator(
	    new RecursiveIteratorIterator($dir),
	    '#^.*\.(ttf|ttc|otf)$#',
	    RegexIterator::MATCH,
	    RegexIterator::USE_KEY
	);
	return $files;
}
function AddFontMeta(int $uploader, string $fontfile, int $fontsize): int {
	global $db;
	if (!ConnectDB()) {
		Log('无法连接到数据库', -1);
		return -1;
	}
	$stmt = $db->prepare("INSERT INTO `fonts_meta` (`uploader`, `fontfile`, `fontsize`) VALUES (?, ?, ?)");
	try {
		if (!$stmt->execute([$uploader, $fontfile, $fontsize])) {
			return -1;
		}
	} catch (Throwable $e) {
		return -1;
	}
	$rowID = $db->lastInsertId();
	$stmt->closeCursor();
	return $rowID;
}
function AddFont(int $rowID, ?string $fontname, ?string $fontfullname, ?string $fontpsname, ?string $fontsubfamily): bool {
	global $db;
	if (!ConnectDB()) {
		Log('无法连接到数据库', -1);
		return false;
	}
	if (empty($fontname) && empty($fontpsname)) {
		return false;
	}
	$stmt = $db->prepare("INSERT INTO `fonts` (`id`, `fontname`, `fontfullname`, `fontpsname`, `fontsubfamily`) VALUES (?, ?, ?, ?, ?)");
	try {
		if (!$stmt->execute([$rowID, $fontname, $fontfullname, $fontpsname, $fontsubfamily])) {
			return false;
		}
	} catch (Throwable $e) {
		return false;
	}
	$stmt->closeCursor();
	return true;
}
function GetFont(array $fontname): array {
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
?>
