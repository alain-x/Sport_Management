-- v3 migration: player login accounts, photo attendance, team/group chat.
-- Safe to run on an existing database (no drops). Fresh installs get this
-- automatically via schema.sql.
--
-- Usage: mysql -u root sport_management < database/migrate_v3.sql

-- 1. Player role + link to the players table.
ALTER TABLE `users`
    ADD COLUMN `player_id` INT UNSIGNED DEFAULT NULL AFTER `team_id`,
    ADD KEY `users_player_id_index` (`player_id`),
    ADD CONSTRAINT `users_player_id_foreign`
        FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE;

ALTER TABLE `users` MODIFY `role` ENUM('admin','manager','player') NOT NULL DEFAULT 'manager';

-- Demo player login (ignore the duplicate-key error if re-run).
INSERT INTO `users` (`name`, `email`, `password`, `role`, `team_id`, `player_id`, `phone`) VALUES
('James Carter', 'james@sport.test', '$2y$10$7ylYB5ExaDFvj09owVL.BOYPghRZGXOHmKK7esgq1KRPH8kXVg8Ny', 'player', 1, 1, '+44 161 496 0010');

-- 2. Attendance.
CREATE TABLE IF NOT EXISTS `attendance` (
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

INSERT IGNORE INTO `attendance` (`event_id`, `player_id`, `status`, `notes`) VALUES
(1, 1, 'present', 'On time.'),
(1, 2, 'present', NULL);

-- 3. Chat.
CREATE TABLE IF NOT EXISTS `chat_rooms` (
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

CREATE TABLE IF NOT EXISTS `chat_room_members` (
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

CREATE TABLE IF NOT EXISTS `chat_messages` (
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

-- Team chat room per club (only for teams that don't have one yet).
INSERT INTO `chat_rooms` (`name`, `type`, `team_id`)
    SELECT CONCAT(t.name, ' Chat'), 'team', t.id FROM `teams` t
    WHERE NOT EXISTS (SELECT 1 FROM `chat_rooms` r WHERE r.type = 'team' AND r.team_id = t.id);
