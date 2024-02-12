<?php
function UUEncode_ASS($filename, $newLine = true): string {
	$filesize = filesize($filename);
	if ($filesize <= 0) {
		return '';
	}
	$retStr = '';
	$written = 0;
	$fileContent = file_get_contents($filename);
	for ($pos = 0; $pos < $filesize; $pos += 3) {
		$chunkSize = ($filesize - $pos);
		$src = [ord($fileContent[$pos]), ($chunkSize > 1 ? ord($fileContent[$pos+1]) : 0), ($chunkSize > 2 ? ord($fileContent[$pos+2]) : 0)];
		$dst = [($src[0] >> 2), ((($src[0] & 0x3) << 4) | (($src[1] & 0xF0) >> 4)), ((($src[1] & 0xF) << 2) | (($src[2] & 0xC0) >> 6)), ($src[2] & 0x3F)];
		for ($i = 0; $i <= min(3, $chunkSize); ++$i) {
			$retStr .= chr($dst[$i] + 33);
			if ($newLine && ++$written == 80 && $pos + 3 < $filesize) {
				$written = 0;
				$retStr .= "\n";
			}
		}
	}
	unset($fileContent);
	return $retStr;
}
function UUDecode_ASS($filename): string {
	$filesize = filesize($filename);
	if ($filesize <= 0) {
		return '';
	}
	$retStr = '';
	$fileContent = file_get_contents($filename);
	for ($pos = 0; $pos < $filesize;) {
		$byte = 0;
		$src = [0, 0, 0, 0];
		for ($i = 0; ($i <= 3 && $pos < $filesize); $pos++) {
			$char = $fileContent[$pos];
			if ($char !== "\n" && $char !== "\r") {
				$src[$i++] = (ord($char) - 33);
				++$byte;
			}
		}
		if ($byte > 1) {
			$retStr .= chr(($src[0] << 2) | ($src[1] >> 4));
		}
		if ($byte > 2) {
			$retStr .= chr((($src[1] & 0xF) << 4) | ($src[2] >> 2));
		}
		if ($byte > 3) {
			$retStr .= chr((($src[2] & 0x3) << 6) | ($src[3]));
		}
	}
	unset($fileContent);
	return $retStr;
}
?>
