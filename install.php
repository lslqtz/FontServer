<?php
if (PHP_SAPI !== 'cli') { die(); }
require_once('config.php');
require_once('mysql.php');
ConnectDB();
Install();
?>
