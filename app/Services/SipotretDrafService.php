<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Draf publikasi BPS Provinsi Kalimantan Selatan yang diupload PJK/SM di SIPOTRET (menu Publikasi → Upload),
 * supaya bisa diperiksa di pubchecker sebelum rilis.
 *
 * Tabel SIPOTRET:
 *   publikasi  — pub_id, pub_judul, pub_tgl_rilis (rencana rilis), pub_tahun, us_id (PJK), pub_issn, pub_rilis
 *   transaksi  — satu baris per versi upload: tr_id, pub_id, tr_flag (v1, v2, …), tr_file_upload,
 *                tr_tanggal_upload_sm; tr_catatan_ipds/tr_file_revisi = hasil pemeriksaan IPDS
 *
 * tr_file_upload berupa "owncloud/sm/<file>" (disimpan di OwnCloud, diambil lewat WebDAV) atau path relatif
 * folder public SIPOTRET (fallback lokal, mis. "upload/fallback/sm/<file>").
 */
class SipotretDrafService
{
    public function enabled(): bool
    {
        return (bool) config('database.connections.sipotret.database');
    }

    private function db(): Connection
    {
        return DB::connection('sipotret');
    }

    /** Tahun-tahun yang punya publikasi di SIPOTRET (terbaru dulu). */
    public function daftarTahun(): array
    {
        return $this->db()->table('publikasi')->whereNotNull('pub_tahun')->where('pub_tahun', '>', 0)
            ->distinct()->orderByDesc('pub_tahun')->pluck('pub_tahun')->map(fn($t) => (int) $t)->all();
    }

    /**
     * Publikasi pada tahun tsb beserta versi draf yang sudah diupload (terbaru dulu).
     *
     * @return array<int, array{pub_id:int, judul:string, judul_en:?string, tgl_rilis:?string, pjk:?string,
     *               status:?string, rilis:bool, issn:?string, versi:array}>
     */
    public function daftar(?int $tahun, ?string $cari = null): array
    {
        $schema = $this->db()->getSchemaBuilder();
        $kolom  = fn(string $k) => $schema->hasColumn('publikasi', $k) ? 'p.' . $k : DB::raw('NULL as ' . $k);
        $q = $this->db()->table('publikasi as p')
            ->leftJoin('users as u', 'u.us_id', '=', 'p.us_id')
            ->when($tahun, fn($q) => $q->where('p.pub_tahun', $tahun)) // null = semua tahun
            ->select('p.pub_id', 'p.pub_tahun', 'p.pub_judul', 'p.pub_judul_en', 'p.pub_tgl_rilis', 'p.pub_status', 'p.pub_rilis',
                'p.pub_issn', 'u.us_name', $kolom('pub_no'), $kolom('pub_volume'), $kolom('pub_no_katalog'), $kolom('pub_abstraksi'),
                $kolom('pub_abstraksi_en'), $kolom('pub_file_berkas'));
        if ($cari = trim((string) $cari)) {
            $q->where(fn($w) => $w->where('p.pub_judul', 'like', "%{$cari}%")->orWhere('p.pub_judul_en', 'like', "%{$cari}%")
                ->orWhere('u.us_name', 'like', "%{$cari}%"));
        }
        $pub = $q->get();
        if ($pub->isEmpty()) return [];

        $versi = $this->db()->table('transaksi')
            ->whereIn('pub_id', $pub->pluck('pub_id'))
            ->whereNotNull('tr_file_upload')->where('tr_file_upload', '!=', '')
            ->orderByDesc('tr_id')
            ->get(['tr_id', 'pub_id', 'tr_flag', 'tr_file_upload', 'tr_tanggal_upload_sm', 'tr_tanggal_upload_ipds', 'tr_file_revisi'])
            ->groupBy('pub_id');
        $tertaut = $schema->hasTable('pasca_rilis')
            ? $this->db()->table('pasca_rilis')->whereIn('pub_id', $pub->pluck('pub_id'))
                ->whereNotNull('pub_id_api')->where('pub_id_api', '!=', '')->distinct()->pluck('pub_id')->map(fn($i) => (int) $i)->flip()
            : collect();

        $portal = \App\Models\PraRilisPortal::untuk($pub->pluck('pub_id')); // ceklis langkah 5 (di pubchecker)

        $daftar = $pub->map(fn($p) => [
            'pub_id'    => (int) $p->pub_id,
            'judul'     => trim((string) $p->pub_judul),
            'judul_en'  => trim((string) $p->pub_judul_en) ?: null,
            'tgl_rilis' => $this->tanggal($p->pub_tgl_rilis),
            'pjk'       => $p->us_name ?: null,
            'status'    => $p->pub_status ?: null,
            'rilis'     => (int) $p->pub_rilis === 1,
            'issn'      => trim((string) $p->pub_issn) ?: null,
            'tahun'     => $p->pub_tahun ? (int) $p->pub_tahun : null,
            'nomor'     => ($n = trim((string) $p->pub_no)) !== '' && $n !== '-' ? $n : null,
            'volume'    => ($v = trim((string) $p->pub_volume)) !== '' && $v !== '-' ? $v : null,
            'versi'     => ($versi[$p->pub_id] ?? collect())->map(fn($t) => [
                'tr_id'   => (int) $t->tr_id,
                'flag'    => $t->tr_flag ?: null,
                'tanggal' => $this->waktu($t->tr_tanggal_upload_sm),
                'pdf'     => strtolower(pathinfo((string) $t->tr_file_upload, PATHINFO_EXTENSION)) === 'pdf',
            ])->values()->all(),
        ])->map(fn($p) => $p + ['tenggat' => $this->tenggat($p['tgl_rilis'])])
          ->map(function ($d) use ($pub, $versi, $tertaut, $portal) {
              $p = $pub->firstWhere('pub_id', $d['pub_id']);
              $berkas = json_decode((string) $p->pub_file_berkas, true) ?: [];
              return $d + ['langkah' => $this->langkah([
                  'isian'     => $this->isianWajib($p), 'abstraksi' => $this->isianAbstraksi($p),
                  'versi'     => ($versi[$p->pub_id] ?? collect())->map(fn($t) => [
                      'tr_id' => (int) $t->tr_id, 'flag' => $t->tr_flag ?: null, 'temuan' => null,
                      'pdf' => strtolower(pathinfo((string) $t->tr_file_upload, PATHINFO_EXTENSION)) === 'pdf',
                      'diperiksa' => (bool) ($this->waktu($t->tr_tanggal_upload_ipds) || trim((string) $t->tr_file_revisi) !== ''),
                  ])->values()->all(),
                  'sprp'      => $this->hasilBerkas($berkas, 'spr', 'sprp', 'SPRP'),
                  'rilis'     => $d['rilis'],
                  'tertaut'   => isset($tertaut[$d['pub_id']]),
                  'portal'    => isset($portal[$d['pub_id']]),
                  'tenggat'   => $d['tenggat'],
                  'tgl_rilis' => $d['tgl_rilis'],
              ])];
          });

        // nomor urut per tahun publikasi; dengan kata kunci daftarnya tidak lengkap → tidak dicek
        $cek = $cari ? [] : $daftar->groupBy('tahun')->map(fn($g) => $this->cekUrutNomor($g->all()))->reduce(fn($a, $b) => $a + $b, []);
        $daftar = $daftar->map(fn($d) => $d + ['cek_nomor' => $cek[$d['pub_id']] ?? null]);

        // terbaru dulu: upload draf terakhir, lalu (yang belum upload) rencana rilis
        return $daftar->sortByDesc(fn($p) => [($p['versi'][0]['tanggal'] ?? null) ?: '', $p['tgl_rilis'] ?: ''])
            ->values()->all();
    }

    /** Angka urut nomor publikasi: kelompok angka terakhir (mis. "63000.2605" → 2605, "No. 12/2026" → 12 bila tahun di akhir). */
    public static function urutNomor(?string $nomor): ?int
    {
        if (!$nomor || !preg_match_all('/\d+/', $nomor, $m)) return null;
        $angka = $m[0];
        // "12/2026": kelompok terakhir = tahun → pakai yang sebelumnya
        if (count($angka) > 1 && preg_match('/^(19|20)\d{2}$/', end($angka))) array_pop($angka);
        return (int) end($angka);
    }

    /**
     * Nomor publikasi harus urut sesuai tanggal rilis (diisi admin/pemeriksa di langkah 1). Untuk tiap publikasi bernomor:
     * 'ganda' (nomor sama dipakai publikasi lain), atau 'urut' (nomornya lebih kecil dari publikasi yang rilisnya lebih awal).
     *
     * @return array<int, array{masalah:string, pesan:string}>  pub_id => masalah
     */
    private function cekUrutNomor(array $rows): array
    {
        $ada = collect($rows)->filter(fn($r) => $r['nomor'] && $r['tgl_rilis'] && self::urutNomor($r['nomor']) !== null)
            ->map(fn($r) => $r + ['_k' => self::urutNomor($r['nomor'])])
            ->sortBy(fn($r) => [$r['tgl_rilis'], $r['_k']])->values();
        $hasil = [];
        $ganda = $ada->groupBy(fn($r) => mb_strtolower($r['nomor']))->filter(fn($g) => $g->count() > 1);
        foreach ($ganda as $g) foreach ($g as $r) {
            $lain = $g->where('pub_id', '!=', $r['pub_id'])->first();
            $hasil[$r['pub_id']] = ['masalah' => 'ganda', 'pesan' => "Nomor {$r['nomor']} juga dipakai \"{$lain['judul']}\""];
        }
        $maks = null; // publikasi bernomor terbesar di antara yang rilisnya lebih awal
        foreach ($ada->groupBy('tgl_rilis') as $tgl => $hari) {
            foreach ($hari as $r) {
                if ($maks && $r['_k'] < $maks['_k'] && !isset($hasil[$r['pub_id']])) {
                    $hasil[$r['pub_id']] = ['masalah' => 'urut', 'pesan' => "Nomor {$r['nomor']} lebih kecil dari {$maks['nomor']} (\"{$maks['judul']}\", rilis lebih awal "
                        . date('d/m/Y', strtotime($maks['tgl_rilis'])) . ')'];
                }
            }
            $top = $hari->sortByDesc('_k')->first();
            if (!$maks || $top['_k'] > $maks['_k']) $maks = $top;
        }
        return $hasil;
    }

    /**
     * Publikasi yang belum rilis dengan rencana rilis dari $mundur hari lalu s.d. $maju hari ke depan (lintas tahun),
     * beserta tahap & tenggat berikutnya — untuk panel "Segera Rilis". Urut rencana rilis terdekat dulu.
     *
     * tahap: belum_upload | menunggu_periksa (versi terakhir belum diperiksa IPDS) | revisi (sudah diperiksa, menunggu
     *        perbaikan/final PJK) | menunggu_sprp (final, SPRP belum diterima) | siap_rilis (SPRP diterima)
     * level: danger (lewat tenggat) | warning (tenggat ≤ 3 hari / masa kirim draf) | info | ok
     */
    public function segera(int $maju = 30, int $mundur = 30): array
    {
        $hariIni = date('Y-m-d');
        $dari    = date('Y-m-d', strtotime("-{$mundur} days"));
        $sampai  = date('Y-m-d', strtotime("+{$maju} days"));
        $schema  = $this->db()->getSchemaBuilder();
        $kolom   = fn(string $k) => $schema->hasColumn('publikasi', $k) ? 'p.' . $k : DB::raw('0 as ' . $k);

        // pub_tahun = tahun publikasi (bisa beda dengan tahun rilis) → ambil rentang lebar, saring tanggal di PHP
        $pub = $this->db()->table('publikasi as p')
            ->leftJoin('users as u', 'u.us_id', '=', 'p.us_id')
            ->where(fn($w) => $w->whereNull('p.pub_rilis')->orWhere('p.pub_rilis', '!=', 1))
            ->whereBetween('p.pub_tahun', [(int) substr($dari, 0, 4) - 1, (int) substr($sampai, 0, 4) + 1])
            ->get(['p.pub_id', 'p.pub_judul', 'p.pub_tgl_rilis', 'p.pub_status', 'p.pub_rilis', $kolom('pub_final'), $kolom('pub_sprp'), 'u.us_name'])
            ->map(fn($p) => tap($p, fn($p) => $p->rilis = $this->tanggal($p->pub_tgl_rilis)))
            ->filter(fn($p) => $p->rilis && $p->rilis >= $dari && $p->rilis <= $sampai);

        return $this->susunTahap($pub);
    }

