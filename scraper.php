<?php
if (PHP_SAPI !== 'cli') { die(); }
set_time_limit(0);
ini_set('memory_limit', '1024M');
require_once('config.php');
require_once('fontFile.php');
function LogStr(string $message, int $status = 0) {
	$logType = ($status === -1 ? '错误' : '信息');
	$date = date('Y-m-d');
	$time = date('H:i:s');
	$logStr = "[{$date} {$time}][{$logType}] {$message}.\n";
	echo $logStr;
}
if (!is_dir('font_import')) {
	LogStr('找不到 font_import 目录', -1);
	die();
}
$fontfiles = GetAllFontsFilename('font_import');
foreach ($fontfiles as $fontfile) {
	gc_collect_cycles();
	$fontPath = $fontfile->getPathname();
	list($addFontErr, $addFontMsg, $addFontAdditionMsgList) = AddFontFile($fontPath, true);
	foreach ($addFontAdditionMsgList as $addFontAdditionMsg) {
		LogStr($addFontAdditionMsg[1], $addFontAdditionMsg[0]);
	}
	if (!empty($addFontMsg)) {
		LogStr($addFontMsg, $addFontErr);
	}
}
?>
