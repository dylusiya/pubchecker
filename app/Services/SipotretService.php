<?php

namespace App\Services;

use App\Models\DetailPemeriksaan;
use App\Models\HasilPemeriksaan;
use App\Models\KriteriaPemeriksaan;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kirim hasil verifikasi "Tidak Sesuai" ke aplikasi SIPOTRET (menu Pasca Rilis), langsung ke database-nya.
 *
 * Alur data SIPOTRET:
 *   pasca_rilis          — publikasi, kunci `pub_id_api` (pub_id API BPS) + `pub_wilayah` (domain)
 *   pasca_rilis_history  — publikasi pada periode pemeriksaan (`bulan`, `tahun`)
 *   pasca_rilis_catatan  — temuan: `rilis_id` → history.id, `item_periksa_id`, `catatan`, `item_keterangan`
 *
 * Kriteria pubchecker = master `item_periksa` SIPOTRET, tapi ID-nya berbeda; dicocokkan lewat
 * kategori + nama (deskripsi pubchecker = "item_nama — item_deskripsi").
 * Meniru PascaRilis::save() & addCatatan() di SIPOTRET. Item yang sudah tercatat di periode yang sama dilewati.
 */
class SipotretService
{
    private ?Collection $items = null;

    public function enabled(): bool
    {
        return (bool) config('database.connections.sipotret.database');
    }

    private function db(): Connection
    {
        return DB::connection('sipotret');
    }

    /**
     * Rencana kiriman untuk satu hasil pada periode tertentu — dipakai untuk pratinjau dan saat mengirim.
     *
     * @return array{bisa: bool, alasan: ?string, pasca: ?object, cocok_judul: bool, history_id: ?int, items: array}
     */
    public function rencana(HasilPemeriksaan $hasil, int $bulan, int $tahun): array
    {
        $temuan = $hasil->detail()
            ->where('status', DetailPemeriksaan::STATUS_TIDAK_SESUAI)
            ->orderBy('id')->get();
        $tambahan = $hasil->catatanTambahan()->get();

        $pasca = null;
        $cocokJudul = false;
        if ($hasil->pub_id_api) {
            $pasca = $this->db()->table('pasca_rilis')->where('pub_id_api', $hasil->pub_id_api)->first();
        } elseif ($hasil->pdf_url) {
            // hasil import lama (sebelum pub_id disimpan): cocokkan judul, hanya bila hasilnya tunggal
            $kandidat = $this->db()->table('pasca_rilis')->where('pub_title', $hasil->judul)->limit(2)->get();
            if ($kandidat->count() === 1) {
                $pasca = $kandidat->first();
                $cocokJudul = true;
            }
        }

        $alasan = match (true) {
            !$hasil->pdf_url                         => 'Hanya hasil dari Import API BPS yang bisa dikirim.',
            !$pasca && !$hasil->bisaKirimSipotret()  => 'Publikasi tidak ditemukan di SIPOTRET dan pub_id BPS tidak tersimpan (hasil lama) — periksa ulang lewat Import API BPS.',
            $temuan->isEmpty() && $tambahan->isEmpty() => 'Tidak ada kriteria Tidak Sesuai maupun catatan tambahan.',
            default                                  => null,
        };

        $history = $pasca
            ? $this->db()->table('pasca_rilis_history')
                ->where(['pasca_id' => $pasca->id, 'bulan' => $bulan, 'tahun' => $tahun])->first()
            : null;
        $sudahAda = $history
            ? $this->db()->table('pasca_rilis_catatan')->where('rilis_id', $history->id)
                ->whereNotNull('item_periksa_id')->pluck('item_periksa_id')->map(fn($v) => (int) $v)->all()
            : [];

        // item lainnya yang sudah tercatat di periode ini: kunci nama item + keterangan
        $customAda = $history
            ? $this->db()->table('pasca_rilis_catatan')->where('rilis_id', $history->id)->whereNull('item_periksa_id')
                ->get(['item_periksa_custom', 'item_keterangan'])
                ->map(fn($c) => $this->kunciCustom($c->item_periksa_custom, $c->item_keterangan))->all()
            : [];

        $items = $temuan->map(function (DetailPemeriksaan $d) use ($sudahAda) {
            $item = $this->cariItem($d);
            return [
                'kode'       => $d->kriteria_id,
                'kategori'   => $d->kategori,
                'nama'       => $item->item_nama ?? $this->namaKriteria($d->deskripsi),
                'keterangan' => $d->keterangan,
                'item_id'    => $item ? (int) $item->item_id : null,
                'flag'       => $item->item_flag_level ?? null,
                'status'     => !$item ? 'tidak_dikenal' : (in_array((int) $item->item_id, $sudahAda, true) ? 'sudah_ada' : 'baru'),
                '_item'      => $item,
            ];
        })->concat($tambahan->map(fn($c) => [
            'kode'       => 'Tambahan',
            'kategori'   => 'Catatan tambahan' . ($c->kategori ? " · {$c->kategori}" : '') . ($c->halaman ? " · hal. {$c->halaman}" : ''),
            'nama'       => $c->item,
            'keterangan' => $c->keterangan,
            'item_id'    => null,
            'flag'       => $c->flag_level,
            'status'     => in_array($this->kunciCustom($c->item, $c->keterangan), $customAda, true) ? 'sudah_ada' : 'baru',
            '_custom'    => true,
        ]))->all();

        // periode yang sudah terdaftar di SIPOTRET untuk publikasi ini — petunjuk memilih periode
        $periode = $pasca
            ? $this->db()->table('pasca_rilis_history')->where('pasca_id', $pasca->id)
                ->orderByDesc('tahun')->orderByDesc('bulan')->get(['bulan', 'tahun'])
                ->map(fn($h) => ['bulan' => (int) $h->bulan, 'tahun' => (int) $h->tahun])->all()
            : [];

        return [
            'bisa'        => $alasan === null,
            'alasan'      => $alasan,
            'pasca'       => $pasca,
            'cocok_judul' => $cocokJudul,
            'history_id'  => $history->id ?? null,
            'periode'     => $periode,
            'items'       => $items,
        ];
    }

