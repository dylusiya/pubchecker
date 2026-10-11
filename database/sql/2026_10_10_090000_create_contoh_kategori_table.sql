-- Migration: 2026_10_10_090000_create_contoh_kategori_table
-- Jalankan HANYA bila baris 2026_10_10_090000_create_contoh_kategori_table belum ada di tabel `migrations`.
create table `contoh_kategori` (`id` int unsigned not null auto_increment primary key, `kategori` varchar(100) not null, `path` varchar(500) not null, `keterangan` varchar(255) null, `urutan` smallint unsigned not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `contoh_kategori` add index `contoh_kategori_kategori_index`(`kategori`);
INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM (SELECT '2026_10_10_090000_create_contoh_kategori_table' AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_090000_create_contoh_kategori_table');
