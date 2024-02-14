<?php
require_once('uue.php');
require_once('font.php');
require_once('vendor/autoload.php');
function GenerateRandomString($length) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[mt_rand(0, $charactersLength - 1)];
	}
	return $randomString;
}
function AddFontDownloadHistory(string $source, int $uid, int $downloadID) {
	global $db;
	$stmt = $db->prepare("INSERT INTO `download_history` (`source`, `user_id`, `download_id`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP()");
	try {
		if (!$stmt->execute([$source, $uid, $downloadID])) {
			return false;
		}
	} catch (Throwable $e) {
		return false;
	}
	$stmt->closeCursor();
	return true;
}
function GetUniqueChar(string &$subsetASSContent, array &$uniqueChar) {
	$uniqueChar = array_unique(array_merge($uniqueChar, preg_split('//u', $subsetASSContent, -1, PREG_SPLIT_NO_EMPTY)), SORT_REGULAR);
}
function ReplaceFontArr(array &$mapFontnameArr, string &$subsetASSContent, string &$subsetFontASSContent) {
	if (!empty($subsetFontASSContent)) {
		$mapFontnameArr = array_filter($mapFontnameArr);
		uksort($mapFontnameArr, function ($a, $b) {
			return (strlen($b) - strlen($a));
		});
		foreach ($mapFontnameArr as $mapFontname => &$fontfile) {
			$subsetASSContent = str_ireplace($mapFontname, $fontfile, $subsetASSContent);
		}
		$subsetASSContent .= "\n[Font]\n{$subsetFontASSContent}";
	}
}
function GetSubsetFontASSContent(?FontLib\TrueType\File $fontInfo, string $mapFontname, string $mapFontfile, string &$subsetFontASSContent, array &$uniqueChar) {
	if ($fontInfo === null) {
		return;
	}
	$uniqueCharStr = implode($uniqueChar);
	$fontData = $fontInfo->getData('name', 'records');
	foreach ($fontData as $key => &$data) {
		if (stripos($key, '3,1,1033,') === false) {
			unset($fontData[$key]);
		} else {
			$expKey = explode(',', $key);
			$nameID = intval(end($expKey));
			switch ($nameID) {
				case 0:
					$fontData[$key]->string = 'Made by ' . Title;
					$fontData[$key]->stringRaw = 'Made by ' . Title;
					break;
				case 1:
				case 3:
				case 4:
					$fontData[$key]->string = $mapFontname;
					$fontData[$key]->stringRaw = $mapFontname;
					break;
				case 2:
				case 6:
					break;
				default:
					unset($fontData[$key]);
					break;
			}
		}
	}
	if (count($fontData) <= 0) {
		return;
	}
	$fontInfo->setData('name', 'records', $fontData);
	$fontInfo->setSubset($uniqueCharStr);
	$subFontTmpFile = tempnam(SysCacheDir, Title . '-SubFont_');
	if ($fontInfo->open($subFontTmpFile, FontLib\BinaryStream::modeReadWrite)) {
		$fontInfo->encode(array("OS/2"));
	} else {
		$subFontTmpFile = null;
	}
	if ($subFontTmpFile !== null) {
		$subsetFontASSContent .= "fontname: {$mapFontfile}\n";
		$subsetFontASSContent .= UUEncode_ASS($subFontTmpFile, true);
		$subsetFontASSContent .= "\n";
		unlink($subFontTmpFile);
	}
	unset($uniqueCharStr);
}
function ProcessFont(string $source, int $uid, array &$font, string &$mapFontfile, array &$mapFontnameArr, array &$fontInfoArr, ?string &$subsetFontASSContent, ?array &$uniqueChar): int {
	if (!is_file(FontPath . '/' . $font['fontfile'])) {
		return -2;
	}
	$mapFontfilename = pathinfo($mapFontfile, PATHINFO_FILENAME);
	foreach (explode("\n", $font['fontfullname']) as &$fontfullname2) {
		if (isset($mapFontnameArr[$fontfullname2])) {
			return -1;
		}
		$mapFontnameArr[$fontfullname2] = $mapFontfilename;
	}
	foreach (explode("\n", $font['fontpsname']) as &$fontpsname2) {
		if (isset($mapFontnameArr[$fontpsname2])) {
			return -1;
		}
		$mapFontnameArr[$fontpsname2] = $mapFontfilename;
	}
	if (!isset($fontInfoArr[$font['fontfile']])) {
		AddFontDownloadHistory($source, $uid, $font['id']);
		$fontInfo = GetMatchedFontInfo($font['fontfile'], $mapFontnameArr);
		$fontInfoArr[$font['fontfile']] = &$fontInfo;
	} else {
		$fontInfo = $fontInfoArr[$font['fontfile']];
	}
	if ($subsetFontASSContent !== null) {
		GetSubsetFontASSContent($fontInfo, $mapFontfilename, $mapFontfile, $subsetFontASSContent, $uniqueChar);
		CloseFontInfo($fontInfo);
	}
	return 0;
}
function ProcessFontArr(string $source, int $uid, array &$fontArr, ?string &$subsetFontASSContent, ?array &$uniqueChar): array {
	if (count($fontArr) > 0) {
		$mapFontnameArr = [];
		$fontInfoArr = [];
		foreach ($fontArr as &$font) {
			$fontExt = pathinfo($font['fontfile'], PATHINFO_EXTENSION);
			$mapFontfile = GenerateRandomString(8) . ".{$fontExt}";
			ProcessFont($source, $uid, $font, $mapFontfile, $mapFontnameArr, $fontInfoArr, $subsetFontASSContent, $uniqueChar);
		}
		return [$mapFontnameArr, $fontInfoArr];
	}
	return [];
}
function ParseFontArr(string $source, int $uid, array &$fontArr, ?string &$subsetASSContent) {
	if ($subsetASSContent !== null) {
		$uniqueChar = [];
		$subsetFontASSContent = '';
		GetUniqueChar($subsetASSContent, $uniqueChar);
		list($mapFontnameArr, $fontInfoArr) = ProcessFontArr($source, $uid, $fontArr, $subsetFontASSContent, $uniqueChar);
		ReplaceFontArr($mapFontnameArr, $subsetASSContent, $subsetFontASSContent);
	}
}
function ParseSubtitleFont(string $buffer, array &$fontnameArr, string &$currentType, int &$fontIndex, bool &$foundFontIndex, array &$matchedTypes, ?string &$subsetASSContent = null): int {
	// 若有子集化需求, 则处理直至结束, 并得到追加字体后的完整字幕内容.
	$buffer = trim($buffer);
	if (!empty($buffer)) {
		if ($buffer[0] === '[' && $buffer[(strlen($buffer) - 1)] === ']') {
			$bufferLower = strtolower($buffer);
			if ($bufferLower === '[v4 styles]' || $bufferLower === '[v4+ styles]') {
				$currentType = 'v4Styles';
				if (!in_array($currentType, $matchedTypes)) {
					$matchedTypes[] = $currentType;
				}
			} else if ($bufferLower === '[events]') {
				$currentType = 'events';
				if (!in_array($currentType, $matchedTypes)) {
					$matchedTypes[] = $currentType;
				}
			} else {
				$currentType = '';
			}
			if ($subsetASSContent !== null) {
				$subsetASSContent .= "\n";
			}
		} else if ($currentType === 'v4Styles') {
			$styleExplode1 = explode(':', $buffer, 2);
			if (count($styleExplode1) === 2) {
				$styleExplode2 = explode(',', $styleExplode1[1]);
				switch (strtolower($styleExplode1[0])) {
					case 'format':
						if (!$foundFontIndex && count($styleExplode2) > 1) {
							$tmpFontIndex = array_search('fontname', array_map('strtolower', $styleExplode2));
							if (is_int($tmpFontIndex)) {
								$foundFontIndex = true;
								$fontIndex = $tmpFontIndex;
							}
						}
						break;
					case 'style':
						$styleExplode2[$fontIndex] = trim($styleExplode2[$fontIndex], ' @');
						if (count($styleExplode2) > $fontIndex && !in_array($styleExplode2[$fontIndex], $fontnameArr)) {
							$fontnameArr[] = $styleExplode2[$fontIndex];
						}
						break;
					default:
						break;
				}
			}
		} else if ($currentType === 'events') {
			preg_match_all('/\\\fn@?(.*?)(?=(\\\|}))/', $buffer, $matches);
			if (count($matches) > 1) {
				foreach ($matches[1] as &$fontname) {
					$fontname = trim($fontname, ' @');
					if ($fontname == 0 || in_array($fontname, $fontnameArr)) {
						continue;
					}
					$fontnameArr[] = $fontname;
				}
			}
		}
		if ($subsetASSContent !== null) {
			$subsetASSContent .= "{$buffer}\n";
		}
	}
	return 0;
}
?>
