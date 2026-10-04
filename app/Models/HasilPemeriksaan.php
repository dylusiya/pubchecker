<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HasilPemeriksaan extends Model
{
    protected $table      = 'hasil_pemeriksaan';
    public    $timestamps = false; // hanya punya created_at, diisi manual

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
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'ocr_lines'  => 'array',
    ];

    public function sesi()
    {
        return $this->belongsTo(SesiPemeriksaan::class, 'sesi_id');
    }

    public function detail()
    {
        return $this->hasMany(DetailPemeriksaan::class, 'hasil_id');
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
        return ($this->pdf_path && Storage::exists($this->pdf_path)) || (bool) $this->pdf_url;
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

        $ok   = $detail->where('status', 'OK')->count();
        $warn = $detail->where('status', 'PERLU DICEK')->count();
        $err  = $detail->where('status', 'TIDAK ADA')->count();
        $skip = $detail->where('status', 'TIDAK DIPERIKSA')->count();

        $this->total_ok            = $ok;
        $this->total_perlu_dicek   = $warn;
        $this->total_tidak_ada     = $err;
        $this->total_tdk_diperiksa = $skip;
        $this->status_akhir        = self::resolveStatus($err, $warn);
        $this->save();
    }
}