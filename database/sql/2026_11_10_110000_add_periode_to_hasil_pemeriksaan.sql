-- Migration: 2026_11_10_110000_add_periode_to_hasil_pemeriksaan
-- Jalankan HANYA bila baris 2026_11_10_110000_add_periode_to_hasil_pemeriksaan belum ada di tabel `migrations`.
alter table `hasil_pemeriksaan` add `periode_bulan` tinyint unsigned null after `sipotret_acuan_tahun`, add `periode_tahun` smallint unsigned null after `periode_bulan`;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_10_110000_add_periode_to_hasil_pemeriksaan' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_10_110000_add_periode_to_hasil_pemeriksaan');
