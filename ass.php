<?php
require_once('uue.php');
require_once('font.php');
require_once('vendor/autoload.php');
define('UTF8_BOM', (chr(0xEF) . chr(0xBB) . chr(0xBF)));
define('UTF32_BIG_ENDIAN_BOM', (chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF)));
define('UTF32_LITTLE_ENDIAN_BOM', (chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00)));
define('UTF16_BIG_ENDIAN_BOM', (chr(0xFE) . chr(0xFF)));
define('UTF16_LITTLE_ENDIAN_BOM', (chr(0xFF) . chr(0xFE)));

function GenerateRandomString($length) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[mt_rand(0, $charactersLength - 1)];
	}
	return $randomString;
}
function ConvertEncode(string $text) {
	$first2 = substr($text, 0, 2);
	$first3 = substr($text, 0, 3);
	$first4 = substr($text, 0, 3);

	$encodeType = '';
	if ($first3 === UTF8_BOM) {
		return str_replace("\xEF\xBB\xBF" , '', $text);
	}
	if ($first4 === UTF32_BIG_ENDIAN_BOM) {
		$encodeType = 'UTF-32BE';
	} else if ($first4 === UTF32_LITTLE_ENDIAN_BOM) {
		$encodeType = 'UTF-32LE';
	} else if ($first2 === UTF16_BIG_ENDIAN_BOM) {
		$encodeType = 'UTF-16BE';
	} else if ($first2 === UTF16_LITTLE_ENDIAN_BOM) {
		$encodeType = 'UTF-16LE';
	}

	if ($encodeType === '') {
		return mb_convert_encoding($text, 'UTF-8', ['UTF-8', 'ASCII', 'GB18030', 'BIG-5', 'SJIS', 'EUC-JP']);
	}

	return mb_convert_encoding($text, 'UTF-8', $encodeType);
}
function GetUniqueChar(string &$subsetASSContent, array &$uniqueChar) {
	$uniqueChar = array_unique(array_merge($uniqueChar, preg_split('//u', $subsetASSContent, -1, PREG_SPLIT_NO_EMPTY)), SORT_REGULAR);
}
function GetFontname(string $fontfile) {
	return pathinfo($fontfile, PATHINFO_FILENAME);
}
function ReplaceFontArr(array &$mapFontnameArr, string &$subsetASSContent, array &$subsetFontASSContent, bool $subsetFontOnly = false) {
	if (count($subsetFontASSContent) > 0) {
		$mapFontnameArr = array_filter($mapFontnameArr);
		uksort($mapFontnameArr, function ($a, $b) {
			return (strlen($b) - strlen($a));
		});
		foreach ($mapFontnameArr as $mapFontname => &$fontname) {
			$subsetASSContent = str_ireplace($mapFontname, $fontname, $subsetASSContent);
		}
		if (!$subsetFontOnly) {
			$subsetASSContent .= "\n[Fonts]\n";
			foreach ($subsetFontASSContent as $mapFontfile => &$fontASSContent) {
				if (in_array(GetFontname($mapFontfile), $mapFontnameArr) && !empty($fontASSContent)) {
					$subsetASSContent .= "fontname: {$mapFontfile}\n{$fontASSContent}\n";
				}
			}
		}
	}
}
function GetSubsetFontFile(?FontLib\TrueType\File $fontInfo, string $mapFontname, array &$uniqueChar): ?string {
	if ($fontInfo === null) {
		return null;
	}
	$uniqueCharStr = implode($uniqueChar);
	$fontData = $fontInfo->getData('name', 'records');
	foreach ($fontData as $key => &$data) {
		if (stripos($key, '3,1,1033') === false) {
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
		return null;
	}
	$fontInfo->setData('name', 'records', $fontData);
	$fontInfo->setSubset($uniqueCharStr);
	$subFontTmpFile = tempnam(SysCacheDir, Title . '-SubFont_');
	if ($fontInfo->open($subFontTmpFile, FontLib\BinaryStream::modeReadWrite)) {
		$fontInfo->encode(array("OS/2"));
	} else {
		$subFontTmpFile = null;
	}
	$fontInfo->revert();
	unset($uniqueCharStr);
	if ($subFontTmpFile !== null) {
		return $subFontTmpFile;
	}
	return null;
}
function GetSubsetFontContent(array &$font, ?FontLib\TrueType\File $fontInfo, string $mapFontname, string $mapFontfile, array &$subsetFontContent, array &$uniqueChar) {
	if ($fontInfo === null) {
		return;
	}
	$subFontTmpFile = GetSubsetFontFile($fontInfo, $mapFontname, $uniqueChar);
	if ($subFontTmpFile !== null) {
		$subsetFontContent["[{$font['fontfullname']}] {$mapFontfile}"] = file_get_contents($subFontTmpFile);
		unlink($subFontTmpFile);
	}
}
function GetSubsetFontASSContent(?FontLib\TrueType\File $fontInfo, string $mapFontname, string $mapFontfile, array &$subsetFontASSContent, array &$uniqueChar) {
	if ($fontInfo === null) {
		return;
	}
	$subFontTmpFile = GetSubsetFontFile($fontInfo, $mapFontname, $uniqueChar);
	if ($subFontTmpFile !== null) {
		$subsetFontASSContent[$mapFontfile] = UUEncode_ASS($subFontTmpFile, true);
		unlink($subFontTmpFile);
	}
}
function ProcessFont(string $source, array &$font, string &$mapFontfile, array &$mapFontnameArr, ?array &$fontInfoArr, ?array &$subsetFontASSContent, ?array &$uniqueChar, bool $subsetFontOnly = false): int {
	$fontPath = GetFontPath($font['fontfile']);
	if ($fontPath === null) {
		return -2;
	}
	$mapFontname = GetFontname($mapFontfile);
	foreach (explode("\n", $font['fontfullname']) as &$fontfullname2) {
		if (isset($mapFontnameArr[$fontfullname2])) {
			continue;
		}
		$mapFontnameArr[$fontfullname2] = $mapFontname;
	}
	foreach (explode("\n", $font['fontname']) as &$fontname2) {
		if (isset($mapFontnameArr[$fontname2])) {
			continue;
		}
		$mapFontnameArr[$fontname2] = $mapFontname;
	}
	foreach (explode("\n", $font['fontpsname']) as &$fontpsname2) {
		if (isset($mapFontnameArr[$fontpsname2])) {
			continue;
		}
		$mapFontnameArr[$fontpsname2] = $mapFontname;
	}
	if ($fontInfoArr === null || !isset($fontInfoArr[$font['fontfile']])) {
		$fontInfo = GetMatchedFontInfo($fontPath, $mapFontnameArr);
		if (SourcePolicy[$source]['MaxCacheFontCount'] > 0 && $fontInfoArr !== null && $fontInfo !== null) {
			$fontInfoArr[$font['fontfile']] = &$fontInfo;
		}
	} else {
		$fontInfo = $fontInfoArr[$font['fontfile']];
	}
	if ($subsetFontASSContent !== null) {
		if (!$subsetFontOnly) {
			GetSubsetFontASSContent($fontInfo, $mapFontname, $mapFontfile, $subsetFontASSContent, $uniqueChar);
		} else {
			GetSubsetFontContent($font, $fontInfo, $mapFontname, $mapFontfile, $subsetFontASSContent, $uniqueChar);
		}
	}
	if ($fontInfoArr === null) {
		CloseFontInfo($fontInfo);
	} else if ($fontInfoArr !== null && count($fontInfoArr) > SourcePolicy[$source]['MaxCacheFontCount']) {
		CloseFontInfo(array_shift($fontInfoArr));
	}
	return 0;
}
function ProcessFontArr(string $source, int $uid, int $torrentID, array &$fontArr, ?array &$fontInfoArr, ?array &$subsetFontASSContent, ?array &$uniqueChar, bool $subsetFontOnly = false): array {
	if (count($fontArr) > 0) {
		$mapFontnameArr = [];
		foreach ($fontArr as &$font) {
			$fontExt = pathinfo($font['fontfile'], PATHINFO_EXTENSION);
			$mapFontfile = GenerateRandomString(8) . ".{$fontExt}";
			if (ProcessFont($source, $font, $mapFontfile, $mapFontnameArr, $fontInfoArr, $subsetFontASSContent, $uniqueChar, $subsetFontOnly) === 0) {
				AddFontDownloadHistory($source, $uid, $torrentID, $font['id']);
			}
		}
		return $mapFontnameArr;
	}
	return [];
}
function AutoProcessFontArr(string $source, int $uid, int $torrentID, array &$fontArr, ?array &$fontInfoArr, ?string &$subsetASSContent, ?array &$subsetFontASSContent, bool $subsetFontOnly = false) {
	if ($subsetASSContent !== null) {
		$uniqueChar = [];
		GetUniqueChar($subsetASSContent, $uniqueChar);
		$mapFontnameArr = ProcessFontArr($source, $uid, $torrentID, $fontArr, $fontInfoArr, $subsetFontASSContent, $uniqueChar, $subsetFontOnly);
		ReplaceFontArr($mapFontnameArr, $subsetASSContent, $subsetFontASSContent, $subsetFontOnly);
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
						if (count($styleExplode2) > $fontIndex && !in_array($styleExplode2[$fontIndex], $fontnameArr) && !empty($styleExplode2[$fontIndex])) {
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
					if (!in_array($fontname, $fontnameArr) && !empty($fontname)) {
						$fontnameArr[] = $fontname;
					}
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
