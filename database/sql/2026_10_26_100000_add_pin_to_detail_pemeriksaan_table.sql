-- Migration: 2026_10_26_100000_add_pin_to_detail_pemeriksaan_table
-- Jalankan HANYA bila baris 2026_10_26_100000_add_pin_to_detail_pemeriksaan_table belum ada di tabel `migrations`.
ALTER TABLE detail_pemeriksaan ADD pin_halaman SMALLINT UNSIGNED NULL AFTER keterangan, ADD pin_x DECIMAL(5,4) NULL AFTER pin_halaman, ADD pin_y DECIMAL(5,4) NULL AFTER pin_x;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_26_100000_add_pin_to_detail_pemeriksaan_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_26_100000_add_pin_to_detail_pemeriksaan_table');
