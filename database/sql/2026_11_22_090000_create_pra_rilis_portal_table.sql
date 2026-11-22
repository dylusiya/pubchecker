-- Migration: 2026_11_22_090000_create_pra_rilis_portal_table
-- Jalankan HANYA bila baris 2026_11_22_090000_create_pra_rilis_portal_table belum ada di tabel `migrations`.
create table `pra_rilis_portal` (`id` bigint unsigned not null auto_increment primary key, `sipotret_pub_id` int unsigned not null, `user_id` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `pra_rilis_portal` add constraint `pra_rilis_portal_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete set null;
alter table `pra_rilis_portal` add unique `pra_rilis_portal_sipotret_pub_id_unique`(`sipotret_pub_id`);
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_22_090000_create_pra_rilis_portal_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_22_090000_create_pra_rilis_portal_table');
