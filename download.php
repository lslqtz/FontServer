<?php
require_once('config.php');
require_once('ass.php');
require_once('zipfile.php');
require_once('vendor/autoload.php');
ini_set('memory_limit', MaxMemoryMB . 'M');

function CheckSign(string $source, int $uid, int $timestamp, string $sign, string $filename, string $fileExt, string $filehash): ?string {
	if ($sign !== sha1(SignKey[$source] . "Download/{$source}_{$uid}-{$timestamp}-{$filename}.{$fileExt}-{$filehash}" . SignKey[$source]) || ($timestamp + DownloadExpireTime) < time()) {
		return null;
	}
	return $sign;
}
if (isset($_GET['source'], $_GET['uid'], $_GET['time'], $_GET['sign'], $_GET['filename']) && !empty($_POST['file'])) {
	if (!isset(SignKey[$_GET['source']])) {
		dieHTML("Bad source!\n");
	}
	$source = $_GET['source'];
	$uid = intval($_GET['uid']);
	$timestamp = intval($_GET['time']);
	$fileInfo = pathinfo($_GET['filename']);
	$filename = $fileInfo['filename'];
	if (empty($filename)) {
		dieHTML("Bad filename!\n");
	}
	$fileExt = $fileInfo['extension'];
	if (!in_array($fileExt, ['ass', 'ssa', 'zip'])) {
		dieHTML("Bad ext!\n");
	}
	$sign = CheckSign($source, $uid, $timestamp, $_GET['sign'], $filename, $fileExt, sha1($_POST['file']));
	if ($sign === null) {
		dieHTML("Bad sign!\n");
	}
	if (($decodedUploadFile = base64_decode($_POST['file'])) === false) {
		dieHTML("Bad file!\n");
	}
	if ((strlen($decodedUploadFile) / 1024 / 1024) > MaxFilesizeMB) {
		dieHTML("Too large file!\n");
	}
	$uploadTmpFilename = tempnam(SysCacheDir, Title . '_');
	$uploadFile = fopen($uploadTmpFilename, ($fileExt === 'zip' ? 'wb+' : 'w+'));
	if ($uploadFile === false) {
		fclose($uploadFile);
		dieHTML("An error occurred while reading subtitles!\n");
	}
	fwrite($uploadFile, $decodedUploadFile);
	unset($decodedUploadFile);
	fseek($uploadFile, 0);

	$fontArr = [];
	$fontnameArr = [];
	$isDownload = ((isset($_GET['download']) && $_GET['download'] == 1) ? true : false);
	$isDownloadFont = (($isDownload && AllowDownloadFont && (isset($_GET['mode']) && $_GET['mode'] === 'font')) ? true : false);
	$isDownloadSubtitle = (($isDownload && !$isDownloadFont && AllowDownloadSubtitle && (isset($_GET['mode']) && $_GET['mode'] === 'subtitle')) ? true : false);
	if ($isDownload) {
		$title = Title;
	}

	switch ($fileExt) {
		case 'ass':
		case 'ssa':
			$currentType = '';
			$fontIndex = 1;
			$foundFontIndex = false;
			$matchedTypes = [];
			$subsetASSContent = ($isDownloadSubtitle ? '' : null);
			while (($buffer = fgets($uploadFile)) !== false) {
				if (count($fontnameArr) > MaxDownloadFontCount) {
					break;
				}
				ParseSubtitleFont($buffer, $fontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent);
			}
			fclose($uploadFile);
			unlink($uploadTmpFilename);
			if (count($fontnameArr) > MaxDownloadFontCount) {
				dieHTML("Too many font!\n");
			}
			$fontArr = GetFont($fontnameArr);
			ParseFontArr($source, $uid, $fontArr, $subsetASSContent);
			if (count($fontArr) <= 0) {
				dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n");
			}
			if ($isDownloadFont) {
				ob_end_clean();
				header('Content-Type: application/zip');
				header("Content-Disposition: attachment; filename={$title}_Font; filename*=utf-8''[{$title}_Font] " . rawurlencode($filename) . ".zip");
				$archive = new ZipFile();
				$archive->setDoWrite();
				foreach ($fontArr as $key => &$font) {
					if (!is_file(FontPath . '/' . $font['fontfile'])) {
						unset($fontArr[$key]);
						continue;
					}
					$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
					unset($fontArr[$key]);
					flush();
				}
				$archive->file();
				die();
			}
			if ($isDownloadSubtitle) {
				header("Content-Disposition: attachment; filename={$title}_Subtitle; filename*=utf-8''[{$title}_Subtitle] " . rawurlencode($filename) . ".{$fileExt}");
				die($subsetASSContent);
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
				dieHTML("An error occurred while reading subtitle archive!\n");
			}
			for ($i = 0; $i < $subtitleArchive->numFiles; $i++) {
				if (count($fontnameArr) > MaxDownloadFontCount) {
					break;
				}
				$currentType = '';
				$fontIndex = 1;
				$foundFontIndex = false;
				$matchedTypes = [];
				$subsetASSContent = ($isDownloadSubtitle ? '' : null);
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
				dieHTML("Too many font!\n");
			}
			foreach ($subsetASSFiles as $filename2 => &$arr) {
				if ((memory_get_peak_usage() / 1024 / 1024) > ceil(MaxMemoryMB / 1.5)) {
					dieHTML("Unable to process this file! (Error: 1)\n");
				}
				$tmpSubsetASSContent = null;
				$tmpFontArr = GetFont($arr[0]);
				ParseFontArr($source, $uid, $tmpFontArr, $tmpSubsetASSContent);
				unset($arr[0]);
				$fontArr = array_unique(array_merge($fontArr, $tmpFontArr), SORT_REGULAR);
			}
			unset($tmpFontArr);
			if (count($fontArr) <= 0) {
				dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n");
			}
			if ($isDownloadFont || $isDownloadSubtitle) {
				$currentFileType = ($isDownloadFont ? 'Font' : 'Subtitle');
				ob_end_clean();
				$archive = new ZipFile();
				$archive->setDoWrite();
				if ($isDownloadFont) {
					header('Content-Type: application/zip');
					header("Content-Disposition: attachment; filename={$title}_{$currentFileType}; filename*=utf-8''[{$title}_{$currentFileType}] " . rawurlencode($filename) . ".zip");
					foreach ($fontArr as $key => &$font) {
						if (!is_file(FontPath . '/' . $font['fontfile'])) {
							unset($fontArr[$key]);
							continue;
						}
						$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
						unset($fontArr[$key]);
						flush();
					}
				} else {
					if (!ProcessFontForEverySubtitle) {
						$uniqueChar = [];
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							if ((memory_get_peak_usage() / 1024 / 1024) > ceil(MaxMemoryMB / 1.5)) {
								dieHTML("Unable to process this file! (Error: 2)\n");
							}
							GetUniqueChar($arr[1], $uniqueChar);
						}
						$subsetFontASSContent = '';
						list($mapFontnameArr, $fontInfoArr) = ProcessFontArr($source, $uid, $fontArr, $subsetFontASSContent, $uniqueChar);
						unset($fontArr, $fontInfoArr);
						header('Content-Type: application/zip');
						header("Content-Disposition: attachment; filename={$title}_{$currentFileType}; filename*=utf-8''[{$title}_{$currentFileType}] " . rawurlencode($filename) . ".zip");
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							ReplaceFontArr($mapFontnameArr, $arr[1], $subsetFontASSContent);
							$archive->addFile($arr[1], $filename2);
							unset($subsetASSFiles[$filename2]);
							flush();
						}
					} else {
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							$subsetFontASSContent = '';
							ParseFontArr($source, $uid, $fontArr, $arr[1]);
						}
						header('Content-Type: application/zip');
						header("Content-Disposition: attachment; filename={$title}_{$currentFileType}; filename*=utf-8''[{$title}_{$currentFileType}] " . rawurlencode($filename) . ".zip");
						foreach ($subsetASSFiles as $filename2 => &$arr) {
							$archive->addFile($arr[1], $filename2);
							unset($subsetASSFiles[$filename2]);
							flush();
						}
					}
				}
				$archive->file();
				die();
			}
			break;
		default:
			dieHTML("Bad ext!\n");
			break;
	}

	HTMLStart('Download');
	if (AllowDownloadFont) {
		echo "<form id=\"downloadFont\" method=\"POST\" action=\"{$_SERVER['REQUEST_URI']}&download=1&mode=font\">\n";
		echo "<input type=\"hidden\" name=\"file\" value=\"{$_POST['file']}\" />\n";
		echo "<p><a href=\"javascript:downloadFont.submit();\">Download Font!</a></p>\n";
		echo "</form>\n";
	}
	if (AllowDownloadSubtitle) {
		echo "<form id=\"downloadSubtitle\" method=\"POST\" action=\"{$_SERVER['REQUEST_URI']}&download=1&mode=subtitle\">\n";
		echo "<input type=\"hidden\" name=\"file\" value=\"{$_POST['file']}\" />\n";
		echo "<p><a href=\"javascript:downloadSubtitle.submit();\">Download Subtitle!</a></p>\n";
		echo "</form>\n";
	}
	echo "<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n";
	ShowTable($fontArr);
	HTMLEnd();
} else {
	dieHTML(":(\n");
}
?>
