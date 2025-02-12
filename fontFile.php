<?php
require_once('config.php');
require_once('mysql.php');
require_once('font.php');
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
function ParseFontByFile(string $fontPath): array {
	$fontsInfoArr = [];

	try {
		$fontsInfo = FontLib\Font::load($fontPath);
		if ($fontsInfo instanceof FontLib\TrueType\Collection) {
			while ($fontsInfo->valid()) {
				$font = $fontsInfo->current();
				$font->parse();
				for ($i = 0; $i < 5; $i++) {
					foreach (LanguageID as &$languageID) {
						$fontname = $font->getFontName(3, $i, $languageID);
						$fontfullname = $font->getFontFullName(3, $i, $languageID);
						$fontpsname = $font->getFontPostscriptName(3, $i, $languageID);
						if (empty($fontfullname)) {
							if (empty($fontname)) {
								if (empty($fontpsname)) {
									continue;
								}
								$fontfullname = $fontpsname;
							} else {
								$fontfullname = $fontname;
							}
						} else if (empty($fontname)) {
							$fontname = $fontfullname;
						}
						$fontsInfoArr[] = [$fontname, $fontfullname, $fontpsname, $font->getFontSubfamily(3, $i, $languageID), $font->getFontVersion(3, $i, $languageID)];
					}
				}
				$fontsInfo->next();
			}
		} else {
			$fontsInfo->parse();
			for ($i = 0; $i < 5; $i++) {
				foreach (LanguageID as &$languageID) {
					$fontname = $fontsInfo->getFontName(3, $i, $languageID);
					$fontfullname = $fontsInfo->getFontFullName(3, $i, $languageID);
					$fontpsname = $fontsInfo->getFontPostscriptName(3, $i, $languageID);
					if (empty($fontfullname)) {
						if (empty($fontname)) {
							if (empty($fontpsname)) {
								continue;
							}
							$fontfullname = $fontpsname;
						} else {
							$fontfullname = $fontname;
						}
					} else if (empty($fontname)) {
						$fontname = $fontfullname;
					}
					if (substr_count($fontname, '?') > 3 && substr_count($fontfullname, '?') > 3) {
						continue;
					}
					$fontsInfoArr[] = [$fontname, $fontfullname, $fontpsname, $fontsInfo->getFontSubfamily(3, $i, $languageID), $fontsInfo->getFontVersion(3, $i, $languageID)];
				}
			}
		}
		try {
			$fontsInfo->close();
		} catch (Throwable $e) {
		}
		unset($font, $fontsInfo);
	} catch (Throwable $e) {
		return [-1, "跳过错误字体: {$e->getMessage()}", $fontsInfoArr];
	}

	return [0, '', $fontsInfoArr];
}
function AddFontFile(string $fontPath, bool $localScraper = false): array {
	$additionalMsgList = [];
	if (!is_file($fontPath)) {
		return [-1, '跳过错误文件', $additionalMsgList];
	}
	$fileSize = filesize($fontPath);
	if ($fileSize <= 0) {
		return [-1, '跳过空文件', $additionalMsgList];
	}
	$fontFileInfo = pathinfo($fontPath);
	$fontExt = strtolower($fontFileInfo['extension']);
	if (!in_array($fontExt, ['ttf', 'ttc'])) {
		if (!$localScraper || $fontExt !== 'otf') {
			return [-1, '跳过不支持文件', $additionalMsgList];
		}
	}
	if ($fontExt === 'otf') {
		if (!is_file('/usr/local/bin/ftcli')) {
			return [-1, '找不到 ftcli', $additionalMsgList];
		}
		system("/usr/local/bin/ftcli converter otf2ttf -out " .  escapeshellarg("{$fontFileInfo['dirname']}/") . " " . escapeshellarg($fontPath), $retcode);
		if ($retcode > 0) {
			return [-1, "转换 otf 失败: {$fontPath}", $additionalMsgList];
		}
		return [-1, "转换 otf 成功: {$fontPath}", $additionalMsgList];
		//unlink($fontPath);
		$fontExt = 'ttf';
		$fontPath = ($fontFileInfo['dirname'] . '/' . $fontFileInfo['filename'] . ".ttf");
	}
	$fontFilename = preg_replace('/\d{10,}/', '', $fontFileInfo['filename']);
	$fontsInfoArr = [];
	list($fontParseErr, $fontParseErrMsg, $fontsInfoArr) = ParseFontByFile($fontPath);
	if ($fontParseErr > 0) {
		return [$fontParseErr, $fontParseErrMsg, $additionalMsgList];
	}
	$hasDupe = false;
	foreach ($fontsInfoArr as $key => &$fontsInfo) {
		$fontArr = DetectDuplicateFont($fontExt, $fontsInfo[0], $fontsInfo[1], $fontsInfo[3], true);
		if ($fontArr[0] > 0) {
			//if (preg_replace('/\d{10,}/', '', strtolower($fontArr[1])) === strtolower("{$fontFilename}.{$fontExt}")) {
			$hasDupe = true;
			$additionalMsgList[] = [-1, "跳过重复字体: {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}"];
			unset($fontsInfoArr[$key]);
			continue;
			//}
		} else if ($fontArr[0] < 0) {
			$additionalMsgList[] = [-1, "跳过错误字体: {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]} (Errno: {$fontArr[0]}"];
			unset($fontsInfoArr[$key]);
		}
	}
	if (count($fontsInfoArr) <= 0) {
		return [-1, "跳过空字体", $additionalMsgList];
	}
	if ($hasDupe) {
		$fontFilename .= time();
	}
	$fontFilename .= ".{$fontExt}";
	$newFontPath = GetMainFontPath($fontFilename);
	if (!rename($fontPath, $newFontPath)) {
		return [-1, "移动字体失败: {$fontPath} -> {$newFontPath}", $additionalMsgList];
	}
	$additionalMsgList[] = [0, "移动字体成功: {$fontPath} -> {$newFontPath}"];
	$rowID = AddFontMeta(1, $fontFilename, $fileSize, true);
	if ($rowID <= 0) {
		if ($localScraper) {
			unlink($newFontPath);
		} else {
			copy($newFontPath, $fontPath);
		}
		return [-1, "添加字体元数据失败: {$newFontPath}", $additionalMsgList];
	}
	foreach ($fontsInfoArr as &$fontsInfo) {
		if (!AddFont($rowID, $fontsInfo[0], $fontsInfo[1], $fontsInfo[2], $fontsInfo[3], $fontsInfo[4])) {
			$additionalMsgList[] = [-1, "添加字体失败: {$rowID}, {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}, {$fontsInfo[4]}"];
		}
		$additionalMsgList[] = [0, "添加字体成功: {$rowID}, {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}, {$fontsInfo[4]}"];
	}

	return [0, '', $additionalMsgList];
}
?>
