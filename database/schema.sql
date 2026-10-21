-- ============================================================
-- Sport Management System - database schema + demo seed data
-- Import:  mysql -u root < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sport_management`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `sport_management`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_room_members`;
DROP TABLE IF EXISTS `chat_rooms`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `player_welfare`;
DROP TABLE IF EXISTS `player_metrics`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `players`;
DROP TABLE IF EXISTS `tournaments`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `teams`;
-- NOTE: foreign key checks stay disabled for the whole import. The seed file
-- contains forward references (e.g. player_metrics.recorded_by points at a
-- user inserted further down) so it is only order-independent with checks off.
-- They are re-enabled at the end of this file.

-- ------------------------------------------------------------
-- Teams
-- ------------------------------------------------------------
CREATE TABLE `teams` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120) NOT NULL,
    `code`         VARCHAR(8)   NOT NULL,
    `coach`        VARCHAR(120) DEFAULT NULL,
    `city`         VARCHAR(100) DEFAULT NULL,
    `founded_year` SMALLINT     DEFAULT NULL,
    `logo`         VARCHAR(255) DEFAULT NULL,
    `description`  TEXT         DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `teams_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teams` (`name`, `code`, `coach`, `city`, `founded_year`, `description`) VALUES
('1000 Hills Rugby',      '1HR', 'Jean Nsengimana',  'Kigali', 2014, 'Founded in 2014, T'),
('Lions De fer',      'LDF', 'Marcus Webb',  'Kigali',     2012, 'A disciplined side built on defensive solidity and set-piece excellence.'),
('Kigali Sharks',  'KS', 'Kamanada',  'Kigali',   2011, 'Fast');

-- ------------------------------------------------------------
-- Users (administrators + team staff)
-- role:  admin   -> full access to everything
--        manager -> team staff, scoped to a single team
-- ------------------------------------------------------------
CREATE TABLE `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(190) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','manager','player') NOT NULL DEFAULT 'manager',
    `team_id`    INT UNSIGNED DEFAULT NULL,
    `phone`      VARCHAR(30)  DEFAULT NULL,
    `photo`      VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_team_id_index` (`team_id`),
    CONSTRAINT `users_team_id_foreign`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo accounts (password for all: password)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `team_id`, `phone`) VALUES