    /**
     * Tahap & tenggat publikasi-publikasi tertentu (mis. yang ditugaskan), termasuk yang sudah rilis (tahap "rilis").
     * Bentuk baris sama dengan segera(); kunci = pub_id.
     */
    public function tahapPublikasi(array $pubIds): array
    {
        $pubIds = array_values(array_unique(array_filter(array_map('intval', $pubIds))));
        if (!$pubIds) return [];
        $schema = $this->db()->getSchemaBuilder();
        $kolom  = fn(string $k) => $schema->hasColumn('publikasi', $k) ? 'p.' . $k : DB::raw('0 as ' . $k);
        $pub = $this->db()->table('publikasi as p')
            ->leftJoin('users as u', 'u.us_id', '=', 'p.us_id')
            ->whereIn('p.pub_id', $pubIds)
            ->get(['p.pub_id', 'p.pub_judul', 'p.pub_tgl_rilis', 'p.pub_status', 'p.pub_rilis', $kolom('pub_final'), $kolom('pub_sprp'), 'u.us_name'])
            ->map(fn($p) => tap($p, fn($p) => $p->rilis = $this->tanggal($p->pub_tgl_rilis)));

        return collect($this->susunTahap($pub))->keyBy('pub_id')->all();
    }

    /** Tahap, tenggat berikutnya & level tiap publikasi (lihat segera()); urut rencana rilis terdekat dulu. */
    private function susunTahap(\Illuminate\Support\Collection $pub): array
    {
        if ($pub->isEmpty()) return [];
        $hariIni = date('Y-m-d');

        $trx = $this->db()->table('transaksi')->whereIn('pub_id', $pub->pluck('pub_id'))
            ->whereNotNull('tr_file_upload')->where('tr_file_upload', '!=', '')
            ->orderByDesc('tr_id')
            ->get(['tr_id', 'pub_id', 'tr_flag', 'tr_tanggal_upload_sm', 'tr_tanggal_upload_ipds', 'tr_file_revisi'])
            ->groupBy('pub_id');

        $sisa = fn(?string $t) => $t ? (int) round((strtotime($t) - strtotime($hariIni)) / 86400) : null;

        return $pub->map(function ($p) use ($trx, $hariIni, $sisa) {
            $versi = $trx[$p->pub_id] ?? collect();
            $akhir = $versi->first();
            $tg    = $this->tenggat($p->rilis);
            $diperiksaIpds = $akhir && ($this->waktu($akhir->tr_tanggal_upload_ipds) || trim((string) $akhir->tr_file_revisi) !== '');

            $tahap = match (true) {
                (int) ($p->pub_rilis ?? 0) === 1 => 'rilis',
                !$akhir                    => 'belum_upload',
                (int) $p->pub_sprp === 1   => 'siap_rilis',
                (int) $p->pub_final === 1  => 'menunggu_sprp',
                !$diperiksaIpds            => 'menunggu_periksa',
                default                    => 'revisi',
            };
            // tenggat berikutnya per tahap: draf → batas kirim (H-7); sesudahnya → final & SPRP (H-3); siap → rilis
            [$label, $batas] = match ($tahap) {
                'belum_upload' => ['Batas kirim draf', $tg['h7'] ?? null],
                'siap_rilis', 'rilis' => ['Rencana rilis', $p->rilis],
                default        => ['Final & SPRP', $tg['h3'] ?? null],
            };
            $n = $sisa($batas);
            $lewatRilis = $p->rilis && $p->rilis < $hariIni && $tahap !== 'rilis';
            $level = match (true) {
                $tahap === 'rilis'                            => 'ok',
                $lewatRilis                                   => 'danger',
                $tahap === 'siap_rilis'                       => 'ok',
                $n !== null && $n < 0                         => 'danger',
                $tahap === 'belum_upload' && $tg && $hariIni >= $tg['h10'] => 'warning',
                $n !== null && $n <= 3                        => 'warning',
                default                                       => 'info',
            };
            if ($lewatRilis) [$label, $batas, $n] = ['Rencana rilis', $p->rilis, $sisa($p->rilis)];

            return [
                'pub_id'      => (int) $p->pub_id,
                'judul'       => trim((string) $p->pub_judul),
                'pjk'         => $p->us_name ?: null,
                'arc'         => $p->pub_status === 'arc',
                'tgl_rilis'   => $p->rilis,
                'sisa_rilis'  => $sisa($p->rilis),
                'tenggat'     => $tg,
                'tahap'       => $tahap,
                'level'       => $level,
                'batas_label' => $label,
                'batas'       => $batas,
                'sisa'        => $n,
                'versi'       => $akhir ? ($akhir->tr_flag ?: 'v?') : null,
                'tr_id'       => $akhir ? (int) $akhir->tr_id : null,
                'upload'      => $akhir ? $this->waktu($akhir->tr_tanggal_upload_sm) : null,
            ];
        })->sortBy(fn($r) => $r['tgl_rilis'] ?? '9999')->values()->all();
    }

    /**
     * Riwayat notifikasi SIPOTRET (tabel notifikasi), terbaru dulu. Peran penerima dibanding publikasinya:
     * pjk (publikasi.us_id), ketua (publikasi.spv_id), admin (users.us_username = 'admin', tujuan insert_notifikasi('admin')).
     *
     * @param ?string $peran pjk | admin | ketua | null (semua)
     * @return array{total:int, items:array}
     */
    public function notifikasi(?int $pubId = null, ?string $peran = null, ?string $cari = null, int $batas = 100, int $lewati = 0): array
    {
        $schema = $this->db()->getSchemaBuilder();
        if (!$schema->hasTable('notifikasi')) return ['total' => 0, 'items' => []];
        $spv = $schema->hasColumn('publikasi', 'spv_id');

        $q = $this->db()->table('notifikasi as n')
            ->leftJoin('users as u', 'u.us_id', '=', 'n.us_id')
            ->leftJoin('publikasi as p', 'p.pub_id', '=', 'n.nf_pub_id');
        if ($pubId) $q->where('n.nf_pub_id', $pubId);
        match ($peran) {
            'pjk'   => $q->whereColumn('n.us_id', 'p.us_id'),
            'ketua' => $spv ? $q->whereColumn('n.us_id', 'p.spv_id') : $q->whereRaw('1 = 0'),
            'admin' => $q->where('u.us_username', 'admin'),
            default => null,
        };
        if ($cari = trim((string) $cari)) {
            $q->where(fn($w) => $w->where('n.nf_judul', 'like', "%{$cari}%")->orWhere('n.nf_pesan', 'like', "%{$cari}%")
                ->orWhere('p.pub_judul', 'like', "%{$cari}%")->orWhere('u.us_name', 'like', "%{$cari}%"));
        }
        $total = (clone $q)->count();

        $rows = $q->orderByDesc('n.nf_id')->offset($lewati)->limit($batas)->get(array_merge(
            ['n.nf_id', 'n.us_id', 'n.nf_judul', 'n.nf_pesan', 'n.nf_data', 'n.nf_flag', 'n.nf_pub_id', 'n.nf_tanggal',
             'u.us_name', 'u.us_username', 'p.pub_judul', 'p.us_id as pjk_id'],
            $spv ? ['p.spv_id'] : []));

        $ikon = ['upload' => 'ti-upload', 'check' => 'ti-check', 'undo' => 'ti-arrow-back-up', 'times' => 'ti-x',
                 'close' => 'ti-x', 'file' => 'ti-file', 'edit' => 'ti-pencil', 'pencil' => 'ti-pencil', 'warning' => 'ti-alert-triangle'];
        $warna = ['available' => 'green', 'success' => 'green', 'warning' => 'yellow', 'danger' => 'red', 'busy' => 'red', 'info' => 'azure'];

        return ['total' => $total, 'items' => $rows->map(function ($n) use ($ikon, $warna) {
            $data  = json_decode((string) $n->nf_data, true) ?: [];
            $badge = (string) ($data['badge'] ?? '');
            $peran = match (true) {
                $n->pjk_id && (int) $n->us_id === (int) $n->pjk_id                 => 'PJK',
                !empty($n->spv_id) && (int) $n->us_id === (int) $n->spv_id         => 'Ketua tim',
                $n->us_username === 'admin'                                        => 'Admin',
                default                                                            => null,
            };
            return [
                'id'       => (int) $n->nf_id,
                'tanggal'  => $this->waktu($n->nf_tanggal),
                'judul'    => trim((string) $n->nf_judul),
                'pesan'    => trim((string) $n->nf_pesan),
                'penerima' => $n->us_name ?: ($n->us_id ? '#' . $n->us_id : '-'),
                'peran'    => $peran,
                'dibaca'   => (int) $n->nf_flag === 1,
                'pub_id'   => $n->nf_pub_id ? (int) $n->nf_pub_id : null,
                'pub'      => $n->pub_judul ? trim((string) $n->pub_judul) : null,
                'ikon'     => collect($ikon)->first(fn($v, $k) => str_contains($badge, 'fa-' . $k)) ?? 'ti-bell',
                'warna'    => $warna[$data['color'] ?? ''] ?? 'blue',
            ];
        })->all()];
    }

    /**
     * Tenggat seperti di SIPOTRET (PublikasiModel::getReminderMilestones): hari kerja sebelum rencana rilis,
     * melewati Sabtu/Minggu & hari libur di setting SIPOTRET.
     *
     * @return array{h10:?string, h7:?string, h3:?string}|null  h10 = awal kirim draf, h7 = hari terakhir
     *         draf dikirim, h3 = publikasi final & SPRP
     */
    public function tenggat(?string $rilis): ?array
    {
        if (!$rilis || !($ts = strtotime($rilis))) return null;
        $libur = array_flip($this->hariLibur(date('Y', $ts)));
        $mundur = function (int $hari) use ($ts, $libur): string {
            $t = $ts;
            while ($hari > 0) {
                $t = strtotime('-1 day', $t);
                if (date('N', $t) >= 6 || isset($libur[date('Y-m-d', $t)])) continue;
                $hari--;
            }
            return date('Y-m-d', $t);
        };
        return ['h10' => $mundur(10), 'h7' => $mundur(7), 'h3' => $mundur(3)];
    }

