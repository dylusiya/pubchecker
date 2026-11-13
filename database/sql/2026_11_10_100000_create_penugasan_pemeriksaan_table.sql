-- Migration: 2026_11_10_100000_create_penugasan_pemeriksaan_table
-- Jalankan HANYA bila baris 2026_11_10_100000_create_penugasan_pemeriksaan_table belum ada di tabel `migrations`.
create table `penugasan_pemeriksaan` (`id` bigint unsigned not null auto_increment primary key, `pub_id_api` varchar(100) not null, `judul` varchar(500) null, `pub_domain` varchar(10) null, `user_id` bigint unsigned not null, `ditugaskan_oleh` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `penugasan_pemeriksaan` add constraint `penugasan_pemeriksaan_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `penugasan_pemeriksaan` add constraint `penugasan_pemeriksaan_ditugaskan_oleh_foreign` foreign key (`ditugaskan_oleh`) references `users` (`id`) on delete set null;
alter table `penugasan_pemeriksaan` add unique `penugasan_pemeriksaan_pub_id_api_unique`(`pub_id_api`);
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_10_100000_create_penugasan_pemeriksaan_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_10_100000_create_penugasan_pemeriksaan_table');
