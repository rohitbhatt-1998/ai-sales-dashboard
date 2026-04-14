-- ============================================================
-- AI Sales Calling Assistant Dashboard v1.0
-- Database Schema
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ----------------------------------------------------------------
-- Users
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `email`        VARCHAR(255) NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `role`         ENUM('admin','agent') NOT NULL DEFAULT 'admin',
  `is_active`    TINYINT(1)  NOT NULL DEFAULT 1,
  `last_login`   TIMESTAMP   NULL DEFAULT NULL,
  `created_at`   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Leads
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100) NOT NULL,
  `phone`          VARCHAR(20)  NOT NULL,
  `email`          VARCHAR(255) DEFAULT NULL,
  `city`           VARCHAR(100) DEFAULT NULL,
  `company`        VARCHAR(100) DEFAULT NULL,
  `status`         ENUM('New','Called','Hot','Warm','Cold') NOT NULL DEFAULT 'New',
  `score`          INT(11)     NOT NULL DEFAULT 0,
  `notes`          TEXT        DEFAULT NULL,
  `call_count`     INT(11)     NOT NULL DEFAULT 0,
  `last_called_at` TIMESTAMP   NULL DEFAULT NULL,
  `created_by`     INT(11)     DEFAULT NULL,
  `created_at`     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_city`   (`city`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_leads_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Calls
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calls` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `lead_id`        INT(11)     NOT NULL,
  `status`         ENUM('Queued','Calling','Connected','No Answer','Completed','Failed','Busy','Voicemail')
                               NOT NULL DEFAULT 'Queued',
  `duration`       INT(11)     NOT NULL DEFAULT 0,
  `transcript`     TEXT        DEFAULT NULL,
  `summary`        TEXT        DEFAULT NULL,
  `ai_score`       INT(11)     NOT NULL DEFAULT 0,
  `sentiment`      ENUM('positive','neutral','negative') DEFAULT NULL,
  `attempt`        INT(11)     NOT NULL DEFAULT 1,
  `call_direction` ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
  `twilio_call_sid` VARCHAR(100) DEFAULT NULL,
  `recording_url`  VARCHAR(500) DEFAULT NULL,
  `notes`          TEXT        DEFAULT NULL,
  `started_at`     TIMESTAMP   NULL DEFAULT NULL,
  `ended_at`       TIMESTAMP   NULL DEFAULT NULL,
  `created_at`     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_id`   (`lead_id`),
  KEY `idx_status`    (`status`),
  KEY `idx_created`   (`created_at`),
  CONSTRAINT `fk_calls_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Call Queue
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `call_queue` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `lead_id`      INT(11)     NOT NULL,
  `call_id`      INT(11)     DEFAULT NULL,
  `priority`     INT(11)     NOT NULL DEFAULT 0,
  `attempt`      INT(11)     NOT NULL DEFAULT 1,
  `max_attempts` INT(11)     NOT NULL DEFAULT 2,
  `status`       ENUM('Pending','Processing','Done','Failed','Skipped') NOT NULL DEFAULT 'Pending',
  `fail_reason`  VARCHAR(255) DEFAULT NULL,
  `scheduled_at` TIMESTAMP   NULL DEFAULT NULL,
  `processed_at` TIMESTAMP   NULL DEFAULT NULL,
  `created_at`   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_id`   (`lead_id`),
  KEY `idx_status`    (`status`),
  KEY `idx_scheduled` (`scheduled_at`),
  CONSTRAINT `fk_queue_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_queue_call` FOREIGN KEY (`call_id`) REFERENCES `calls`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- AI Configuration (key-value store)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_config` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `key_name`    VARCHAR(100) NOT NULL,
  `value`       TEXT        DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ----------------------------------------------------------------
-- Seed Data
-- ----------------------------------------------------------------

-- Default AI configuration
INSERT INTO `ai_config` (`key_name`, `value`, `description`) VALUES
('language_style',    'English',    'Language style for AI calls'),
('tone',              'friendly',   'Tone of AI conversations'),
('opening_script',    'Hello! My name is Alex and I am calling from TechSales Inc. May I speak with [Lead Name]? I hope this is a good time. We have an exciting solution that could help you significantly.', 'Opening script'),
('question_flow',     'Q1: What are your current challenges in your business?\nQ2: Have you tried any similar solutions before?\nQ3: What is your approximate budget for this?\nQ4: How soon are you looking to implement?', 'Question flow'),
('closing_statement', 'Thank you so much for your time today! Based on our conversation, I believe our solution is a great fit. I will send you a detailed proposal via email within 24 hours. Have a wonderful day!', 'Closing statement'),
('retry_attempts',    '2',          'Max retry attempts for failed calls'),
('call_interval',     '5',          'Seconds between bulk calls'),
('simulation_mode',   '1',          'Use simulation when API keys are not configured'),
('twilio_sid',        '',           'Twilio Account SID'),
('twilio_token',      '',           'Twilio Auth Token'),
('twilio_from',       '',           'Twilio phone number (E.164 format)'),
('openai_api_key',    '',           'OpenAI API key for summaries'),
('webhook_url',       '',           'Public URL for Twilio webhooks');
