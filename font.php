<?php
require_once('config.php');
require_once('mysql.php');
require_once('vendor/autoload.php');
function GetAllFontsFilename(string $fontdir) {
	$dir = new RecursiveDirectoryIterator(
	    $fontdir,
	    RecursiveDirectoryIterator::KEY_AS_FILENAME | 
	    RecursiveDirectoryIterator::CURRENT_AS_FILEINFO
	);
	$files = new RegexIterator(
	    new RecursiveIteratorIterator($dir),
	    '#^.*\.([tT][tT][fF]|[tT][tT][cC]|[oO][tT][fF])$#',
	    RegexIterator::MATCH,
	    RegexIterator::USE_KEY
	);
	return $files;
}
function AddFontMeta(int $uploader, string $fontfile, int $fontsize): int {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
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
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return false;
	}
	if (empty($fontfullname)) {
		if (empty($fontname)) {
			if (empty($fontpsname)) {
				$fontfullname = $fontpsname;
			} else {
				return false;
			}
		} else {
			$fontfullname = $fontname;
		}
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
	$stmt = $db->prepare("SELECT `fonts_meta`.`id`, `fonts_meta`.`uploader`, `fonts_meta`.`fontfile`, `fonts_meta`.`fontsize`, MAX(`fonts_meta`.`created_at`) AS `created_at`, GROUP_CONCAT(DISTINCT `fonts`.`fontname` SEPARATOR '\n') AS `fontname`, GROUP_CONCAT(DISTINCT `fonts`.`fontfullname` SEPARATOR '\n') AS `fontfullname`, GROUP_CONCAT(DISTINCT `fonts`.`fontpsname` SEPARATOR '\n') AS `fontpsname`, GROUP_CONCAT(DISTINCT `fonts`.`fontsubfamily` SEPARATOR '\n') AS `fontsubfamily` FROM `fonts` JOIN `fonts_meta` ON `fonts_meta`.`id` = `fonts`.`id` WHERE `fonts`.`fontfullname` IN ({$fontnameInPlaceholder}) OR `fonts`.`fontpsname` IN ({$fontnameInPlaceholder}) GROUP BY `fonts`.`fontfullname` LIMIT " . MaxDownloadFontCount);
	try {
		if (!$stmt->execute(array_merge($fontname, $fontname))) {
			return [];
		}
	} catch (Throwable $e) {
		return [];
	}
	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	return $result;
}
function DetectDuplicateFont(string $fontext, ?string $fontname, ?string $fontfullname, ?string $fontsubfamily, bool $deleteWorseExt = false): bool {
	global $db;
	if (empty($fontfullname)) {
		if (empty($fontname)) {
			return false;
		}
		$fontfullname = $fontname;
	}
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return false;
	}
	$stmt = $db->prepare("SELECT `fonts`.`id`, `fonts_meta`.`fontfile` FROM `fonts` JOIN `fonts_meta` ON `fonts_meta`.`id` = `fonts`.`id` WHERE `fonts`.`fontname` = ? AND `fonts`.`fontfullname` = ? AND `fonts`.`fontsubfamily` = ? LIMIT 1");
	try {
		if (!$stmt->execute([$fontname, $fontfullname, $fontsubfamily])) {
			return true;
		}
	} catch (Throwable $e) {
		return true;
	}

	$fontID = $stmt->fetchColumn(0);
	$fontfile = $stmt->fetchColumn(1);

	$stmt->closeCursor();

	$dbFontExt = strtolower(pathinfo($fontfile, PATHINFO_EXTENSION));
	$betterExts = ['ttf', 'ttc'];

	if ($fontID !== false) {
		if (in_array($fontext, $betterExts) && !in_array($dbFontExt, $betterExts)) {
			if ($deleteWorseExt) {
				$db->exec("DELETE FROM `fonts` WHERE `id` = {$fontID}");
				$db->exec("DELETE FROM `fonts_meta` WHERE `id` = {$fontID}");
				return false;
			}
		}
		return true;
	}

	return false;
}
function GetMatchedFontInfo(string $fontfile, array &$mapFontnameArr): null|FontLib\TrueType\File|FontLib\TrueType\Collection {
	$fontInfo = FontLib\Font::load($fontfile);
	if ($fontInfo instanceof FontLib\TrueType\Collection) {
		while ($fontInfo->valid()) {
			$font2 = $fontInfo->current();
			$matched = false;
			try {
				$font2->parse();
				for ($i = 0; $i < 5; $i++) {
					$fontFullname3 = @$font2->getFontFullName(3, $i,  1033);
					if (($fontFullname3 !== null && isset($mapFontnameArr[$fontFullname3])) || ($fontFullname3 === null && ($fontname3 = @$font2->getFontName(3, $i,  1033)) !== null && isset($mapFontnameArr[$fontname3])) || (($fontpsname3 = @$font2->getFontPostscriptName(3, $i,  1033)) !== null && isset($mapFontnameArr[$fontpsname3]))) {
						$matched = true;
					}
				}
			} catch (Throwable $e) {
			}
			unset($fontFullname3, $fontname3, $fontpsname3);
			if (!$matched) {
				try {
					$font2->close();
				} catch (Throwable $e) {
				}
				$fontInfo->next();
				continue;
			}
			return $font2;
		}
	} else {
		try {
			$fontInfo->parse();
			return $fontInfo;
		} catch (Throwable $e) {
		}
		try {
			$fontInfo->close();
		} catch (Throwable $e) {
		}
	}
	return null;
}
function CloseFontInfo(FontLib\TrueType\File $fontInfo): bool {
	try {
		$fontInfo->close();
		return true;
	} catch (Throwable $e) {
	}
	return false;
}
function CloseFontInfoArr(array &$fontInfoArr) {
	foreach ($fontInfoArr as $key => &$fontInfo) {
		CloseFontInfo($fontInfo);
		unset($fontInfoArr[$key]);
	}
}
?>
