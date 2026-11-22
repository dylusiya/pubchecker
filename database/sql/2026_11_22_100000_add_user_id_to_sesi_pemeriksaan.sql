-- Migration: 2026_11_22_100000_add_user_id_to_sesi_pemeriksaan
-- Jalankan HANYA bila baris 2026_11_22_100000_add_user_id_to_sesi_pemeriksaan belum ada di tabel `migrations`.
alter table `sesi_pemeriksaan` add `user_id` bigint unsigned null after `dibuat_oleh`;
alter table `sesi_pemeriksaan` add constraint `sesi_pemeriksaan_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete set null;
UPDATE sesi_pemeriksaan s
            JOIN (SELECT name, MIN(id) AS id FROM users GROUP BY name HAVING COUNT(*) = 1) u ON u.name = s.dibuat_oleh
            SET s.user_id = u.id
            WHERE s.user_id IS NULL;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_22_100000_add_user_id_to_sesi_pemeriksaan' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_22_100000_add_user_id_to_sesi_pemeriksaan');
