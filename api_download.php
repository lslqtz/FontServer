<?php
require_once('config.php');
require_once('ass.php');
require_once('font.php');
require_once('user.php');
require_once('vendor/autoload.php');
ini_set('memory_limit', MaxMemoryMB . 'M');
$queueInfo = null;
function dieWithQueue(string $status = '') {
	global $queueInfo;
	Unqueue($queueInfo);
	die($status);
}
function dieWithQueueJSON(int $code = -233, string $status = '') {
	global $queueInfo;
	Unqueue($queueInfo);
	dieJSON($code, $status);
}
function dieJSON(int $code = -233, string $status = '') {
	$json = array('code' => $code, 'status' => "{$status}.");
	die(json_encode($json, JSON_NUMERIC_CHECK));
}

if (!isset($_GET['action'], $_GET['filename'])) {
	dieJSON(-1, ':(');
}
if ($_GET['action'] !== 'downloadSubsetSubtitle') {
	dieJSON(-2, 'Bad action');
}
$loginPolicy = IsLogin();
if ($loginPolicy === null) {
	dieJSON(-3, 'Require auth');
}
$fileInfo = pathinfo($_GET['filename']);
$filename = $fileInfo['filename'];
if (empty($filename)) {
	dieJSON(-4, 'Bad filename');
}
$fileExt = $fileInfo['extension'];
$source = $loginPolicy[0];
$uid = $loginPolicy[1];
$torrentID = isset($_GET['torrent_id']) ? intval($_GET['torrent_id']) : 0;
$sourcePolicy = $loginPolicy[2];

if (!isset($sourcePolicy['AllowAPIRequest']) || !$sourcePolicy['AllowAPIRequest']) {
	dieJSON(-10, 'No permission');
}
if (empty($_POST['file'])) {
	dieJSON(-5, 'Empty file');
}
if (!in_array($fileExt, ['ass', 'ssa', 'zip', 'rar', '7z'])) {
	dieJSON(-6, 'Bad file ext');
}

$isDownloadReq = true;
$isDownloadFont = false;
if ($isDownloadFont && !$sourcePolicy['AllowDownloadFontArchive']) {
	dieJSON(-7, 'Disabled action');
}
$isDownloadSubsetSubtitleWithoutSeparateFont = true;
if ($isDownloadSubsetSubtitleWithoutSeparateFont && !$sourcePolicy['AllowDownloadSubsetSubtitle']) {
	dieJSON(-7, 'Disabled action');
}
$isDownloadSubsetSubtitleWithSeparateFont = false;
if ($isDownloadSubsetSubtitleWithSeparateFont && !$sourcePolicy['AllowDownloadSubsetSubtitleWithSeparateFont']) {
	dieJSON(-7, 'Disabled action');
}
$isDownloadSubsetSubtitle = ($isDownloadSubsetSubtitleWithoutSeparateFont || $isDownloadSubsetSubtitleWithSeparateFont);
$isDownload = ($isDownloadFont || $isDownloadSubsetSubtitle);

$fontArr = [];
$subtitleFontnameArr = [];

if (($decodedUploadFile = base64_decode($_POST['file'])) === false || empty($decodedUploadFile)) {
	dieJSON(-8, 'Bad file');
}
if ((strlen($decodedUploadFile) / 1024 / 1024) > $sourcePolicy['MaxSubtitleFilesizeMB']) {
	dieJSON(-9, 'Too big file');
}

