<?php
/**
 * Database schema for fresh installations.
 *
 * @return array<int, string>
 */
declare(strict_types=1);

function qb_schema(string $prefix): array
{
    if (!preg_match('/^[A-Za-z0-9_]{0,24}$/', $prefix)) {
        throw new InvalidArgumentException('Invalid table prefix.');
    }

    $categories = $prefix . 'categories';
    $quotes = $prefix . 'quotes';
    $admins = $prefix . 'admins';
    $settings = $prefix . 'settings';

    return array(
        "CREATE TABLE IF NOT EXISTS `{$categories}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(60) NOT NULL,
            `slug` VARCHAR(40) NOT NULL,
            `description` VARCHAR(255) NOT NULL DEFAULT '',
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_slug` (`slug`),
            KEY `idx_sort` (`sort_order`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$quotes}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` INT UNSIGNED NULL DEFAULT NULL,
            `content` TEXT NOT NULL,
            `author` VARCHAR(100) NOT NULL DEFAULT '',
            `source` VARCHAR(200) NOT NULL DEFAULT '',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_category` (`category_id`),
            KEY `idx_active_category` (`is_active`, `category_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$admins}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            `last_login_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$settings}` (
            `setting_key` VARCHAR(64) NOT NULL,
            `setting_value` TEXT NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );
}
