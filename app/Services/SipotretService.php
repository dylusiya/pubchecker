<?php

namespace App\Services;

use App\Models\DetailPemeriksaan;
use App\Models\HasilPemeriksaan;
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
