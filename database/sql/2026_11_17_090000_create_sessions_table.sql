-- Migration: 2026_11_17_090000_create_sessions_table
-- Tabel session untuk SESSION_DRIVER=database (aman dijalankan ulang: IF NOT EXISTS).
-- Sesudah dijalankan, ubah .env server: SESSION_DRIVER=database, lalu `php artisan config:clear` bila config di-cache.
create table if not exists `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`), index `sessions_user_id_index`(`user_id`), index `sessions_last_activity_index`(`last_activity`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_17_090000_create_sessions_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_17_090000_create_sessions_table');
