<?php
require_once('config.php');
require_once('PHPMailer/src/PHPMailer.php');
require_once('PHPMailer/src/Exception.php');
require_once('PHPMailer/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function SendMail(string $receiver, string $subject, string $content): bool {
	$mail = new PHPMailer(true);
	try {
		$mail->isSMTP();
		$mail->Host = SMTPAddress;
		$mail->Port = SMTPPort;
		$mail->SMTPAuth = true;
		$mail->Username = SMTPUsername;
		$mail->Password = SMTPPassword;

		if (SMTPSSL) {
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
		}

		$mail->CharSet = PHPMailer::CHARSET_UTF8;

		$mail->setFrom(SMTPUsername, Title);
		$mail->addAddress($receiver);

		$mail->isHTML(false);
		$mail->Subject = $subject;
		$mail->Body = $content;

		$mail->send();

		return true;
	} catch (Throwable $e) {
		return false;
	}
}
?>
