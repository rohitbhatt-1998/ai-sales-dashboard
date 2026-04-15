-- AI Sales Calling Assistant Dashboard
-- Database Schema

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table: users (Admin authentication)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','agent') NOT NULL DEFAULT 'agent',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user: admin / admin123
INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
('Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- --------------------------------------------------------
-- Table: leads
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `company` VARCHAR(150) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('new','calling','connected','no_answer','called','hot','warm','cold') NOT NULL DEFAULT 'new',
  `score` INT(3) DEFAULT 0,
  `retry_count` INT(1) DEFAULT 0,
  `last_called_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: call_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `call_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lead_id` INT(11) NOT NULL,
  `call_sid` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('queued','calling','connected','no_answer','failed','completed') NOT NULL DEFAULT 'queued',
  `duration` INT(6) DEFAULT 0 COMMENT 'Duration in seconds',
  `transcript` TEXT DEFAULT NULL,
  `summary` TEXT DEFAULT NULL,
  `lead_score` ENUM('hot','warm','cold') DEFAULT NULL,
  `attempt` INT(1) DEFAULT 1,
  `started_at` DATETIME DEFAULT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lead_id` (`lead_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_call_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: call_queue
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `call_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lead_id` INT(11) NOT NULL,
  `priority` INT(3) DEFAULT 5,
  `status` ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  `attempt` INT(1) DEFAULT 1,
  `scheduled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_queue_lead` (`lead_id`),
  KEY `idx_queue_status` (`status`),
  CONSTRAINT `fk_queue_lead_id` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: ai_config
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `config_key` VARCHAR(100) NOT NULL,
  `config_value` TEXT NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default AI Configuration
INSERT INTO `ai_config` (`config_key`, `config_value`) VALUES
('language_style', 'english'),
('tone', 'friendly'),
('opening_script', 'Hello! This is an AI assistant calling on behalf of our sales team. Am I speaking with {{lead_name}}?'),
('question_flow', 'Are you currently looking for solutions in our space?\nWhat is your biggest challenge right now?\nWould you be interested in learning how we can help?\nWhen would be a good time to schedule a proper call?'),
('closing_statement', 'Thank you for your time {{lead_name}}! Our representative will follow up with you shortly. Have a great day!'),
('max_retries', '2'),
('call_delay_seconds', '5'),
('ai_provider', 'openrouter'),
('ai_api_key', ''),
('twilio_account_sid', ''),
('twilio_auth_token', ''),
('twilio_phone_number', '');
