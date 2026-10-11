-- Migration: 2026_10_05_090000_add_tinjauan_columns_to_pemeriksaan_tables
-- Jalankan HANYA bila baris 2026_10_05_090000_add_tinjauan_columns_to_pemeriksaan_tables belum ada di tabel `migrations`.
ALTER TABLE hasil_pemeriksaan ADD pdf_path VARCHAR(500) NULL AFTER error_msg, ADD pdf_url TEXT NULL AFTER pdf_path;
ALTER TABLE detail_pemeriksaan ADD ditinjau_at TIMESTAMP NULL DEFAULT NULL AFTER catatan;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_05_090000_add_tinjauan_columns_to_pemeriksaan_tables' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_05_090000_add_tinjauan_columns_to_pemeriksaan_tables');
