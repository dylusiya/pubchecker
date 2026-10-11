-- Migration: 2026_10_01_145800_widen_deskripsi_column_on_kriteria_pemeriksaan_table
-- Jalankan HANYA bila baris 2026_10_01_145800_widen_deskripsi_column_on_kriteria_pemeriksaan_table belum ada di tabel `migrations`.
ALTER TABLE kriteria_pemeriksaan MODIFY deskripsi TEXT NOT NULL;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_01_145800_widen_deskripsi_column_on_kriteria_pemeriksaan_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_01_145800_widen_deskripsi_column_on_kriteria_pemeriksaan_table');
