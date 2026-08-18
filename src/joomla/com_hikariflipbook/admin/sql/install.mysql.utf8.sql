CREATE TABLE IF NOT EXISTS `#__hikariflipbook_books` (
	`id` int NOT NULL AUTO_INCREMENT,
	`title` varchar(255) NOT NULL DEFAULT '',
	`path` varchar(1024) NOT NULL DEFAULT '',
	`params` mediumtext,
	`published` tinyint NOT NULL DEFAULT 1,
	`access` int unsigned NOT NULL DEFAULT 1,
	`ordering` int NOT NULL DEFAULT 0,
	`language` char(7) NOT NULL DEFAULT '*',
	`checked_out` int unsigned DEFAULT NULL,
	`checked_out_time` datetime DEFAULT NULL,
	`created` datetime DEFAULT NULL,
	`created_by` int unsigned NOT NULL DEFAULT 0,
	`modified` datetime DEFAULT NULL,
	`modified_by` int unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_state` (`published`),
	KEY `idx_access` (`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
