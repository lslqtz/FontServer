<?php
require_once('config.php');
require_once('ass.php');
require_once('user.php');
require_once('vendor/autoload.php');
ini_set('memory_limit', MaxMemoryMB . 'M');
if (!isset($_GET['source'], $_GET['uid'], $_GET['torrent_id'], $_GET['time'], $_GET['sign'], $_GET['filename'])) {
	dieHTML(":(", 'Download');
}
if (!isset(SourcePolicy[$_GET['source']])) {
	dieHTML("坏来源!", 'Download');
}
if (!is_numeric($_GET['uid']) || !is_numeric($_GET['torrent_id']) || !is_numeric($_GET['time'])) {
	dieHTML("坏参数!", 'Download');
}
$fileInfo = pathinfo($_GET['filename']);
$filename = $fileInfo['filename'];
if (empty($filename)) {
	dieHTML("坏文件名!", 'Download');
}
$fileExt = $fileInfo['extension'];
$source = $_GET['source'];
$uid = intval($_GET['uid']);
$torrentID = intval($_GET['torrent_id']);
$timestamp = intval($_GET['time']);
$rawSign = $_GET['sign'];
$sourcePolicy = SourcePolicy[$source];
if (isset($_GET['font_id'])) {
	if (!is_numeric($_GET['font_id'])) {
		dieHTML("坏参数!", 'Download');
	}
	if (!$sourcePolicy['AllowDownloadFont']) {
		dieHTML("下载字体功能当前被停用!", 'Download');
	}
	$fontID = intval($_GET['font_id']);
	$sign = CheckSign($source, $uid, $torrentID, $timestamp, $rawSign, "{$filename}.{$fileExt}", ((isset($_GET['upload_subtitle']) && $_GET['upload_subtitle'] == 1) ? 'Unknown' : sha1($_GET['font_id'])));
	if ($sign === null) {
		dieHTML("坏签名!", 'Download');
	}
	$fontFile = GetFontFileByID($fontID);
	if ($fontFile === null || ($fontPath = GetFontPath($fontFile)) === null) {
		dieHTML("找不到字体!", 'Download');
	}
	AddFontDownloadHistory($source, $uid, 0, $fontID);
	header('X-Accel-Buffering: no');
	header("X-Accel-Redirect: /{$fontPath}");
	header("Content-Disposition: attachment; filename=" . rawurlencode($fontFile) . "; filename*=utf-8''" . rawurlencode($fontFile));
	die();
}
if (empty($_POST['file'])) {
	dieHTML("坏参数!", 'Download');
}
if ((isset($_GET['upload_subtitle']) && $_GET['upload_subtitle'] == 1 && $fileExt !== 'ass') || !in_array($fileExt, ['ass', 'ssa', 'zip'])) {
	dieHTML("坏扩展名!", 'Download');
}

$isDownload = ((isset($_GET['download']) && $_GET['download'] == 1) ? true : false);
$isDownloadFont = (($isDownload && (isset($_GET['mode']) && $_GET['mode'] === 'font')) ? true : false);
if ($isDownloadFont && !$sourcePolicy['AllowDownloadFontArchive']) {
	dieHTML("下载打包字体功能当前被停用!", 'Download');
}
$isDownloadSubsetSubtitle = (($isDownload && !$isDownloadFont && (isset($_GET['mode']) && $_GET['mode'] === 'subsetSubtitle')) ? true : false);
if ($isDownloadSubsetSubtitle && !$sourcePolicy['AllowDownloadSubsetSubtitle']) {
	dieHTML("下载自动子集化字幕 (嵌入字体) 功能当前被停用!", 'Download');
}
$isDownloadSubsetSubtitleWithSeparateFont = (($isDownload && !$isDownloadSubsetSubtitle && (isset($_GET['mode']) && $_GET['mode'] === 'subsetSubtitleWithSeparateFont')) ? true : false);
if ($isDownloadSubsetSubtitleWithSeparateFont && !$sourcePolicy['AllowDownloadSubsetSubtitleWithSeparateFont']) {
	dieHTML("下载自动子集化字幕 (非嵌入字体) 功能当前被停用!", 'Download');
}

$sign = CheckSign($source, $uid, $torrentID, $timestamp, $rawSign, "{$filename}.{$fileExt}", ((isset($_GET['upload_subtitle']) && $_GET['upload_subtitle'] == 1) ? 'Unknown' : sha1($_POST['file'])));
if ($sign === null) {
	dieHTML("坏签名!", 'Download');
}

