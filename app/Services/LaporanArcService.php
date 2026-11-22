<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Laporan pemantauan Advance Release Calendar (ARC) publikasi BPS kabupaten/kota, untuk dashboard:
 *   1. Rekap monitoring (rilis bulan ini, rilis Jan s.d. bulan ini, sudah/belum sesuai standar)
 *   2. Lima besar temuan ketidaksesuaian (SIPOTRET)
 *   3. Hasil pemantauan ARC (jumlah ARC tahun ini & tahun lalu)
 *   4. Rincian ARC per jenis publikasi
 *   5. Rekap pemantauan (on time / tidak on time / tidak rilis, sesuai standar, non ARC)
 *
 * Realisasi rilis: Web API BPS (model publication; sch_date = jadwal, rl_date = rilis), dikelompokkan ke jenis ARC
 * menurut judulnya. Kesesuaian standar: periode pemeriksaan SIPOTRET terakhir s.d. bulan laporan.
 *
 * Target ARC (daftar publikasi yang dijadwalkan, termasuk yang belum/tidak rilis) belum tersambung: Web API daftar
 * publikasi hanya memuat yang sudah rilis. Sampai sumbernya tersambung (targetArc()), jumlah ARC = yang sudah rilis
 * dan kolom "Tidak Rilis" serta keberadaan ARC di website/portal belum bisa diisi.
 */
class LaporanArcService
{
    /** Kab/kota yang dipantau (domain Web API). */
    public const WILAYAH = [
        6301 => 'Tanah Laut', 6302 => 'Kotabaru', 6303 => 'Banjar', 6304 => 'Barito Kuala', 6305 => 'Tapin',
        6306 => 'Hulu Sungai Selatan', 6307 => 'Hulu Sungai Tengah', 6308 => 'Hulu Sungai Utara', 6309 => 'Tabalong',
        6310 => 'Tanah Bumbu', 6311 => 'Balangan', 6371 => 'Banjarmasin', 6372 => 'Banjarbaru',
    ];

    /**
     * Jenis publikasi menurut judul. wajib = ARC wajib (dihitung di rekap ARC); selain itu non ARC wajib.
     * Judul yang tidak cocok = publikasi non ARC.
     */
    public const JENIS = [
        'dda_kab'  => ['label' => 'DDA Kabupaten/Kota', 'wajib' => true,  'pola' => '/^\s*(kabupaten|kota)\s.+\sdalam\s+angka\b/iu'],
        'dda_kec'  => ['label' => 'DDA Kecamatan',      'wajib' => true,  'pola' => '/^\s*kecamatan\s.+\sdalam\s+angka\b/iu'],
        'pdrb_lu'  => ['label' => 'PDRB Lapangan Usaha', 'wajib' => true, 'pola' => '/produk\s+domestik\s+regional\s+bruto.*lapangan\s+usaha/iu'],
        'pdrb_pg'  => ['label' => 'PDRB Pengeluaran',   'wajib' => true,  'pola' => '/produk\s+domestik\s+regional\s+bruto.*pengeluaran/iu'],
        'kesra'    => ['label' => 'Statistik Kesejahteraan Rakyat', 'wajib' => true, 'pola' => '/^\s*statistik\s+kesejahteraan\s+rakyat/iu'],
        'skd'      => ['label' => 'Analisis Hasil SKD', 'wajib' => false, 'pola' => '/analisis\s+(hasil\s+)?survei\s+kebutuhan\s+data/iu'],
        'statda'   => ['label' => 'Statistik Daerah Kabupaten/Kota', 'wajib' => false, 'pola' => '/^\s*statistik\s+daerah\b/iu'],
        'ikesra'   => ['label' => 'Indikator Kesejahteraan Rakyat Kabupaten/Kota', 'wajib' => false, 'pola' => '/indikator\s+kesejahteraan\s+rakyat/iu'],
    ];

    private const CACHE_DETIK = 3600;

    public function __construct(private BpsApiService $api, private SipotretService $sipotret)
    {
    }

    public static function jenis(string $judul): ?string
    {
        foreach (self::JENIS as $kode => $j) {
            if (preg_match($j['pola'], $judul)) return $kode;
        }
        return null;
    }