    /**
     * Kirim temuan baru ke SIPOTRET. Mengembalikan jumlah yang ditambah/dilewati.
     *
     * @return array{ditambah: int, sudah_ada: int, tidak_dikenal: int, history_id: int}
     */
    public function kirim(HasilPemeriksaan $hasil, int $bulan, int $tahun): array
    {
        $r = $this->rencana($hasil, $bulan, $tahun);
        if (!$r['bisa']) {
            throw new \RuntimeException($r['alasan']);
        }

        $hitung = ['ditambah' => 0, 'sudah_ada' => 0, 'tidak_dikenal' => 0];

        $historyId = $this->db()->transaction(function () use ($hasil, $bulan, $tahun, $r, &$hitung) {
            $now = now()->format('Y-m-d H:i:s');

            // pasca_rilis: cari/buat berdasarkan pub_id_api (seperti PascaRilis::save)
            $pasca = $r['pasca'];
            if (!$pasca) {
                $pascaId = $this->db()->table('pasca_rilis')->insertGetId([
                    'pub_id'            => null,
                    'pub_id_api'        => $hasil->pub_id_api,
                    'pub_wilayah'       => (int) $hasil->pub_domain,
                    'pub_tanggal_rilis' => $hasil->pub_tanggal_rilis?->format('Y-m-d'),
                    'pub_title'         => $hasil->judul,
                    'pub_issn'          => $hasil->pub_issn,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            } else {
                $pascaId = (int) $pasca->id;
                $isi = array_filter([
                    'pub_tanggal_rilis' => empty($pasca->pub_tanggal_rilis) ? $hasil->pub_tanggal_rilis?->format('Y-m-d') : null,
                    'pub_issn'          => empty($pasca->pub_issn) ? $hasil->pub_issn : null,
                ]);
                if ($isi) {
                    $this->db()->table('pasca_rilis')->where('id', $pascaId)->update($isi + ['updated_at' => $now]);
                }
            }

            // pasca_rilis_history: publikasi pada periode bulan/tahun
            $historyId = $r['history_id'] ?? $this->db()->table('pasca_rilis_history')->insertGetId([
                'pasca_id'   => $pascaId,
                'bulan'      => $bulan,
                'tahun'      => $tahun,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // pasca_rilis_catatan: satu baris per kriteria Tidak Sesuai yang belum tercatat
            foreach ($r['items'] as $it) {
                if ($it['status'] !== 'baru') {
                    $hitung[$it['status']]++;
                    continue;
                }
                if (!empty($it['_custom'])) {
                    // "Item Lainnya (Custom)" — sama seperti form SIPOTRET: catatan = nama item
                    $this->db()->table('pasca_rilis_catatan')->insert([
                        'rilis_id'            => $historyId,
                        'item_periksa_id'     => null,
                        'item_periksa_custom' => $it['nama'],
                        'item_flag_level'     => $it['flag'],
                        'item_keterangan'     => (string) $it['keterangan'],
                        'catatan'             => $it['nama'],
                    ]);
                    $hitung['ditambah']++;
                    continue;
                }
                $item = $it['_item'];
                $this->db()->table('pasca_rilis_catatan')->insert([
                    'rilis_id'            => $historyId,
                    'item_periksa_id'     => (int) $item->item_id,
                    'item_periksa_custom' => null,
                    'item_flag_level'     => $item->item_flag_level,
                    'item_keterangan'     => (string) $it['keterangan'],
                    // format sama dengan SIPOTRET (buildMasterItemCatatan): "nama - deskripsi"
                    'catatan'             => trim(trim((string) $item->item_nama) . ' - ' . trim((string) $item->item_deskripsi), ' -'),
                ]);
                $hitung['ditambah']++;
            }

            return (int) $historyId;
        });

        $hasil->update(['sipotret_terkirim_at' => now(), 'sipotret_history_id' => $historyId]);

        return $hitung + ['history_id' => $historyId];
    }

    // ── Rekap dashboard ──────────────────────────────────────────────────

    /** Satker Kalsel, urut seperti daftar domain BPS. */
    public const WILAYAH = [
        6300 => 'Provinsi Kalimantan Selatan',
        6301 => 'Tanah Laut',
        6302 => 'Kotabaru',
        6303 => 'Banjar',
        6304 => 'Barito Kuala',
        6305 => 'Tapin',
        6306 => 'Hulu Sungai Selatan',
        6307 => 'Hulu Sungai Tengah',
        6308 => 'Hulu Sungai Utara',
        6309 => 'Tabalong',
        6310 => 'Tanah Bumbu',
        6311 => 'Balangan',
        6371 => 'Banjarmasin',
        6372 => 'Banjarbaru',
    ];

    /** Nilai `pub_file_rev_status` di SIPOTRET: NULL = belum upload perbaikan. */
    public const REV_MENUNGGU = ['uploaded', 'uploaded_kabkota']; // file perbaikan sudah diunggah, menunggu approve
    public const REV_APPROVE  = 'approve';

    /** 'belum' | 'menunggu' | 'approve' */
    public static function tahapRev(?string $rev): string
    {
        return match (true) {
            $rev === self::REV_APPROVE                    => 'approve',
            in_array($rev, self::REV_MENUNGGU, true)      => 'menunggu',
            default                                       => 'belum',
        };
    }

    /**
     * Publikasi bermasalah (punya catatan Pasca Rilis) per wilayah per bulan dalam satu tahun,
     * beserta tahap perbaikannya (`pub_file_rev_status`).
     *
     * @return array<int, array<int, array{masalah: int, menunggu: int, diperbaiki: int}>>  [pub_wilayah][bulan]; diperbaiki = sudah approve
     */
    public function rekapWilayah(int $tahun): array
    {
        $kolomRev = $this->kolomRev();
        $menunggu = "'" . implode("','", self::REV_MENUNGGU) . "'";

        $baris = $this->queryMasalah($tahun)
            ->groupBy('p.pub_wilayah', 'h.bulan')
            ->selectRaw("p.pub_wilayah as wilayah, h.bulan,
                COUNT(DISTINCT h.id) as masalah,
                COUNT(DISTINCT CASE WHEN {$kolomRev} IN ({$menunggu}) THEN h.id END) as menunggu,
                COUNT(DISTINCT CASE WHEN {$kolomRev} = ? THEN h.id END) as diperbaiki", [self::REV_APPROVE])
            ->get();

        $rekap = [];
        foreach ($baris as $b) {
            $rekap[(int) $b->wilayah][(int) $b->bulan] = [
                'masalah'    => (int) $b->masalah,
                'menunggu'   => (int) $b->menunggu,
                'diperbaiki' => (int) $b->diperbaiki,
            ];
        }
        return $rekap;
    }

    /**
     * Approve file perbaikan satu publikasi-periode (seperti tombol approve di SIPOTRET).
     * Hanya mengubah status yang sedang menunggu approve; mengembalikan false bila statusnya bukan itu.
     */
    public function approve(int $historyId): bool
    {
        $kolomRev = $this->kolomRev();
        [$tabel, $id] = str_starts_with($kolomRev, 'h.')
            ? ['pasca_rilis_history', $historyId]
            : ['pasca_rilis', $this->db()->table('pasca_rilis_history')->where('id', $historyId)->value('pasca_id')];
        if (!$id) {
            return false;
        }

        $isi = ['pub_file_rev_status' => self::REV_APPROVE];
        if ($this->db()->getSchemaBuilder()->hasColumn($tabel, 'updated_at')) {
            $isi['updated_at'] = now()->format('Y-m-d H:i:s');
        }

        return $this->db()->table($tabel)
            ->where('id', $id)
            ->whereIn('pub_file_rev_status', self::REV_MENUNGGU)
            ->update($isi) > 0;
    }

    /**
     * Daftar publikasi bermasalah satu wilayah (opsional satu bulan) beserta catatannya — untuk klik di dashboard.
     *
     * @return array<int, array{history_id: int, pub_id_api: ?string, judul: string, bulan: int, rev: ?string, tahap: string, file_rev: ?string, diubah_at: ?string, catatan: array, riwayat: array}>
     */
    public function daftarMasalah(int $wilayah, int $tahun, ?int $bulan = null): array
    {
        $kolomRev = $this->kolomRev();

        // file perbaikan yang diunggah langsung di SIPOTRET (provinsi) + waktu perubahannya
        $schema     = $this->db()->getSchemaBuilder();
        $kolomFile  = $schema->hasColumn('pasca_rilis_history', 'pub_file_rev') ? 'h.pub_file_rev' : 'NULL';
        $kolomWaktu = $schema->hasColumn('pasca_rilis_history', 'updated_at') ? 'h.updated_at' : 'NULL';

        $pub = $this->queryMasalah($tahun)
            ->where('p.pub_wilayah', $wilayah)
            ->when($bulan, fn($q) => $q->where('h.bulan', $bulan))
            ->orderBy('h.bulan')->orderBy('p.pub_title')
            ->selectRaw("h.id as history_id, h.pasca_id, p.pub_id_api, p.pub_title, h.bulan, {$kolomRev} as rev,
                {$kolomFile} as file_rev, {$kolomWaktu} as diubah_at")
            ->get();

        $catatan = $this->db()->table('pasca_rilis_catatan')
            ->whereIn('rilis_id', $pub->pluck('history_id'))
            ->orderBy('id')
            ->get(['rilis_id', 'item_periksa_custom', 'catatan', 'item_keterangan'])
            ->groupBy('rilis_id');

        // riwayat semua periode pemeriksaan tiap publikasi di SIPOTRET (pasca_rilis_history)
        $riwayat = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('h.pasca_id', $pub->pluck('pasca_id')->unique())
            ->orderByDesc('h.tahun')->orderByDesc('h.bulan')
            ->selectRaw("h.id, h.pasca_id, h.bulan, h.tahun, {$kolomRev} as rev,
                (SELECT COUNT(*) FROM pasca_rilis_catatan c WHERE c.rilis_id = h.id) as jumlah_catatan")
            ->get()
            ->groupBy('pasca_id');

        return $pub->map(fn($p) => [
            'history_id' => (int) $p->history_id,
            'pub_id_api' => $p->pub_id_api,
            'judul'      => (string) $p->pub_title,
            'bulan'      => (int) $p->bulan,
            'rev'        => $p->rev,
            'tahap'      => self::tahapRev($p->rev),
            'file_rev'   => trim((string) $p->file_rev) !== '' ? (string) $p->file_rev : null,
            'diubah_at'  => $p->diubah_at,
            'catatan'    => ($catatan[$p->history_id] ?? collect())->map(fn($c) => [
                'catatan'    => (string) ($c->catatan ?: $c->item_periksa_custom),
                'keterangan' => (string) $c->item_keterangan,
            ])->values()->all(),
            'riwayat'    => ($riwayat[$p->pasca_id] ?? collect())->map(fn($h) => [
                'bulan'      => (int) $h->bulan,
                'tahun'      => (int) $h->tahun,
                'catatan'    => (int) $h->jumlah_catatan,
                'tahap'      => self::tahapRev($h->rev),
                'ini'        => (int) $h->id === (int) $p->history_id,
            ])->values()->all(),
        ])->all();
    }

    /**
     * Catatan periode SIPOTRET terakhir tiap publikasi, untuk mengisi awal tinjauan pemeriksaan ulang
     * (mis. terakhir September → diperiksa ulang untuk Oktober). Tidak menulis apa pun; baru masuk ke
     * periode baru saat petugas mengirim ke SIPOTRET.
     *
     * @param  string[] $pubIdApis
     * @return array<string, array{periode: array{bulan:int,tahun:int}, kriteria: array<string,string>, lainnya: array}>
     *         kriteria: kode kriteria pubchecker → keterangan; lainnya: item custom (tanpa kriteria)
     */
    public function isianTerakhir(array $pubIdApis): array
    {
        $pubIdApis = array_values(array_unique(array_filter($pubIdApis)));
        if (!$pubIdApis) {
            return [];
        }

        // periode terakhir yang punya catatan, per publikasi
        $history = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('p.pub_id_api', $pubIdApis)
            ->whereExists(fn($q) => $q->from('pasca_rilis_catatan as c')->whereColumn('c.rilis_id', 'h.id'))
            ->orderByDesc('h.tahun')->orderByDesc('h.bulan')
            ->get(['h.id', 'h.bulan', 'h.tahun', 'p.pub_id_api'])
            ->unique('pub_id_api');

        $catatan = $this->db()->table('pasca_rilis_catatan')
            ->whereIn('rilis_id', $history->pluck('id'))
            ->orderBy('id')
            ->get(['rilis_id', 'item_periksa_id', 'item_periksa_custom', 'item_flag_level', 'item_keterangan'])
            ->groupBy('rilis_id');

        // item_periksa SIPOTRET → kode kriteria pubchecker (kategori sama, deskripsi diawali "item_nama — ")
        $items = $this->db()->table('item_periksa')->get(['item_id', 'item_nama', 'item_kategori'])->keyBy('item_id');
        $kriteria = KriteriaPemeriksaan::get(['kode', 'kategori', 'deskripsi'])->groupBy('kategori');
        $kodeUntuk = function ($itemId) use ($items, $kriteria): array {
            $item = $items[$itemId] ?? null;
            if (!$item) return [];
            $awalan = trim((string) $item->item_nama) . ' — ';
            return ($kriteria[$item->item_kategori] ?? collect())
                ->filter(fn($k) => str_starts_with($k->deskripsi, $awalan))
                ->pluck('kode')->all();
        };

        $hasil = [];
        foreach ($history as $h) {
            $isi = ['periode' => ['bulan' => (int) $h->bulan, 'tahun' => (int) $h->tahun], 'kriteria' => [], 'lainnya' => []];
            foreach ($catatan[$h->id] ?? [] as $c) {
                $ket = trim((string) $c->item_keterangan);
                if ($c->item_periksa_id === null) {
                    $isi['lainnya'][] = ['item' => (string) $c->item_periksa_custom, 'keterangan' => $ket, 'flag_level' => $c->item_flag_level];
                    continue;
                }
                foreach ($kodeUntuk($c->item_periksa_id) as $kode) {
                    $isi['kriteria'][$kode][] = $ket;
                }
            }
            // beberapa catatan untuk kriteria yang sama → keterangan digabung
            $isi['kriteria'] = array_map(fn($k) => implode('; ', array_unique(array_filter($k))), $isi['kriteria']);
            $hasil[(string) $h->pub_id_api] = $isi;
        }
        return $hasil;
    }

    /** Path relatif file perbaikan (pub_file_rev) satu publikasi-periode, atau null. */
    public function fileRev(int $historyId): ?string
    {
        if (!$this->db()->getSchemaBuilder()->hasColumn('pasca_rilis_history', 'pub_file_rev')) {
            return null;
        }
        $path = trim((string) $this->db()->table('pasca_rilis_history')->where('id', $historyId)->value('pub_file_rev'));
        return $path !== '' ? $path : null;
    }

    /** Publikasi pada periode tahun tsb yang punya minimal satu catatan Pasca Rilis. */
    private function queryMasalah(int $tahun)
    {
        return $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->where('h.tahun', $tahun)
            ->whereExists(fn($q) => $q->from('pasca_rilis_catatan as c')->whereColumn('c.rilis_id', 'h.id'));
    }

    /** pub_file_rev_status bisa di history (per periode) atau di pasca_rilis (per publikasi). */
    private function kolomRev(): string
    {
        $schema = $this->db()->getSchemaBuilder();
        return match (true) {
            $schema->hasColumn('pasca_rilis_history', 'pub_file_rev_status') => 'h.pub_file_rev_status',
            $schema->hasColumn('pasca_rilis', 'pub_file_rev_status')         => 'p.pub_file_rev_status',
            default => throw new \RuntimeException('Kolom pub_file_rev_status tidak ditemukan di SIPOTRET.'),
        };
    }

    // ── Pencocokan kriteria ↔ item_periksa ──────────────────────────────

    private function cariItem(DetailPemeriksaan $d): ?object
    {
        $this->items ??= $this->db()->table('item_periksa')
            ->select('item_id', 'item_nama', 'item_kategori', 'item_deskripsi', 'item_flag_level')
            ->get()->groupBy('item_kategori');

        // nama terpanjang dulu: "Posisi Logo BPS pada Publikasi Kerjasama" sebelum "Posisi Logo BPS"
        return ($this->items[$d->kategori] ?? collect())
            ->sortByDesc(fn($i) => mb_strlen((string) $i->item_nama))
            ->first(fn($i) => str_starts_with($d->deskripsi, trim((string) $i->item_nama) . ' — '));
    }

    private function kunciCustom(?string $item, ?string $keterangan): string
    {
        $norm = fn($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $s)));
        return $norm($item) . "\x00" . $norm($keterangan);
    }

    private function namaKriteria(string $deskripsi): string
    {
        return trim(explode(' — ', $deskripsi, 2)[0]);
    }
}