switch ($fileExt) {
	case 'ass':
	case 'ssa':
		list($subtitleFontnameArr, $subsetASSContent) = ParseSubtitleContent($decodedUploadFile, $sourcePolicy['MaxDownloadFontCount'], $isDownloadSubsetSubtitle);
		if (count($subtitleFontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
			dieJSON(-12, 'Too many fonts');
		}
		$fontArr = GetFontByNameArr($sourcePolicy['MaxDownloadFontCount'], $subtitleFontnameArr);
		if (count($fontArr) <= 0) {
			dieJSON(-13, 'No font found');
		}
		if ($isDownload) {
			$queueInfo = Queue();
			if ($queueInfo[0] < 0) {
				dieJSON(-11, 'Server is busy');
			}
			$subsetFontASSContent = [];
			AutoProcessFontArr($source, $uid, $torrentID, $fontArr, $subsetASSContent, $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
			$db = null;
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
					$archive->addFileFromPath(
						fileName: $font['fontfile'],
						path: $fontPath
					);
					unset($fontArr[$key]);
				}
				$archive->finish();
				dieWithQueue();
			}
			if ($isDownloadSubsetSubtitleWithoutSeparateFont) {
				header("Content-Disposition: attachment; filename=" . Title . "_Subtitle; filename*=utf-8''[" . Title . "_SubsetSubtitle] " . rawurlencode($filename) . ".{$fileExt}");
				dieWithQueue($subsetASSContent);
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
				dieWithQueue();
			}
		}
		break;
	case 'zip':
	case 'rar':
	case '7z':
		try {
			$subtitleArchive = \Kiwilan\Archive\Archive::readFromString($decodedUploadFile, extension: $fileExt);
		} catch (Throwable $e) {
			dieJSON(-14, 'Archive read error');
		}
		unset($decodedUploadFile);
		$subtitleArchiveFiles = $subtitleArchive->getFileItems();
		if (count($subtitleArchiveFiles) > $sourcePolicy['MaxSubtitleFileCount']) {
			dieJSON(-15, 'Too many subtitles');
		}
		list($subsetASSFiles, $subtitleFontnameArr) = ParseArchiveSubtitles($subtitleArchive, $sourcePolicy['MaxDownloadFontCount'], $isDownloadSubsetSubtitle);
		unset($subtitleArchive, $subtitleArchiveFiles);
		if (count($subtitleFontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
			dieJSON(-12, 'Too many fonts');
		}
		$subsetASSFontArr = [];
		foreach ($subsetASSFiles as $filename2 => &$arr) {
			$subsetASSFontArr[$filename2] = GetFontByNameArr($sourcePolicy['MaxDownloadFontCount'], $arr[0]);
			unset($arr[0]);
		}
		$fontArr = array_unique(array_merge($fontArr, ...array_values($subsetASSFontArr)), SORT_REGULAR);
		if (count($fontArr) <= 0) {
			dieJSON(-13, 'No font found');
		}

		$cacheFontInfoArr = [];
		$maxCacheCount = SourcePolicy[$source]['MaxCacheFontCount'];
		if ($isDownload && $maxCacheCount > 0 && $sourcePolicy['ProcessFontForEverySubtitle']) {
			$cacheFontInfoArr = BuildCacheFontInfoArr($subsetASSFontArr, $maxCacheCount);
		}
		if ($isDownload) {
			$queueInfo = Queue();
			if ($queueInfo[0] < 0) {
				dieJSON(-11, 'Server is busy');
			}
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
					foreach ($subsetASSFiles as $filename2 => &$arr) {
						GetUniqueChar($arr[1], $uniqueChar);
					}
					$subsetFontASSContent = [];
					$mapSubtitleFontnameArr = ProcessFontArr($source, $uid, $torrentID, $fontArr, $subsetFontASSContent, $uniqueChar, $isDownloadSubsetSubtitleWithSeparateFont, $cacheFontInfoArr);
					unset($fontArr, $uniqueChar);
					$db = null;
					if ($isDownloadSubsetSubtitleWithSeparateFont) {
						foreach ($subsetFontASSContent as $fontfilename => &$fontContent) {
							$archive->addFile(
								fileName: "Font/{$fontfilename}",
								data: $fontContent
							);
							unset($subsetFontASSContent[$fontfilename]);
						}
					}
					foreach ($subsetASSFiles as $filename2 => &$arr) {
						ReplaceFontArr($mapSubtitleFontnameArr, $arr[1], $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
						$archive->addFile(
							fileName: "{$filename2}",
							data: $arr[1]
						);
						unset($subsetASSFiles[$filename2]);
					}
					CloseFontInfoArr($cacheFontInfoArr);
					$db = null;
				} else {
					foreach ($subsetASSFiles as $filename2 => &$arr) {
						$subsetFontASSContent = [];
						AutoProcessFontArr($source, $uid, $torrentID, $subsetASSFontArr[$filename2], $arr[1], $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont, $cacheFontInfoArr);
						if ($isDownloadSubsetSubtitleWithSeparateFont) {
							foreach ($subsetFontASSContent as $fontfilename2 => &$fontContent2) {
								$archive->addFile(
									fileName: "Font/{$fontfilename2}",
									data: $fontContent2
								);
								unset($subsetFontASSContent[$fontfilename2]);
							}
						}
						$archive->addFile(
							fileName: "{$filename2}",
							data: $arr[1]
						);
						unset($subsetASSFiles[$filename2]);
					}
					CloseFontInfoArr($cacheFontInfoArr);
					$db = null;
				}
			}
			$archive->finish();
			dieWithQueue();
		}
		break;
	default:
		dieJSON(-6, 'Bad file ext');
		break;
}
?>