    /**
     * Publikasi yang sudah rilis di Web API tahun itu, semua kab/kota (cache 1 jam; $segarkan = ambil ulang).
     *
     * @return array<int, array{pub_id: string, wilayah: int, judul: string, jadwal: ?string, rilis: ?string, jenis: ?string}>
     */
    public function publikasi(int $tahun, bool $segarkan = false): array
    {
        return $this->ambil($tahun, $segarkan)['data'];
    }

    /** Waktu data rilis tahun itu diambil dari Web API (null bila belum pernah). */
    public function diambil(int $tahun): ?\Carbon\Carbon
    {
        $w = Cache::get("laporan_arc.publikasi.{$tahun}")['diambil'] ?? null;
        return $w ? \Carbon\Carbon::parse($w) : null;
    }

    private function ambil(int $tahun, bool $segarkan): array
    {
        $kunci = "laporan_arc.publikasi.{$tahun}";
        if ($segarkan) Cache::forget($kunci);
        return Cache::remember($kunci, self::CACHE_DETIK, fn() => ['diambil' => now()->toIso8601String(), 'data' => $this->dariApi($tahun)]);
    }

    private function dariApi(int $tahun): array
    {
        { // (satu blok: isi semula di dalam Cache::remember)
            $out = [];
            foreach (array_keys(self::WILAYAH) as $domain) {
                $hal = 1;
                do {
                    $r = $this->api->getPublications((string) $domain, $hal, null, $tahun);
                    if (($r['status'] ?? 'OK') !== 'OK' && empty($r['items'])) {
                        throw new \RuntimeException("Web API BPS gagal untuk domain {$domain}: " . ($r['message'] ?? $r['status'] ?? 'tanpa keterangan'));
                    }
                    foreach ($r['items'] ?? [] as $p) {
                        $judul = trim((string) ($p['title'] ?? ''));
                        $jadwal = $this->tanggal($p['sch_date'] ?? null);
                        $rilis  = $this->tanggal($p['rl_date'] ?? null);
                        // daftar per tahun memakai tanggal rilis; jadwal tahun lain (mis. rilis Januari untuk jadwal Desember) dibuang
                        if (($jadwal ?? $rilis) && (int) substr($jadwal ?? $rilis, 0, 4) !== $tahun) continue;
                        $out[] = [
                            'pub_id'  => (string) ($p['pub_id'] ?? ''),
                            'wilayah' => $domain,
                            'judul'   => $judul,
                            'jadwal'  => $jadwal,
                            'rilis'   => $rilis,
                            'jenis'   => self::jenis($judul),
                        ];
                    }
                    $hal++;
                } while ($hal <= (int) ($r['pagination']['pages'] ?? 1) && $hal <= 50);
            }
            return $out;
        }
    }

    /**
     * Target ARC per kab/kota per jenis: [wilayah => [jenis => [bulan jadwal 1–12 => jumlah]]], atau null bila tidak ada.
     *
     * Web API BPS tidak punya daftar ARC/jadwal terbit: publikasi baru muncul (dengan sch_date) setelah rilis.
     * Karena itu target diambil dari pola ARC tahun lalu (jenis, jumlah, dan bulan jadwal publikasi tahun-1),
     * seperti di laporan manual (jumlah ARC tahun ini = tahun lalu). Perubahan ARC (mis. kecamatan baru) baru
     * terlihat setelah publikasinya rilis: realisasi yang melebihi target tetap dihitung.
     */
    public function targetArc(int $tahun, ?array $pubLalu = null): ?array
    {
        $pubLalu ??= $this->publikasi($tahun - 1);
        $target = [];
        foreach ($pubLalu as $p) {
            if (!$p['jenis']) continue;
            $bln = (int) substr($p['jadwal'] ?? $p['rilis'] ?? '0000-00', 5, 2);
            if ($bln < 1) continue;
            $target[$p['wilayah']][$p['jenis']][$bln] = ($target[$p['wilayah']][$p['jenis']][$bln] ?? 0) + 1;
        }
        return $target ?: null;
    }