$fontArr = [];
$fontnameArr = [];

if (($decodedUploadFile = base64_decode($_POST['file'])) === false || empty($decodedUploadFile)) {
	dieHTML("坏文件!", 'Download');
}
if ((strlen($decodedUploadFile) / 1024 / 1024) > $sourcePolicy['MaxFilesizeMB']) {
	dieHTML("太大的文件!", 'Download');
}

switch ($fileExt) {
	case 'ass':
	case 'ssa':
		$currentType = '';
		$fontIndex = 1;
		$foundFontIndex = false;
		$matchedTypes = [];
		$subsetASSContent = (($isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) ? '' : null);
		$uploadFileContentArr = explode("\n", ConvertEncode($decodedUploadFile));
		foreach ($uploadFileContentArr as $uploadFileLine) {
			if (count($fontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
				break;
			}
			ParseSubtitleFont($uploadFileLine, $fontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent);
		}
		if (count($fontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
			dieHTML("太多的字体!", 'Download');
		}
		$fontArr = GetFontByNameArr($sourcePolicy['MaxDownloadFontCount'], $fontnameArr);
		if (count($fontArr) <= 0) {
			dieHTML("找不到字体!\n字体数: " . count($fontnameArr) . ", 字体名: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5), 'Download');
		}
		$fontInfoArr = null;
		$subsetFontASSContent = [];
		AutoProcessFontArr($source, $uid, $torrentID, $fontArr, $fontInfoArr, $subsetASSContent, $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
		$db = null;
		if ($isDownloadFont || $isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) {
			ob_implicit_flush(true);
			ob_end_clean();
			header('X-Accel-Buffering: no');
			if ($isDownloadFont) {
				$archive = new ZipStream\ZipStream(
					outputName: "[" . Title . "_Font] {$filename}.zip",
					sendHttpHeaders: true,
					enableZip64: false,
					defaultEnableZeroHeader: false,
					defaultDeflateLevel: CompressLevel,
					contentType: 'application/zip'
				);
				foreach ($fontArr as $key => &$font) {
					$fontPath = GetFontPath($font['fontfile']);
					if ($fontPath === null) {
						unset($fontArr[$key]);
						continue;
					}
					$archive->addFile(
						fileName: $font['fontfile'],
						path: $fontPath
					);
					unset($fontArr[$key]);
				}
				$archive->finish();
				die();
			}
			if ($isDownloadSubsetSubtitle) {
				header("Content-Disposition: attachment; filename=" . Title . "_Subtitle; filename*=utf-8''[" . Title . "_SubsetSubtitle] " . rawurlencode($filename) . ".{$fileExt}");
				die($subsetASSContent);
			}
			if ($isDownloadSubsetSubtitleWithSeparateFont) {
				$archive = new ZipStream\ZipStream(
					outputName: "[" . Title . "_SubsetSubtitleWithSeparateFont] {$filename}.zip",
					sendHttpHeaders: true,
					enableZip64: false,
					defaultEnableZeroHeader: false,
					defaultDeflateLevel: CompressLevel,
					contentType: 'application/zip'
				);
				foreach ($subsetFontASSContent as $fontfilename => &$fontContent) {
					$archive->addFile(
						fileName: "Font/{$fontfilename}",
						data: $fontContent
					);
					unset($subsetFontASSContent[$fontfilename]);
				}
				$archive->addFile(
					fileName: "{$filename}.{$fileExt}",
					data: $subsetASSContent
				);
				$archive->finish();
				die();
			}
		}
		break;
	case 'zip':
		$uploadTmpFilename = tempnam(SysCacheDir, Title . '_');
		$uploadFile = fopen($uploadTmpFilename, ($fileExt === 'zip' ? 'wb+' : 'w+'));
		if ($uploadFile === false) {
			fclose($uploadFile);
			dieHTML("读取字幕时发生错误!", 'Download');
		}
		fwrite($uploadFile, $decodedUploadFile);
		unset($decodedUploadFile);
		fseek($uploadFile, 0);
		$subsetASSFiles = [];
		$subtitleArchive = new \ZipArchive();
		$subtitleArchive->open($uploadTmpFilename);
		if ($subtitleArchive === false) {
			$subtitleArchive->close();
			fclose($uploadFile);
			unlink($uploadTmpFilename);
			dieHTML("读取字幕压缩包时发生错误!", 'Download');
		}
		for ($i = 0; $i < $subtitleArchive->numFiles; $i++) {
			if (count($fontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
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
			$subtitleContent = $subtitleArchive->getFromName($subtitleFileName);
			if (empty($subtitleContent)) {
				continue;
			}
			$subtitleContentArr = explode("\n", ConvertEncode($subtitleContent));
			foreach ($subtitleContentArr as $subtitleContentLine) {
				if (count($tmpFontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
					$fontnameArr = $tmpFontnameArr;
					break 2;
				}
				ParseSubtitleFont($subtitleContentLine, $tmpFontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent);
			}
			$fontnameArr = array_unique(array_merge($fontnameArr, $tmpFontnameArr), SORT_REGULAR);
			if (count($fontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
				break;
			}
			$subsetASSFiles[$subtitleFileName] = [$tmpFontnameArr, $subsetASSContent];
		}
		$subtitleArchive->close();
		fclose($uploadFile);
		unlink($uploadTmpFilename);
		unset($uploadFile, $uploadTmpFilename, $subtitleArchive, $subsetASSContent, $tmpFontnameArr);
		if (count($fontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
			dieHTML("太多的字体!", 'Download');
		}
		$subsetASSFontArr = [];
		foreach ($subsetASSFiles as $filename2 => &$arr) {
			if ((memory_get_peak_usage() / 1024 / 1024) > ceil(MaxMemoryMB / 1.5)) {
				dieHTML("无法处理这个字幕! (Error: 1)", 'Download');
			}
			$subsetASSFontArr[$filename2] = GetFontByNameArr($sourcePolicy['MaxDownloadFontCount'], $arr[0]);
			$fontArr = array_unique(array_merge($fontArr, $subsetASSFontArr[$filename2]), SORT_REGULAR);
			unset($arr[0]);
		}
		if (count($fontArr) <= 0) {
			dieHTML("找不到字体!\n字体数: " . count($fontnameArr) . ", 字体名: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5), 'Download');
		}
		if ($isDownloadFont || $isDownloadSubsetSubtitle || $isDownloadSubsetSubtitleWithSeparateFont) {
			ob_implicit_flush(true);
			ob_end_clean();
			$currentFileType = ($isDownloadFont ? 'Font' : ($isDownloadSubsetSubtitleWithSeparateFont ? 'SubsetSubtitleWithSeparateFont' : 'SubsetSubtitle'));
			header('X-Accel-Buffering: no');
			$archive = new ZipStream\ZipStream(
				outputName: "[" . Title . "_{$currentFileType}] {$filename}.zip",
				sendHttpHeaders: true,
				enableZip64: false,
				defaultEnableZeroHeader: false,
				defaultDeflateLevel: CompressLevel,
				contentType: 'application/zip'
			);
			if ($isDownloadFont) {
				foreach ($fontArr as $key => &$font) {
					$fontPath = GetFontPath($font['fontfile']);
					if ($fontPath === null) {
						unset($fontArr[$key]);
						continue;
					}
					$archive->addFileFromPath(
						fileName: $font['fontfile'],
						path: $fontPath
					);
					unset($fontArr[$key]);
				}
			} else {
				if (!$sourcePolicy['ProcessFontForEverySubtitle']) {
					$uniqueChar = [];
					// 预处理, 得到字幕所有字符的并集.
					foreach ($subsetASSFiles as $filename2 => &$arr) {
						$arr[1] = ConvertEncode($arr[1]);
						GetUniqueChar($arr[1], $uniqueChar);
					}
					// 准备好所需的子集化字幕用附加字体信息.
					$fontInfoArr = null;
					$subsetFontASSContent = [];
					$mapFontnameArr = ProcessFontArr($source, $uid, $torrentID, $fontArr, $fontInfoArr, $subsetFontASSContent, $uniqueChar, $isDownloadSubsetSubtitleWithSeparateFont);
					unset($fontArr, $uniqueChar);
					$db = null;
					if ($isDownloadSubsetSubtitleWithSeparateFont) {
						// 仅 Subset Font 模式下, subsetFontASSContent 实际被当作 subsetFontContent 使用.
						foreach ($subsetFontASSContent as $fontfilename2 => &$fontContent2) {
							$archive->addFile(
								fileName: "Font/{$fontfilename2}",
								data: $fontContent2
							);
							unset($subsetFontASSContent[$fontfilename2]);
						}
					}
					foreach ($subsetASSFiles as $filename2 => &$arr) {
						ReplaceFontArr($mapFontnameArr, $arr[1], $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
						$archive->addFile(
							fileName: $filename2,
							data: $arr[1]
						);
						unset($subsetASSFiles[$filename2]);
					}
				} else {
					// 边输出边为每个字幕处理子集化.
					$fontInfoArr = [];
					foreach ($subsetASSFiles as $filename2 => &$arr) {
						$subsetFontASSContent = [];
						$arr[1] = ConvertEncode($arr[1]);
						AutoProcessFontArr($source, $uid, $torrentID, $subsetASSFontArr[$filename2], $fontInfoArr, $arr[1], $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
						if ($isDownloadSubsetSubtitleWithSeparateFont) {
							// 仅 Subset Font 模式下, subsetFontASSContent 实际被当作 subsetFontContent 使用.
							foreach ($subsetFontASSContent as $fontfilename2 => &$fontContent2) {
								$archive->addFile(
									fileName: "Font/{$filename2}/{$fontfilename2}",
									data: $fontContent2
								);
								unset($subsetFontASSContent[$fontfilename2]);
							}
						}
						$archive->addFile(
							fileName: $filename2,
							data: $arr[1]
						);
						unset($subsetASSFiles[$filename2]);
					}
					CloseFontInfoArr($fontInfoArr);
				}
			}
			$archive->finish();
			die();
		}
		break;
	default:
		dieHTML("坏扩展名!", 'Download');
		break;
}


HTMLStart('Download', GetUserBar($source, $uid, false));
echo "<script src=\"base64.js\"></script>\n";
echo "<script>function Download(target, filename = null) { switch (target) { case 'font': case 'subsetSubtitle': case 'subsetSubtitleWithSeparateFont': break; case 'originalSubtitle':  let blob = new Blob([Base64.toUint8Array(downloadForm.querySelector('input[name=\"file\"]').value)]); let ele = document.createElement('a'); ele.setAttribute('download', filename); ele.href = window.URL.createObjectURL(blob); document.body.appendChild(ele); ele.click(); ele.remove(); return; default: console.log('Bad target: ' + target); return; break; } downloadForm.action = downloadForm.action.replace(/(&|\?)download=(1|0)/, '').replace(/(&|\?)mode=(font|subsetSubtitleWithSeparateFont|subsetSubtitle)/i, ''); downloadForm.action += ('&download=1&mode=' + target); downloadForm.submit(); }</script>\n";
if ($sourcePolicy['AllowDownloadFontArchive'] || $sourcePolicy['AllowDownloadSubsetSubtitle'] || $sourcePolicy['AllowDownloadSubsetSubtitleWithSeparateFont']) {
	echo "<form id=\"downloadForm\" method=\"POST\">\n";
	echo "<input type=\"hidden\" name=\"file\" value=\"{$_POST['file']}\" />\n";
	echo "<p><a href=\"javascript:Download('originalSubtitle', '" . htmlspecialchars("{$filename}.{$fileExt}") . "');\">下载原始字幕!</a></p>\n";
	if ($sourcePolicy['AllowDownloadFontArchive']) {
		echo "<p><a href=\"javascript:Download('font');\">下载打包字体!</a></p>\n";
	}
	if ($sourcePolicy['AllowDownloadSubsetSubtitle']) {
		echo "<p><a href=\"javascript:Download('subsetSubtitle');\">下载自动子集化字幕 (推荐, 嵌入字体)!</a></p>\n";
	}
	if ($sourcePolicy['AllowDownloadSubsetSubtitleWithSeparateFont']) {
		echo "<p><a href=\"javascript:Download('subsetSubtitleWithSeparateFont');\">下载自动子集化字幕 (非嵌入字体)!</a></p>\n";
	}
	echo "</form>\n";
}
echo "<p>字体数: " . count($fontnameArr) . ", 字体名: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n";
ShowTable($fontArr, true, ($sourcePolicy['AllowDownloadFont'] ? [$source, $uid, $torrentID, $timestamp] : null), (isset($_GET['upload_subtitle']) && $_GET['upload_subtitle'] == 1));
HTMLEnd();
?>
