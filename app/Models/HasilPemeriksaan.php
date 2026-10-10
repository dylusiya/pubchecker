<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HasilPemeriksaan extends Model
{
    protected $table      = 'hasil_pemeriksaan';
    public    $timestamps = false; // hanya punya created_at, diisi manual

    /**
     * Disk privat (storage/app) untuk PDF hasil upload — bukan disk "public", supaya file tidak ikut
     * terbuka bila `php artisan storage:link` dijalankan. Disajikan hanya lewat route yang wajib login.
     */
    public const DISK = 'local';

    protected $fillable = [
        'sesi_id',
        'nama_file',
        'ukuran_file',
        'total_halaman',
        'metode_ekstraksi',
        'total_ok',
        'total_perlu_dicek',
        'total_tidak_ada',
        'total_tdk_diperiksa',
        'status_akhir',
        'error_msg',
        'pdf_path',
        'pdf_url',
        'ocr_lines',
        // identitas publikasi BPS (Import API) — kunci pencocokan ke SIPOTRET
        'pub_id_api',
        'pub_domain',
        'pub_issn',
        'pub_tanggal_rilis',
        'sipotret_terkirim_at',
        'sipotret_history_id',
    ];

    protected $casts = [
        'created_at'           => 'datetime',
        'ocr_lines'            => 'array',
        'pub_tanggal_rilis'    => 'date',
        'sipotret_terkirim_at' => 'datetime',
    ];

    public function sesi()
    {
        return $this->belongsTo(SesiPemeriksaan::class, 'sesi_id');
    }

    public function detail()
    {
        return $this->hasMany(DetailPemeriksaan::class, 'hasil_id');
    }

    /** Temuan petugas di luar daftar kriteria. */
    public function catatanTambahan()
    {
        return $this->hasMany(CatatanTambahan::class, 'hasil_id')->orderBy('id');
    }

    /**
     * Judul publikasi yang mudah dibaca dari nama file
     * (nama file BPS import memakai '_' sebagai pengganti spasi).
     */
    public function getJudulAttribute(): string
    {
        $judul = preg_replace('/\.pdf$/i', '', $this->nama_file);
        return trim(str_replace('_', ' ', $judul));
    }

    /** Sumber PDF: 'BPS' (import web BPS) atau 'Upload'. */
    public function getSumberAttribute(): ?string
    {
        if ($this->pdf_url)  return 'BPS';
        if ($this->pdf_path) return 'Upload';
        return null;
    }

    /**
     * PDF tersedia untuk ditinjau ulang (file upload tersimpan atau URL BPS).
     */
    public function hasPdf(): bool
    {
        return ($this->pdf_path && Storage::disk(self::DISK)->exists($this->pdf_path)) || (bool) $this->pdf_url;
    }

    /** Hasil dari Import API BPS yang identitasnya lengkap, bisa dikirim ke SIPOTRET. */
    public function bisaKirimSipotret(): bool
    {
        return $this->pub_id_api && $this->pub_domain;
    }

    /**
     * Tentukan status_akhir berdasarkan jumlah temuan.
     */
    public static function resolveStatus(int $tidakAda, int $perluDicek): string
    {
        if ($tidakAda > 0)   return 'masalah';
        if ($perluDicek > 0) return 'perlu_dicek';
        return 'ok';
    }

    /**
     * Hitung ulang total status dari detail (dipakai setelah review manual).
     */
    public function recalcFromDetail(): void
    {
        $detail = $this->detail()->get();

        $ok   = $detail->where('status', DetailPemeriksaan::STATUS_OK)->count();
        $warn = $detail->where('status', DetailPemeriksaan::STATUS_PERLU_DICEK)->count();
        // "Tidak Ada" (otomatis) & "Tidak Sesuai" (verifikasi petugas) sama-sama dihitung masalah
        $err  = $detail->whereIn('status', DetailPemeriksaan::STATUS_MASALAH)->count();
        $skip = $detail->where('status', DetailPemeriksaan::STATUS_TIDAK_DIPERIKSA)->count();

        $this->total_ok            = $ok;
        $this->total_perlu_dicek   = $warn;
        $this->total_tidak_ada     = $err;
        $this->total_tdk_diperiksa = $skip;
        $this->status_akhir        = self::resolveStatus($err, $warn);
        $this->save();
    }
}