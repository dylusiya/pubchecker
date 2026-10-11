-- Migration: 2026_10_05_110000_add_ocr_lines_to_hasil_pemeriksaan_table
-- Jalankan HANYA bila baris 2026_10_05_110000_add_ocr_lines_to_hasil_pemeriksaan_table belum ada di tabel `migrations`.
ALTER TABLE hasil_pemeriksaan ADD ocr_lines LONGTEXT NULL AFTER pdf_url;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_05_110000_add_ocr_lines_to_hasil_pemeriksaan_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_05_110000_add_ocr_lines_to_hasil_pemeriksaan_table');
