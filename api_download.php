<?php
require_once('config.php');
require_once('ass.php');
require_once('font.php');
require_once('user.php');
require_once('vendor/autoload.php');
ini_set('memory_limit', MaxMemoryMB . 'M');
$queueInfo = null;
function dieWithQueue(int $code = -233, string $status = '') {
	global $queueInfo;
	Unqueue($queueInfo);
	dieJSON($code, $status);
}
function dieJSON(int $code = -233, string $status = ''); {
	$json = array('code' => $code, 'status' => "{$status}.");
	die(json_encode($json, JSON_NUMERIC_CHECK));
}

if (!isset($_GET['action'], $_GET['filename'])) {
	dieJSON(-1, ':(')
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
$sourcePolicy = $loginPolicy[2];

if (!isset($sourcePolicy['AllowAPIRequest']) || !$sourcePolicy['AllowAPIRequest']) {
	dieJSON(-10, 'No permission');
}
if (empty($_POST['file'])) {
	dieJSON(-5, 'Empty file');
}
if ($fileExt !== 'ass') {
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
		$currentType = '';
		$fontIndex = 1;
		$foundFontIndex = false;
		$matchedTypes = [];
		$subsetASSContent = ($isDownloadSubsetSubtitle ? '' : null);
		$uploadFileContentArr = explode("\n", ConvertEncode($decodedUploadFile));
		foreach ($uploadFileContentArr as $uploadFileLine) {
			if (count($subtitleFontnameArr) > $sourcePolicy['MaxDownloadFontCount']) {
				break;
			}
			ParseSubtitleFont($uploadFileLine, $subtitleFontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent);
		}
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
			$fontInfoArr = null;
			$subsetFontASSContent = [];
			AutoProcessFontArr($source, $uid, $torrentID, $fontArr, $fontInfoArr, $subsetASSContent, $subsetFontASSContent, $isDownloadSubsetSubtitleWithSeparateFont);
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
					$archive->addFile(
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
	default:
		dieJSON(-6, 'Bad file ext');
		break;
}
?>
