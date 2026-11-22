-- Migration: 2026_11_23_100000_add_pins_to_detail_pemeriksaan
-- Jalankan HANYA bila baris 2026_11_23_100000_add_pins_to_detail_pemeriksaan belum ada di tabel `migrations`.
alter table `detail_pemeriksaan` add `pins` text null after `pin_y`;
UPDATE detail_pemeriksaan
            SET pins = CONCAT('[{"hal":', pin_halaman, ',"x":', pin_x, ',"y":', pin_y, '}]')
            WHERE pin_halaman IS NOT NULL AND pin_x IS NOT NULL AND pin_y IS NOT NULL AND pins IS NULL;
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_11_23_100000_add_pins_to_detail_pemeriksaan' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_23_100000_add_pins_to_detail_pemeriksaan');
