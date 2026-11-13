
-- TeamFlow - Ban 2: Tao va giao nhiem vu
-- Tao 2 bang phuc vu quan ly va giao nhiem vu
-- Khong chua du lieu kiem thu

CREATE TABLE IF NOT EXISTS `#__teamflow_tasks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `criteria` TEXT NOT NULL,
    `due_at` DATETIME NOT NULL,
    `created_by` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_due_at` (`due_at`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__teamflow_task_groups` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `task_id` INT NOT NULL,
    `group_id` INT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_task_group` (`task_id`, `group_id`),
    KEY `idx_group_id` (`group_id`),
    CONSTRAINT `fk_teamflow_task_groups_task`
        FOREIGN KEY (`task_id`)
        REFERENCES `#__teamflow_tasks` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
