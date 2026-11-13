-- Migration: 2026_11_10_090000_sipotret_master_item_on_catatan_tambahan
-- Jalankan HANYA bila baris 2026_11_10_090000_sipotret_master_item_on_catatan_tambahan belum ada di tabel `migrations`.
alter table `catatan_tambahan` add `item_periksa_id` int unsigned null after `item`;
ALTER TABLE catatan_tambahan MODIFY flag_level ENUM('minor','moderate','major') NULL DEFAULT 'minor';
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_10_090000_sipotret_master_item_on_catatan_tambahan' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_10_090000_sipotret_master_item_on_catatan_tambahan');
