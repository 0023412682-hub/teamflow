
CREATE TABLE IF NOT EXISTS `#__teamflow_assignments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_group_id` INT UNSIGNED NOT NULL,
    `assignee_user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_task_group_id` (`task_group_id`),
    KEY `idx_assignee_user_id` (`assignee_user_id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
