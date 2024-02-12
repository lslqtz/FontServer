<?php
require_once('font.php');
require_once('vendor/autoload.php');
function UUEncode_ASS($filename, $newLine = true): string {
	$retStr = '';
	$pos = 0;
	$written = 0;
	$filesize = filesize($filename);
	$fileContent = file_get_contents($filename);
	for ($pos = 0; $pos < $filesize; $pos += 3) {
		$chunkSize = ($filesize - $pos);
		$bufferOrd = [ord($fileContent[$pos]), ($chunkSize > 1 ? ord($fileContent[$pos+1]) : 0), ($chunkSize > 2 ? ord($fileContent[$pos+2]) : 0)];
		$dstStr = [($bufferOrd[0] >> 2), ((($bufferOrd[0] & 0x3) << 4) | (($bufferOrd[1] & 0xF0) >> 4)), ((($bufferOrd[1] & 0xF) << 2) | (($bufferOrd[2] & 0xC0) >> 6)), ($bufferOrd[2] & 0x3F)];
		for ($i = 0; $i < min(4, ($chunkSize + 1)); ++$i) {
			$retStr .= chr($dstStr[$i] + 33);
			if ($newLine && ++$written == 80 && $pos + 3 < $filesize) {
				$written = 0;
				$retStr .= "\n";
			}
		}
	}
	unset($fileContent);
	return $retStr;
}
function GenerateRandomString($length) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[mt_rand(0, $charactersLength - 1)];
	}
	return $randomString;
}
function ParseSubtitleFont(int $uid, string $buffer, array &$fontArr, array &$fontnameArr, string &$currentType, int &$fontIndex, bool &$foundFontIndex, array &$matchedTypes, ?string &$subsetASSContent = null, ?array &$mapFontfileArr = null): int {
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
			if ($subsetASSConten !== null) {
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
						if (count($styleExplode2) > $fontIndex && !in_array($styleExplode2[$fontIndex], $fontnameArr)) {
							$fontnameArr[] = $styleExplode2[$fontIndex];
						}
						break;
					default:
						break;
				}
			}
		} else if ($currentType === 'events') {
			preg_match_all('/\\\fn(.*?)(?=(\\\|}))/', $buffer, $matches);
			if (count($matches) > 1) {
				foreach ($matches[1] as $fontname) {
					if ($fontname == 0 || in_array($fontname, $fontnameArr)) {
						continue;
					}
					$fontnameArr[] = $fontname;
				}
			}
		}
		if (count($matchedTypes) === 2 && !in_array('Fonts', $matchedTypes)) {
			$matchedTypes[] = 'Fonts';
			$fontCount = count($fontnameArr);
			$fontArr = GetFont($fontnameArr);
			if ($subsetASSContent === null) {
				return 0;
			} else {
				// 处理字体追加并直到结束.
				if (count($fontArr) > 0) {
					$subsetASSContent .= "[Font]\n";
					foreach ($fontArr as $font) {
						if (!is_file(FontPath . '/' . $font['fontfile'])) {
							continue;
						}
						AddFontDownloadHistory($uid, $font['id']);
						$fontExt = pathinfo($font['fontfile'], PATHINFO_EXTENSION);
						$mapFontfile = GenerateRandomString(8) . ".{$fontExt}";
						foreach (explode("\n", $font['fontname']) as $fontname2) {
							$fontnames[] = $fontname2;
						}
						foreach (explode("\n", $font['fontfullname']) as $fontfullname2) {
							$fontnames[] = $fontfullname2;
						}
						foreach (explode("\n", $font['fontpsname']) as $fontpsname2) {
							$fontnames[] = $fontpsname2;
						}
						$fontnames = array_filter($fontnames);
						usort($fontnames, function ($a, $b) {
							return (strlen($b) - strlen($a));
						});
						$fontInfo = FontLib\Font::load((FontPath . '/' . $font['fontfile']));
						if ($fontInfo instanceof FontLib\TrueType\Collection) {
							while ($fontInfo->valid()) {
								$font2 = $fontInfo->current();
								$font2->parse();
								$font2->setSubset($subsetASSContent);
								$subFontTmpFile = tempnam('/tmp', 'FontServer-SubFont_');
								$font2->open($subFontTmpFile, FontLib\BinaryStream::modeReadWrite);
								$font2->encode(array("OS/2"));
								$font2->close();
								$mapFontfileArr[$mapFontfile][] = $fontnames;
								$fontInfo->next();
							}
						} else {
							$fontInfo->parse();
							$fontInfo->setSubset($subsetASSContent);
							$subFontTmpFile = tempnam('/tmp', 'FontServer-SubFont_');
							$fontInfo->open($subFontTmpFile, FontLib\BinaryStream::modeReadWrite);
							$fontInfo->encode(array("OS/2"));
							$fontInfo->close();
							$mapFontfileArr[$mapFontfile][] = $fontnames;
						}
						$subsetASSContent .= "fontname: {$mapFontfile}\n";
						$subsetASSContent .= UUEncode_ASS($subFontTmpFile, true);
						$subsetASSContent .= "\n";
					}
					$subsetASSContent .= "\n";
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