('Administrator', 'admin@sport.test',       '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'admin',   NULL, '+250 782 407 887');

-- ------------------------------------------------------------
-- Players (profile, statistics + optional uploaded photo)
-- ------------------------------------------------------------
CREATE TABLE `players` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id`         INT UNSIGNED NOT NULL,
    `first_name`      VARCHAR(80)  NOT NULL,
    `last_name`       VARCHAR(80)  NOT NULL,
    `jersey_number`   TINYINT UNSIGNED DEFAULT NULL,
    `position`        VARCHAR(40)     DEFAULT NULL,
    `date_of_birth`   DATE            DEFAULT NULL,
    `nationality`     VARCHAR(80)     DEFAULT NULL,
    `foot`            ENUM('left','right','both') DEFAULT NULL,
    `photo`           VARCHAR(255)    DEFAULT NULL,
    -- statistics
    `goals`           INT UNSIGNED NOT NULL DEFAULT 0,
    `assists`         INT UNSIGNED NOT NULL DEFAULT 0,
    `appearances`     INT UNSIGNED NOT NULL DEFAULT 0,
    `minutes_played`  INT UNSIGNED NOT NULL DEFAULT 0,
    `yellow_cards`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `red_cards`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `rating`          DECIMAL(3,2)  DEFAULT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `players_team_id_index` (`team_id`),
    CONSTRAINT `players_team_id_foreign`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `players`
    (`team_id`, `first_name`, `last_name`, `jersey_number`, `position`, `date_of_birth`, `nationality`, `foot`,
     `goals`, `assists`, `appearances`, `minutes_played`, `yellow_cards`, `red_cards`, `rating`) VALUES
-- Thunder FC
(1, 'James',  'Carter',   9,  'Forward',     '2001-04-12', 'English',    'right', 7, 2, 8, 690, 1, 0, 8.24);

-- ------------------------------------------------------------
-- Physical metrics history (fitness tests)
-- ------------------------------------------------------------
CREATE TABLE `player_metrics` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`   INT UNSIGNED NOT NULL,
    `test_date`   DATE         NOT NULL,
    `height_cm`   DECIMAL(5,1) DEFAULT NULL,
    `weight_kg`   DECIMAL(5,1) DEFAULT NULL,
    `top_speed`   DECIMAL(4,1) DEFAULT NULL,   -- km/h
    `stamina`     TINYINT UNSIGNED DEFAULT NULL,  -- %
    `strength`    TINYINT UNSIGNED DEFAULT NULL,  -- %
    `agility`     TINYINT UNSIGNED DEFAULT NULL,  -- %
    `body_fat`    TINYINT UNSIGNED DEFAULT NULL,  -- %
    `fitness`     ENUM('excellent','good','fair','poor','injured') NOT NULL DEFAULT 'good',
    `notes`       TEXT         DEFAULT NULL,
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `player_metrics_player_id_index` (`player_id`),
    CONSTRAINT `metrics_player_id_foreign`
        FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
    CONSTRAINT `metrics_recorded_by_foreign`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `player_metrics`
    (`player_id`, `test_date`, `height_cm`, `weight_kg`, `top_speed`, `stamina`, `strength`, `agility`, `body_fat`, `fitness`, `notes`, `recorded_by`) VALUES
(1,  '2026-10-05', 183.0, 78.5, 34.2, 88, 74, 81, 11, 'excellent', 'Peak condition ahead of the derby.', 3);

-- ------------------------------------------------------------
-- Mental / welfare assessments (1-10 scales)
-- ------------------------------------------------------------
CREATE TABLE `player_welfare` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`       INT UNSIGNED NOT NULL,
    `assessment_date` DATE         NOT NULL,
    `confidence`      TINYINT UNSIGNED NOT NULL,  -- 1-10
    `focus`           TINYINT UNSIGNED NOT NULL,  -- 1-10
    `motivation`      TINYINT UNSIGNED NOT NULL,  -- 1-10
    `stress`          TINYINT UNSIGNED NOT NULL,  -- 1-10 (higher = more stress)
    `wellbeing`       ENUM('great','good','okay','low','struggling') NOT NULL DEFAULT 'good',
    `notes`           TEXT         DEFAULT NULL,
    `assessed_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `player_welfare_player_id_index` (`player_id`),
    CONSTRAINT `welfare_player_id_foreign`
        FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
    CONSTRAINT `welfare_assessed_by_foreign`
        FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `player_welfare`
    (`player_id`, `assessment_date`, `confidence`, `focus`, `motivation`, `stress`, `wellbeing`, `notes`, `assessed_by`) VALUES
(1,  '2026-10-10', 9, 9, 10, 3, 'great',  'In the form of his life, leading the scoring chart.', 3);

-- ------------------------------------------------------------
-- Tournaments
-- ------------------------------------------------------------
CREATE TABLE `tournaments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `sport`       VARCHAR(60)  NOT NULL DEFAULT 'Football',
    `start_date`  DATE         DEFAULT NULL,
    `end_date`    DATE         DEFAULT NULL,
    `status`      ENUM('upcoming','ongoing','completed') NOT NULL DEFAULT 'upcoming',
    `description` TEXT         DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tournaments` (`name`, `sport`, `start_date`, `end_date`, `status`, `description`) VALUES
('Rwanda Rugby National League 2026', 'Rugby', '2026-10-03', '2026-12-20', 'ongoing',
 'The main league competition of the season. Every team plays each other twice, home and away.'),
('GMT Cup 2027',          'Rugby', '2027-07-20', '2027-07-21', 'upcoming',
 'Knockout cup played during the Summer break.');

-- ------------------------------------------------------------
-- Matches
-- ------------------------------------------------------------
CREATE TABLE `matches` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tournament_id` INT UNSIGNED DEFAULT NULL,
    `home_team_id`  INT UNSIGNED NOT NULL,
    `away_team_id`  INT UNSIGNED NOT NULL,
    `home_score`    SMALLINT UNSIGNED DEFAULT NULL,
    `away_score`    SMALLINT UNSIGNED DEFAULT NULL,
    `match_date`    DATETIME     DEFAULT NULL,
    `venue`         VARCHAR(150) DEFAULT NULL,
    `status`        ENUM('scheduled','live','finished') NOT NULL DEFAULT 'scheduled',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `matches_tournament_id_index` (`tournament_id`),
    KEY `matches_home_team_id_index` (`home_team_id`),
    KEY `matches_away_team_id_index` (`away_team_id`),
    CONSTRAINT `matches_tournament_id_foreign`
        FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `matches_home_team_id_foreign`
        FOREIGN KEY (`home_team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `matches_away_team_id_foreign`
        FOREIGN KEY (`away_team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Premier Championship 2026 (tournament 1)
INSERT INTO `matches`
    (`tournament_id`, `home_team_id`, `away_team_id`, `home_score`, `away_score`, `match_date`, `venue`, `status`) VALUES
(1, 1, 2,  2, 1, '2026-08-15 18:00:00', 'National Arena', 'finished');



-- ------------------------------------------------------------
-- Calendar events (training, meetings, travel, deadlines, ...)
-- team_id NULL = league-wide event visible to everyone
-- ------------------------------------------------------------
CREATE TABLE `events` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(150) NOT NULL,
    `type`          ENUM('training','meeting','travel','deadline','media','other') NOT NULL DEFAULT 'training',
    `starts_at`     DATETIME     NOT NULL,
    `team_id`       INT UNSIGNED DEFAULT NULL,
    `tournament_id` INT UNSIGNED DEFAULT NULL,
    `description`   TEXT         DEFAULT NULL,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `events_team_id_index` (`team_id`),
    KEY `events_starts_at_index` (`starts_at`),
    CONSTRAINT `events_team_id_foreign`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `events_tournament_id_foreign`
        FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `events_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `events` (`title`, `type`, `starts_at`, `team_id`, `tournament_id`, `description`, `created_by`) VALUES
('Squad training - tactics session', 'training', '2026-10-06 10:00:00', 1, NULL, 'Video analysis followed by pitch work.', 2);

-- ------------------------------------------------------------
-- Player login accounts (v3): link a `player` user to a players row.
-- Added after `players` exists so the foreign key can be created.
-- ------------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `player_id` INT UNSIGNED DEFAULT NULL AFTER `team_id`,
    ADD KEY `users_player_id_index` (`player_id`),
    ADD CONSTRAINT `users_player_id_foreign`
        FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE;

-- Demo player login for James Carter (Thunder FC, player 1). Password: password
INSERT INTO `users` (`name`, `email`, `password`, `role`, `team_id`, `player_id`, `phone`) VALUES
('Gasore Alain', 'alainvalentin@gmail.com', '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'player', 1, 1, '+44 161 496 0010');

-- ------------------------------------------------------------
-- Attendance: a player records presence for a training session,
-- optionally attaching a check-in photo (selfie). One row per
-- player per session (enforced by the unique key).
-- ------------------------------------------------------------
CREATE TABLE `attendance` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id`   INT UNSIGNED NOT NULL,
    `player_id`  INT UNSIGNED NOT NULL,
    `status`     ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `photo`      VARCHAR(255) DEFAULT NULL,
    `notes`      TEXT         DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `attendance_event_player_unique` (`event_id`,`player_id`),
    KEY `attendance_player_id_index` (`player_id`),
    CONSTRAINT `attendance_event_id_foreign`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `attendance_player_id_foreign`
        FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Chat: a team room per team (everyone in that team can post) plus
-- admin-created group rooms with explicit membership.
-- ------------------------------------------------------------
CREATE TABLE `chat_rooms` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `type`       ENUM('team','group') NOT NULL DEFAULT 'team',
    `team_id`    INT UNSIGNED DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `chat_rooms_team_id_index` (`team_id`),
    CONSTRAINT `chat_rooms_team_id_foreign`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chat_rooms_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_room_members` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `chat_room_members_unique` (`room_id`,`user_id`),
    CONSTRAINT `chat_member_room_foreign`
        FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chat_member_user_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `body`       TEXT         NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `chat_messages_room_id_index` (`room_id`),
    CONSTRAINT `chat_message_room_foreign`
        FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chat_message_user_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team chat room for every club.
INSERT INTO `chat_rooms` (`name`, `type`, `team_id`)
    SELECT CONCAT(t.name, ' Chat'), 'team', t.id FROM `teams` t;

-- Admin-created group room with two members (admin + Thunder FC manager).
INSERT INTO `chat_rooms` (`name`, `type`, `team_id`, `created_by`)
    VALUES ('Captain''s Council', 'group', NULL, 1);
INSERT INTO `chat_room_members` (`room_id`, `user_id`)
    SELECT r.id, u.id FROM `chat_rooms` r CROSS JOIN `users` u
    WHERE r.type = 'group' AND r.name = 'Captain''s Council' AND u.email IN ('admin@sport.test', 'manager@sport.test');

-- Demo messages in the Thunder FC team chat.
INSERT INTO `chat_messages` (`room_id`, `user_id`, `body`)
    SELECT r.id, u.id, 'Welcome to the Thunder FC team chat, lads.' FROM `chat_rooms` r CROSS JOIN `users` u
    WHERE r.type = 'team' AND r.team_id = 1 AND u.email = 'manager@sport.test';
INSERT INTO `chat_messages` (`room_id`, `user_id`, `body`)
    SELECT r.id, u.id, 'Thanks gaffer - ready for tomorrow''s session.' FROM `chat_rooms` r CROSS JOIN `users` u
    WHERE r.type = 'team' AND r.team_id = 1 AND u.email = 'james@sport.test';

SET FOREIGN_KEY_CHECKS = 1;
