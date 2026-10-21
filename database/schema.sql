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
SET FOREIGN_KEY_CHECKS = 1;

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
('Thunder FC',      'THU', 'Alex Turner',  'Manchester', 1889, 'Founded in 1889, Thunder FC is known for its high-pressing style and passionate home support at the National Arena.'),
('Iron Lions',      'IRL', 'Marcus Webb',  'London',     1902, 'A disciplined side built on defensive solidity and set-piece excellence.'),
('Coastal Sharks',  'CSH', 'Daniel Cruz',  'Brighton',   1948, 'Fast, technical football played on the south coast since 1948.'),
('Royal Strikers',  'RST', 'Henry Cole',   'Leeds',      1912, 'Historic club with a reputation for developing young talent.'),
('Green Rangers',   'GRN', 'Owen Blake',   'Glasgow',    1921, 'Attacking football and a fierce derby rivalry define the Rangers.'),
('Desert Falcons',  'DFL', 'Karim Haddad', 'Dubai',      1975, 'The youngest club in the league, rising quickly through the ranks.'),
('Arctic Wolves',   'AWL', 'Erik Nilsen',  'Oslo',       1966, 'Winter-hardened squad with exceptional fitness levels.'),
('Silver Eagles',   'SEG', 'Victor Ramos', 'Madrid',     1930, 'Possession-based football with three league titles to their name.');

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
('Administrator', 'admin@sport.test',       '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'admin',   NULL, '+44 20 7946 0000'),
('Alex Turner',   'manager@sport.test',     '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'manager', 1,    '+44 161 496 0001'),
('Marcus Webb',   'irl.staff@sport.test',   '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'manager', 2,    '+44 20 7946 0002'),
('Sara Nolan',    'fitness@sport.test',     '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'manager', 1,    '+44 161 496 0002');

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
(1, 'James',  'Carter',   9,  'Forward',     '1998-04-12', 'English',    'right', 7, 2, 8, 690, 1, 0, 8.24),
(1, 'Liam',   'Harrison', 8,  'Midfielder',  '1996-09-03', 'English',    'both',  2, 5, 8, 712, 2, 0, 7.65),
(1, 'Mateo',  'Silva',    1,  'Goalkeeper',  '1994-01-27', 'Portuguese', 'right', 0, 0, 8, 720, 0, 0, 7.40),
-- Iron Lions
(2, 'Noah',   'Bennett',  10, 'Forward',     '1997-06-19', 'English',    'left',  4, 1, 7, 601, 2, 0, 7.55),
(2, 'Ethan',  'Wright',   6,  'Midfielder',  '1999-11-08', 'English',    'right', 1, 4, 7, 588, 3, 1, 7.10),
(2, 'Kofi',   'Mensah',   4,  'Defender',    '1995-03-22', 'Ghanaian',   'right', 0, 1, 7, 630, 1, 0, 7.25),
-- Coastal Sharks
(3, 'Daniel', 'Moreno',   11, 'Forward',     '2000-02-14', 'Spanish',    'left',  5, 3, 7, 574, 0, 0, 7.90),
(3, 'Jack',   'Robinson', 5,  'Defender',    '1993-07-30', 'English',    'right', 1, 0, 7, 645, 2, 0, 7.05),
(3, 'Sami',   'Haddad',   7,  'Midfielder',  '1998-12-01', 'Tunisian',   'both',  2, 6, 7, 660, 1, 0, 7.80),
-- Royal Strikers
(4, 'Oliver', 'Grant',    9,  'Forward',     '1996-05-16', 'English',    'right', 3, 2, 7, 590, 1, 0, 7.35),
(4, 'Hugo',   'Martinez', 8,  'Midfielder',  '1999-08-25', 'Argentine',  'left',  1, 3, 7, 615, 0, 0, 7.20),
(4, 'Callum', 'Reid',     2,  'Defender',    '1994-10-09', 'Scottish',   'right', 0, 0, 7, 630, 3, 0, 6.95),
-- Green Rangers
(5, 'Scott',  'MacLeod',  7,  'Midfielder',  '1997-01-11', 'Scottish',   'left',  4, 4, 7, 605, 1, 0, 7.75),
(5, 'Ben',    'Fletcher', 9,  'Forward',     '1995-04-02', 'English',    'right', 6, 1, 7, 585, 2, 0, 8.05),
(5, 'Nikola', 'Ivanovic', 1,  'Goalkeeper',  '1992-09-18', 'Serbian',    'right', 0, 0, 7, 630, 0, 0, 7.15),
-- Desert Falcons
(6, 'Omar',   'Farouk',    10, 'Forward',     '1999-03-07', 'Egyptian',   'right', 5, 2, 6, 495, 1, 0, 7.85),
(6, 'Yousef', 'Al-Amari',  6,  'Midfielder',  '2001-06-21', 'Emirati',    'left',  2, 5, 6, 510, 2, 0, 7.45),
(6, 'Rashid', 'Khan',      3,  'Defender',    '1996-11-29', 'Pakistani',  'right', 0, 1, 6, 540, 2, 1, 6.90),
-- Arctic Wolves
(7, 'Lars',   'Johansen',  9,  'Forward',     '1994-02-26', 'Norwegian',  'left',  3, 1, 6, 500, 0, 0, 7.30),
(7, 'Emil',   'Larsson',   8,  'Midfielder',  '1998-07-14', 'Swedish',    'right', 2, 4, 6, 525, 1, 0, 7.40),
(7, 'Jonas',  'Berg',      5,  'Defender',    '1993-12-05', 'Norwegian',  'right', 1, 0, 6, 540, 2, 0, 7.10),
-- Silver Eagles
(8, 'Diego',  'Fernandez', 11, 'Forward',     '1997-08-31', 'Spanish',    'left',  6, 3, 7, 580, 1, 0, 8.10),
(8, 'Carlos', 'Vega',      7,  'Midfielder',  '1995-05-23', 'Mexican',    'right', 3, 7, 7, 640, 0, 0, 7.95),
(8, 'Adrian', 'Ruiz',      4,  'Defender',    '1992-10-17', 'Spanish',    'right', 1, 1, 7, 630, 2, 0, 7.20);

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
(1,  '2026-10-05', 183.0, 78.5, 34.2, 88, 74, 81, 11, 'excellent', 'Peak condition ahead of the derby.', 3),
(2,  '2026-10-05', 178.0, 73.0, 32.8, 91, 68, 84, 10, 'excellent', null, 3),
(3,  '2026-10-05', 191.0, 84.0, 27.5, 79, 82, 62, 13, 'good', null, 3),
(4,  '2026-10-06', 180.0, 76.0, 33.5, 84, 71, 78, 12, 'good', null, NULL),
(7,  '2026-10-06', 176.0, 70.5, 35.1, 90, 62, 88, 9,  'excellent', 'Fastest sprint recorded this season.', NULL),
(10, '2026-10-07', 185.0, 79.0, 31.9, 82, 76, 70, 12, 'good', null, NULL),
(13, '2026-10-07', 181.0, 77.5, 33.0, 86, 70, 79, 11, 'good', null, NULL),
(16, '2026-10-08', 179.0, 74.0, 34.8, 87, 66, 85, 10, 'excellent', null, NULL),
(19, '2026-10-08', 188.0, 86.0, 26.9, 71, 88, 58, 15, 'fair', 'Recovering from hamstring strain - light program.', 3),
(22, '2026-10-09', 177.0, 72.0, 33.9, 89, 65, 83, 10, 'injured', 'Ankle sprain, out for approx. 3 weeks.', NULL);

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
(1,  '2026-10-10', 9, 9, 10, 3, 'great',  'In the form of his life, leading the scoring chart.', 3),
(2,  '2026-10-10', 8, 8, 9,  4, 'great',  null, 3),
(4,  '2026-10-11', 7, 7, 8,  5, 'good',   'Slight dip after the draw, but responding well.', NULL),
(5,  '2026-10-11', 6, 6, 7,  7, 'okay',   'Frustrated by the red card suspension - keep him engaged.', NULL),
(7,  '2026-10-12', 9, 8, 9,  3, 'great',  null, 3),
(13, '2026-10-12', 8, 7, 8,  4, 'good',   null, NULL),
(19, '2026-10-13', 5, 6, 6,  8, 'low',    'Injury layoff is affecting mood - schedule a check-in.', 3),
(22, '2026-10-13', 4, 5, 5,  9, 'struggling', 'Long-term injury, needs psychological support.', 3);

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
('Premier Championship 2026', 'Football', '2026-08-01', '2026-12-20', 'ongoing',
 'The main league competition of the season. Every team plays each other twice, home and away.'),
('Winter Cup 2027',          'Football', '2027-01-10', '2027-02-28', 'upcoming',
 'Knockout cup played during the winter break.'),
('Autumn Series 2025',       'Football', '2025-08-15', '2025-11-30', 'completed',
 'Pre-season league contested by six clubs. Archive of last year''s results.');

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
(1, 1, 2,  2, 1, '2026-08-15 18:00:00', 'National Arena', 'finished'),
(1, 3, 4,  0, 0, '2026-08-22 17:30:00', 'Coastal Stadium', 'finished'),
(1, 5, 6,  3, 2, '2026-09-05 19:00:00', 'Rangers Park', 'finished'),
(1, 7, 8,  1, 2, '2026-09-19 18:00:00', 'Frost Bowl', 'finished'),
(1, 2, 3,  2, 2, '2026-10-03 17:00:00', 'Iron Arena', 'finished'),
(1, 4, 5,  1, 0, '2026-10-11 19:30:00', 'Royal Field', 'finished'),
(1, 1, 8,  4, 1, '2026-10-18 18:00:00', 'National Arena', 'finished'),
(1, 6, 7,  2, 1, '2026-10-20 20:00:00', 'Falcon Dome', 'live'),
(1, 3, 1, NULL, NULL, '2026-10-25 17:30:00', 'Coastal Stadium', 'scheduled'),
(1, 2, 5, NULL, NULL, '2026-11-01 18:00:00', 'Iron Arena', 'scheduled'),
(1, 8, 4, NULL, NULL, '2026-11-08 19:00:00', 'Eagle Nest', 'scheduled'),
(1, 7, 1, NULL, NULL, '2026-11-15 18:00:00', 'Frost Bowl', 'scheduled');

-- Winter Cup 2027 (tournament 2)
INSERT INTO `matches`
    (`tournament_id`, `home_team_id`, `away_team_id`, `home_score`, `away_score`, `match_date`, `venue`, `status`) VALUES
(2, 1, 5, NULL, NULL, '2027-01-12 18:00:00', 'National Arena', 'scheduled'),
(2, 8, 2, NULL, NULL, '2027-01-14 18:00:00', 'Eagle Nest', 'scheduled');

-- Autumn Series 2025 (tournament 3, completed)
INSERT INTO `matches`
    (`tournament_id`, `home_team_id`, `away_team_id`, `home_score`, `away_score`, `match_date`, `venue`, `status`) VALUES
(3, 1, 4, 1, 1, '2025-09-01 18:00:00', 'National Arena', 'finished'),
(3, 2, 5, 0, 2, '2025-09-15 18:00:00', 'Iron Arena', 'finished'),
(3, 3, 8, 3, 1, '2025-10-05 17:30:00', 'Coastal Stadium', 'finished'),
(3, 6, 7, 2, 2, '2025-10-20 19:00:00', 'Falcon Dome', 'finished');

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
('Squad training - tactics session', 'training', '2026-10-06 10:00:00', 1, NULL, 'Video analysis followed by pitch work.', 2),
('Squad training - recovery',        'training', '2026-10-08 10:00:00', 1, NULL, null, 2),
('Physio assessment day',            'meeting',  '2026-10-09 09:00:00', 1, NULL, 'Monthly screening for all first-team players.', 3),
('Iron Lions media obligations',     'media',    '2026-10-12 14:00:00', 2, NULL, 'Pre-match press conference and sponsor duties.', NULL),
('Squad training - set pieces',      'training', '2026-10-14 10:00:00', 1, NULL, null, 2),
('Travel to Brighton',               'travel',   '2026-10-24 16:00:00', 1, 1, 'Coach departs Manchester at 16:00, hotel check-in 19:00.', 2),
('vs Coastal Sharks - matchday prep','training', '2026-10-25 10:00:00', 1, 1, 'Light activation session before kickoff.', 2),
('Transfer window review',           'deadline', '2026-10-30 17:00:00', NULL, NULL, 'League-wide review of mid-season registrations.', NULL),
('Winter Cup registration deadline', 'deadline', '2026-12-15 23:59:00', NULL, 2, 'Final squad lists must be submitted.', NULL),
('Foundation open day',              'media',    '2026-11-05 11:00:00', NULL, NULL, 'Community event at the National Arena.', NULL);

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
('James Carter', 'james@sport.test', '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'player', 1, 1, '+44 161 496 0010');

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

-- Demo attendance: a couple of Thunder FC players checked in to the first training session (event 1).
INSERT INTO `attendance` (`event_id`, `player_id`, `status`, `notes`) VALUES
(1, 1, 'present', 'On time.'),
(1, 2, 'present', NULL);

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
