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
		dieHTML("DB Error: {$e}.");
	}
	dieHTML("DB Error.");
	return false;
}
function Install() {
	global $db;
	if ($db === null) {
		return;
	}
	$db->exec("CREATE TABLE `download_history` (
		`id` bigint NOT NULL AUTO_INCREMENT,
		`source` varchar(11) NOT NULL,
		`user_id` int NOT NULL,
		`torrent_id` bigint NOT NULL,
		`download_id` bigint NOT NULL,
		`created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`) USING BTREE,
		UNIQUE KEY `index_unique` (`source`,`user_id`,`torrent_id`,`download_id`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
	$db->exec("CREATE TABLE `users` (
		`id` bigint NOT NULL AUTO_INCREMENT,
		`username` varchar(123) NOT NULL,
		`email` varchar(123) NOT NULL,
		`password` varchar(123) NOT NULL,
		`status` tinyint(1) DEFAULT 0,
		`created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`) USING BTREE,
		UNIQUE KEY `index_unique_username` (`username`) USING BTREE,
		UNIQUE KEY `index_unique_email` (`email`) USING BTREE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
	$db->exec("CREATE TABLE `fonts` (
		`id` bigint NOT NULL,
		`fontname` varchar(123) DEFAULT NULL,
		`fontfullname` varchar(123) DEFAULT NULL,
		`fontpsname` varchar(123) DEFAULT NULL,
		`fontsubfamily` varchar(123) DEFAULT NULL,
		`fontversion` varchar(123) DEFAULT NULL,
		KEY `index_id` (`id`),
		KEY `index_fontname` (`fontname`),
		KEY `index_fontfullname` (`fontfullname`),
		KEY `index_fontpsname` (`fontpsname`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
	$db->exec("CREATE TABLE `fonts_meta` (
		`id` bigint NOT NULL AUTO_INCREMENT,
		`uploader` int DEFAULT NULL,
		`fontfile` varchar(255) DEFAULT NULL,
		`fontsize` bigint DEFAULT NULL,
		`created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `index_unique` (`fontfile`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
}
?>
