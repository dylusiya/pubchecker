-- Migration: 2026_11_10_120000_widen_item_on_catatan_tambahan
-- Jalankan HANYA bila baris 2026_11_10_120000_widen_item_on_catatan_tambahan belum ada di tabel `migrations`.
ALTER TABLE catatan_tambahan MODIFY item TEXT NOT NULL;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_10_120000_widen_item_on_catatan_tambahan' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_10_120000_widen_item_on_catatan_tambahan');
