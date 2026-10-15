-- IRIA 2027 Abstract Review Portal
-- Compatible with MySQL 5.7+
-- Does NOT modify the existing `abstract` table.

CREATE TABLE IF NOT EXISTS `review_admin` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reviewers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_reviewer_email` (`email`), KEY `idx_reviewer_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `review_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `abstract_id` int NOT NULL,
  `reviewer_id` int unsigned NOT NULL,
  `assigned_by` int unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `review_deadline` datetime DEFAULT NULL,
  `status` enum('pending','under_review','accepted','rejected','resubmit','cannot_review') NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `opened_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), KEY `idx_assignment_abstract` (`abstract_id`), KEY `idx_assignment_reviewer` (`reviewer_id`), KEY `idx_assignment_status` (`status`), KEY `idx_assignment_active` (`is_active`), KEY `idx_assignment_deadline` (`review_deadline`),
  CONSTRAINT `fk_assignment_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `reviewers` (`id`),
  CONSTRAINT `fk_assignment_admin` FOREIGN KEY (`assigned_by`) REFERENCES `review_admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `abstract_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint unsigned NOT NULL,
  `abstract_id` int NOT NULL,
  `reviewer_id` int unsigned NOT NULL,
  `decision` enum('pending','under_review','accepted','rejected','resubmit','cannot_review') NOT NULL DEFAULT 'pending',
  `reviewer_comment` text,
  `is_final` tinyint(1) NOT NULL DEFAULT '0',
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_assignment_review` (`assignment_id`), KEY `idx_review_abstract` (`abstract_id`), KEY `idx_review_reviewer` (`reviewer_id`), KEY `idx_review_decision` (`decision`),
  CONSTRAINT `fk_review_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `review_assignments` (`id`),
  CONSTRAINT `fk_review_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `reviewers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `review_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `abstract_id` int DEFAULT NULL,
  `assignment_id` bigint unsigned DEFAULT NULL,
  `actor_type` enum('admin','reviewer','system') NOT NULL,
  `actor_id` int unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `old_value` text,
  `new_value` text,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_log_abstract` (`abstract_id`), KEY `idx_log_assignment` (`assignment_id`), KEY `idx_log_actor` (`actor_type`,`actor_id`), KEY `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `review_password_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reviewer_id` int unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_reset_reviewer` (`reviewer_id`), KEY `idx_reset_expiry` (`expires_at`),
  CONSTRAINT `fk_reset_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `reviewers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `review_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `review_settings` (`setting_key`,`setting_value`,`updated_at`) VALUES
('conference_name','IRIA 2027 Delhi',NOW()),
('portal_name','Abstract Review Portal',NOW()),
('review_reminder_enabled','1',NOW())
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`),`updated_at`=NOW();
