-- Migration: 2026_10_26_090000_add_posisi_to_catatan_tambahan
-- Jalankan HANYA bila baris 2026_10_26_090000_add_posisi_to_catatan_tambahan belum ada di tabel `migrations`.
ALTER TABLE catatan_tambahan ADD pos_x DECIMAL(5,4) NULL AFTER halaman, ADD pos_y DECIMAL(5,4) NULL AFTER pos_x;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_26_090000_add_posisi_to_catatan_tambahan' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_26_090000_add_posisi_to_catatan_tambahan');
