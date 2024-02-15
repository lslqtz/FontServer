<?php
require_once('config.php');
require_once('ass.php');
require_once('zipfile.php');
require_once('vendor/autoload.php');
ini_set('memory_limit', MaxMemoryMB . 'M');

function CheckSign(string $source, int $uid, int $torrentID, int $timestamp, string $sign, string $filename, string $fileExt, string $filehash): ?string {
	if ($sign !== sha1(SignKey[$source] . "Download/{$source}_{$uid}-{$torrentID}-{$timestamp}-{$filename}.{$fileExt}-{$filehash}" . SignKey[$source]) || ($timestamp + DownloadExpireTime) < time()) {
		return null;
	}
	return $sign;
}
if (isset($_GET['source'], $_GET['uid'], $_GET['torrent_id'], $_GET['time'], $_GET['sign'], $_GET['filename']) && !empty($_POST['file'])) {
	if (!isset(SignKey[$_GET['source']])) {
		dieHTML("Bad source!\n", 'Download');
	}
	$source = $_GET['source'];
	$uid = intval($_GET['uid']);
	$torrentID = intval($_GET['torrent_id']);
	$timestamp = intval($_GET['time']);
	$fileInfo = pathinfo($_GET['filename']);
	$filename = $fileInfo['filename'];
	if (empty($filename)) {
		dieHTML("Bad filename!\n", 'Download');
	}
	$fileExt = $fileInfo['extension'];
	if (!in_array($fileExt, ['ass', 'ssa', 'zip'])) {
		dieHTML("Bad ext!\n", 'Download');
	}
	$sign = CheckSign($source, $uid, $torrentID, $timestamp, $_GET['sign'], $filename, $fileExt, sha1($_POST['file']));
	if ($sign === null) {
		dieHTML("Bad sign!\n", 'Download');
	}
	if (($decodedUploadFile = base64_decode($_POST['file'])) === false) {
		dieHTML("Bad file!\n", 'Download');
	}
	if ((strlen($decodedUploadFile) / 1024 / 1024) > MaxFilesizeMB) {
		dieHTML("Too large file!\n", 'Download');
	}
	$uploadTmpFilename = tempnam(SysCacheDir, Title . '_');
	$uploadFile = fopen($uploadTmpFilename, ($fileExt === 'zip' ? 'wb+' : 'w+'));
	if ($uploadFile === false) {
		fclose($uploadFile);
		dieHTML("An error occurred while reading subtitles!\n", 'Download');
	}
	fwrite($uploadFile, $decodedUploadFile);
	unset($decodedUploadFile);
	fseek($uploadFile, 0);

	$fontArr = [];
	$fontnameArr = [];
	$isDownload = ((isset($_GET['download']) && $_GET['download'] == 1) ? true : false);
	$isDownloadFont = (($isDownload && (isset($_GET['mode']) && $_GET['mode'] === 'font')) ? true : false);
	if ($isDownloadFont && !AllowDownloadFont) {
		dieHTML("Downloading font is currently disabled!\n", 'Download');
	}
	$isDownloadSubsetSubtitle = (($isDownload && !$isDownloadFont && (isset($_GET['mode']) && $_GET['mode'] === 'subsetSubtitle')) ? true : false);
	if ($isDownloadSubsetSubtitle && !AllowDownloadSubsetSubtitle) {
		dieHTML("Downloading subset subtitle is currently disabled!\n", 'Download');
	}
	$isDownloadSubsetSubtitleWithSeparateFont = (($isDownload && !$isDownloadSubsetSubtitle && (isset($_GET['mode']) && $_GET['mode'] === 'subsetSubtitleWithSeparateFont')) ? true : false);
	if ($isDownloadSubsetSubtitleWithSeparateFont && !AllowDownloadSubsetSubtitleWithSeparateFont) {
		dieHTML("Downloading subset subtitle with font is currently disabled!\n", 'Download');
	}

	switch ($fileExt) {
		case 'ass':
		case 'ssa':
			$currentType = '';
			$fontIndex = 1;
			$foundFontIndex = false;
			$matchedTypes = [];
			$subsetASSContent = (($isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) ? '' : null);
			while (($buffer = fgets($uploadFile)) !== false) {
				if (count($fontnameArr) > MaxDownloadFontCount) {
					break;
				}
				ParseSubtitleFont($buffer, $fontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent);
			}
			fclose($uploadFile);
			unlink($uploadTmpFilename);
			if (count($fontnameArr) > MaxDownloadFontCount) {
				dieHTML("Too many font!\n", 'Download');
			}
			$fontArr = GetFont($fontnameArr);
			if (count($fontArr) <= 0) {
				dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n", 'Download');
			}
			$fontInfoArr = null;
			$subsetFontASSContent = [];
			AutoProcessFontArr($source, $uid, $torrentID, $fontArr, $fontInfoArr, $subsetASSContent, $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
			if ($isDownloadFont || $isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) {
				ob_implicit_flush(true);
				ob_end_clean();
				header('X-Accel-Buffering: no');
				if ($isDownloadFont) {
					header('Content-Type: application/zip');
					header("Content-Disposition: attachment; filename=" . Title . "_Font; filename*=utf-8''[" . Title . "_Font] " . rawurlencode($filename) . ".zip");
					$archive = new ZipFile();
					$archive->setDoWrite();
					foreach ($fontArr as $key => &$font) {
						if (!is_file(FontPath . '/' . $font['fontfile'])) {
							unset($fontArr[$key]);
							continue;
						}
						$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
						unset($fontArr[$key]);
					}
					$archive->file();
					die();
				}
				if ($isDownloadSubsetSubtitle) {
					header("Content-Disposition: attachment; filename=" . Title . "_Subtitle; filename*=utf-8''[" . Title . "_SubsetSubtitle] " . rawurlencode($filename) . ".{$fileExt}");
					die($subsetASSContent);
				}
				if ($isDownloadSubsetSubtitleWithSeparateFont) {
					header('Content-Type: application/zip');
					header("Content-Disposition: attachment; filename=" . Title . "_SubsetSubtitleWithSeparateFont; filename*=utf-8''[" . Title . "_SubsetSubtitleWithSeparateFont] " . rawurlencode($filename) . ".zip");
					$archive = new ZipFile();
					$archive->setDoWrite();
					foreach ($subsetFontASSContent as $fontfilename => $fontContent) {
						$archive->addFile($fontContent, "Font/{$fontfilename}");
						unset($subsetFontASSContent[$fontfilename]);
					}
					$archive->addFile($subsetASSContent, "{$filename}.{$fileExt}");
					$archive->file();
					die();
				}
			}
			break;
		case 'zip':
			$subsetASSFiles = [];
			$subtitleArchive = new \ZipArchive();
			$subtitleArchive->open($uploadTmpFilename);
			if ($subtitleArchive === false) {
				$subtitleArchive->close();
				fclose($uploadFile);
				unlink($uploadTmpFilename);
				dieHTML("An error occurred while reading subtitle archive!\n", 'Download');
			}
			for ($i = 0; $i < $subtitleArchive->numFiles; $i++) {
				if (count($fontnameArr) > MaxDownloadFontCount) {
					break;
				}
				$currentType = '';
				$fontIndex = 1;
				$foundFontIndex = false;
				$matchedTypes = [];
				$subsetASSContent = (($isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) ? '' : null);
				$tmpFontnameArr = [];
				$subtitleFileName = $subtitleArchive->getNameIndex($i);
				if (stripos($subtitleFileName, '__MACOSX') !== false) {
					continue;
				}
				$fileInfo2 = pathinfo($subtitleFileName);
				$filename2 = $fileInfo2['filename'];
				if ($filename2[0] === '.' || !isset($fileInfo2['extension'])) {
					continue;
				}
				$subtitleExt = $fileInfo2['extension'];
				if ($subtitleExt !== 'ass' && $subtitleExt !== 'ssa') {
					continue;
				}
				$subtitleContentHandle = $subtitleArchive->getStream($subtitleFileName);
				while (($buffer = fgets($subtitleContentHandle)) !== false) {
					if (count($tmpFontnameArr) > MaxDownloadFontCount) {
						$fontnameArr = $tmpFontnameArr;
						break 2;
					}
					ParseSubtitleFont($buffer, $tmpFontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent);
				}
				fclose($subtitleContentHandle);
				$fontnameArr = array_unique(array_merge($fontnameArr, $tmpFontnameArr), SORT_REGULAR);
				if (count($fontnameArr) > MaxDownloadFontCount) {
					break;
				}
				$subsetASSFiles[$subtitleFileName] = [$tmpFontnameArr, $subsetASSContent];
			}
			$subtitleArchive->close();
			fclose($uploadFile);
			unlink($uploadTmpFilename);
			unset($uploadFile, $uploadTmpFilename, $subtitleArchive, $subsetASSContent, $tmpFontnameArr);
			if (count($fontnameArr) > MaxDownloadFontCount) {
				dieHTML("Too many font!\n", 'Download');
			}
			$subsetASSFontArr = [];
			foreach ($subsetASSFiles as $filename2 => &$arr) {
				if ((memory_get_peak_usage() / 1024 / 1024) > ceil(MaxMemoryMB / 1.5)) {
					dieHTML("Unable to process this file! (Error: 1)\n", 'Download');
				}
				$subsetASSFontArr[$filename2] = GetFont($arr[0]);
				$fontArr = array_unique(array_merge($fontArr, $subsetASSFontArr[$filename2]), SORT_REGULAR);
				unset($arr[0]);
			}
			if (count($fontArr) <= 0) {
				dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n", 'Download');
			}
			if ($isDownloadFont || $isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) {
				ob_implicit_flush(true);
				ob_end_clean();
				$currentFileType = ($isDownloadFont ? 'Font' : ($isDownloadSubsetSubtitleWithSeparateFont ? 'SubsetSubtitleWithSeparateFont' : 'SubsetSubtitle'));
				header('X-Accel-Buffering: no');
				header('Content-Type: application/zip');
				header("Content-Disposition: attachment; filename=" . Title . "_{$currentFileType}; filename*=utf-8''[" . Title . "_{$currentFileType}] " . rawurlencode($filename) . ".zip");
				$archive = new ZipFile();
				$archive->setDoWrite();
				if ($isDownloadFont) {
					foreach ($fontArr as $key => &$font) {
						if (!is_file(FontPath . '/' . $font['fontfile'])) {
							unset($fontArr[$key]);
							continue;
						}
						$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
						unset($fontArr[$key]);
					}
				} else {
					if (!ProcessFontForEverySubtitle) {
						$uniqueChar = [];
						// 预处理, 得到字幕所有字符的并集.
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							GetUniqueChar($arr[1], $uniqueChar);
						}
						// 准备好所需的子集化字幕用附加字体信息.
						$fontInfoArr = null;
						$subsetFontASSContent = [];
						$mapFontnameArr = ProcessFontArr($source, $uid, $torrentID, $fontArr, $fontInfoArr, $subsetFontASSContent, $uniqueChar, $isDownloadSubsetSubtitleWithSeparateFont);
						unset($fontArr, $uniqueChar);
						if ($isDownloadSubsetSubtitleWithSeparateFont) {
							// 仅 Subset Font 模式下, subsetFontASSContent 实际被当作 subsetFontContent 使用.
							foreach ($subsetFontASSContent as $fontfilename2 => $fontContent2) {
								$archive->addFile($fontContent2, "Font/{$fontfilename2}");
								unset($subsetFontASSContent[$fontfilename2]);
							}
						}
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							ReplaceFontArr($mapFontnameArr, $arr[1], $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
							$archive->addFile($arr[1], $filename2);
							unset($subsetASSFiles[$filename2]);
						}
					} else {
						// 边输出边为每个字幕处理子集化.
						$fontInfoArr = [];
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							$subsetFontASSContent = [];
							AutoProcessFontArr($source, $uid, $torrentID, $subsetASSFontArr[$filename2], $fontInfoArr, $arr[1], $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
							if ($isDownloadSubsetSubtitleWithSeparateFont) {
								// 仅 Subset Font 模式下, subsetFontASSContent 实际被当作 subsetFontContent 使用.
								foreach ($subsetFontASSContent as $fontfilename2 => $fontContent2) {
									$archive->addFile($fontContent2, "Font/{$filename2}/{$fontfilename2}");
									unset($subsetFontASSContent[$fontfilename2]);
								}
							}
							$archive->addFile($arr[1], $filename2);
							unset($subsetASSFiles[$filename2]);
						}
						CloseFontInfoArr($fontInfoArr);
					}
				}
				$archive->file();
				die();
			}
			break;
		default:
			dieHTML("Bad ext!\n", 'Download');
			break;
	}

	HTMLStart('Download');
	echo "<script src=\"base64.js\"></script>\n";
	echo "<script>function Download(target, filename = null) { switch (target) { case 'font': case 'subsetSubtitle': case 'subsetSubtitleWithSeparateFont': break; case 'originalSubtitle':  let blob = new Blob([Base64.toUint8Array(downloadForm.querySelector('input[name=\"file\"]').value)]); let ele = document.createElement('a'); ele.setAttribute('download', filename); ele.href = window.URL.createObjectURL(blob); document.body.appendChild(ele); ele.click(); ele.remove(); return; default: console.log('Bad target: ' + target); return; break; } downloadForm.action = downloadForm.action.replace(/(&|\?)download=(1|0)/, '').replace(/(&|\?)mode=(font|subsetSubtitleWithSeparateFont|subsetSubtitle)/i, ''); downloadForm.action += ('&download=1&mode=' + target); downloadForm.submit(); }</script>\n";
	if (AllowDownloadFont || AllowDownloadSubsetSubtitle || AllowDownloadSubsetSubtitleWithSeparateFont) {
		echo "<form id=\"downloadForm\" method=\"POST\">\n";
		echo "<input type=\"hidden\" name=\"file\" value=\"{$_POST['file']}\" />\n";
		if (AllowDownloadFont) {
			echo "<p><a href=\"javascript:Download('font');\">下载字体!</a></p>\n";
		}
		if (AllowDownloadSubsetSubtitle) {
			echo "<p><a href=\"javascript:Download('subsetSubtitle');\">下载自动子集化字幕 (推荐, 嵌入字体)!</a></p>\n";
		}
		if (AllowDownloadSubsetSubtitleWithSeparateFont) {
			echo "<p><a href=\"javascript:Download('subsetSubtitleWithSeparateFont');\">下载自动子集化字幕 (非嵌入字体)!</a></p>\n";
		}
		echo "<p><a href=\"javascript:Download('originalSubtitle', '" . htmlspecialchars("{$filename}.{$fileExt}") . "');\">下载原始字幕!</a></p>\n";
		echo "</form>\n";
	}
	echo "<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n";
	ShowTable($fontArr);
	HTMLEnd();
} else {
	dieHTML(":(\n", 'Download');
}
?>
