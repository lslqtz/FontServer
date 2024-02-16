<?php
if (PHP_SAPI !== 'cli') { die(); }
ini_set('memory_limit', '1024M');
require_once('config.php');
require_once('mysql.php');
require_once('font.php');
require_once('vendor/autoload.php');
define('LanguageID', [1028, 1033, 1041, 1152, 2052, 2057, 3076, 4100, 5124]);
function LogStr(string $message, int $status = 0) {
	$logType = ($status === -1 ? '错误' : '信息');
	$date = date('Y-m-d');
	$time = date('H:i:s');
	$logStr = "[{$date} {$time}][{$logType}] {$message}.\n";
	echo $logStr;
}
if (!is_dir('../font2')) {
	LogStr('找不到 ../font2 目录', -1);
	return;
}
$fontfiles = GetAllFontsFilename('../font2');
foreach ($fontfiles as $fontfile) {
	$oldFontPath = $fontfile->getPathname();
	if (!is_file($oldFontPath)) {
		LogStr('跳过错误文件', -1);
		continue;
	}
	$fontFileInfo = pathinfo($fontfile->getFilename());
	$fontExt = strtolower($fontFileInfo['extension']);
	$fontFilename = "{$fontFileInfo['filename']}.{$fontExt}";
	$fontsInfoArr = [];
	try {
		$fontsInfo = FontLib\Font::load($oldFontPath);
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
						}
						$fontsInfoArr[] = [$fontname, $fontfullname, $fontpsname, $font->getFontSubfamily(3, $i, $languageID)];
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
					}
					$fontsInfoArr[] = [$fontname, $fontfullname, $fontpsname, $fontsInfo->getFontSubfamily(3, $i, $languageID)];
				}
			}
		}
		try {
			$fontsInfo->close();
		} catch (Throwable $e) {
		}
		unset($fontsInfo);
	} catch (Throwable $e) {
		LogStr("跳过错误字体: {$e->getMessage()}", -1);
		continue;
	}
	foreach ($fontsInfoArr as $key => &$fontsInfo) {
		$fontArr = DetectDuplicateFont($fontExt, $fontsInfo[0], $fontsInfo[1], $fontsInfo[3], true);
		if ($fontArr[0] > 0) {
			if (strtolower($fontArr[1]) === strtolower($fontFilename)) {
				$fontFilename = "{$fontFileInfo['filename']}" . time() . ".{$fontExt}";
				LogStr("跳过重复字体: {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}", -1);
				unset($fontsInfoArr[$key]);
				continue;
			}
		} else if ($fontArr[0] < 0) {
			LogStr("跳过错误字体: {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}", -1);
			unset($fontsInfoArr[$key]);
		}
	}
	if (count($fontsInfoArr) <= 0) {
		LogStr("跳过空字体", -1);
		continue;
	}
	$fontPath = GetMainFontPath($fontFilename);
	if (!rename($oldFontPath, $fontPath)) {
		LogStr("移动字体失败: {$oldFontPath} -> {$fontPath}", -1);
		continue;
	}
	LogStr("移动字体成功: {$oldFontPath} -> {$fontPath}");
	$rowID = AddFontMeta(1, $fontFilename, filesize($fontPath), true);
	if ($rowID <= 0) {
		rename($fontPath, $oldFontPath);
		LogStr("添加字体元数据失败: {$fontPath}", -1);
		continue;
	}
	foreach ($fontsInfoArr as &$fontsInfo) {
		if (!AddFont($rowID, $fontsInfo[0], $fontsInfo[1], $fontsInfo[2], $fontsInfo[3])) {
			LogStr("添加字体失败: {$rowID}, {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}", -1);
			continue;
		}
		LogStr("添加字体成功: {$rowID}, {$fontsInfo[0]}, {$fontsInfo[1]}, {$fontsInfo[2]}, {$fontsInfo[3]}");
	}
}
?>
