-- Migration: 2026_11_21_090000_create_penugasan_pra_rilis_table
-- Jalankan HANYA bila baris 2026_11_21_090000_create_penugasan_pra_rilis_table belum ada di tabel `migrations`.
create table `penugasan_pra_rilis` (`id` bigint unsigned not null auto_increment primary key, `sipotret_pub_id` int unsigned not null, `judul` varchar(500) null, `tgl_rilis` date null, `user_id` bigint unsigned not null, `ditugaskan_oleh` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `penugasan_pra_rilis` add constraint `penugasan_pra_rilis_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `penugasan_pra_rilis` add constraint `penugasan_pra_rilis_ditugaskan_oleh_foreign` foreign key (`ditugaskan_oleh`) references `users` (`id`) on delete set null;
alter table `penugasan_pra_rilis` add unique `penugasan_pra_rilis_sipotret_pub_id_unique`(`sipotret_pub_id`);
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_21_090000_create_penugasan_pra_rilis_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_21_090000_create_penugasan_pra_rilis_table');
