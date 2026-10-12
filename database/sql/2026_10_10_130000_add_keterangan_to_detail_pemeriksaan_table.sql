-- Migration: 2026_10_10_130000_add_keterangan_to_detail_pemeriksaan_table
-- Jalankan HANYA bila baris 2026_10_10_130000_add_keterangan_to_detail_pemeriksaan_table belum ada di tabel `migrations`.
ALTER TABLE detail_pemeriksaan ADD keterangan TEXT NULL AFTER catatan;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_10_130000_add_keterangan_to_detail_pemeriksaan_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_130000_add_keterangan_to_detail_pemeriksaan_table');
