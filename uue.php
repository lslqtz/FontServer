<?php
function UUEncode_ASS($filename, $newLine = true): string {
	$filesize = filesize($filename);
	if ($filesize <= 0) {
		return '';
	}
	static $b64Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
	static $assAlphabet = null;
	if ($assAlphabet === null) {
		$assAlphabet = '';
		for ($i = 0; $i < 64; $i++) $assAlphabet .= chr($i + 33);
	}
	$fileContent = file_get_contents($filename);
	$b64 = base64_encode($fileContent);
	unset($fileContent);
	$b64 = str_replace('=', '', $b64);
	$mapped = strtr($b64, $b64Alphabet, $assAlphabet);
	if ($newLine) {
		return wordwrap($mapped, 80, "\n", true);
	}
	return $mapped;
}
function UUDecode_ASS($filename): string {
	$filesize = filesize($filename);
	if ($filesize <= 0) {
		return '';
	}
	static $b64Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
	static $assAlphabet = null;
	if ($assAlphabet === null) {
		$assAlphabet = '';
		for ($i = 0; $i < 64; $i++) $assAlphabet .= chr($i + 33);
	}
	$fileContent = file_get_contents($filename);
	$fileContent = str_replace(["\n", "\r"], '', $fileContent);
	$mapped = strtr($fileContent, $assAlphabet, $b64Alphabet);
	unset($fileContent);
	return base64_decode($mapped);
}
?>
