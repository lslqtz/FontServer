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
?>