    /** Semua tabel laporan periode bulan/tahun. */
    public function laporan(int $tahun, int $bulan, bool $segarkan = false): array
    {
        $pub = $this->publikasi($tahun, $segarkan);
        $pubLalu = null;
        try {
            $pubLalu = $this->publikasi($tahun - 1, $segarkan);
        } catch (\Throwable $e) {
            report($e); // tahun lalu hanya untuk satu kolom: laporan tetap tampil
        }
        $target = $pubLalu !== null ? $this->targetArc($tahun, $pubLalu) : null;

        $wajib = fn($p) => $p['jenis'] && self::JENIS[$p['jenis']]['wajib'];
        // sch_date = jadwal (ARC), rl_date = realisasi rilis. Tanggal dibandingkan sebagai teks Y-m-d.
        $awal  = sprintf('%04d-01-01', $tahun);
        $akhir = \Carbon\Carbon::create($tahun, $bulan)->endOfMonth()->format('Y-m-d');
        $awalBulan = sprintf('%04d-%02d-01', $tahun, $bulan);
        $jadwalOf = fn($p) => $p['jadwal'] ?? $p['rilis'];
        $dalam = fn(?string $t, string $dari, string $sampai) => $t !== null && $t >= $dari && $t <= $sampai;
        $arc = array_values(array_filter($pub, $wajib));
        // Tabel 1: dirilis (rl_date) Januari s.d. akhir bulan laporan
        $sdBulan = array_values(array_filter($arc, fn($p) => $dalam($p['rilis'], $awal, $akhir)));
        // Tabel 5: dijadwalkan (sch_date) Januari s.d. akhir bulan laporan
        $jadwalSd = array_values(array_filter($arc, fn($p) => $dalam($jadwalOf($p), $awal, $akhir)));

        // kesesuaian standar (SIPOTRET): ARC yang sudah rilis s.d. bulan laporan sesuai standar bila TIDAK punya catatan
        // Pasca Rilis pada bulan laporan itu. $status hanya berisi publikasi yang punya catatan di bulan itu.
        $status = [];
        $statusError = null;
        if (!$this->sipotret->enabled()) {
            $statusError = 'Koneksi SIPOTRET belum diatur, kolom sesuai standar tidak bisa diisi.';
        } else {
            try {
                $status = $this->sipotret->catatanPeriode(array_column($sdBulan, 'pub_id'), $bulan, $tahun);
            } catch (\Throwable $e) {
                report($e);
                $statusError = 'Tidak bisa membaca SIPOTRET: ' . $e->getMessage();
            }
        }

        $temuan = [];
        $temuanError = null;
        if ($this->sipotret->enabled()) {
            try {
                $temuan = $this->sipotret->temuanTerbanyak($tahun, 1, $bulan, 5, array_keys(self::WILAYAH));
            } catch (\Throwable $e) {
                report($e);
                $temuanError = 'Tidak bisa membaca SIPOTRET: ' . $e->getMessage();
            }
        }

        $baris = [];
        foreach (self::WILAYAH as $kode => $nama) {
            $milik = fn(array $list) => array_values(array_filter($list, fn($p) => $p['wilayah'] === $kode));
            $sdW     = $milik($sdBulan);                                            // dirilis s.d. bulan laporan
            $bulanW  = array_filter($sdW, fn($p) => $dalam($p['rilis'], $awalBulan, $akhir)); // dirilis bulan laporan
            $jdW     = $milik($jadwalSd);                                           // dijadwalkan s.d. bulan laporan
            // dari yang dijadwalkan: on time (rilis ≤ jadwal), tidak on time (rilis sesudah jadwal tapi masih s.d. bulan
            // laporan); rilis sesudah bulan laporan = belum rilis pada periode laporan
            $onTime  = count(array_filter($jdW, fn($p) => $p['rilis'] && $p['rilis'] <= $jadwalOf($p)));
            $telat   = count(array_filter($jdW, fn($p) => $p['rilis'] && $p['rilis'] > $jadwalOf($p) && $p['rilis'] <= $akhir));
            $sesuai  = count(array_filter($sdW, fn($p) => empty($status[$p['pub_id']]['catatan'])));
            // per jenis: dijadwalkan = maks(target pola tahun lalu, realisasi) — realisasi di luar target tetap dihitung,
            // yang belum/tidak rilis = dijadwalkan − realisasi
            $perJenis = array_fill_keys(array_keys(self::JENIS), 0);
            $jadwalBulan = 0;
            $dijadwalkan = 0;
            foreach (self::JENIS as $j => $info) {
                $realJ = array_filter($milik($pub), fn($p) => $p['jenis'] === $j);
                $tgt   = $target[$kode][$j] ?? [];
                $perJenis[$j] = max(count($realJ), array_sum($tgt));
                if (!$info['wajib']) continue;
                $realSd = count(array_filter($realJ, fn($p) => $dalam($jadwalOf($p), $awal, $akhir)));
                $realBl = count(array_filter($realJ, fn($p) => $dalam($jadwalOf($p), $awalBulan, $akhir)));
                $tgtSd  = array_sum(array_filter($tgt, fn($b) => $b <= $bulan, ARRAY_FILTER_USE_KEY));
                $dijadwalkan += max($realSd, $tgtSd);
                $jadwalBulan += max($realBl, $tgt[$bulan] ?? 0);
            }
            $jumlahArc = array_sum(array_intersect_key($perJenis, array_filter(self::JENIS, fn($j) => $j['wajib'])));

            $baris[$kode] = [
                'kode'          => $kode,
                'nama'          => $nama,
                'rilis_bulan'   => count($bulanW),
                'rilis_sd'      => count($sdW),
                'sesuai'        => $statusError ? null : $sesuai,
                'belum_sesuai'  => $statusError ? null : count($sdW) - $sesuai,
                'jadwal_bulan'  => $target ? $jadwalBulan : count(array_filter($jdW, fn($p) => $dalam($jadwalOf($p), $awalBulan, $akhir))),
                'jadwal_sd'     => $target ? $dijadwalkan : count($jdW),
                'on_time'       => $onTime,
                'tidak_on_time' => $telat,
                // dijadwalkan s.d. bulan laporan − yang rilis s.d. bulan laporan (on time + tidak on time)
                'tidak_rilis'   => $target ? max(0, $dijadwalkan - $onTime - $telat) : count($jdW) - $onTime - $telat,
                'per_jenis'     => $perJenis,
                'jumlah_arc'    => $jumlahArc,
                'jumlah_arc_lalu' => $pubLalu === null ? null
                    : count(array_filter($pubLalu, fn($p) => $p['wilayah'] === $kode && $wajib($p))),
                'non_arc'       => count(array_filter($milik($pub), fn($p) => !$wajib($p))),
                // rincian: dijadwalkan atau dirilis s.d. bulan laporan, urut jadwal
                'publikasi'     => collect($jdW)->concat($sdW)->unique('pub_id')->sortBy(fn($p) => $jadwalOf($p) . $p['judul'])->values()->all(),
                'status'        => array_intersect_key($status, array_flip(array_column($sdW, 'pub_id'))),
            ];
        }

        $jumlah = fn(string $k) => in_array(null, array_column($baris, $k), true) ? null : array_sum(array_column($baris, $k));
        $total = [];
        foreach (['rilis_bulan', 'rilis_sd', 'sesuai', 'belum_sesuai', 'jadwal_bulan', 'jadwal_sd', 'on_time',
                  'tidak_on_time', 'tidak_rilis', 'jumlah_arc', 'jumlah_arc_lalu', 'non_arc'] as $k) {
            $total[$k] = $jumlah($k);
        }
        $total['per_jenis'] = array_map(fn($j) => array_sum(array_map(fn($b) => $b['per_jenis'][$j], $baris)), array_combine(array_keys(self::JENIS), array_keys(self::JENIS)));

        return [
            'tahun'        => $tahun,
            'bulan'        => $bulan,
            'baris'        => $baris,
            'total'        => $total,
            'temuan'       => $temuan,
            'temuanError'  => $temuanError,
            'statusError'  => $statusError,
            'adaTarget'    => $target !== null,
            'tahunLaluError' => $pubLalu === null,
            'diambil'      => $this->diambil($tahun) ?? now(),
        ];
    }

    /** Persen untuk tabel (2 desimal, koma). null bila pembagi 0. */
    public static function persen(?int $n, ?int $dari): ?string
    {
        if ($n === null || !$dari) return null;
        return number_format($n / $dari * 100, 2, ',', '.');
    }

    private function tanggal(?string $s): ?string
    {
        $s = trim((string) $s);
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $s) ? substr($s, 0, 10) : null;
    }
}
