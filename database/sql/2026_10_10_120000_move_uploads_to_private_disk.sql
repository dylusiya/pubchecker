-- Migration: 2026_10_10_120000_move_uploads_to_private_disk
-- Jalankan HANYA bila baris 2026_10_10_120000_move_uploads_to_private_disk belum ada di tabel `migrations`.
-- Tidak ada perubahan database. Migration ini MEMINDAHKAN FILE (lakukan manual di cPanel File Manager):
--   storage/app/public/pdf-hasil        ->  storage/app/pdf-hasil
--   storage/app/public/contoh-kategori  ->  storage/app/contoh-kategori
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_10_120000_move_uploads_to_private_disk' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_120000_move_uploads_to_private_disk');