    /** Tanggal libur satu tahun: setting HOLIDAY_CALENDAR_JSON + HOLIDAY_MANUAL_DATES SIPOTRET. */
    private function hariLibur(string $tahun): array
    {
        static $cache = [];
        if (isset($cache[$tahun])) return $cache[$tahun];

        try {
            $set = $this->db()->table('setting')->whereIn('set_key', ['HOLIDAY_CALENDAR_JSON', 'HOLIDAY_MANUAL_DATES'])
                ->pluck('set_val', 'set_key');
        } catch (\Throwable $e) {
            report($e);
            $set = collect();
        }
        $tgl = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $libur = [];

        foreach ((array) json_decode((string) ($set['HOLIDAY_CALENDAR_JSON'] ?? ''), true) as $k => $v) {
            if (is_int($k) && $tgl($v)) {
                $libur[] = $v;
            } elseif ($tgl($k)) {
                $ya = is_array($v) ? (!array_key_exists('holiday', $v) || filter_var($v['holiday'], FILTER_VALIDATE_BOOLEAN))
                    : (is_bool($v) ? $v : true);
                if ($ya) $libur[] = $k;
            } elseif (is_array($v) && ($d = $tgl((string) ($v['date'] ?? $v['tanggal'] ?? '')))
                && filter_var($v['holiday'] ?? $v['is_holiday'] ?? $v['isHoliday'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $libur[] = $d;
            }
        }
        foreach ((array) json_decode((string) ($set['HOLIDAY_MANUAL_DATES'] ?? '[]'), true) as $k => $v) {
            if ($d = is_int($k) ? $tgl($v) : $tgl($k)) $libur[] = $d;
        }

        return $cache[$tahun] = array_values(array_unique(array_filter($libur, fn($d) => str_starts_with($d, $tahun . '-'))));
    }

    /**
     * Detail satu publikasi: identitas (nomor, katalog, ISSN, PJK, ketua tim), abstraksi, tenggat, dan semua
     * versi upload beserta hasil pemeriksaan IPDS di SIPOTRET (transaksi_item + catatan lainnya). null bila tidak ada.
     */
    public function detail(int $pubId): ?array
    {
        $p = $this->db()->table('publikasi')->where('pub_id', $pubId)->first();
        if (!$p) return null;
        $schema = $this->db()->getSchemaBuilder();
        $nilai  = fn($v) => ($v = trim((string) $v)) !== '' && $v !== '-' ? $v : null;
        $user   = fn($id) => $id ? $this->db()->table('users')->where('us_id', $id)->value('us_name') : null;

        $katalog = ($no = $nilai($p->pub_no_katalog ?? null)) && $schema->hasTable('no_katalog')
            ? $this->db()->table('no_katalog')->where('pub_no_katalog', $no)->first() : null;
        $issn = ($i = $nilai($p->pub_issn ?? null)) && $schema->hasTable('issn')
            ? $this->db()->table('issn')->where('pub_issn', $i)->first() : null;

        $trx = $this->db()->table('transaksi')->where('pub_id', $pubId)->orderByDesc('tr_id')->get();
        $item = collect();
        if ($trx->isNotEmpty() && $schema->hasTable('transaksi_item')) {
            $ket = $schema->hasColumn('transaksi_item', 'item_keterangan') ? 'ti.item_keterangan' : DB::raw('NULL as item_keterangan');
            $item = $this->db()->table('transaksi_item as ti')
                ->leftJoin('item_periksa as ip', 'ip.item_id', '=', 'ti.item_id')
                ->whereIn('ti.tr_id', $trx->pluck('tr_id'))->orderBy('ti.item_id')
                ->get(['ti.tr_id', 'ti.item_catatan', $ket, 'ip.item_nama', 'ip.item_kategori', 'ip.item_flag_level'])
                ->groupBy('tr_id');
        }

        $rilis  = $this->tanggal($p->pub_tgl_rilis ?? null);
        $berkas = json_decode((string) ($p->pub_file_berkas ?? ''), true);
        $berkas = is_array($berkas) ? $berkas : [];
        // satu versi sudah diperiksa IPDS bila ada tanggal/temuan/file revisi, atau sudah ada versi sesudahnya
        $idTerbaru = (int) ($trx->first()->tr_id ?? 0);
        $portal = \App\Models\PraRilisPortal::untuk([$pubId])->first(); // ceklis langkah 5 (di pubchecker)
        return [
            'pub_id'       => (int) $p->pub_id,
            'judul'        => trim((string) $p->pub_judul),
            'judul_en'     => $nilai($p->pub_judul_en ?? null),
            'tahun'        => $p->pub_tahun ?? null,
            'nomor'        => $nilai($p->pub_no ?? null),
            'volume'       => $nilai($p->pub_volume ?? null),
            'katalog'      => $nilai($p->pub_no_katalog ?? null),
            'nama_katalog' => $katalog->pub_nama_katalog ?? null,
            'nama_katalog_en' => $katalog->pub_nama_en_katalog ?? null,
            'issn'         => $nilai($p->pub_issn ?? null),
            'issn_link'    => $nilai($issn->issn_link ?? null),
            'status'       => $p->pub_status ?? null,
            'pjk'          => $user($p->us_id ?? null),
            'ketua_tim'    => $user($p->spv_id ?? null),
            'pjk_id'       => ($p->us_id ?? null) ? (int) $p->us_id : null,
            'ketua_tim_id' => ($p->spv_id ?? null) ? (int) $p->spv_id : null,
            'tgl_rilis'    => $rilis,
            'tenggat'      => $this->tenggat($rilis),
            'rilis'        => (int) ($p->pub_rilis ?? 0) === 1,
            'final'        => (int) ($p->pub_final ?? 0) === 1,
            'abstraksi'    => $nilai($p->pub_abstraksi ?? null),
            'abstraksi_en' => $nilai($p->pub_abstraksi_en ?? null),
            'versi'        => $trx->map(fn($t) => [
                'tr_id'        => (int) $t->tr_id,
                'flag'         => $t->tr_flag ?: null,
                'tanggal'      => $this->waktu($t->tr_tanggal_upload_sm ?? null),
                'ada_file'     => (bool) $nilai($t->tr_file_upload ?? null),
                'pdf'          => strtolower(pathinfo((string) ($t->tr_file_upload ?? ''), PATHINFO_EXTENSION)) === 'pdf',
                'catatan_pjk'  => $nilai($t->tr_catatan ?? null),
                'tanggal_ipds' => $this->waktu($t->tr_tanggal_upload_ipds ?? null),
                'ada_revisi'   => (bool) $nilai($t->tr_file_revisi ?? null),
                // temuan IPDS: item master + keterangan, lalu catatan lainnya (custom / tr_catatan_ipds)
                'temuan'       => ($item[$t->tr_id] ?? collect())->map(fn($i) => [
                    'item'       => trim((string) ($i->item_nama ?: $i->item_catatan)),
                    'kategori'   => $i->item_kategori ?? null,
                    'level'      => $i->item_flag_level ? strtoupper($i->item_flag_level) : null,
                    'keterangan' => $nilai($i->item_keterangan),
                ])->concat(collect([
                    ($c = $nilai($t->tr_item_periksa_custom ?? null))
                        ? ['item' => $c, 'kategori' => 'Lainnya', 'level' => ($l = $nilai($t->tr_item_flag_level ?? null)) ? strtoupper($l) : null, 'keterangan' => null] : null,
                    ($ipds = $nilai(strip_tags((string) ($t->tr_catatan_ipds ?? '')))) && strcasecmp($ipds, (string) $c) !== 0
                        ? ['item' => $ipds, 'kategori' => 'Lainnya', 'level' => null, 'keterangan' => null] : null,
                ])->filter())->values()->all(),
                'final'        => str_contains((string) $t->tr_flag, 'final'),
                'ada_versi_baru' => (int) $t->tr_id !== $idTerbaru,
            ])->all(),
            'kelengkapan'  => [
                // pub_file_berkas: {file_spr, sprp: 0|1, status: "<teks status terakhir>"}; SPM tidak dipakai lagi
                'status' => $nilai($berkas['status'] ?? null),
                'berkas' => collect(['spr' => ['SPRP', 'Surat Persetujuan Rilis', 'sprp', 'Tim Diseminasi (IPDS)']])
                    ->map(fn($b, $jenis) => [
                        'jenis'    => $jenis,
                        'label'    => $b[0],
                        'nama'     => $b[1],
                        'pemeriksa'=> $b[3],
                        'ada_file' => (bool) $nilai($berkas["file_{$jenis}"] ?? null),
                        'ext'      => strtolower(pathinfo((string) ($berkas["file_{$jenis}"] ?? ''), PATHINFO_EXTENSION)) ?: null,
                        'hasil'    => $this->hasilBerkas($berkas, $jenis, $b[2], $b[0]),
                    ])->values()->all(),
            ],
            // publikasi yang sudah rilis & tercatat di Pasca Rilis SIPOTRET (pub_id_api Web API BPS)
            'pasca'        => $pasca = $this->pascaRilis((int) $p->pub_id, trim((string) $p->pub_judul)),
            'langkah'      => $this->langkah([
                'isian'     => $this->isianWajib($p), 'abstraksi' => $this->isianAbstraksi($p),
                'versi'     => $trx->filter(fn($t) => trim((string) $t->tr_file_upload) !== '')->map(fn($t) => [
                    'tr_id' => (int) $t->tr_id, 'flag' => $t->tr_flag ?: null,
                    'temuan' => ($item[$t->tr_id] ?? collect())->count() + (trim((string) ($t->tr_item_periksa_custom ?? '')) !== '' ? 1 : 0),
                    'pdf' => strtolower(pathinfo((string) $t->tr_file_upload, PATHINFO_EXTENSION)) === 'pdf',
                    'diperiksa' => (bool) ($this->waktu($t->tr_tanggal_upload_ipds ?? null) || trim((string) ($t->tr_file_revisi ?? '')) !== ''),
                ])->values()->all(),
                'sprp'      => $this->hasilBerkas($berkas, 'spr', 'sprp', 'SPRP'),
                'rilis'     => (int) ($p->pub_rilis ?? 0) === 1,
                'tertaut'   => (bool) $pasca,
                'portal'    => (bool) $portal,
                'tenggat'   => $this->tenggat($rilis),
                'tgl_rilis' => $rilis,
            ]),
            'peristiwa'    => $this->peristiwa((int) $p->pub_id, $trx, $pasca),
            // ceklis langkah 5: sudah diupload ke portal (oleh, waktu) atau null
            'portal'       => $portal ? ['oleh' => $portal->user?->full_name ?? $portal->user?->name, 'waktu' => $portal->created_at?->format('Y-m-d H:i:s')] : null,
        ];
    }

    /**
     * Tanggal kejadian penting satu publikasi di SIPOTRET (urut waktu): upload draf tiap versi, hasil pemeriksaan
     * IPDS, berkas SPRP (dari notifikasi), rilis, tayang di Web BPS. Notifikasi yang tanggalnya tidak tercatat
     * (insert_notifikasi SIPOTRET menulis teks ke kolom unix timestamp) dilewati.
     *
     * @return array<int, array{kode:string, waktu:string, judul:string, ikon:string, warna:string}>
     */
    private function peristiwa(int $pubId, $trx, array $pasca): array
    {
        $e = [];
        foreach ($trx as $t) {
            $flag = explode('-', (string) ($t->tr_flag ?: 'v?'))[0];
            if (trim((string) $t->tr_file_upload) !== '' && ($w = $this->waktu($t->tr_tanggal_upload_sm ?? null))) {
                $e[] = ['kode' => 'draf', 'waktu' => $w, 'judul' => "Draf {$flag} diupload", 'ikon' => 'ti-upload', 'warna' => 'orange'];
            }
            if ($w = $this->waktu($t->tr_tanggal_upload_ipds ?? null)) {
                $e[] = ['kode' => 'periksa', 'waktu' => $w, 'judul' => "Hasil periksa {$flag} dikirim ke PJK", 'ikon' => 'ti-clipboard-check', 'warna' => 'blue'];
            }
        }

        if ($this->db()->getSchemaBuilder()->hasTable('notifikasi')) {
            // judul notifikasi SIPOTRET / pubchecker → kejadian; yang sudah terwakili transaksi (draf, feedback) tidak diambil
            $peta = [
                'SM mengupload kelengkapan'   => ['sprp_upload', 'SPRP diupload PJK', 'ti-file-upload', 'orange'],
                'SPRP diupload admin'         => ['sprp_upload', 'SPRP diupload admin', 'ti-file-upload', 'orange'],
                'Berkas diterima'             => ['sprp_terima', 'SPRP diterima', 'ti-file-certificate', 'green'],
                'Berkas ditolak'              => ['sprp_tolak', 'SPRP ditolak', 'ti-file-x', 'red'],
                'Publikasi final dan rilis'   => ['rilis', 'Ditandai final & rilis', 'ti-rocket', 'green'],
                'Publikasi dirilis'           => ['rilis', 'Ditandai rilis', 'ti-rocket', 'green'],
                'dikembalikan untuk revisi'   => ['kembali', 'Dikembalikan untuk revisi', 'ti-arrow-back-up', 'red'],
                'Publikasi dicetak'           => ['cetak', 'Publikasi dicetak', 'ti-printer', 'secondary'],
            ];
            $notif = $this->db()->table('notifikasi')->where('nf_pub_id', $pubId)->orderBy('nf_id')->get(['nf_judul', 'nf_tanggal']);
            foreach ($notif as $n) {
                $m = collect($peta)->first(fn($v, $k) => stripos((string) $n->nf_judul, $k) !== false);
                if ($m && ($w = $this->waktu($n->nf_tanggal))) {
                    $e[] = ['kode' => $m[0], 'waktu' => $w, 'judul' => $m[1], 'ikon' => $m[2], 'warna' => $m[3]];
                }
            }
        }
        foreach ($pasca as $r) {
            if ($r['tgl_rilis']) {
                $e[] = ['kode' => 'web', 'waktu' => $r['tgl_rilis'], 'judul' => 'Tayang di Web BPS', 'ikon' => 'ti-world', 'warna' => 'green'];
            }
        }
        // notifikasi yang dikirim ke beberapa penerima (PJK & admin) → satu kejadian
        return collect($e)->unique(fn($x) => $x['kode'] . '|' . substr($x['waktu'], 0, 16))
            ->sortBy('waktu')->values()->all();
    }

    private function terisi($v): bool
    {
        return ($v = trim(strip_tags((string) $v))) !== '' && $v !== '-';
    }

    /** Isian kelengkapan publikasi (langkah 1, diisi pemeriksa/admin): label => terisi? */
    private function isianWajib(object $p): array
    {
        return [
            'Nomor publikasi' => $this->terisi($p->pub_no ?? null),
            'Nomor katalog'   => $this->terisi($p->pub_no_katalog ?? null),
            'Judul (EN)'      => $this->terisi($p->pub_judul_en ?? null),
            // ISSN tidak wajib: ada publikasi yang memang tidak punya ISSN
        ];
    }

    /** Abstraksi yang diisi PJK di SIPOTRET (bagian langkah 2): label => terisi? */
    private function isianAbstraksi(object $p): array
    {
        return [
            'abstraksi'     => $this->terisi($p->pub_abstraksi ?? null),
            'abstract (EN)' => $this->terisi($p->pub_abstraksi_en ?? null),
        ];
    }

    /**
     * Enam langkah pra rilis satu publikasi, supaya tidak ada yang terlewat:
     *  1 pemeriksa isi kelengkapan (nomor, katalog, judul EN; ISSN opsional) · 2 PJK upload draf & isi abstraksi · 3 pemeriksa
     *  memeriksa · 4 pemeriksa terima SPRP (siap rilis) · 5 pemeriksa upload ke portal publikasi BPS (H-3, sama dengan
     *  Final & SPRP) · 6 pemeriksa tandai rilis & tautkan ke publikasi Web BPS.
     * aksi: kelengkapan | sipotret | periksa | sprp | portal | rilis | null
     * status: selesai | aktif (langkah berikutnya) | terlewat (belum selesai padahal langkah sesudahnya sudah) | menunggu
     *
     * @param array $d isian, abstraksi, versi (terbaru dulu: tr_id, flag, pdf, diperiksa, temuan), sprp, rilis, tertaut, tenggat, tgl_rilis
     */
    public function langkah(array $d): array
    {
        $hariIni = date('Y-m-d');
        $tg      = $d['tenggat'] ?? null;
        $akhir   = $d['versi'][0] ?? null;
        $kurang  = array_keys(array_filter($d['isian'], fn($v) => !$v));
        $absKurang = array_keys(array_filter($d['abstraksi'] ?? [], fn($v) => !$v));
        $temuan  = $akhir['temuan'] ?? null;
        $portalBeres = !empty($d['portal']) || !empty($d['tertaut']);
        $final   = ($d['sprp'] ?? null) === 'diterima' || !empty($d['rilis'])
            || collect($d['versi'])->contains(fn($v) => str_contains((string) ($v['flag'] ?? ''), 'final'));

        $l = [
            [1, 'Isi kelengkapan', 'Pemeriksa', !$kurang,
             $kurang ? 'Belum diisi: ' . implode(', ', $kurang) : 'Nomor, katalog & judul EN lengkap',
             null, 'kelengkapan'],
            // sudah final → draf dianggap tuntas (tidak merah) walau abstraksi belum lengkap; kekurangannya tetap disebut
            [2, 'Upload draf & abstraksi', 'PJK', $akhir && (!$absKurang || $final),
             trim(($akhir ? count($d['versi']) . ' versi, terakhir ' . ($akhir['flag'] ?? 'v?') : 'PJK belum upload draf')
                 . ($absKurang ? ' · ' . ucfirst(implode(' & ', $absKurang)) . ' belum diisi' : ' · abstraksi lengkap')),
             $tg['h7'] ?? null, 'sipotret'],
            // sudah final (SPRP diterima / rilis) → pemeriksaan draf dianggap tuntas walau versi terakhir tidak diperiksa
            [3, 'Periksa draf', 'Pemeriksa', $akhir && ($akhir['diperiksa'] || $final),
             !$akhir ? 'Menunggu draf dari PJK'
                : (!$akhir['diperiksa'] && $final ? 'Sudah final' . (($akhir['flag'] ?? null) ? ', ' . $akhir['flag'] . ' tidak diperiksa' : '')
                : (!$akhir['diperiksa'] ? ($akhir['flag'] ?? 'Versi terakhir') . ' belum diperiksa'
                : ($akhir['flag'] ?? 'Versi terakhir') . ' sudah diperiksa' . ($temuan ? " · {$temuan} temuan, PJK upload perbaikan bila perlu" : ''))),
             $tg['h3'] ?? null, $akhir && !$akhir['diperiksa'] && !$final && $akhir['pdf'] ? 'periksa' : null],
            [4, 'Terima SPRP & finalkan', 'Pemeriksa', ($d['sprp'] ?? null) === 'diterima',
             match ($d['sprp'] ?? null) {
                 'diterima' => 'SPRP diterima, publikasi siap rilis',
                 'menunggu' => 'SPRP sudah diupload, perlu diterima / ditolak',
                 'ditolak'  => 'SPRP ditolak, menunggu PJK upload ulang',
                 default    => 'PJK belum upload SPRP',
             },
             $tg['h3'] ?? null, ($d['sprp'] ?? null) === 'menunggu' ? 'sprp' : null],
            // selesai lewat ceklis admin/pemeriksa (pra_rilis_portal), atau otomatis bila sudah tertaut ke Web BPS
            [5, 'Upload ke portal publikasi BPS', 'Pemeriksa', $portalBeres,
             !empty($d['portal']) ? 'Ditandai sudah diupload ke portal' . ($d['tertaut'] ? ' & tertaut di Web BPS' : '')
                : ($d['tertaut'] ? 'Sudah tayang & tertaut di Web BPS' : 'Upload PDF final ke portal, lalu centang sudah diupload'),
             $d['rilis'] ? null : ($tg['h3'] ?? null), $portalBeres ? null : 'portal'],
            [6, 'Finalkan & tautkan ke Web BPS', 'Pemeriksa', (bool) $d['rilis'],
             $d['rilis'] ? ($d['tertaut'] ? 'Ditandai rilis & tertaut ke Pasca Rilis' : 'Ditandai rilis tanpa tautan Web BPS')
                : 'Tandai final & sudah rilis, pilih publikasi Web BPS-nya',
             $d['tgl_rilis'] ?? null, $d['rilis'] ? null : 'rilis'],
        ];

        $terakhirSelesai = 0;
        foreach ($l as $x) if ($x[3]) $terakhirSelesai = $x[0];
        $sekarang = null;
        $hasil = array_map(function ($x) use (&$sekarang, $terakhirSelesai, $hariIni) {
            [$no, $judul, $oleh, $selesai, $ket, $batas, $aksi] = $x;
            $status = match (true) {
                $selesai                 => 'selesai',
                $no < $terakhirSelesai   => 'terlewat',
                $sekarang === null       => 'aktif',
                default                  => 'menunggu',
            };
            if ($status === 'aktif') $sekarang = $no;
            return [
                'no' => $no, 'judul' => $judul, 'oleh' => $oleh, 'status' => $status, 'ket' => $ket,
                'batas' => $selesai ? null : $batas, 'lewat' => !$selesai && $batas && $batas < $hariIni,
                'aksi' => $selesai ? null : $aksi,
            ];
        }, $l);

        return [
            'langkah'  => $hasil,
            'sekarang' => $sekarang,
            'selesai'  => count(array_filter($l, fn($x) => $x[3])),
            'terlewat' => count(array_filter($hasil, fn($x) => $x['status'] === 'terlewat')),
            'tenggat'  => $this->tenggatBerikut($d, $akhir),
        ];
    }

    /**
     * Tenggat terdekat yang harus dikejar (untuk diingat): batas kirim draf H-7 → final, SPRP & upload portal H-3 → rilis.
     * null bila sudah rilis / rencana rilis belum diisi. sisa = hari kalender dari hari ini (negatif = lewat).
     */
    private function tenggatBerikut(array $d, ?array $akhir): ?array
    {
        $tg = $d['tenggat'] ?? null;
        if (!empty($d['rilis']) || !($d['tgl_rilis'] ?? null)) return null;
        [$label, $tgl, $kode] = match (true) {
            !$akhir => ['Batas kirim draf', $tg['h7'] ?? null, 'H-7'],
            // H-3 terpenuhi bila SPRP diterima dan upload portal sudah dicentang (atau sudah tertaut)
            ($d['sprp'] ?? null) !== 'diterima' || (empty($d['portal']) && empty($d['tertaut']))
                => ['Final, SPRP & upload portal', $tg['h3'] ?? null, 'H-3'],
            default => ['Rilis', $d['tgl_rilis'], 'H'],
        };
        if (!$tgl) return null;
        $sisa = (int) round((strtotime($tgl) - strtotime(date('Y-m-d'))) / 86400);
        return ['label' => $label, 'kode' => $kode, 'tgl' => $tgl, 'sisa' => $sisa,
                'level' => $sisa < 0 ? 'danger' : ($sisa <= 3 ? 'warning' : 'primary')];
    }

    /** Master nomor katalog & ISSN SIPOTRET (pilihan form kelengkapan). */
    public function masterKelengkapan(): array
    {
        $schema = $this->db()->getSchemaBuilder();
        return [
            'katalog' => $schema->hasTable('no_katalog')
                ? $this->db()->table('no_katalog')->orderBy('pub_no_katalog')->get(['pub_no_katalog', 'pub_nama_katalog'])
                    ->mapWithKeys(fn($k) => [(string) $k->pub_no_katalog => trim((string) $k->pub_nama_katalog)])->all()
                : [],
            'issn' => $schema->hasTable('issn')
                ? $this->db()->table('issn')->orderBy('pub_issn')->get(['pub_issn', 'issn_judul'])
                    ->mapWithKeys(fn($i) => [(string) $i->pub_issn => trim((string) $i->issn_judul)])->all()
                : [],
            // pilihan PJK & ketua tim: semua user kecuali akun admin, seperti form edit publikasi SIPOTRET
            'users' => $this->daftarUser(),
        ];
    }

    /** User SIPOTRET untuk PJK / ketua tim: us_id => "nama · email" (tanpa akun admin), urut nama. */
    private function daftarUser(): array
    {
        $schema = $this->db()->getSchemaBuilder();
        if (!$schema->hasTable('users')) return [];
        $email = $schema->hasColumn('users', 'us_email');
        return $this->db()->table('users')->where('us_id', '!=', 1)
            ->where(fn($q) => $q->whereNull('us_username')->orWhere('us_username', '!=', 'admin'))
            ->orderBy('us_name')->get(array_merge(['us_id', 'us_name'], $email ? ['us_email'] : []))
            ->mapWithKeys(fn($u) => [(string) $u->us_id => trim((string) $u->us_name)
                . ($email && trim((string) $u->us_email) !== '' ? ' · ' . trim((string) $u->us_email) : '')])->all();
    }

    /**
     * Simpan identitas & kelengkapan publikasi ke SIPOTRET, seperti form Publikasi SIPOTRET (PublikasiModel::add): nomor &
     * katalog kosong disimpan "-", katalog harus dari master; judul, pub_status (arc|non_arc), us_id (PJK) & spv_id
     * (ketua tim, boleh kosong) diubah hanya bila dikirim. Abstraksi tidak disentuh (langkah 2).
     *
     * @throws \RuntimeException
     */
    public function simpanKelengkapan(int $pubId, array $d): string
    {
        if (!$this->db()->table('publikasi')->where('pub_id', $pubId)->exists()) {
            throw new \RuntimeException('Publikasi tidak ditemukan di SIPOTRET.');
        }
        $schema  = $this->db()->getSchemaBuilder();
        $teks    = fn($k) => trim((string) ($d[$k] ?? ''));
        $katalog = $teks('pub_no_katalog') ?: '-';
        if ($katalog !== '-' && $schema->hasTable('no_katalog')
            && !$this->db()->table('no_katalog')->where('pub_no_katalog', $katalog)->exists()) {
            throw new \RuntimeException('Nomor katalog tidak terdaftar di master SIPOTRET. Pilih dari daftar.');
        }
        // ISSN opsional, tapi bila diisi harus dari master ISSN (seperti pilihan ISSN di form publikasi SIPOTRET)
        if (($issn = $teks('pub_issn')) !== '' && $schema->hasTable('issn')
            && !$this->db()->table('issn')->where('pub_issn', $issn)->exists()) {
            throw new \RuntimeException('ISSN tidak terdaftar di master SIPOTRET. Pilih dari daftar, atau kosongkan bila tidak punya ISSN.');
        }
        $data = [
            'pub_no'           => $teks('pub_no') ?: '-',
            'pub_volume'       => $teks('pub_volume') ?: null,
            'pub_no_katalog'   => $katalog,
            'pub_issn'         => $teks('pub_issn'),
            'pub_judul_en'     => $teks('pub_judul_en'),
        ];
        // identitas (judul, jenis ARC, PJK, ketua tim): hanya diubah bila ikut dikirim
        if (array_key_exists('pub_judul', $d)) {
            if ($teks('pub_judul') === '') throw new \RuntimeException('Judul publikasi tidak boleh kosong.');
            $data['pub_judul'] = $teks('pub_judul');
        }
        if (array_key_exists('pub_status', $d)) {
            if (!in_array($teks('pub_status'), ['arc', 'non_arc'], true)) throw new \RuntimeException('Jenis publikasi harus ARC atau Non ARC.');
            $data['pub_status'] = $teks('pub_status');
        }
        $userAda = fn($id) => $this->db()->table('users')->where('us_id', (int) $id)->exists();
        if (array_key_exists('us_id', $d)) {
            if ($teks('us_id') === '' || !$userAda($teks('us_id'))) throw new \RuntimeException('PJK harus dipilih dari daftar user SIPOTRET.');
            $data['us_id'] = (int) $teks('us_id');
        }
        if (array_key_exists('spv_id', $d)) {
            if ($teks('spv_id') !== '' && !$userAda($teks('spv_id'))) throw new \RuntimeException('Ketua tim harus dipilih dari daftar user SIPOTRET.');
            $data['spv_id'] = $teks('spv_id') !== '' ? (int) $teks('spv_id') : null;
        }
        $data = array_filter($data, fn($k) => $schema->hasColumn('publikasi', $k), ARRAY_FILTER_USE_KEY);
        foreach (['pub_no', 'pub_volume', 'pub_no_katalog', 'pub_issn', 'pub_judul_en', 'pub_judul'] as $k) {
            if (isset($data[$k])) $data[$k] = $this->potong('publikasi', $k, $data[$k]);
        }
        $this->db()->table('publikasi')->where('pub_id', $pubId)->update($data);
        return 'Identitas & kelengkapan publikasi disimpan ke SIPOTRET.';
    }

    /**
     * Tambah publikasi baru ke SIPOTRET, seperti PublikasiModel::add (form input publikasi): pub_tahun dari tahun
     * rencana rilis, nomor/katalog kosong "-", kolom lain memakai default tabel. Isian dicek seperti simpanKelengkapan
     * (katalog & ISSN dari master, PJK & ketua tim user SIPOTRET). Mengembalikan pub_id baru.
     *
     * @param array $d pub_judul*, pub_tgl_rilis* (Y-m-d), pub_status, us_id*, spv_id, pub_judul_en, pub_no, pub_volume, pub_no_katalog, pub_issn
     * @throws \RuntimeException
     */
    public function tambahPublikasi(array $d): int
    {
        $judul = trim((string) ($d['pub_judul'] ?? ''));
        $rilis = trim((string) ($d['pub_tgl_rilis'] ?? ''));
        if ($judul === '') throw new \RuntimeException('Judul publikasi wajib diisi.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rilis) || !strtotime($rilis)) throw new \RuntimeException('Rencana rilis wajib diisi (tanggal).');
        $schema = $this->db()->getSchemaBuilder();
        $sama = $this->db()->table('publikasi')->where('pub_tahun', substr($rilis, 0, 4))->whereRaw('LOWER(TRIM(pub_judul)) = ?', [mb_strtolower($judul)])->value('pub_id');
        if ($sama) throw new \RuntimeException("Publikasi dengan judul ini sudah ada di SIPOTRET tahun " . substr($rilis, 0, 4) . " (pub_id {$sama}).");

        return $this->db()->transaction(function () use ($d, $judul, $rilis, $schema) {
            $now  = date('Y-m-d H:i:s');
            $baru = array_filter([
                'pub_judul'      => $this->potong('publikasi', 'pub_judul', $judul),
                'pub_tgl_rilis'  => $rilis,
                'pub_tahun'      => substr($rilis, 0, 4),
                'pub_no'         => '-',
                'pub_no_katalog' => '-',
                'pub_issn'       => '',
                'pub_status'     => 'non_arc',
                'pub_created_at' => $now,
                'pub_updated_at' => $now,
            ], fn($v, $k) => $schema->hasColumn('publikasi', $k), ARRAY_FILTER_USE_BOTH);
            $pubId = (int) $this->db()->table('publikasi')->insertGetId($baru, 'pub_id');
            // sisanya lewat simpanKelengkapan supaya pengecekannya sama (melempar → transaksi dibatalkan)
            $this->simpanKelengkapan($pubId, array_intersect_key($d, array_flip(
                ['pub_no', 'pub_volume', 'pub_no_katalog', 'pub_issn', 'pub_judul_en', 'pub_judul', 'pub_status', 'us_id', 'spv_id'])));
            return $pubId;
        });
    }

    /**
     * Simpan abstraksi (ID & EN) ke SIPOTRET, sama dengan PublikasiModel::saveAbstraksi (teks polos, di-trim).
     * Biasanya diisi PJK; admin/pemeriksa bisa melengkapi atau membetulkan.
     *
     * @throws \RuntimeException
     */
    public function simpanAbstraksi(int $pubId, ?string $id, ?string $en): string
    {
        if (!$this->db()->table('publikasi')->where('pub_id', $pubId)->exists()) {
            throw new \RuntimeException('Publikasi tidak ditemukan di SIPOTRET.');
        }
        $schema = $this->db()->getSchemaBuilder();
        $data = array_filter(['pub_abstraksi' => trim((string) $id), 'pub_abstraksi_en' => trim((string) $en)],
            fn($k) => $schema->hasColumn('publikasi', $k), ARRAY_FILTER_USE_KEY);
        foreach ($data as $k => $v) $data[$k] = $this->potong('publikasi', $k, $v);
        if ($data) $this->db()->table('publikasi')->where('pub_id', $pubId)->update($data);
        return 'Abstraksi disimpan ke SIPOTRET.';
    }

    /**
     * Hasil pemeriksaan satu berkas kelengkapan: 'diterima' (flag = 1), 'ditolak' (flag 0 & status terakhir
     * "Berkas X Ditolak"), 'menunggu' (file ada, belum diputus), atau null (belum diupload).
     */
    private function hasilBerkas(array $berkas, string $jenis, string $flag, string $label): ?string
    {
        if ((int) ($berkas[$flag] ?? 0) === 1) return 'diterima';
        if (trim((string) ($berkas["file_{$jenis}"] ?? '')) === '') return null;
        $status = mb_strtolower((string) ($berkas['status'] ?? ''));
        return str_contains($status, 'ditolak') && str_contains($status, mb_strtolower($label)) ? 'ditolak' : 'menunggu';
    }

    /** Baris pasca_rilis untuk publikasi ini: lewat pub_id, atau (bila belum terhubung) judul yang sama. */
    private function pascaRilis(int $pubId, string $judul): array
    {
        if (!$this->db()->getSchemaBuilder()->hasTable('pasca_rilis')) return [];
        $q = fn() => $this->db()->table('pasca_rilis')->whereNotNull('pub_id_api')->where('pub_id_api', '!=', '')
            ->orderByDesc('id');
        $baris = $q()->where('pub_id', $pubId)->get(['pub_id_api', 'pub_wilayah', 'pub_title', 'pub_tanggal_rilis']);
        if ($baris->isEmpty() && $judul !== '') {
            $baris = $q()->where('pub_title', $judul)->get(['pub_id_api', 'pub_wilayah', 'pub_title', 'pub_tanggal_rilis']);
        }
        return $baris->unique('pub_id_api')->map(fn($r) => [
            'pub_id_api' => (string) $r->pub_id_api,
            'domain'     => $r->pub_wilayah ? (string) $r->pub_wilayah : null,
            'judul'      => $r->pub_title,
            'tgl_rilis'  => $this->tanggal($r->pub_tanggal_rilis),
        ])->values()->all();
    }

    /** Berkas kelengkapan SPRP (spr) satu publikasi: objek untuk ambilFile(…, 'berkas_path'), atau null. SPM tidak dipakai lagi. */
    public function berkas(int $pubId, string $jenis): ?object
    {
        if ($jenis !== 'spr') return null;
        $p = $this->db()->table('publikasi')->where('pub_id', $pubId)->first(['pub_judul', 'pub_file_berkas']);
        $b = $p ? json_decode((string) $p->pub_file_berkas, true) : null;
        $path = is_array($b) ? ltrim(trim((string) ($b["file_{$jenis}"] ?? '')), './') : '';
        if ($path === '') return null;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'pdf';
        return (object) [
            'berkas_path' => $path,
            'ext'         => $ext,
            'nama_file'   => preg_replace('/\.pdf$/', '', $this->namaFile($p->pub_judul, null)) . ' - ' . ($jenis === 'spr' ? 'SPRP' : 'SPM') . ".{$ext}",
        ];
    }

    /** Satu versi draf beserta identitas publikasinya, atau null. */
    /** pub_id SIPOTRET pemilik satu versi draf (di-cache: versi tidak pernah pindah publikasi); null bila tidak ada. */
    public function pubDariTr(int $trId): ?int
    {
        $pub = \Cache::remember('sipotret_tr_pub:' . $trId, now()->addDays(7),
            fn() => (int) $this->db()->table('transaksi')->where('tr_id', $trId)->value('pub_id'));
        return $pub ?: null;
    }

    public function draf(int $trId): ?object
    {
        $d = $this->db()->table('transaksi as t')
            ->join('publikasi as p', 'p.pub_id', '=', 't.pub_id')
            ->where('t.tr_id', $trId)
            ->first(['t.tr_id', 't.tr_flag', 't.tr_file_upload', 't.tr_file_revisi', 'p.pub_id', 'p.pub_judul', 'p.pub_tgl_rilis',
                'p.pub_issn', 'p.pub_no', 'p.pub_no_katalog']);
        if (!$d) return null;
        $d->tgl_rilis = $this->tanggal($d->pub_tgl_rilis);
        $d->nama_file = $this->namaFile($d->pub_judul, $d->tr_flag);
        return $d;
    }

    /**
     * Isian SIPOTRET sebagai pembanding cek sama_api (ISSN, nomor katalog, nomor publikasi) — format sama dengan
     * isian Web API di BpsImportController::dataApi(). "-" / kosong = belum diisi di SIPOTRET (kriteria jadi Skip).
     */
    public function dataPembanding(object $draf): array
    {
        $isi = fn($v) => ($v = trim((string) $v)) !== '' && $v !== '-' ? $v : null;
        return [
            'issn'            => $isi($draf->pub_issn ?? null),
            'katalog'         => $isi($draf->pub_no_katalog ?? null),
            'nomor_publikasi' => $isi($draf->pub_no ?? null),
        ];
    }

    /** Nama file PDF hasil pemeriksaan: "<judul> (v2).pdf". */
    public function namaFile(?string $judul, ?string $flag): string
    {
        $judul = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', (string) $judul)) ?: 'Draf publikasi';
        $judul = trim(preg_replace('/\s+/u', ' ', mb_substr($judul, 0, 150)), ' .');
        return $judul . ($flag ? " ({$flag})" : '') . '.pdf';
    }

    /**
     * Isi file draf. Mengembalikan ['path' => file lokal, 'hapus' => bool (file sementara)].
     *
     * @throws \RuntimeException bila file tidak bisa diambil
     */
    public function ambilFile(object $draf, string $kolom = 'tr_file_upload'): array
    {
        $path = trim((string) ($draf->{$kolom} ?? ''));
        if ($path === '' || str_contains($path, '..') || preg_match('#^([a-z][a-z0-9+.-]*:|[\\\\/])#i', $path)) {
            throw new \RuntimeException('Path file draf di SIPOTRET tidak valid.');
        }

        // draf upload PJK tidak pernah ditimpa (versi baru = nama file baru) → unduhan disimpan di cache server;
        // SPRP/SPM & file revisi IPDS bisa ditimpa dengan nama yang sama, jadi selalu diambil ulang
        $cache = $kolom === 'tr_file_upload';

        if (str_starts_with($path, 'owncloud/')) {
            return $this->dariCache($path, $cache, fn() => $this->unduhOwnCloud(substr($path, 9)));
        }

        // fallback lokal SIPOTRET (folder public-nya): satu server → baca langsung, selain itu lewat URL
        if ($akar = realpath((string) config('services.sipotret.file_path'))) {
            foreach ([$akar . DIRECTORY_SEPARATOR . 'public', $akar] as $dasar) {
                $file = realpath($dasar . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
                if ($file && str_starts_with($file, $akar . DIRECTORY_SEPARATOR) && is_file($file)) {
                    return ['path' => $file, 'hapus' => false];
                }
            }
        }
        if ($url = rtrim((string) config('services.sipotret.file_url'), '/')) {
            return $this->dariCache($path, $cache, fn() => $this->unduh($url . '/' . $this->encodePath($path)));
        }
        throw new \RuntimeException('File draf tidak ditemukan di SIPOTRET.');
    }

    /**
     * File dari penyimpanan SIPOTRET lewat cache storage/app/sipotret-draf (bila $cache): unduhan pertama bisa lama
     * (OwnCloud di luar jaringan BPS), berikutnya langsung. Isi dicek benar PDF — OwnCloud bisa membalas halaman
     * login HTML dengan status 200 bila akun salah. Cache yang tidak dipakai 30 hari dibuang.
     */
    private function dariCache(string $path, bool $cache, \Closure $unduh): array
    {
        $pdf = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
        $cek = function (string $file) use ($pdf) {
            if ($pdf && file_get_contents($file, false, null, 0, 5) !== '%PDF-') {
                @unlink($file);
                throw new \RuntimeException('Yang diterima dari penyimpanan SIPOTRET bukan file PDF (akun OwnCloud salah atau file sudah dihapus?).');
            }
        };
        if (!$cache) {
            $tmp = $unduh();
            $cek($tmp);
            return ['path' => $tmp, 'hapus' => true];
        }

        $dir  = storage_path('app/sipotret-draf');
        $file = $dir . DIRECTORY_SEPARATOR . sha1($path) . '.' . (strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin');
        if (is_file($file) && filesize($file) > 0) {
            @touch($file);
            return ['path' => $file, 'hapus' => false];
        }
        $tmp = $unduh();
        $cek($tmp);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!@rename($tmp, $file) && !(@copy($tmp, $file) && @unlink($tmp))) {
            return ['path' => $tmp, 'hapus' => true]; // cache tidak bisa ditulis: tetap kirim filenya
        }
        if (random_int(1, 20) === 1) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $lama) {
                if (is_file($lama) && filemtime($lama) < time() - 30 * 86400) @unlink($lama);
            }
        }
        return ['path' => $file, 'hapus' => false];
    }

    /**
     * Kirim hasil pemeriksaan draf ke SIPOTRET sebagai feedback IPDS pada versi upload itu (meniru
     * PublikasiModel::addTransaksiIpds): file PDF hasil pemeriksaan → transaksi.tr_file_revisi (folder ipds),
     * temuan item master → transaksi_item (+ keterangan), temuan lainnya → tr_catatan_ipds / tr_item_periksa_custom,
     * tr_tanggal_upload_ipds = sekarang, lalu notifikasi ke PJK. Temuan IPDS sebelumnya pada versi ini diganti.
     *
     * @param  array $temuan  dari SipotretService::temuanItem()
     * @return array{master:int, lainnya:int, file:bool}
     * @throws \RuntimeException
     */
    public function kirimHasil(int $trId, array $temuan, ?\Illuminate\Http\UploadedFile $file, string $oleh): array
    {
        $trx = $this->db()->table('transaksi as t')->join('publikasi as p', 'p.pub_id', '=', 't.pub_id')
            ->where('t.tr_id', $trId)->first(['t.tr_id', 't.pub_id', 'p.pub_judul', 'p.us_id']);
        if (!$trx) throw new \RuntimeException('Versi draf ini sudah tidak ada di SIPOTRET.');
        $schema = $this->db()->getSchemaBuilder();

        // catatan tambahan yang isinya sama dengan kriteria Tidak Sesuai (mis. komentar di PDF untuk temuan yang
        // sama) tidak dikirim dua kali
        $norm  = fn($s) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $s)));
        $ketKriteria = collect($temuan)->where('kode', '!=', 'Tambahan')->map(fn($t) => $norm($t['keterangan']))->filter()->all();
        $temuan = array_filter($temuan, fn($t) => $t['kode'] !== 'Tambahan' || $t['item_id']
            || !in_array($norm($t['keterangan']), $ketKriteria, true) || mb_strlen($norm($t['keterangan'])) < 8);

