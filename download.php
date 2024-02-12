<?php
require_once('config.php');
require_once('mysql.php');
require_once('ass.php');
require_once('zipfile.php');
require_once('vendor/autoload.php');
ini_set('memory_limit', '128M');

function CheckSign(string $sign, int $uid, int $timestamp, string $filename, string $fileExt, string $filehash): ?string {
	if ($sign !== sha1(SignKey . "Download/{$uid}-{$timestamp}-{$filename}.{$fileExt}-{$filehash}" . SignKey) || ($timestamp + DownloadExpireTime) < time()) {
		return null;
	}
	return $sign;
}
function AddFontDownloadHistory(int $uid, int $downloadID) {
	global $db;
	return $db->exec("INSERT INTO `download_history` (`user_id`, `download_id`) VALUES ({$uid}, {$downloadID}) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP()");
}
if (isset($_GET['sign'], $_GET['uid'], $_GET['time'], $_GET['filename']) && !empty($_POST['file'])) {
	$uid = intval($_GET['uid']);
	$timestamp = intval($_GET['time']);
	$fileInfo = pathinfo($_GET['filename']);
	$filename = $fileInfo['filename'];
	$fileExt = $fileInfo['extension'];
	if (!in_array($fileExt, ['ass', 'ssa', 'zip'])) {
		dieHTML("Bad ext!\n");
	}
	$sign = CheckSign($_GET['sign'], $uid, $timestamp, $filename, $fileExt, sha1($_POST['file']));
	if ($sign === null) {
		dieHTML("Bad sign!\n");
	}
	if (($decodedUploadFile = base64_decode($_POST['file'])) === false) {
		dieHTML("Bad file!\n");
	}
	$uploadTmpFilename = tempnam('/tmp', 'FontServer_');
	$uploadFile = fopen($uploadTmpFilename, ($fileExt === 'zip' ? 'wb+' : 'w+'));
	if ($uploadFile === false) {
		fclose($uploadFile);
		dieHTML("An error occurred while reading subtitles!\n");
	}
	fwrite($uploadFile, $decodedUploadFile);
	fseek($uploadFile, 0);

	$fontArr = [];
	$fontnameArr = [];
	$matchedTypes = [];
	$currentType = '';
	$fontIndex = 1;
    $foundFontIndex = false;
	$isDownload = ((isset($_GET['download']) && $_GET['download'] == 1) ? true : false);
	$isDownloadFont = (($isDownload && AllowDownloadFont && (isset($_GET['mode']) && $_GET['mode'] === 'font')) ? true : false);
	$isDownloadSubtitle = (($isDownload && AllowDownloadSubtitle && (isset($_GET['mode']) && $_GET['mode'] === 'subtitle')) ? true : false);

	switch ($fileExt) {
		case 'ass':
		case 'ssa':
			$subsetASSContent = ($isDownloadSubtitle ? '' : null);
			$mapFontfileArr = ($isDownloadSubtitle ? [] : null);
			while (($buffer = fgets($uploadFile)) !== false) {
				if (count($fontArr) > MaxDownloadFontCount) {
					break;
				}
				if (ParseSubtitleFont($uid, $buffer, $fontArr, $fontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent, $mapFontfileArr) === -1) {
					break;
				}
			}
			fclose($uploadFile);
			$fontCount = count($fontArr);
            if ($fontCount <= 0) {
            	dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n");
            } else if ($fontCount > MaxDownloadFontCount) {
                dieHTML("Too many font!\n");
            }
			if ($isDownloadFont) {
				ob_end_clean();
				header('Content-Type: application/zip');
				header("Content-Disposition: attachment; filename=MDU-Fonts; filename*=utf-8''MDU-Fonts_" . rawurlencode($filename) . ".zip");
				$tmpArr = [];
				$archive = new ZipFile();
				$archive->setDoWrite();
				foreach ($fontArr as $font) {
					if (!is_file(FontPath . '/' . $font['fontfile'])) {
						continue;
					}
					AddFontDownloadHistory($uid, $font['id']);
					$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
					flush();
				}
				$archive->file();
				die();
			}
			if ($isDownloadSubtitle) {
				foreach ($mapFontfileArr as $mapFontfile => $fontnames) {
					foreach ($fontnames as $fontname) {
						$subsetASSContent = str_replace($fontname, $mapFontfile, $subsetASSContent);
					}
				}
				header("Content-Disposition: attachment; filename=MDU-Subtitle; filename*=utf-8''MDU-Subtitle_" . rawurlencode($filename) . ".{$fileExt}");
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
				dieHTML("An error occurred while reading subtitle archive!\n");
			}
            for ($i = 0; $i < $subtitleArchive->numFiles; $i++) {
				$subsetASSContent = ($isDownloadSubtitle ? '' : null);
				$mapFontfileArr = ($isDownloadSubtitle ? [] : null);
                $subtitleFileName = $subtitleArchive->getNameIndex($i);
                $subtitleExt = pathinfo($subtitleFileName, PATHINFO_EXTENSION);
                if ($subtitleExt !== 'ass' && $subtitleExt !== 'ssa') {
                    continue;
                }
                $subtitleContentHandle = $subtitleArchive->getStream($subtitleFileName);
                while (($buffer = fgets($subtitleContentHandle)) !== false) {
					if (count($fontArr) > MaxDownloadFontCount) {
						break 2;
					}
                    if (ParseSubtitleFont($uid, $buffer, $fontArr, $fontnameArr, $currentType, $fontIndex, $foundFontIndex, $matchedTypes, $subsetASSContent, $mapFontfileArr) === -1) {
                		fclose($subtitleContentHandle);
                    	break 2;
                    }
                }
                fclose($subtitleContentHandle);
                if ($isDownloadSubtitle) {
					foreach ($mapFontfileArr as $mapFontfile => $fontnames) {
						foreach ($fontnames as $fontname) {
							$subsetASSContent = str_replace($fontname, $mapFontfile, $subsetASSContent);
						}
					}
	                $subsetASSFiles["MDU-Subtitle_{$subtitleFileName}"] = $subsetASSContent;
	            }
            }
            $subtitleArchive->close();
			fclose($uploadFile);
			$fontCount = count($fontArr);
            if ($fontCount <= 0) {
            	dieHTML("No font found!\n<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars(implode(',', $fontnameArr), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n");
            } else if ($fontCount > MaxDownloadFontCount) {
                dieHTML("Too many font!\n");
            }
			if ($isDownloadFont || $isDownloadSubtitle) {
				$currentFileType = ($isDownloadFont ? 'Font' : 'Subtitle');
				ob_end_clean();
				header('Content-Type: application/zip');
				header("Content-Disposition: attachment; filename=MDU-{$currentFileType}; filename*=utf-8''MDU-{$currentFileType}_" . rawurlencode($filename) . ".zip");
				$tmpArr = [];
				$archive = new ZipFile();
				$archive->setDoWrite();
				if ($isDownloadFont) {
					foreach ($fontArr as $font) {
						if (!is_file(FontPath . '/' . $font['fontfile'])) {
							continue;
						}
						AddFontDownloadHistory($uid, $font['id']);
						$archive->addFile(file_get_contents(FontPath . '/' . $font['fontfile']), $font['fontfile']);
						flush();
					}
				} else {
					foreach ($subsetASSFiles as $filename => $content) {
						$archive->addFile($content, $filename);
						flush();
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
	echo "<p>Fontcount: " . count($fontnameArr) . ", Fontname: " . htmlspecialchars($_GET['fontname'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) . "</p>\n";
	ShowTable($fontArr);
	HTMLEnd();
} else {
	dieHTML(":(\n");
}
?>
