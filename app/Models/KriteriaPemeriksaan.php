<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KriteriaPemeriksaan extends Model
{
    protected $table = 'kriteria_pemeriksaan';

    protected $fillable = [
        'kode', 'kategori', 'deskripsi',
        'tipe_cek', 'parameter', 'target',
        'status_gagal', 'pesan_ok', 'pesan_gagal',
        'aktif', 'urutan',
    ];

    protected $casts = [
        'parameter' => 'array',
        'aktif'     => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────
    public function scopeAktif($q)
    {
        return $q->where('aktif', true)->orderBy('urutan');
    }

    // ── Helpers ───────────────────────────────────────────────
    public static function tipeOptions(): array
    {
        return [
            'regex'        => 'Regex — cocokkan pola teks',
            'not_regex'    => 'Not Regex — pola teks TIDAK boleh ditemukan',
            'contains'     => 'Contains — cari teks tertentu',
            'not_contains' => 'Not Contains — teks TIDAK boleh ada',
            'posisi_area'  => 'Posisi Area — cek letak kata di halaman',
            'min_pages'    => 'Jumlah Halaman — cek min. halaman PDF',
            'urutan'       => 'Urutan — komponen harus muncul berurutan di halaman',
            'sama_kover'   => 'Sama dengan Kover — isian harus sama dengan di kover (mis. ISSN)',
            'paritas'      => 'Halaman Ganjil/Genap — bagian dimulai di halaman ganjil/genap',
            'romawi_genap' => 'Romawi Genap — jumlah halaman romawi di Katalog harus genap',
            'jumlah_min'   => 'Jumlah Minimal — pola harus ditemukan minimal N kali',
            'nomor_urut'   => 'Nomor Berurutan — nomor (mis. tabel) berurutan & formatnya konsisten',
            'bab'          => 'Bab — awal bab di halaman ganjil / nomor bab berurutan',
            'bulan_rilis'  => 'Bulan Rilis — bulan tanda tangan maks. 1 bulan sebelum rilis (Import BPS)',
            'abjad'        => 'Urut Abjad — entri (mis. daftar pustaka) tersusun menurut abjad',
            'judul_kover'  => 'Judul seperti Kover — judul & tahun judul sama dengan kover',
            'manual'       => 'Manual — selalu PERLU DICEK (cek manusia)',
        ];
    }

    public static function targetOptions(): array
    {
        return [
            'cover' => 'Halaman 1 (Kover)',
            'page2' => 'Halaman 2',
            'front' => 'Bagian awal (20 halaman pertama)',
            'katalog'        => 'Halaman Katalog (dikenali otomatis)',
            'tim_penyusun'   => 'Halaman Tim Penyusun (dikenali otomatis)',
            'kata_sambutan'   => 'Halaman Kata Sambutan (dikenali otomatis)',
            'kata_pengantar'  => 'Halaman Kata Pengantar (dikenali otomatis)',
            'abstraksi'       => 'Halaman Abstraksi (dikenali otomatis)',
            'daftar_isi'      => 'Halaman Daftar Isi (dikenali otomatis)',
            'daftar_tabel'    => 'Halaman Daftar Tabel (dikenali otomatis)',
            'daftar_gambar'   => 'Halaman Daftar Gambar (dikenali otomatis)',
            'daftar_lampiran' => 'Halaman Daftar Lampiran (dikenali otomatis)',
            'daftar_pustaka'  => 'Halaman Daftar Pustaka (dikenali otomatis)',
            'glosarium'       => 'Halaman Glosarium (dikenali otomatis)',
            'indeks'          => 'Halaman Indeks (dikenali otomatis)',
            'lampiran'        => 'Halaman awal Lampiran (dikenali otomatis)',
            'last'  => 'Halaman terakhir (Kover Belakang)',
            'all'   => 'Semua halaman',
        ];
    }

    public static function areaOptions(): array
    {
        return [
            'top'           => 'Atas (30% teratas)',
            'top_right'     => 'Kanan atas',
            'bottom'        => 'Bawah (40% terbawah)',
            'bottom_left'   => 'Kiri bawah',
            'below_katalog' => 'Di bawah Nomor Katalog',
        ];
    }
}