        // item master (satu baris per item; keterangan digabung) & item lainnya
        $master  = [];
        $lainnya = [];
        foreach ($temuan as $t) {
            $ket = trim((string) $t['keterangan']);
            if ($t['item_id']) {
                $m = $master[$t['item_id']] ?? ['item' => $t['item'], 'ket' => []];
                if ($ket !== '') $m['ket'][] = $ket;
                $master[$t['item_id']] = $m;
            } else {
                $lainnya[] = ['teks' => trim($t['nama']) . ($ket !== '' ? ": {$ket}" : ''), 'flag' => $t['flag']];
            }
        }
        // satu kolom flag untuk semua item lainnya: level tertinggi (SIPOTRET: minor / moderate / major)
        $urut = ['minor' => 1, 'moderate' => 2, 'major' => 3];
        $flag = collect($lainnya)->pluck('flag')->map(fn($f) => strtolower((string) $f))->filter(fn($f) => isset($urut[$f]))
            ->sortByDesc(fn($f) => $urut[$f])->first() ?? ($lainnya ? 'minor' : null);
        $teksLain = implode('; ', array_column($lainnya, 'teks'));

        $path = $file ? $this->uploadIpds($trx, $file) : null;

        $this->db()->transaction(function () use ($trx, $schema, $master, $teksLain, $flag, $path) {
            $isi = [
                'tr_catatan_ipds'        => $this->potong('transaksi', 'tr_catatan_ipds', $teksLain),
                'tr_tanggal_upload_ipds' => time(), // SIPOTRET menyimpan unix timestamp
            ];
            if ($schema->hasColumn('transaksi', 'tr_item_periksa_custom')) {
                $isi['tr_item_periksa_custom'] = $teksLain !== '' ? $this->potong('transaksi', 'tr_item_periksa_custom', $teksLain) : null;
            }
            if ($schema->hasColumn('transaksi', 'tr_item_flag_level')) $isi['tr_item_flag_level'] = $flag;
            if ($path) $isi['tr_file_revisi'] = $path;
            $this->db()->table('transaksi')->where('tr_id', $trx->tr_id)->update($isi);

            if ($schema->hasTable('transaksi_item')) {
                $adaKet = $schema->hasColumn('transaksi_item', 'item_keterangan');
                $this->db()->table('transaksi_item')->where('tr_id', $trx->tr_id)->delete();
                foreach ($master as $itemId => $m) {
                    $i = $m['item'];
                    // item_catatan seperti PublikasiModel::buildItemCatatanText: "nama - deskripsi (LEVEL)"
                    $catatan = trim($i->item_nama . ($i->item_deskripsi ? ' - ' . $i->item_deskripsi : '')
                        . ($i->item_flag_level ? ' (' . strtoupper($i->item_flag_level) . ')' : ''));
                    $this->db()->table('transaksi_item')->insert(['tr_id' => $trx->tr_id, 'item_id' => $itemId, 'item_catatan' => $catatan]
                        + ($adaKet ? ['item_keterangan' => implode('; ', array_unique($m['ket']))] : []));
                }
            }

            if ($trx->us_id && $schema->hasTable('notifikasi')) {
                $this->db()->table('notifikasi')->insert([
                    'us_id'     => (int) $trx->us_id,
                    'nf_judul'  => 'Tim Diseminasi memberikan feedback',
                    'nf_pesan'  => 'Publikasi ' . $trx->pub_judul . ' diberikan feedback oleh Tim Diseminasi',
                    'nf_data'   => json_encode(['badge' => 'fa fa-check', 'color' => 'away']),
                    'nf_link'   => rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $trx->pub_id,
                    'nf_flag'   => 0,
                    'nf_pub_id' => (int) $trx->pub_id,
                    'nf_tanggal'=> time(),
                ]);
            }
        });

