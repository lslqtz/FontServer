<?php
global $db;
$db = null;
function ConnectDB(): bool {
	global $db;
	if ($db !== null) {
		return true;
	}
	try {
		$db = new PDO(DBAddress, DBUsername, DBPassword, (DBPersistent ? [PDO::ATTR_PERSISTENT => true] : []));
		if ($db !== null) {
			return true;
		}
	} catch (Throwable $e) {
		dieHTML("DB Error: {$e}\n");
	}
	return false;
}
function Install() {
	global $db;
	if ($db === null) {
		return;
	}
	$db->exec("CREATE TABLE `download_history` (
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`user_id` int(11) NOT NULL,
		`download_id` bigint(20) NOT NULL,
		`created_at` timestamp NULL DEFAULT current_timestamp(),
		`updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
		PRIMARY KEY (`id`),
		UNIQUE KEY `index_unique` (`user_id`, `download_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
	$db->exec("CREATE TABLE `fonts` (
		`id` bigint(20) NOT NULL,
		`fontname` varchar(123) DEFAULT NULL,
		`fontfullname` varchar(123) DEFAULT NULL,
		`fontpsname` varchar(123) DEFAULT NULL,
		`fontsubfamily` varchar(123) DEFAULT NULL,
		KEY `index_id` (`id`),
		UNIQUE KEY `index_unique` (`fontname`, `fontfullname`, `fontpsname`, `fontsubfamily`) USING HASH
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
	$db->exec("CREATE TABLE `fonts_meta` (
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`uploader` int(11) DEFAULT NULL,
		`fontfile` varchar(255) DEFAULT NULL,
		`fontsize` bigint(20) DEFAULT NULL,
		`created_at` timestamp NULL DEFAULT current_timestamp(),
		PRIMARY KEY (`id`),
		UNIQUE KEY `index_unique` (`fontfile`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
?>
