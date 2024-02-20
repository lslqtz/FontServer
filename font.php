<?php
require_once('config.php');
require_once('mysql.php');
require_once('vendor/autoload.php');
define('BetterExt', ['ttf', 'ttc']);
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
function AddFontMeta(int $uploader, string $fontfile, int $fontsize, bool $force = false): int {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return -1;
	}
	if ($force) {
		DeleteFontByFilename($fontfile);
	}
	$stmt = $db->prepare("INSERT INTO `fonts_meta` (`uploader`, `fontfile`, `fontsize`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `uploader` = VALUES(`uploader`), `fontfile` = VALUES(`fontfile`), `fontsize` = VALUES(`fontsize`), `created_at` = current_timestamp()");
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
	} else if (empty($fontname)) {
		$fontname = $fontfullname;
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
function DeleteFontByID(int $fontID) {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
	}
	$db->exec("DELETE FROM `fonts` WHERE `id` = {$fontID}");
	$db->exec("DELETE FROM `fonts_meta` WHERE `id` = {$fontID} LIMIT 1");
}
function DeleteFontByFilename(string $filename) {
	global $db;
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
	}
	$fontID = GetFontIDByFilename($filename);
	if ($fontID > 0) {
		DeleteFontByID($fontID);
	}
}
function GetFontFileByID(int $fontID): ?string {
	global $db;
	$stmt = $db->prepare("SELECT `fontfile` FROM `fonts_meta` WHERE `id` = ? LIMIT 1");
	try {
		if (!$stmt->execute($fontID)) {
			return null;
		}
	} catch (Throwable $e) {
		return null;
	}
	$fontfile = $stmt->fetchColumn(0);
	$stmt->closeCursor();

	if ($fontfile === false) {
		return null;
	}

	return $fontfile;
}
function GetFontByNameArr(int $maxDownloadFontCount, array $fontname): array {
	global $db;
	if (count($fontname) < 1 || !ConnectDB()) {
		return [];
	}
	$fontnameInPlaceholder = (str_repeat('?,', count($fontname) - 1) . '?');
	$stmt = $db->prepare("SELECT MAX(`fonts`.`id`) AS `id`, `fonts_meta`.`uploader`, `fonts_meta`.`fontfile`, `fonts_meta`.`fontsize`, MAX(`fonts_meta`.`created_at`) AS `created_at`, GROUP_CONCAT(DISTINCT `fonts`.`fontname` SEPARATOR '\n') AS `fontname`, GROUP_CONCAT(DISTINCT `fonts`.`fontfullname` SEPARATOR '\n') AS `fontfullname`, GROUP_CONCAT(DISTINCT `fonts`.`fontpsname` SEPARATOR '\n') AS `fontpsname`, GROUP_CONCAT(DISTINCT `fonts`.`fontsubfamily` SEPARATOR '\n') AS `fontsubfamily` FROM `fonts` JOIN `fonts_meta` ON `fonts_meta`.`id` = `fonts`.`id` WHERE `fonts`.`fontfullname` IN ({$fontnameInPlaceholder}) GROUP BY `fonts`.`fontfullname` LIMIT {$maxDownloadFontCount}");
	try {
		if (!$stmt->execute($fontname)) {
			return [];
		}
	} catch (Throwable $e) {
		return [];
	}
	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	foreach ($result as &$r) {
		$key = array_search($r['fontfullname'], $fontname);
		if ($key !== false) {
			unset($fontname[$key]);
		}
	}
	$fontname = array_values($fontname);

	if (count($fontname) < 1) {
		return $result;
	}
	$fontnameInPlaceholder2 = (str_repeat('?,', count($fontname) - 1) . '?');
	$stmt2 = $db->prepare("SELECT MAX(`fonts`.`id`) AS `id`, `fonts_meta`.`uploader`, `fonts_meta`.`fontfile`, `fonts_meta`.`fontsize`, MAX(`fonts_meta`.`created_at`) AS `created_at`, GROUP_CONCAT(DISTINCT `fonts`.`fontname` SEPARATOR '\n') AS `fontname`, GROUP_CONCAT(DISTINCT `fonts`.`fontfullname` SEPARATOR '\n') AS `fontfullname`, GROUP_CONCAT(DISTINCT `fonts`.`fontpsname` SEPARATOR '\n') AS `fontpsname`, GROUP_CONCAT(DISTINCT `fonts`.`fontsubfamily` SEPARATOR '\n') AS `fontsubfamily` FROM `fonts` JOIN `fonts_meta` ON `fonts_meta`.`id` = `fonts`.`id` WHERE `fonts`.`fontname` IN ({$fontnameInPlaceholder2}) GROUP BY `fonts`.`fontname` LIMIT {$maxDownloadFontCount}");
	try {
		if (!$stmt2->execute($fontname)) {
			return $result;
		}
	} catch (Throwable $e) {
		return $result;
	}
	$result2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
	$stmt2->closeCursor();

	return array_merge($result, $result2);
}
function GetFontIDByFilename(string $filename): int {
	global $db;
	if (!empty($filename)) {
		$stmt = $db->prepare("SELECT `id` FROM `fonts_meta` WHERE `fontfile` = ? LIMIT 1");
		try {
			if (!$stmt->execute([$filename])) {
				return 0;
			}
		} catch (Throwable $e) {
			return 0;
		}
		$fontID = $stmt->fetchColumn(0);
		$stmt->closeCursor();

		if ($fontID === false) {
			return 0;
		}

		return $fontID;
	}
	return 0;
}
function DetectDuplicateFont(string $fontext, ?string $fontname, ?string $fontfullname, ?string $fontsubfamily, bool $deleteWorseExt = false): array {
	global $db;
	if (empty($fontfullname)) {
		if (empty($fontname)) {
			return [-1];
		}
		$fontfullname = $fontname;
	}
	if (!ConnectDB()) {
		if (function_exists('LogStr')) {
			LogStr('无法连接到数据库', -1);
		}
		return [-1];
	}
	$stmt = $db->prepare("SELECT `fonts`.`id`, `fonts_meta`.`fontfile` FROM `fonts` JOIN `fonts_meta` ON `fonts_meta`.`id` = `fonts`.`id` WHERE `fonts`.`fontname` = ? AND `fonts`.`fontfullname` = ? AND `fonts`.`fontsubfamily` = ? LIMIT 1");
	try {
		if (!$stmt->execute([$fontname, $fontfullname, $fontsubfamily])) {
			return [-1];
		}
	} catch (Throwable $e) {
		return [-1];
	}

	$fontInfo = $stmt->fetch(PDO::FETCH_NUM);

	$stmt->closeCursor();

	if ($fontInfo !== false && count($fontInfo) === 2) {
		$dbFontFileInfo = pathinfo($fontInfo[1]);
		$dbFontExt = strtolower($dbFontFileInfo['extension']);
		if (in_array($fontext, BetterExt) && !in_array($dbFontExt, BetterExt)) {
			if ($deleteWorseExt) {
				DeleteFontByID($fontInfo[0]);
				return [0];
			}
		}
		$dbFontFilename = "{$dbFontFileInfo['filename']}.{$dbFontExt}";
		return [$fontInfo[0], $dbFontFilename];
	}

	return [0];
}
function GetMatchedFontInfo(string $fontfile, array &$mapFontnameArr): ?FontLib\TrueType\File {
	$fontInfo = FontLib\Font::load($fontfile);
	if ($fontInfo instanceof FontLib\TrueType\Collection) {
		while ($fontInfo->valid()) {
			$font2 = $fontInfo->current();
			$matched = false;
			try {
				$font2->parse();
				for ($i = 0; $i < 5; $i++) {
					foreach (LanguageID as &$languageID) {
						$fontFullname3 = @$font2->getFontFullName(3, $i,  $languageID);
						if ($fontFullname3 !== null && isset($mapFontnameArr[$fontFullname3])) {
							$matched = true;
							break 2;
						}
					}
				}
			} catch (Throwable $e) {
			}
			unset($fontFullname3);
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
		$fontInfo->rewind();
		while ($fontInfo->valid()) {
			$font2 = $fontInfo->current();
			$matched = false;
			try {
				$font2->parse();
				for ($i = 0; $i < 5; $i++) {
					foreach (LanguageID as &$languageID) {
						$fontname3 = @$font2->getFontName(3, $i,  $languageID);
						if ($fontname3 !== null && isset($mapFontnameArr[$fontname3])) {
							$matched = true;
							break 2;
						}
					}
				}
			} catch (Throwable $e) {
			}
			unset($fontname3);
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
function CloseFontInfo(?FontLib\TrueType\File $fontInfo): bool {
	if ($fontInfo === null) {
		return false;
	}
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