        return ['master' => count($master), 'lainnya' => count($lainnya), 'file' => $path];
    }

    /**
     * Terima / tolak berkas SPRP oleh Tim Diseminasi, sama dengan Pengecekan::terimaBerkas / tolakBerkas ('sprp')
     * di SIPOTRET: status di pub_file_berkas, flag transaksi berkas_sprp, status publikasi (terima: siap rilis),
     * lalu notifikasi ke PJK.
     *
     * @throws \RuntimeException
     */
    public function putusSprp(int $pubId, bool $terima): string
    {
        $pub = $this->db()->table('publikasi')->where('pub_id', $pubId)->first();
        if (!$pub) throw new \RuntimeException('Publikasi tidak ditemukan di SIPOTRET.');
        $berkas = json_decode((string) $pub->pub_file_berkas, true);
        $berkas = is_array($berkas) ? $berkas : [];
        if (trim((string) ($berkas['file_spr'] ?? '')) === '') {
            throw new \RuntimeException('File SPRP belum diupload PJK.');
        }
        if ($terima && (int) ($berkas['sprp'] ?? 0) === 1) {
            throw new \RuntimeException('Berkas SPRP sudah diterima sebelumnya.');
        }

        $this->db()->transaction(function () use ($pub, $berkas, $terima) {
            $berkas['status'] = 'Berkas SPRP ' . ($terima ? 'Diterima' : 'Ditolak');
            $berkas['sprp']   = $terima ? 1 : 0;
            // tr_flag "…berkas_sprp…" → sprp_final (terima) / final (tolak), seperti SIPOTRET
            $this->db()->update('UPDATE transaksi SET tr_flag = REPLACE(tr_flag, ?, ?) WHERE tr_flag LIKE ? AND pub_id = ?',
                ['berkas_sprp', $terima ? 'sprp_final' : 'final', '%berkas_sprp%', (int) $pub->pub_id]);
            $this->db()->table('publikasi')->where('pub_id', $pub->pub_id)->update(['pub_file_berkas' => json_encode($berkas)]
                + ($terima ? ['pub_sprp' => 1, 'pub_acc' => 1, 'pub_flag' => 'rilis', 'pub_cetak' => 'rilis', 'pub_berkas' => 1] : []));

            if ($pub->us_id && $this->db()->getSchemaBuilder()->hasTable('notifikasi')) {
                $this->db()->table('notifikasi')->insert([
                    'us_id'     => (int) $pub->us_id,
                    'nf_judul'  => 'Berkas ' . ($terima ? 'diterima' : 'ditolak') . ' Tim Diseminasi',
                    'nf_pesan'  => $terima
                        ? 'Berkas publikasi ' . $pub->pub_judul . ' (SPRP) diterima Tim Diseminasi. Publikasi siap untuk diupload di simpub'
                        : 'Berkas publikasi ' . $pub->pub_judul . ' SPRP ditolak Tim Diseminasi. Silahkan diperbaiki',
                    'nf_data'   => json_encode($terima ? ['badge' => 'fa fa-check', 'color' => 'available'] : ['badge' => 'fa fa-time', 'color' => 'busy']),
                    'nf_link'   => rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $pub->pub_id,
                    'nf_flag'   => 0,
                    'nf_pub_id' => (int) $pub->pub_id,
                    'nf_tanggal'=> time(), // kolom unix timestamp (seperti Publikasi.php SIPOTRET)
                ]);
            }
        });

        return $terima ? 'Berkas SPRP diterima; publikasi siap rilis dan PJK diberi notifikasi.'
                       : 'Berkas SPRP ditolak; PJK diberi notifikasi untuk memperbaiki.';
    }

    /**
     * Tandai publikasi final & sudah rilis, sama dengan Pengecekan::setFinalPasca SIPOTRET (tombol Set Final/Rilis):
     * versi terpilih → tr_flag "<flag>-final"; publikasi pub_flag=rilis, pub_final/pub_acc/pub_rilis=1; bila publikasi
     * Web API BPS dipilih → pasca_rilis (pub_id_api, wilayah) + periode pasca_rilis_history; notifikasi ke PJK.
     *
     * @param array $api ['pub_id' => id Web API, 'domain' => kode wilayah, 'judul' => ?, 'tgl_rilis' => ?] atau [] (tanpa Web API)
     * @throws \RuntimeException
     */
    public function tandaiRilis(int $trId, array $api, int $bulan, int $tahun): string
    {
        $trx = $this->db()->table('transaksi as t')->join('publikasi as p', 'p.pub_id', '=', 't.pub_id')
            ->where('t.tr_id', $trId)->first(['t.tr_id', 't.tr_flag', 'p.pub_id', 'p.pub_judul', 'p.pub_issn', 'p.us_id']);
        if (!$trx) throw new \RuntimeException('Versi draf ini sudah tidak ada di SIPOTRET.');
        $schema = $this->db()->getSchemaBuilder();
        $now    = date('Y-m-d H:i:s');
        $pubIdApi = trim((string) ($api['pub_id'] ?? ''));

        $this->db()->transaction(function () use ($trx, $schema, $now, $pubIdApi, $api, $bulan, $tahun) {
            $this->db()->table('transaksi')->where('tr_id', $trx->tr_id)
                ->update(['tr_flag' => explode('-', (string) $trx->tr_flag)[0] . '-final']);
            $this->db()->table('publikasi')->where('pub_id', $trx->pub_id)
                ->update(['pub_flag' => 'rilis', 'pub_final' => 1, 'pub_acc' => 1, 'pub_rilis' => 1]);

            if ($pubIdApi !== '' && $schema->hasTable('pasca_rilis')) {
                $waktu = fn(string $t) => $schema->hasColumn($t, 'created_at') ? ['created_at' => $now, 'updated_at' => $now] : [];
                $pasca = $this->db()->table('pasca_rilis')->where('pub_id_api', $pubIdApi)->first();
                if (!$pasca) {
                    $pascaId = $this->db()->table('pasca_rilis')->insertGetId(array_filter([
                        'pub_id'            => (int) $trx->pub_id,
                        'pub_id_api'        => $pubIdApi,
                        'pub_wilayah'       => (int) $api['domain'],
                        // judul/tanggal/ISSN dari Web API & SIPOTRET (setFinalPasca mengosongkannya; diisi nanti)
                        'pub_title'         => $api['judul'] ?? null,
                        'pub_tanggal_rilis' => $api['tgl_rilis'] ?? null,
                        'pub_issn'          => trim((string) $trx->pub_issn) ?: null,
                    ], fn($v) => $v !== null && $v !== '') + $waktu('pasca_rilis'));
                } else {
                    $pascaId = (int) $pasca->id;
                    $ubah = array_filter([
                        'pub_id'      => empty($pasca->pub_id) ? (int) $trx->pub_id : null,
                        'pub_wilayah' => (int) ($pasca->pub_wilayah ?? 0) !== (int) $api['domain'] ? (int) $api['domain'] : null,
                    ]);
                    if ($ubah) $this->db()->table('pasca_rilis')->where('id', $pascaId)->update($ubah + array_intersect_key($waktu('pasca_rilis'), ['updated_at' => 1]));
                }
                $ada = $this->db()->table('pasca_rilis_history')->where(['pasca_id' => $pascaId, 'bulan' => $bulan, 'tahun' => $tahun])->exists();
                if (!$ada) {
                    $this->db()->table('pasca_rilis_history')->insert(['pasca_id' => $pascaId, 'bulan' => $bulan, 'tahun' => $tahun] + $waktu('pasca_rilis_history'));
                }
            }

            if ($trx->us_id && $schema->hasTable('notifikasi')) {
                $this->db()->table('notifikasi')->insert([
                    'us_id'     => (int) $trx->us_id,
                    'nf_judul'  => 'Publikasi final dan rilis',
                    'nf_pesan'  => 'Publikasi ' . $trx->pub_judul . ' telah diset final dan dirilis oleh Tim Diseminasi',
                    'nf_data'   => json_encode(['badge' => 'fa fa-check', 'color' => 'available']),
                    'nf_link'   => rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $trx->pub_id,
                    'nf_flag'   => 0,
                    'nf_pub_id' => (int) $trx->pub_id,
                    'nf_tanggal'=> time(), // kolom unix timestamp
                ]);
            }
        });

        return 'Publikasi ditandai final & sudah rilis di SIPOTRET'
            . ($pubIdApi !== '' ? sprintf(', terhubung ke Web API BPS (pub_id %s) dan terdaftar di Pasca Rilis periode %02d/%d.', $pubIdApi, $bulan, $tahun) : '.');
    }

    /**
     * Data Pasca Rilis SIPOTRET milik publikasi (pasca_rilis.pub_id) yang ikut terhapus bila rilis dibatalkan.
     *
     * @return array{pasca: int, periode: int, catatan: int, pub_id_api: string[]}
     */
    public function hitungPasca(int $pubId): array
    {
        $kosong = ['pasca' => 0, 'periode' => 0, 'catatan' => 0, 'pub_id_api' => []];
        $schema = $this->db()->getSchemaBuilder();
        if (!$schema->hasTable('pasca_rilis')) return $kosong;

        $pasca = $this->db()->table('pasca_rilis')->where('pub_id', $pubId)->get(['id', 'pub_id_api']);
        if ($pasca->isEmpty()) return $kosong;
        $hist = $schema->hasTable('pasca_rilis_history')
            ? $this->db()->table('pasca_rilis_history')->whereIn('pasca_id', $pasca->pluck('id'))->pluck('id') : collect();
        $catatan = $hist->isNotEmpty() && $schema->hasTable('pasca_rilis_catatan')
            ? $this->db()->table('pasca_rilis_catatan')->whereIn('rilis_id', $hist)->count() : 0;

        return ['pasca' => $pasca->count(), 'periode' => $hist->count(), 'catatan' => $catatan,
                'pub_id_api' => $pasca->pluck('pub_id_api')->filter()->map(fn($v) => (string) $v)->values()->all()];
    }

    /**
     * Batalkan status rilis, sama dengan Pengecekan::revertRilis SIPOTRET: pub_rilis = 0, lalu (bila $hapusPasca)
     * hapus pasca_rilis milik publikasi beserta periode (pasca_rilis_history) dan catatannya; notifikasi ke PJK.
     * Hasil pemeriksaan di pubchecker tidak disentuh.
     *
     * @throws \RuntimeException
     */
    public function batalRilis(int $pubId, bool $hapusPasca): string
    {
        $pub = $this->db()->table('publikasi')->where('pub_id', $pubId)->first(['pub_id', 'pub_judul', 'us_id', 'pub_rilis']);
        if (!$pub) throw new \RuntimeException('Publikasi tidak ditemukan di SIPOTRET.');
        if ((int) $pub->pub_rilis !== 1) throw new \RuntimeException('Publikasi ini memang belum berstatus rilis di SIPOTRET.');
        $schema = $this->db()->getSchemaBuilder();
        $hapus  = $hapusPasca ? $this->hitungPasca($pubId) : null;

        $this->db()->transaction(function () use ($pub, $schema, $hapusPasca) {
            $this->db()->table('publikasi')->where('pub_id', $pub->pub_id)->update(['pub_rilis' => 0]);

            if ($hapusPasca && $schema->hasTable('pasca_rilis')) {
                $pascaIds = $this->db()->table('pasca_rilis')->where('pub_id', $pub->pub_id)->pluck('id');
                if ($pascaIds->isNotEmpty()) {
                    if ($schema->hasTable('pasca_rilis_history')) {
                        $histIds = $this->db()->table('pasca_rilis_history')->whereIn('pasca_id', $pascaIds)->pluck('id');
                        if ($histIds->isNotEmpty()) {
                            if ($schema->hasTable('pasca_rilis_catatan')) {
                                $this->db()->table('pasca_rilis_catatan')->whereIn('rilis_id', $histIds)->delete();
                            }
                            $this->db()->table('pasca_rilis_history')->whereIn('id', $histIds)->delete();
                        }
                    }
                    $this->db()->table('pasca_rilis')->whereIn('id', $pascaIds)->delete();
                }
            }

            if ($pub->us_id && $schema->hasTable('notifikasi')) {
                $this->db()->table('notifikasi')->insert([
                    'us_id'     => (int) $pub->us_id,
                    'nf_judul'  => 'Publikasi dikembalikan untuk revisi',
                    'nf_pesan'  => 'Publikasi ' . $pub->pub_judul . ' dibatalkan status rilisnya'
                                   . ($hapusPasca ? ' dan data pasca rilis dihapus' : ''),
                    'nf_data'   => json_encode(['badge' => 'fa fa-undo', 'color' => 'warning']),
                    'nf_link'   => rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $pub->pub_id,
                    'nf_flag'   => 0,
                    'nf_pub_id' => (int) $pub->pub_id,
                    'nf_tanggal'=> time(), // kolom unix timestamp
                ]);
            }
        });

        if (!$hapusPasca) return 'Status rilis dibatalkan di SIPOTRET; data Pasca Rilis dibiarkan. PJK diberi notifikasi.';
        return $hapus['pasca']
            ? sprintf('Status rilis dibatalkan; %d data Pasca Rilis (%d periode, %d catatan) dihapus dari SIPOTRET. PJK diberi notifikasi.',
                      $hapus['pasca'], $hapus['periode'], $hapus['catatan'])
            : 'Status rilis dibatalkan di SIPOTRET (tidak ada data Pasca Rilis). PJK diberi notifikasi.';
    }

    /**
     * Simpan PDF hasil pemeriksaan seperti uploadFileIpds SIPOTRET: "<judul>_v<n+1>_<d-m-Y>.pdf" di folder ipds
     * OwnCloud; tanpa akun OwnCloud → folder fallback lokal SIPOTRET (satu server). Mengembalikan path untuk DB.
     */
    private function uploadIpds(object $trx, \Illuminate\Http\UploadedFile $file): string
    {
        return $this->simpanBerkas($trx, $file, 'ipds');
    }

    /**
     * Simpan file ke folder SIPOTRET ($folder: sm = draf PJK, ipds = hasil pemeriksaan) dengan nama seperti
     * uploadFileSM/uploadFileIpds: "<judul>_v<n+1>_<d-m-Y>.<ext>" (n = jumlah transaksi publikasi). OwnCloud bila
     * akunnya diatur, selain itu folder fallback lokal SIPOTRET (satu server). Mengembalikan path untuk DB.
     */
    private function simpanBerkas(object $pub, \Illuminate\Http\UploadedFile $file, string $folder, ?string $akhiran = null): string
    {
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        // akhiran bawaan "_v<n+1>_<d-m-Y>" (draf/ipds); berkas kelengkapan "_SPR" seperti uploadFileKelengkapan SIPOTRET
        $akhiran ??= '_v' . ($this->db()->table('transaksi')->where('pub_id', $pub->pub_id)->count() + 1) . '_' . date('d-m-Y');
        $nama = preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace([' ', '(', ')', '[', ']', '{', '}', '&', '#', '@'], '_', (string) $pub->pub_judul))
            . $akhiran . '.' . $ext;
        $c = config('services.sipotret');

        $gagalOc = null; // alasan OwnCloud gagal (lalu ke folder lokal, seperti fallbackToLocal SIPOTRET)
        if (!empty($c['owncloud_user']) && !empty($c['owncloud_password'])) {
            $url = rtrim((string) $c['owncloud_url'], '/') . '/remote.php/dav/files/'
                . $this->encodePath($c['owncloud_user'] . '/' . trim((string) $c['owncloud_dir'], '/') . '/' . $folder . '/' . $nama);
            try {
                $res = Http::timeout(120)->connectTimeout(15)->withBasicAuth($c['owncloud_user'], $c['owncloud_password'])
                    ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')->put($url);
                if ($res->successful()) return 'owncloud/' . $folder . '/' . $nama;
                $gagalOc = 'OwnCloud membalas HTTP ' . $res->status();
            } catch (\Throwable $e) {
                $gagalOc = 'OwnCloud tidak bisa dihubungi: ' . $this->ringkasError($e->getMessage());
            }
            if (!filter_var($c['owncloud_fallback'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                throw new \RuntimeException('Upload ke OwnCloud SIPOTRET gagal — ' . $gagalOc . '.');
            }
            \Log::warning('SIPOTRET: upload OwnCloud gagal, simpan ke folder lokal', ['file' => $nama, 'sebab' => $gagalOc]);
        }

        $akar = realpath((string) ($c['file_path'] ?? ''));
        $dir  = $akar ? $akar . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'fallback' . DIRECTORY_SEPARATOR . $folder : null;
        if (!$dir || (!is_dir($dir) && !@mkdir($dir, 0775, true)) || !@copy($file->getRealPath(), $dir . DIRECTORY_SEPARATOR . $nama)) {
            throw new \RuntimeException('File tidak bisa disimpan ke SIPOTRET: '
                . ($gagalOc ? $gagalOc . ', dan' : 'akun OwnCloud belum diatur (SIPOTRET_OWNCLOUD_USER & SIPOTRET_OWNCLOUD_PASSWORD) dan')
                . ' folder lokal SIPOTRET (SIPOTRET_FILE_PATH/public/upload/fallback) tidak bisa ditulisi.');
        }
        return 'upload/fallback/' . $folder . '/' . $nama;
    }

    /**
     * Upload draf atas nama PJK (mis. PJK telat), sama dengan addTransaksiSM SIPOTRET dari admin: file ke folder sm,
     * transaksi baru tr_flag v<n+1> + tr_tanggal_upload_sm, inisialisasi pub_file_berkas pada upload pertama,
     * notifikasi "Transaksi baru dari admin" ke PJK. Mengembalikan [tr_id, pesan].
     *
     * @return array{0:int, 1:string}
     * @throws \RuntimeException
     */
    public function uploadDraf(int $pubId, \Illuminate\Http\UploadedFile $file, ?string $catatan, string $oleh): array
    {
        $pub = $this->db()->table('publikasi')->where('pub_id', $pubId)->first(['pub_id', 'pub_judul', 'us_id', 'pub_rilis', 'pub_file_berkas']);
        if (!$pub) throw new \RuntimeException('Publikasi tidak ditemukan di SIPOTRET.');
        if ((int) $pub->pub_rilis === 1) throw new \RuntimeException('Publikasi sudah rilis; batalkan rilis dulu bila perlu upload draf baru.');

        $path   = $this->simpanBerkas($pub, $file, 'sm'); // file disimpan dulu (di luar transaksi DB)
        $schema = $this->db()->getSchemaBuilder();
        $catatan = trim((string) $catatan);
        $catatan = trim(($catatan !== '' ? $catatan . "\n" : '') . '(diupload oleh ' . $oleh . ' lewat pubchecker)');

        $trId = $this->db()->transaction(function () use ($pub, $path, $catatan, $schema) {
            $n = $this->db()->table('transaksi')->where('pub_id', $pub->pub_id)->count();
            $trId = $this->db()->table('transaksi')->insertGetId([
                'pub_id'               => (int) $pub->pub_id,
                'tr_catatan'           => $this->potong('transaksi', 'tr_catatan', $catatan),
                'tr_file_upload'       => $path,
                'tr_flag'              => 'v' . ($n + 1),
                'tr_tanggal_upload_sm' => date('Y-m-d H:i:s'),
            ], 'tr_id');
            if ($n === 0 && trim((string) $pub->pub_file_berkas) === '' && $schema->hasColumn('publikasi', 'pub_file_berkas')) {
                $this->db()->table('publikasi')->where('pub_id', $pub->pub_id)
                    ->update(['pub_file_berkas' => json_encode(['sprp' => 0, 'status' => 'Input SPRP'])]);
            }
            if ($pub->us_id && $schema->hasTable('notifikasi')) {
                $this->db()->table('notifikasi')->insert([
                    'us_id'     => (int) $pub->us_id,
                    'nf_judul'  => 'Transaksi baru dari admin',
                    'nf_pesan'  => 'Admin mengirim transaksi baru untuk publikasi ' . $pub->pub_judul,
                    'nf_data'   => json_encode(['badge' => 'fa fa-upload', 'color' => 'available']),
                    'nf_link'   => rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $pub->pub_id,
                    'nf_flag'   => 0,
                    'nf_pub_id' => (int) $pub->pub_id,
                    'nf_tanggal'=> time(), // kolom unix timestamp
                ]);
            }
            return (int) $trId;
        });

        $versi = $this->db()->table('transaksi')->where('tr_id', $trId)->value('tr_flag');
        $lokasi = str_starts_with($path, 'owncloud/') ? 'OwnCloud' : 'folder lokal SIPOTRET';
        return [$trId, "Draf {$versi} diupload ke SIPOTRET ({$lokasi}) atas nama PJK; PJK diberi notifikasi."];
    }

    /**
     * Upload berkas SPRP atas nama PJK, sama dengan addKelengkapan SIPOTRET (file_spr): file "<judul>_SPR.<ext>" ke
     * folder kelengkapan, pub_file_berkas {file_spr, status "Menunggu pengecekan SPR oleh IPDS", sprp 0}, pub_sprp = 1,
     * lalu notifikasi ke PJK. Sesudahnya SPRP tinggal diterima / ditolak seperti biasa.
     *
     * @throws \RuntimeException
     */
    public function uploadSprp(int $pubId, \Illuminate\Http\UploadedFile $file, string $oleh): string
    {
        $pub = $this->db()->table('publikasi')->where('pub_id', $pubId)->first(['pub_id', 'pub_judul', 'us_id', 'pub_rilis', 'pub_file_berkas']);
        if (!$pub) throw new \RuntimeException('Publikasi tidak ditemukan di SIPOTRET.');
        $berkas = json_decode((string) $pub->pub_file_berkas, true);
        $berkas = is_array($berkas) ? $berkas : [];
        if ((int) ($berkas['sprp'] ?? 0) === 1) throw new \RuntimeException('Berkas SPRP sudah diterima; tidak perlu upload lagi.');

        $path   = $this->simpanBerkas($pub, $file, 'kelengkapan', '_SPR'); // file disimpan dulu (di luar transaksi DB)
        $schema = $this->db()->getSchemaBuilder();
        $this->db()->transaction(function () use ($pub, $berkas, $path, $schema, $oleh) {
            $berkas['file_spr'] = $path;
            $berkas['status']   = 'Menunggu pengecekan SPR oleh IPDS';
            $berkas['sprp']     = 0;
            $this->db()->table('publikasi')->where('pub_id', $pub->pub_id)
                ->update(['pub_file_berkas' => json_encode($berkas)] + ($schema->hasColumn('publikasi', 'pub_sprp') ? ['pub_sprp' => 1] : []));
            if ($pub->us_id && $schema->hasTable('notifikasi')) {
                $this->db()->table('notifikasi')->insert([
                    'us_id'     => (int) $pub->us_id,
                    'nf_judul'  => 'SPRP diupload admin',
                    'nf_pesan'  => 'Berkas SPRP publikasi ' . $pub->pub_judul . ' diupload oleh ' . $oleh . ' (Tim Diseminasi) atas nama PJK',
                    'nf_data'   => json_encode(['badge' => 'fa fa-upload', 'color' => 'available']),
                    'nf_link'   => rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $pub->pub_id,
                    'nf_flag'   => 0,
                    'nf_pub_id' => (int) $pub->pub_id,
                    'nf_tanggal'=> time(), // kolom unix timestamp
                ]);
            }
        });
        $lokasi = str_starts_with($path, 'owncloud/') ? 'OwnCloud' : 'folder lokal SIPOTRET';
        return "Berkas SPRP diupload ke SIPOTRET ({$lokasi}) atas nama PJK. Sekarang tinggal diterima atau ditolak.";
    }

    /** Potong teks selebar kolom varchar SIPOTRET (bila terbaca dari information_schema), supaya insert tidak gagal. */
    private function potong(string $tabel, string $kolom, string $teks): string
    {
        static $lebar = [];
        $k = "{$tabel}.{$kolom}";
        if (!array_key_exists($k, $lebar)) {
            try {
                $lebar[$k] = (int) $this->db()->table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = DATABASE()')
                    ->where(['TABLE_NAME' => $tabel, 'COLUMN_NAME' => $kolom])->value('CHARACTER_MAXIMUM_LENGTH') ?: null;
            } catch (\Throwable $e) {
                $lebar[$k] = null; // bukan MySQL (uji) / tak terbaca
            }
        }
        return $lebar[$k] && mb_strlen($teks) > $lebar[$k] ? mb_substr($teks, 0, $lebar[$k] - 1) . '…' : $teks;
    }

    /** Tanggal SIPOTRET bisa "Y-m-d H:i:s" atau unix timestamp (tr_tanggal_upload_ipds, data lama) → "Y-m-d H:i:s". */
    public function waktu($v): ?string
    {
        if ($v === null || $v === '' || $v === '0' || $v === 0) return null;
        $ts = is_numeric($v) ? (int) $v : strtotime((string) $v);
        // angka kecil (mis. 2026) = tanggal teks yang terpotong saat masuk kolom unix timestamp → tidak tercatat
        return $ts && $ts > 100000000 ? date('Y-m-d H:i:s', $ts) : null;
    }

    /** "sm/<file>" dari OwnCloud SIPOTRET (WebDAV), ke file sementara. */
    private function unduhOwnCloud(string $relatif): string
    {
        $c = config('services.sipotret');
        if (empty($c['owncloud_user']) || empty($c['owncloud_password'])) {
            throw new \RuntimeException('File draf ada di OwnCloud SIPOTRET, tetapi akun OwnCloud belum diatur di pubchecker '
                . '(SIPOTRET_OWNCLOUD_USER & SIPOTRET_OWNCLOUD_PASSWORD di .env).');
        }
        $url = rtrim((string) $c['owncloud_url'], '/') . '/remote.php/dav/files/'
            . $this->encodePath($c['owncloud_user'] . '/' . trim((string) $c['owncloud_dir'], '/') . '/' . $relatif);
        return $this->unduh($url, [$c['owncloud_user'], $c['owncloud_password']]);
    }

    private function unduh(string $url, ?array $auth = null): string
    {
        $tmp   = tempnam(sys_get_temp_dir(), 'sipotret_draf_');
        $host  = parse_url($url, PHP_URL_HOST);
        $mulai = microtime(true);
        try {
            $req = Http::timeout(150)->connectTimeout(10)->withOptions([
                'sink' => $tmp,
                // koneksi tersendat (mis. tertahan filter jaringan): putus bila < 2 KB/detik selama 20 detik
                'curl' => [CURLOPT_LOW_SPEED_LIMIT => 2048, CURLOPT_LOW_SPEED_TIME => 20],
            ]);
            if ($auth) $req = $req->withBasicAuth(...$auth);
            $res = $req->get($url);
        } catch (\Throwable $e) {
            @unlink($tmp);
            \Log::warning('SIPOTRET draf: unduh gagal', ['host' => $host, 'detik' => round(microtime(true) - $mulai, 1), 'error' => $e->getMessage()]);
            throw new \RuntimeException("Server pubchecker tidak bisa mengambil file dari {$host} (" . $this->ringkasError($e->getMessage())
                . '). Bila terus terjadi, kemungkinan koneksi server ke host itu diblokir/tersendat di jaringan BPS.');
        }
        \Log::info('SIPOTRET draf: unduh', ['host' => $host, 'status' => $res->status(), 'kb' => (int) (@filesize($tmp) / 1024),
            'detik' => round(microtime(true) - $mulai, 1)]);
        if (!$res->successful()) {
            @unlink($tmp);
            throw new \RuntimeException("File draf tidak bisa diambil dari {$host} (HTTP " . $res->status()
                . ($res->status() === 401 ? ', akun OwnCloud salah' : ($res->status() === 404 ? ', file tidak ada' : '')) . ').');
        }
        return $tmp;
    }

    /** Pesan cURL yang mudah dibaca: timeout / koneksi diputus / gagal terhubung. */
    private function ringkasError(string $pesan): string
    {
        return match (true) {
            (bool) preg_match('/cURL error 28|timed out|Operation too slow/i', $pesan) => 'waktu habis / terlalu lambat',
            (bool) preg_match('/cURL error (56|35)|reset by peer|SSL_ERROR_SYSCALL/i', $pesan) => 'koneksi diputus',
            (bool) preg_match('/cURL error (6|7)|resolve|Failed to connect/i', $pesan) => 'tidak bisa terhubung',
            default => mb_substr($pesan, 0, 120),
        };
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /** pub_tgl_rilis bisa "Y-m-d" atau timestamp unix (data lama) → "Y-m-d" / null. */
    private function tanggal($v): ?string
    {
        if ($v === null || $v === '' || $v === '0000-00-00') return null;
        $ts = is_numeric($v) ? (int) $v : strtotime((string) $v);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
