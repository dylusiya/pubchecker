<?php

namespace App\Services;

use App\Models\CatatanTambahan;
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
     * Nama lengkap item custom satu baris pasca_rilis_catatan: kolom `catatan` (TEXT) bila ia memuat nama
     * di item_periksa_custom (yang bisa terpotong lebar kolomnya); selain itu item_periksa_custom.
     */
    private function namaCustom(object $r): string
    {
        $custom = trim((string) $r->item_periksa_custom);
        $cat    = trim((string) ($r->catatan ?? ''));
        return $cat !== '' && mb_strlen($cat) > mb_strlen($custom) && str_starts_with($cat, $custom) ? $cat : $custom;
    }

    /**
     * Item custom yang sudah tercatat: nama di SIPOTRET (mis. versi terpotong) berbeda dengan nama di pubchecker
     * → "beda" meski keterangan & level sama, supaya bisa diperbarui lewat "Perbarui yang beda".
     */
    private function bandingNama(array $b, string $nama, \Closure $norm): array
    {
        $r = $b['_row'] ?? null;
        if (!$r || $norm($this->namaCustom($r)) === $norm($nama)) return $b;
        return [
            'status'              => 'beda',
            'sipotret_keterangan' => (string) $r->item_keterangan,
            'sipotret_flag'       => $r->item_flag_level,
            'sipotret_nama'       => $this->namaCustom($r),
            'beda_level'          => $b['beda_level'] ?? false,
            'beda_nama'           => true,
            '_row_id'             => (int) $r->id,
            '_row'                => $r,
        ];
    }

    /** Nama item custom dipotong selebar kolom item_periksa_custom (nama lengkap tetap di kolom catatan). */
    private function potongCustom(string $nama): string
    {
        $n = $this->panjangItemCustom();
        return $n ? mb_substr($nama, 0, $n) : $nama;
    }

    /**
     * Lebar kolom item_periksa_custom di database SIPOTRET (jumlah karakter), dibaca langsung dari
     * information_schema & disimpan 1 jam. null bila SIPOTRET tidak aktif / tidak bisa dibaca.
     */
    public function panjangItemCustom(): ?int
    {
        if (!$this->enabled()) return null;
        try {
            $n = \Illuminate\Support\Facades\Cache::remember('sipotret.panjang_item_custom', 3600, fn() => (int) $this->db()->table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where(['TABLE_NAME' => 'pasca_rilis_catatan', 'COLUMN_NAME' => 'item_periksa_custom'])
                ->value('CHARACTER_MAXIMUM_LENGTH'));
            return $n > 0 ? $n : null; // TEXT dsb.: sangat panjang, dibatasi kolom pubchecker
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
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
            !$pasca && !$hasil->bisaKirimSipotret()  => 'Publikasi tidak ditemukan di SIPOTRET dan pub_id BPS tidak tersimpan (hasil lama). Periksa ulang lewat Import API BPS.',
            $temuan->isEmpty() && $tambahan->isEmpty() => 'Tidak ada kriteria Tidak Sesuai maupun catatan tambahan.',
            default                                  => null,
        };

        $history = $pasca
            ? $this->db()->table('pasca_rilis_history')
                ->where(['pasca_id' => $pasca->id, 'bulan' => $bulan, 'tahun' => $tahun])->first()
            : null;
        // catatan yang sudah ada di SIPOTRET untuk publikasi & periode ini: dibandingkan dengan temuan pubchecker
        $adaRows = $history
            ? $this->db()->table('pasca_rilis_catatan')->where('rilis_id', $history->id)->orderBy('id')
                ->get(['id', 'item_periksa_id', 'item_periksa_custom', 'item_flag_level', 'item_keterangan', 'catatan'])
            : collect();
        $norm = fn($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $s)));
        $terpakai = []; // id baris SIPOTRET yang cocok dengan temuan pubchecker

        /**
         * Status satu temuan terhadap baris SIPOTRET yang sama itemnya:
         * baru (belum ada) | sama (keterangan & level sama) | beda (sudah ada tapi keterangan/level berbeda).
         */
        $banding = function (array $baris, ?string $ket, $flag, bool $cekFlag) use ($norm, &$terpakai): array {
            $baris = array_values(array_filter($baris, fn($r) => !in_array($r->id, $terpakai, true)));
            if (!$baris) return ['status' => 'baru'];
            $sama = collect($baris)->first(fn($r) => $norm($r->item_keterangan) === $norm($ket)
                && (!$cekFlag || (string) $r->item_flag_level === (string) $flag));
            $r = $sama ?? $baris[0];
            $terpakai[] = $r->id;
            if ($sama) return ['status' => 'sama', '_row_id' => (int) $r->id, '_row' => $r];
            return [
                'status'              => 'beda',
                'sipotret_keterangan' => (string) $r->item_keterangan,
                'sipotret_flag'       => $r->item_flag_level,
                'beda_level'          => $cekFlag && (string) $r->item_flag_level !== (string) $flag,
                '_row_id'             => (int) $r->id,
                '_row'                => $r,
            ];
        };
        $perItem   = fn($id) => $adaRows->where('item_periksa_id', $id)->all();
        // dicocokkan dengan nama lengkap (kolom catatan), versi terpotongnya di item_periksa_custom, atau nama lama
        // yang terpotong (awalan nama pubchecker, min. 20 karakter); yang persis sama didahulukan
        $perCustom = function ($nama) use ($adaRows, $norm) {
            $n = $norm($nama);
            $cocok = $adaRows->filter(function ($r) use ($n, $nama, $norm) {
                if ($r->item_periksa_id !== null) return false;
                $di = $norm($this->namaCustom($r));
                return $di === $n || $norm($r->item_periksa_custom) === $norm($this->potongCustom($nama))
                    || (mb_strlen($di) >= 20 && str_starts_with($n, $di));
            });
            return $cocok->sortBy(fn($r) => $norm($this->namaCustom($r)) === $n ? 0 : 1)->values()->all();
        };

        $items = $temuan->map(function (DetailPemeriksaan $d) use ($banding, $perItem, $perCustom, $norm) {
            $item = $this->cariItem($d);
            if (!$item) {
                // kriteria pubchecker tanpa item master SIPOTRET (mis. kesesuaian dengan Web API):
                // dikirim sebagai "Item Lainnya (Custom)" bernama kriteria, level major
                $nama = $this->namaKriteria($d->deskripsi);
                return [
                    'kode'       => $d->kriteria_id,
                    'kategori'   => $d->kategori,
                    'nama'       => $nama,
                    'keterangan' => $d->keterangan,
                    'item_id'    => null,
                    'flag'       => 'major',
                    '_custom'    => true,
                ] + $this->bandingNama($banding($perCustom($nama), $d->keterangan, 'major', true), $nama, $norm);
            }
            return [
                'kode'       => $d->kriteria_id,
                'kategori'   => $d->kategori,
                'nama'       => $item->item_nama ?? $this->namaKriteria($d->deskripsi),
                'keterangan' => $d->keterangan,
                'item_id'    => $item ? (int) $item->item_id : null,
                'flag'       => $item->item_flag_level ?? null,
                '_item'      => $item,
            ] + ($item ? $banding($perItem((int) $item->item_id), $d->keterangan, null, false) : ['status' => 'tidak_dikenal']);
        })->concat($tambahan->map(function ($c) use ($banding, $perItem, $perCustom, $norm) {
            $label = 'Catatan tambahan' . ($c->kategori ? " · {$c->kategori}" : '') . ($c->halaman ? " · hal. {$c->halaman}" : '');
            // item master SIPOTRET tanpa kriteria di pubchecker: dikirim sebagai item master
            $item = $c->item_periksa_id ? $this->itemMaster((int) $c->item_periksa_id) : null;
            if ($item) {
                return [
                    'kode'       => 'Tambahan',
                    'kategori'   => $label,
                    'nama'       => $item->item_nama,
                    'keterangan' => $c->keterangan,
                    'item_id'    => (int) $item->item_id,
                    'flag'       => $item->item_flag_level,
                    '_item'      => $item,
                ] + $banding($perItem((int) $item->item_id), $c->keterangan, null, false);
            }
            return [
                'kode'       => 'Tambahan',
                'kategori'   => $label,
                'nama'       => $c->item,
                'keterangan' => $c->keterangan,
                'item_id'    => null,
                'flag'       => $c->flag_level, // null = tidak perlu perbaikan
                '_custom'    => true,
            ] + $this->bandingNama($banding($perCustom($c->item), $c->keterangan, $c->flag_level, true), $c->item, $norm);
        }))->all();

        // tercatat di SIPOTRET periode ini tapi tidak ada di temuan pubchecker (mis. sudah dinilai Sesuai)
        $hanyaSipotret = $adaRows->reject(fn($r) => in_array($r->id, $terpakai, true))->map(fn($r) => [
            'nama'       => $r->item_periksa_id !== null
                ? (string) ($this->itemMaster((int) $r->item_periksa_id)->item_nama ?? $r->catatan)
                : $this->namaCustom($r),
            'keterangan' => (string) $r->item_keterangan,
            'flag'       => $r->item_flag_level,
        ])->values()->all();

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
            'hanya_sipotret' => $hanyaSipotret,
        ];
    }

    /**
     * Kirim temuan baru ke SIPOTRET. Mengembalikan jumlah yang ditambah/dilewati.
     *
     * @param  bool $perbarui temuan yang sudah ada tapi keterangan/levelnya beda ikut diperbarui di SIPOTRET
     * @return array{ditambah: int, sama: int, beda: int, diperbarui: int, tidak_dikenal: int, history_id: int}
     */
    public function kirim(HasilPemeriksaan $hasil, int $bulan, int $tahun, bool $perbarui = false): array
    {
        $r = $this->rencana($hasil, $bulan, $tahun);
        if (!$r['bisa']) {
            throw new \RuntimeException($r['alasan']);
        }

        $hitung = ['ditambah' => 0, 'sama' => 0, 'beda' => 0, 'diperbarui' => 0, 'tidak_dikenal' => 0];

        $historyId = $this->db()->transaction(function () use ($hasil, $bulan, $tahun, $r, $perbarui, &$hitung) {
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
                if ($it['status'] === 'beda' && $perbarui) {
                    // sudah tercatat tapi keterangan (item custom: juga level) berbeda → samakan dengan pubchecker
                    $this->db()->table('pasca_rilis_catatan')->where('id', $it['_row_id'])->update(
                        ['item_keterangan' => (string) $it['keterangan']]
                        + (!empty($it['_custom']) ? ['item_flag_level' => $it['flag']] : [])
                        // nama item custom yang di SIPOTRET masih versi pendek/lama → nama dari pubchecker
                        + (!empty($it['beda_nama']) ? ['item_periksa_custom' => $this->potongCustom($it['nama']), 'catatan' => $it['nama']] : [])
                    );
                    $hitung['diperbarui']++;
                    continue;
                }
                if ($it['status'] !== 'baru') {
                    $hitung[$it['status']]++;
                    continue;
                }
                if (!empty($it['_custom'])) {
                    // "Item Lainnya (Custom)" — sama seperti form SIPOTRET: catatan = nama item.
                    // Nama lengkap di catatan (TEXT); item_periksa_custom dipotong hanya bila kolomnya lebih sempit
                    $this->db()->table('pasca_rilis_catatan')->insert([
                        'rilis_id'            => $historyId,
                        'item_periksa_id'     => null,
                        'item_periksa_custom' => $this->potongCustom($it['nama']),
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
    /**
     * Salin catatan Pasca Rilis periode tepat sebelumnya ke periode pemeriksaan (bulan/tahun), untuk publikasi
     * yang perbaikannya belum diunggah atau belum di-approve — ketidaksesuaian dianggap masih ada.
     * Sama seperti "Dari History" di SIPOTRET: baris disalin apa adanya (item master / custom, level, keterangan).
     * Catatan yang sudah ada di periode pemeriksaan (item & keterangan sama) tidak didobel.
     *
     * @param  string[] $pubIdApis
     * @param  bool     $simpan false = pratinjau saja
     * @return array<int, array{pub_id:string, judul:?string, sumber:?array, tahap:?string, catatan:int, baru:int, sudah:int, bisa:bool, alasan:?string}>
     */
    public function salinSebelumnya(array $pubIdApis, int $bulan, int $tahun, bool $simpan = false): array
    {
        $pubIdApis = array_values(array_unique(array_filter(array_map('strval', $pubIdApis))));
        if (!$pubIdApis) return [];
        $lalu = \Illuminate\Support\Carbon::create($tahun, $bulan, 1)->subMonthNoOverflow();
        $kolomRev = $this->kolomRev();
        $norm = fn($v) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
        $kunci = fn($r) => ($r->item_periksa_id !== null ? 'm' . (int) $r->item_periksa_id : 'c' . $norm($this->namaCustom($r)))
            . "\x00" . $norm($r->item_keterangan);

        $pascaList = $this->db()->table('pasca_rilis')->whereIn('pub_id_api', $pubIdApis)->orderBy('id')->get()->keyBy('pub_id_api');
        $histori = fn(int $pascaId, int $b, int $t) => $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->where(['h.pasca_id' => $pascaId, 'h.bulan' => $b, 'h.tahun' => $t])
            ->selectRaw("h.id, {$kolomRev} as rev")->first();
        $barisCatatan = fn(int $historyId) => $this->db()->table('pasca_rilis_catatan')->where('rilis_id', $historyId)->orderBy('id')
            ->get(['id', 'item_periksa_id', 'item_periksa_custom', 'item_flag_level', 'item_keterangan', 'catatan']);

        // keputusan pemeriksa periode ini (konflik acuan vs cek otomatis yang sudah diputuskan) tidak boleh ditimpa
        $keputusan = $this->keputusanPemeriksa($pubIdApis, $bulan, $tahun);
        [, $kodeUntuk, $semuaKriteria] = $this->petaKriteria();
        $kodeCustom = $this->kodeNamaKriteria($semuaKriteria);
        $kodeBaris = fn($c) => $c->item_periksa_id !== null
            ? $kodeUntuk((int) $c->item_periksa_id)
            : array_filter([$kodeCustom[$norm($this->namaCustom($c))] ?? null]);

        $laporan = [];
        foreach ($pubIdApis as $pubId) {
            $r = ['pub_id' => $pubId, 'judul' => null, 'sumber' => ['bulan' => $lalu->month, 'tahun' => $lalu->year],
                  'tahap' => null, 'catatan' => 0, 'baru' => 0, 'sudah' => 0, 'bisa' => false, 'alasan' => null,
                  'diputuskan' => [], 'keputusan_ts' => 0];
            $pasca = $pascaList[$pubId] ?? null;
            if (!$pasca) {
                $r['alasan'] = 'Publikasi belum ada di SIPOTRET';
                $laporan[] = $r;
                continue;
            }
            $r['judul'] = (string) $pasca->pub_title;

            $src = $histori((int) $pasca->id, $lalu->month, $lalu->year);
            $sumber = $src ? $barisCatatan((int) $src->id) : collect();
            $r['tahap'] = $src ? self::tahapRev($src->rev) : null;
            $r['catatan'] = $sumber->count();
            if (!$src || $sumber->isEmpty()) {
                $r['alasan'] = 'Tidak ada catatan di periode sebelumnya';
            } elseif ($r['tahap'] === 'approve') {
                $r['alasan'] = 'Perbaikan periode sebelumnya sudah di-approve';
            }
            if ($r['alasan']) { $laporan[] = $r; continue; }

            // keputusan pemeriksa: Sesuai/Skip → tidak disalin; Tidak Sesuai → disalin dengan keterangan pemeriksa
            $putus = $keputusan[$pubId]['kriteria'] ?? [];
            $terpakai = [];
            $sumber = $sumber->map(function ($c) use ($putus, $kodeBaris, &$terpakai, &$r) {
                $kode = collect($kodeBaris($c))->first(fn($k) => isset($putus[$k]) && !isset($terpakai[$k]));
                if ($kode === null) return $c;
                $terpakai[$kode] = true; // satu keputusan untuk satu catatan (catatan kedua dst. tetap usulan biasa)
                [$status, $ket] = $putus[$kode];
                if ($status !== DetailPemeriksaan::STATUS_TIDAK_SESUAI) {
                    $r['diputuskan'][] = $kode;
                    return null;
                }
                $r['keputusan_ts']++;
                $c = clone $c;
                $c->dariKeputusan = true;
                if ($ket !== '') $c->item_keterangan = $ket;
                return $c;
            })->filter()->values();

            $tujuan = $histori((int) $pasca->id, $bulan, $tahun);
            $adaBaris = $tujuan ? $barisCatatan((int) $tujuan->id) : collect();
            $ada = $adaBaris->map($kunci)->flip();
            // item master yang sudah tercatat di periode ini & keterangannya dari keputusan pemeriksa: sudah dikirim
            // lewat hasil pemeriksaan (mungkin keterangannya beda) → tidak ditambah lagi
            $masterAda = $adaBaris->whereNotNull('item_periksa_id')->pluck('item_periksa_id')->map(fn($v) => (int) $v)->flip();
            $salin = $sumber->reject(fn($c) => $ada->has($kunci($c))
                || (!empty($c->dariKeputusan) && $c->item_periksa_id !== null && $masterAda->has((int) $c->item_periksa_id)))
                ->unique($kunci)->values();
            $r['sudah'] = $sumber->count() - $salin->count();
            $r['baru'] = $salin->count();
            $r['bisa'] = $salin->isNotEmpty();
            if (!$r['bisa']) $r['alasan'] = $sumber->isEmpty()
                ? 'Semua catatan bulan lalu sudah diputuskan Sesuai/Skip oleh pemeriksa'
                : 'Semua catatan sudah ada di periode pemeriksaan';

            if ($simpan && $r['bisa']) {
                $this->db()->transaction(function () use ($pasca, $tujuan, $bulan, $tahun, $salin) {
                    $now = now()->format('Y-m-d H:i:s');
                    $historyId = $tujuan ? (int) $tujuan->id : $this->db()->table('pasca_rilis_history')->insertGetId([
                        'pasca_id' => (int) $pasca->id, 'bulan' => $bulan, 'tahun' => $tahun, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $this->db()->table('pasca_rilis_catatan')->insert($salin->map(fn($c) => [
                        'rilis_id'            => $historyId,
                        'item_periksa_id'     => $c->item_periksa_id,
                        'item_periksa_custom' => $c->item_periksa_custom,
                        'item_flag_level'     => $c->item_flag_level,
                        'item_keterangan'     => (string) $c->item_keterangan,
                        'catatan'             => $c->catatan,
                    ])->all());
                });
            }
            $laporan[] = $r;
        }
        return $laporan;
    }

    /** nama kriteria (ternormalisasi) → kode; hanya nama yang unik (nama sama di beberapa kategori tidak pasti). */
    private function kodeNamaKriteria(Collection $semuaKriteria): array
    {
        $norm = fn($v) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
        return $semuaKriteria->groupBy(fn($k) => $norm($this->namaKriteria($k->deskripsi)))
            ->filter(fn($g) => $g->count() === 1)->map(fn($g) => $g->first()->kode)->all();
    }

    /**
     * Keputusan pemeriksa per publikasi pada periode pemeriksaan: kriteria yang sudah ditinjau & disimpan di
     * hasil pemeriksaan terbaru periode itu → [pub_id => ['hasil_id' => .., 'kriteria' => [kode => [status, keterangan]]]].
     * Periode hasil seperti Tugas Saya: periode tempat dikirim ke SIPOTRET → periode pemeriksaan yang dicatat
     * saat Periksa → bulan sebelum tanggal pemeriksaan.
     */
    public function keputusanPemeriksa(array $pubIdApis, int $bulan, int $tahun): array
    {
        $kolom = array_merge(['id', 'pub_id_api', 'sipotret_history_id', 'created_at'],
            HasilPemeriksaan::punyaKolomPeriode() ? ['periode_bulan', 'periode_tahun'] : []);
        $hasil = HasilPemeriksaan::whereIn('pub_id_api', $pubIdApis)->orderByDesc('created_at')->orderByDesc('id')->get($kolom);
        $kirim = $this->periodeHistory($hasil->pluck('sipotret_history_id')->all());
        $periode = function ($h) use ($kirim) {
            if ($h->sipotret_history_id && isset($kirim[(int) $h->sipotret_history_id])) return $kirim[(int) $h->sipotret_history_id];
            if (!empty($h->periode_bulan) && !empty($h->periode_tahun)) return ['bulan' => (int) $h->periode_bulan, 'tahun' => (int) $h->periode_tahun];
            $x = $h->created_at?->copy()->subMonthNoOverflow();
            return $x ? ['bulan' => $x->month, 'tahun' => $x->year] : null;
        };
        $out = [];
        foreach ($hasil->groupBy('pub_id_api') as $pubId => $list) {
            $h = $list->first(fn($h) => ($x = $periode($h)) && $x['bulan'] === $bulan && $x['tahun'] === $tahun);
            if (!$h) continue;
            $out[(string) $pubId] = [
                'hasil_id' => (int) $h->id,
                'kriteria' => DetailPemeriksaan::where('hasil_id', $h->id)->whereNotNull('ditinjau_at')
                    ->get(['kriteria_id', 'status', 'keterangan'])
                    ->mapWithKeys(fn($d) => [(string) $d->kriteria_id => [$d->status, trim((string) $d->keterangan)]])->all(),
            ];
        }
        return $out;
    }

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
        $pub = $this->queryMasalah($tahun)
            ->where('p.pub_wilayah', $wilayah)
            ->when($bulan, fn($q) => $q->where('h.bulan', $bulan))
            ->orderBy('h.bulan')->orderBy('p.pub_title')
            ->selectRaw($this->kolomBaris())
            ->get();
        return $this->susunDaftar($pub);
    }

    /**
     * Baris SIPOTRET acuan untuk Tugas Saya: per publikasi, periode terakhir yang punya catatan SEBELUM periode
     * pemeriksaan (periode Oktober → catatan September, atau sebelumnya). Bentuknya sama dengan daftarMasalah().
     *
     * @param  string[] $pubIdApis
     * @return array<string, array> per pub_id; publikasi tanpa catatan sebelumnya tidak ada di hasil
     */
    public function acuanSebelum(array $pubIdApis, int $bulan, int $tahun): array
    {
        $pubIdApis = array_values(array_unique(array_filter($pubIdApis)));
        if (!$pubIdApis) return [];
        $pub = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('p.pub_id_api', $pubIdApis)
            ->whereRaw('(h.tahun * 12 + h.bulan) < ?', [$tahun * 12 + $bulan])
            ->whereExists(fn($q) => $q->from('pasca_rilis_catatan as c')->whereColumn('c.rilis_id', 'h.id'))
            ->orderByDesc('h.tahun')->orderByDesc('h.bulan')
            ->selectRaw($this->kolomBaris())
            ->get()
            ->unique('pub_id_api')->values();
        return collect($this->susunDaftar($pub))->keyBy('pub_id_api')->all();
    }

    /**
     * Baris SIPOTRET tepat pada satu periode untuk beberapa publikasi (kartu Tugas Saya, sama seperti
     * Publikasi Bermasalah per bulan): catatan, status perbaikan & approve periode itu.
     *
     * @param  string[] $pubIdApis
     * @return array<string, array> per pub_id; belum tercatat di periode itu tidak ada di hasil
     */
    public function barisPeriode(array $pubIdApis, int $bulan, int $tahun): array
    {
        $pubIdApis = array_values(array_unique(array_filter($pubIdApis)));
        if (!$pubIdApis) return [];
        $pub = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('p.pub_id_api', $pubIdApis)
            ->where(['h.bulan' => $bulan, 'h.tahun' => $tahun])
            ->selectRaw($this->kolomBaris())
            ->get()
            ->unique('pub_id_api')->values();
        return collect($this->susunDaftar($pub))->keyBy('pub_id_api')->all();
    }

    /** Kolom select baris daftar (history + publikasi + status perbaikan). */
    private function kolomBaris(): string
    {
        $kolomRev = $this->kolomRev();
        // file perbaikan yang diunggah langsung di SIPOTRET (provinsi) + waktu perubahannya
        $schema     = $this->db()->getSchemaBuilder();
        $kolomFile  = $schema->hasColumn('pasca_rilis_history', 'pub_file_rev') ? 'h.pub_file_rev' : 'NULL';
        $kolomWaktu = $schema->hasColumn('pasca_rilis_history', 'updated_at') ? 'h.updated_at' : 'NULL';
        return "h.id as history_id, h.pasca_id, p.pub_id_api, p.pub_title, p.pub_wilayah, h.bulan, h.tahun, {$kolomRev} as rev,
            {$kolomFile} as file_rev, {$kolomWaktu} as diubah_at";
    }

    /**
     * Baris daftar publikasi SIPOTRET beserta catatan (urut kriteria) & riwayat semua periodenya — dipakai
     * Publikasi Bermasalah & Tugas Saya (komponen kartu yang sama).
     *
     * @return array<int, array{history_id: int, pub_id_api: ?string, judul: string, wilayah: ?string, bulan: int, tahun: int, rev: ?string, tahap: string, file_rev: ?string, diubah_at: ?string, catatan: array, riwayat: array}>
     */
    private function susunDaftar(Collection $pub): array
    {
        if ($pub->isEmpty()) return [];
        $kolomRev = $this->kolomRev();

        $catatan = $this->db()->table('pasca_rilis_catatan')
            ->whereIn('rilis_id', $pub->pluck('history_id'))
            ->orderBy('id')
            ->get(['id', 'rilis_id', 'item_periksa_id', 'item_periksa_custom', 'catatan', 'item_keterangan'])
            ->groupBy('rilis_id');

        $susun = $this->penyusunCatatan();

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
            'wilayah'    => $p->pub_wilayah !== null ? (string) $p->pub_wilayah : null,
            'bulan'      => (int) $p->bulan,
            'tahun'      => (int) $p->tahun,
            'rev'        => $p->rev,
            'tahap'      => self::tahapRev($p->rev),
            'file_rev'   => trim((string) $p->file_rev) !== '' ? (string) $p->file_rev : null,
            'diubah_at'  => $p->diubah_at,
            'catatan'    => $susun($catatan[$p->history_id] ?? collect()),
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
        return $this->isianDari($pubIdApis);
    }

    /**
     * Seperti isianTerakhir(), tetapi dari periode tertentu yang dipilih petugas sebagai acuan
     * (mis. September 2026), bukan periode terakhir yang bisa berubah. Publikasi tanpa catatan
     * di periode itu tidak ada di hasil.
     */
    public function isianPeriode(array $pubIdApis, int $bulan, int $tahun): array
    {
        return $this->isianDari($pubIdApis, $bulan, $tahun);
    }

    /**
     * Pemetaan item_periksa SIPOTRET → kriteria pubchecker (kategori sama, deskripsi diawali "item_nama — ").
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Closure, 2: \Illuminate\Support\Collection}
     *         [item_periksa per item_id, fn(item_id): string[] kode, kriteria per kode (kategori, urutan)]
     */
    private function petaKriteria(): array
    {
        $items = $this->db()->table('item_periksa')->get(['item_id', 'item_nama', 'item_kategori'])->keyBy('item_id');
        $semua = KriteriaPemeriksaan::orderBy('urutan')->get(['kode', 'kategori', 'deskripsi', 'urutan']);
        $perKategori = $semua->groupBy('kategori');
        $kodeUntuk = function ($itemId) use ($items, $perKategori): array {
            $item = $items[$itemId] ?? null;
            if (!$item) return [];
            $awalan = trim((string) $item->item_nama) . ' — ';
            return ($perKategori[$item->item_kategori] ?? collect())
                ->filter(fn($k) => str_starts_with($k->deskripsi, $awalan))
                ->pluck('kode')->all();
        };
        return [$items, $kodeUntuk, $semua->keyBy('kode')];
    }

    /**
     * fn(baris pasca_rilis_catatan) → catatan berurut seperti urutan kriteria pubchecker, diberi kode &
     * kategorinya (seperti daftar saat meninjau); item lainnya / item tanpa kriteria yang cocok di akhir.
     * Baris wajib membawa kolom id, item_periksa_id, item_periksa_custom, catatan, item_keterangan.
     */
    private function penyusunCatatan(): \Closure
    {
        [$items, $kodeUntuk, $kriteria] = $this->petaKriteria();
        return fn($baris) => collect($baris)->map(function ($c) use ($items, $kodeUntuk, $kriteria) {
            $kode = $c->item_periksa_id !== null ? ($kodeUntuk($c->item_periksa_id)[0] ?? null) : null;
            $k    = $kode ? $kriteria[$kode] : null;
            return [
                'catatan'    => (string) ($c->catatan ?: $c->item_periksa_custom),
                'keterangan' => (string) $c->item_keterangan,
                'kode'       => $kode,
                'kategori'   => $k?->kategori ?? ($c->item_periksa_id !== null ? ($items[$c->item_periksa_id]->item_kategori ?? null) : null),
                '_urut'      => [$k ? 0 : 1, $k?->urutan ?? PHP_INT_MAX, (int) $c->id],
            ];
        })->sortBy('_urut')->map(fn($c) => array_diff_key($c, ['_urut' => 1]))->values()->all();
    }

    private function isianDari(array $pubIdApis, ?int $bulan = null, ?int $tahun = null): array
    {
        $pubIdApis = array_values(array_unique(array_filter($pubIdApis)));
        if (!$pubIdApis) {
            return [];
        }

        // periode yang punya catatan per publikasi: periode acuan, atau yang terakhir
        $history = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('p.pub_id_api', $pubIdApis)
            ->when($bulan && $tahun, fn($q) => $q->where(['h.bulan' => $bulan, 'h.tahun' => $tahun]))
            ->whereExists(fn($q) => $q->from('pasca_rilis_catatan as c')->whereColumn('c.rilis_id', 'h.id'))
            ->orderByDesc('h.tahun')->orderByDesc('h.bulan')
            ->get(['h.id', 'h.bulan', 'h.tahun', 'p.pub_id_api'])
            ->unique('pub_id_api');

        $catatan = $this->db()->table('pasca_rilis_catatan')
            ->whereIn('rilis_id', $history->pluck('id'))
            ->orderBy('id')
            ->get(['rilis_id', 'item_periksa_id', 'item_periksa_custom', 'item_flag_level', 'item_keterangan', 'catatan'])
            ->groupBy('rilis_id');

        [$items, $kodeUntuk, $semuaKriteria] = $this->petaKriteria();
        // item custom bernama sama dengan kriteria tanpa item master (mis. P-361 Kesesuaian ISSN dengan Website BPS,
        // yang dikirim pubchecker sebagai item custom) → kembali ke kriterianya
        $norm = fn($v) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
        $kodeCustom = $this->kodeNamaKriteria($semuaKriteria);

        $hasil = [];
        foreach ($history as $h) {
            $baris = $catatan[$h->id] ?? collect();
            $isi = [
                'periode'  => ['bulan' => (int) $h->bulan, 'tahun' => (int) $h->tahun],
                'kriteria' => [],
                'lainnya'  => [],
                // nama item SIPOTRET per kode kriteria: dipakai bila kriteria itu tidak ada di hasil pemeriksaan
                'nama'     => [],
                'itemId'   => [], // item_periksa_id per kode (kriteria acuan yang tidak ada di hasil tetap jadi item master)
                'flag'     => [],
                // rincian jumlah baris catatan (angka di pilihan acuan = jumlah baris, bukan jumlah kriteria)
                'rincian'  => ['catatan' => $baris->count(), 'lainnya' => 0, 'tanpaKriteria' => 0, 'kedua' => 0, 'dobel' => 0],
            ];
            foreach ($baris as $c) {
                $ket = trim((string) $c->item_keterangan);
                $kodeC = $c->item_periksa_id === null ? ($kodeCustom[$norm($this->namaCustom($c))] ?? null) : null;
                if ($kodeC && !isset($isi['kriteria'][$kodeC])) {
                    $isi['kriteria'][$kodeC] = $ket;
                    $isi['nama'][$kodeC] = $this->namaCustom($c);
                    $isi['flag'][$kodeC] = $c->item_flag_level;
                    continue;
                }
                if ($c->item_periksa_id === null) {
                    $isi['lainnya'][] = ['item' => $this->namaCustom($c), 'keterangan' => $ket, 'flag_level' => $c->item_flag_level];
                    $isi['rincian']['lainnya']++;
                    continue;
                }
                $kodes = $kodeUntuk($c->item_periksa_id);
                if (!$kodes) {
                    // item SIPOTRET yang tidak cocok dengan kriteria mana pun: jangan hilang, jadikan item lainnya
                    $nama = trim((string) ($items[$c->item_periksa_id]->item_nama ?? 'Item SIPOTRET #' . $c->item_periksa_id));
                    // item_periksa_id ikut, supaya saat dikirim tetap jadi item master (seperti "Dari History" di SIPOTRET)
                    $isi['lainnya'][] = ['item' => $nama, 'keterangan' => $ket, 'flag_level' => $c->item_flag_level,
                        'item_periksa_id' => (int) $c->item_periksa_id];
                    $isi['rincian']['tanpaKriteria']++;
                    continue;
                }
                // kriteria yang sudah terisi catatan lain: catatan ini temuan tersendiri (di SIPOTRET baris terpisah),
                // jadi tidak digabung ke keterangan kriteria tetapi menjadi item lainnya dengan item master yang sama
                $baru = array_filter($kodes, fn($k) => !isset($isi['kriteria'][$k]));
                if (!$baru) {
                    $kembar = collect($kodes)->contains(fn($k) => mb_strtolower($isi['kriteria'][$k]) === mb_strtolower($ket));
                    if ($kembar) {
                        $isi['rincian']['dobel']++; // keterangan persis sama: cukup sekali
                        continue;
                    }
                    $isi['lainnya'][] = ['item' => trim((string) $items[$c->item_periksa_id]->item_nama), 'keterangan' => $ket,
                        'flag_level' => $c->item_flag_level, 'item_periksa_id' => (int) $c->item_periksa_id];
                    $isi['rincian']['kedua']++;
                    continue;
                }
                foreach ($baru as $kode) {
                    $isi['kriteria'][$kode] = $ket;
                    $isi['nama'][$kode] = trim((string) $items[$c->item_periksa_id]->item_nama);
                    $isi['itemId'][$kode] = (int) $c->item_periksa_id;
                    $isi['flag'][$kode] = $c->item_flag_level;
                }
            }
            $hasil[(string) $h->pub_id_api] = $isi;
        }
        return $hasil;
    }

    /**
     * Riwayat pemeriksaan per bulan satu publikasi di SIPOTRET (terbaru dulu), untuk halaman
     * detail publikasi. Kosong bila publikasi belum pernah masuk Pasca Rilis.
     *
     * @return array<int, array{bulan: int, tahun: int, tahap: string, catatan: array<int, array{catatan: string, keterangan: string}>}>
     */
    public function riwayatPublikasi(string $pubIdApi): array
    {
        $pascaIds = $this->db()->table('pasca_rilis')->where('pub_id_api', $pubIdApi)->pluck('id');
        if ($pascaIds->isEmpty()) {
            return [];
        }

        $kolomRev = $this->kolomRev();
        $history = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('h.pasca_id', $pascaIds)
            ->orderByDesc('h.tahun')->orderByDesc('h.bulan')
            ->selectRaw("h.id, h.bulan, h.tahun, {$kolomRev} as rev")
            ->get();

        $catatan = $this->db()->table('pasca_rilis_catatan')
            ->whereIn('rilis_id', $history->pluck('id'))
            ->orderBy('id')
            ->get(['id', 'rilis_id', 'item_periksa_id', 'item_periksa_custom', 'catatan', 'item_keterangan'])
            ->groupBy('rilis_id');
        $susun = $this->penyusunCatatan();

        return $history->map(fn($h) => [
            'bulan'   => (int) $h->bulan,
            'tahun'   => (int) $h->tahun,
            // tanpa catatan = tidak ada temuan pada periode itu
            'tahap'   => isset($catatan[$h->id]) ? self::tahapRev($h->rev) : 'bersih',
            'catatan' => $susun($catatan[$h->id] ?? collect()),
        ])->values()->all();
    }

    /**
     * Status beberapa publikasi di SIPOTRET pada satu periode (untuk halaman Tugas Saya).
     *
     * @param  string[] $pubIdApis
     * @return array<string, array{history_id:int, catatan:int, tahap:string}> per pub_id; tidak ada = belum tercatat di periode itu
     */
    public function statusPeriode(array $pubIdApis, int $bulan, int $tahun): array
    {
        $pubIdApis = array_values(array_unique(array_filter($pubIdApis)));
        if (!$pubIdApis) return [];
        $kolomRev = $this->kolomRev();
        return $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('p.pub_id_api', $pubIdApis)
            ->where(['h.bulan' => $bulan, 'h.tahun' => $tahun])
            ->selectRaw("p.pub_id_api, h.id, {$kolomRev} as rev,
                (SELECT COUNT(*) FROM pasca_rilis_catatan c WHERE c.rilis_id = h.id) as jumlah")
            ->get()
            ->mapWithKeys(fn($r) => [(string) $r->pub_id_api => [
                'history_id' => (int) $r->id,
                'catatan'    => (int) $r->jumlah,
                'tahap'      => (int) $r->jumlah ? self::tahapRev($r->rev) : 'bersih',
            ]])->all();
    }

    /**
     * Status SIPOTRET pada periode TEPAT SEBELUM suatu periode (Tugas Saya; periode Oktober → September), untuk
     * menandai publikasi yang sudah tidak bermasalah: periode sebelumnya tanpa catatan tidak sesuai — baris
     * periodenya tanpa catatan, atau publikasinya sudah tidak ada di SIPOTRET pada periode itu.
     * Hanya publikasi yang pernah tercatat di SIPOTRET sebelum periode ini yang ada di hasil.
     *
     * @param  string[] $pubIdApis
     * @return array<string, array{bulan:int, tahun:int, catatan:int, tercatat:bool}> per pub_id;
     *         bulan/tahun = periode sebelumnya, catatan = jumlah catatan di periode itu (0 bila tidak tercatat)
     */
    public function statusSebelum(array $pubIdApis, int $bulan, int $tahun): array
    {
        $pubIdApis = array_values(array_unique(array_filter($pubIdApis)));
        if (!$pubIdApis) return [];
        $lalu = \Illuminate\Support\Carbon::create($tahun, $bulan, 1)->subMonthNoOverflow();

        $baris = $this->db()->table('pasca_rilis_history as h')
            ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
            ->whereIn('p.pub_id_api', $pubIdApis)
            ->whereRaw('(h.tahun * 12 + h.bulan) < ?', [$tahun * 12 + $bulan])
            ->selectRaw('p.pub_id_api, h.bulan, h.tahun,
                (SELECT COUNT(*) FROM pasca_rilis_catatan c WHERE c.rilis_id = h.id) as jumlah')
            ->get()
            ->groupBy('pub_id_api');

        return $baris->map(function ($rows) use ($lalu) {
            $diLalu = $rows->first(fn($r) => (int) $r->bulan === $lalu->month && (int) $r->tahun === $lalu->year);
            return [
                'bulan'    => $lalu->month,
                'tahun'    => $lalu->year,
                'catatan'  => $diLalu ? (int) $diLalu->jumlah : 0,
                'tercatat' => (bool) $diLalu, // false = sudah tidak ada di SIPOTRET pada periode sebelumnya
            ];
        })->all();
    }

    /**
     * Periode (bulan, tahun) beberapa baris pasca_rilis_history — periode tempat hasil pemeriksaan dikirim.
     *
     * @param  int[] $historyIds
     * @return array<int, array{bulan:int, tahun:int}>
     */
    public function periodeHistory(array $historyIds): array
    {
        $historyIds = array_values(array_unique(array_filter(array_map('intval', $historyIds))));
        if (!$historyIds) return [];
        return $this->db()->table('pasca_rilis_history')->whereIn('id', $historyIds)->get(['id', 'bulan', 'tahun'])
            ->mapWithKeys(fn($h) => [(int) $h->id => ['bulan' => (int) $h->bulan, 'tahun' => (int) $h->tahun]])->all();
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

    // ── Perbaikan data lama: catatan tambahan dari acuan yang tersimpan sebelum disamakan dengan "Dari History" ──

    /**
     * Catatan tambahan yang dulu diambil dari catatan SIPOTRET tetapi tersimpan berbeda:
     * (a) item master SIPOTRET (tanpa kriteria di pubchecker) tersimpan sebagai item custom → isi item_periksa_id;
     * (b) flag kosong di SIPOTRET dipaksa "minor" → kosongkan lagi.
     * Dicocokkan lewat nama item (maks. 50 karakter) + keterangan dengan catatan SIPOTRET publikasi yang sama,
     * diutamakan periode acuan. Opsional ikut membetulkan baris yang sudah terkirim ke SIPOTRET.
     *
     * @return array<int, array{hasil_id:int, catatan_id:int, item:string, ubah:string[], sipotret:?string}>
     */
    public function perbaikiCatatanAcuan(bool $simpan = false, bool $kirimUlang = false): array
    {
        [$items, $kodeUntuk] = $this->petaKriteria();
        $norm = fn($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $s)));
        $kunci = fn($item, $ket) => $norm(mb_substr(trim((string) $item), 0, CatatanTambahan::MAX_ITEM)) . "\x00" . $norm($ket);

        $hasilList = HasilPemeriksaan::whereNotNull('pub_id_api')
            ->whereHas('catatanTambahan', fn($q) => $q->where(fn($w) => $w->whereNull('item_periksa_id')->orWhere('flag_level', 'minor')
                // nama item master yang dulu terpotong 50 karakter
                ->orWhereRaw('CHAR_LENGTH(item) >= ?', [CatatanTambahan::MAX_ITEM])))
            ->with(['catatanTambahan', 'detail:id,hasil_id,kriteria_id'])->get();

        $laporan = [];
        foreach ($hasilList as $hasil) {
            $kodeHasil = $hasil->detail->pluck('kriteria_id')->map(fn($k) => (string) $k)->flip();
            // semua catatan SIPOTRET publikasi ini; periode acuan didahulukan
            $acuan = $hasil->acuanSipotret();
            $baris = $this->db()->table('pasca_rilis_catatan as c')
                ->join('pasca_rilis_history as h', 'h.id', '=', 'c.rilis_id')
                ->join('pasca_rilis as p', 'p.id', '=', 'h.pasca_id')
                ->where('p.pub_id_api', $hasil->pub_id_api)
                ->orderByDesc('h.tahun')->orderByDesc('h.bulan')
                ->get(['c.id', 'c.rilis_id', 'c.item_periksa_id', 'c.item_periksa_custom', 'c.item_flag_level', 'c.item_keterangan', 'c.catatan', 'h.bulan', 'h.tahun'])
                ->sortBy(fn($r) => $acuan && (int) $r->bulan === $acuan['bulan'] && (int) $r->tahun === $acuan['tahun'] ? 0 : 1);
            $sumber = [];
            foreach ($baris as $r) {
                $nama = $r->item_periksa_id !== null ? ($items[$r->item_periksa_id]->item_nama ?? null) : $this->namaCustom($r);
                if ($nama === null) continue;
                $sumber[$kunci($nama, $r->item_keterangan)] ??= $r; // periode acuan menang
            }

            foreach ($hasil->catatanTambahan as $c) {
                $r = $sumber[$kunci($c->item, $c->keterangan)] ?? null;
                if (!$r) continue;
                $ubah = [];
                // (a) item master tanpa kriteria di hasil ini (kriteria yang ada di hasil seharusnya jadi kriteria, bukan catatan)
                $kodeAda = array_filter($kodeUntuk($r->item_periksa_id ?? 0) ?: [], fn($k) => isset($kodeHasil[(string) $k]));
                if ($c->item_periksa_id === null && $r->item_periksa_id !== null && !$kodeAda) {
                    $ubah['item_periksa_id'] = (int) $r->item_periksa_id;
                }
                // (c) nama item terpotong (dulu 50 / 255 karakter) → nama lengkap dari SIPOTRET:
                //     item master = item_nama; item custom = kolom catatan (TEXT)
                $idMaster = $ubah['item_periksa_id'] ?? $c->item_periksa_id;
                $namaLengkap = match (true) {
                    $idMaster && (int) $idMaster === (int) $r->item_periksa_id => trim((string) ($items[$r->item_periksa_id]->item_nama ?? '')),
                    !$idMaster && $r->item_periksa_id === null                 => $this->namaCustom($r),
                    default                                                    => '',
                };
                if ($namaLengkap !== '' && mb_strlen($namaLengkap) > mb_strlen($c->item)
                    && str_starts_with(mb_strtolower($namaLengkap), mb_strtolower(trim($c->item)))) {
                    $ubah['item'] = mb_substr($namaLengkap, 0, CatatanTambahan::MAX_NAMA);
                }
                // (b) flag kosong di SIPOTRET
                if ($r->item_flag_level === null && $c->flag_level !== null) {
                    $ubah['flag_level'] = null;
                }
                if (!$ubah) continue;

                $label = array_map(fn($k, $v) => match ($k) {
                    'flag_level' => "flag {$c->flag_level} → kosong",
                    'item'       => 'nama lengkap',
                    default      => "item master #{$v}",
                }, array_keys($ubah), $ubah);
                $infoSipotret = null;
                // di SIPOTRET hanya baris custom yang perlu dibetulkan (item master sudah tampil dengan nama lengkap)
                if ($kirimUlang && $hasil->sipotret_history_id && array_diff_key($ubah, ['item' => 1])) {
                    $infoSipotret = $this->betulkanTerkirim((int) $hasil->sipotret_history_id, $c, $ubah, $simpan);
                }
                $itemLama = $c->item;
                if ($simpan) {
                    $c->update($ubah);
                }
                $laporan[] = [
                    'hasil_id'   => $hasil->id,
                    'catatan_id' => $c->id,
                    'item'       => $itemLama,
                    'ubah'       => $label,
                    'sipotret'   => $infoSipotret,
                ];
            }
        }
        return $laporan;
    }

    /** Baris catatan yang sudah terkirim ke SIPOTRET (item custom / flag minor) disamakan. */
    private function betulkanTerkirim(int $historyId, CatatanTambahan $c, array $ubah, bool $simpan): string
    {
        $q = fn() => $this->db()->table('pasca_rilis_catatan')->where('rilis_id', $historyId)
            ->whereNull('item_periksa_id')->where('item_periksa_custom', $c->item);
        $row = $q()->get()->first(fn($r) => $this->kunciCustom($r->item_periksa_custom, $r->item_keterangan) === $this->kunciCustom($c->item, $c->keterangan));
        if (!$row) return 'tidak ditemukan di periode terkirim';

        $isi = [];
        if (isset($ubah['item_periksa_id'])) {
            $item = $this->itemMaster($ubah['item_periksa_id']);
            if (!$item) return 'item master sudah tidak ada';
            $dobel = $this->db()->table('pasca_rilis_catatan')->where('rilis_id', $historyId)
                ->where('item_periksa_id', $item->item_id)->exists();
            if ($dobel) return 'item master sudah ada di periode itu, tidak diubah';
            $isi = [
                'item_periksa_id'     => (int) $item->item_id,
                'item_periksa_custom' => null,
                'item_flag_level'     => $item->item_flag_level,
                'catatan'             => trim(trim((string) $item->item_nama) . ' - ' . trim((string) $item->item_deskripsi), ' -'),
            ];
        } elseif (array_key_exists('flag_level', $ubah)) {
            $isi = ['item_flag_level' => null];
        }
        if ($simpan) {
            $this->db()->table('pasca_rilis_catatan')->where('id', $row->id)->update($isi);
        }
        return ($simpan ? 'dibetulkan' : 'akan dibetulkan') . " (baris #{$row->id})";
    }

    /** Item master SIPOTRET berdasarkan item_id, atau null bila sudah tidak ada. */
    private function itemMaster(int $itemId): ?object
    {
        $this->items ??= $this->db()->table('item_periksa')
            ->select('item_id', 'item_nama', 'item_kategori', 'item_deskripsi', 'item_flag_level')
            ->get()->groupBy('item_kategori');
        return $this->items->flatten(1)->first(fn($i) => (int) $i->item_id === $itemId);
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
