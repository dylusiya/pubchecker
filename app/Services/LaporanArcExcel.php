<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Laporan ARC (LaporanArcService::laporan) → Excel, satu sheet per tabel dengan tampilan seperti di halaman. */
class LaporanArcExcel
{
    private const BIRU   = '2F5B8C';
    private const MUDA   = '9AB7DC';
    private const SOROT  = 'E8999A';
    private const BATAS_SESUAI = 3; // sama dengan halaman: sel merah bila sesuai standar < 3

    public function buat(array $l, array $namaBulan): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->removeSheetByIndex(0);
        $tahun = $l['tahun'];
        $bulan = $l['bulan'];
        $periode = $bulan === 1 ? "Januari {$tahun}" : "Januari–{$namaBulan[$bulan]} {$tahun}";
        $baris = array_values($l['baris']);
        $t = $l['total'];
        $jenis = LaporanArcService::JENIS;
        $wajib = array_keys(array_filter($jenis, fn($j) => $j['wajib']));
        $non   = array_keys(array_filter($jenis, fn($j) => !$j['wajib']));
        $pct = fn($a, $b) => $a === null ? '…' : ($a === 0 || !$b ? '–' : round($a / $b * 100, 2));

        // 1. Rekap monitoring
        $this->sheet($ss, '1 Rekap Monitoring',
            "REKAP MONITORING PUBLIKASI ARC {$tahun} BPS KABUPATEN/KOTA DI PROVINSI KALIMANTAN SELATAN",
            [[['No'], ['Kabupaten/Kota'], ["Jumlah Publikasi ARC yang dirilis {$namaBulan[$bulan]} {$tahun}"],
              ["Jumlah Publikasi ARC yang dirilis {$periode}"], ['Jumlah Publikasi ARC Sudah Sesuai Standar'],
              ['Jumlah Publikasi ARC Belum Sesuai Standar']]],
            array_map(fn($b, $i) => [$i + 1, $b['nama'], $b['rilis_bulan'], $b['rilis_sd'], $b['sesuai'], $b['belum_sesuai']], $baris, array_keys($baris)),
            [['Total', 2], $t['rilis_bulan'], $t['rilis_sd'], $t['sesuai'], $t['belum_sesuai']],
            [6, 24, 18, 18, 18, 18],
            fn($b) => $b['belum_sesuai'] ? [6] : [], $baris);

        // 2. Lima besar temuan
        $this->sheet($ss, '2 Lima Besar Temuan',
            "REKAPITULASI LIMA BESAR TEMUAN KETIDAKSESUAIAN PUBLIKASI ARC SELAMA {$periode}",
            [[['No'], ['Item Pemeriksaan'], ['Deskripsi'], ['Keterangan']]],
            array_map(fn($x, $i) => [$i + 1, $x['item'], $x['deskripsi'], "Ditemukan pada {$x['publikasi']} publikasi"],
                $l['temuan'], array_keys($l['temuan'])),
            null, [6, 26, 60, 24], null, [], true);

        // 3. Hasil pemantauan ARC
        $this->sheet($ss, '3 Pemantauan ARC',
            "HASIL PEMANTAUAN ADVANCE RELEASE CALENDAR (ARC) PUBLIKASI BPS KABUPATEN/KOTA {$tahun}",
            [[['No', 1, 2], ['Kode', 1, 2], ['Kabupaten/Kota', 1, 2], ["Keberadaan ARC {$tahun} (✓: ada, x: tidak ada)", 2, 1],
              ["Jumlah Publikasi ARC {$tahun}", 1, 2], ['Jumlah Publikasi ARC ' . ($tahun - 1), 1, 2], ['Keterangan', 1, 2]],
             [null, null, null, ['Website'], ['Portal Publikasi']]],
            array_map(fn($b, $i) => [$i + 1, $b['kode'], $b['nama'], '', '', $b['jumlah_arc'], $b['jumlah_arc_lalu'], ''], $baris, array_keys($baris)),
            [['Total', 3], '', '', $t['jumlah_arc'], $t['jumlah_arc_lalu'], ''],
            [6, 8, 24, 12, 14, 14, 14, 18]);

        // 4. Rincian ARC per jenis
        $kepala = [['No', 1, 2], ['Kode', 1, 2], ['Kabupaten/Kota', 1, 2], ['ARC Wajib', count($wajib), 1],
                   ['Jumlah Publikasi ARC Wajib', 1, 2], ['Non ARC Wajib', count($non), 1]];
        $kedua = array_merge([null, null, null], array_map(fn($j) => [$jenis[$j]['label']], $wajib), [null],
            array_map(fn($j) => [$jenis[$j]['label']], $non));
        $this->sheet($ss, '4 Rincian ARC',
            "RINCIAN ADVANCE RELEASE CALENDAR (ARC) PUBLIKASI BPS KABUPATEN/KOTA {$tahun}",
            [$kepala, $kedua],
            array_map(fn($b, $i) => array_merge([$i + 1, $b['kode'], $b['nama']], array_map(fn($j) => $b['per_jenis'][$j], $wajib),
                [$b['jumlah_arc']], array_map(fn($j) => $b['per_jenis'][$j], $non)), $baris, array_keys($baris)),
            array_merge([['Total', 3]], array_map(fn($j) => $t['per_jenis'][$j], $wajib), [$t['jumlah_arc']],
                array_map(fn($j) => $t['per_jenis'][$j], $non)),
            array_merge([6, 8, 24], array_fill(0, count($jenis) + 1, 13)));

        // 5. Rekapitulasi pemantauan
        $akhirBulan = \Carbon\Carbon::create($tahun, $bulan)->endOfMonth()->day;
        $this->sheet($ss, '5 Rekap Pemantauan',
            "REKAPITULASI HASIL PEMANTAUAN ADVANCE RELEASE CALENDAR (ARC) PUBLIKASI BPS KABUPATEN/KOTA {$tahun}",
            [[['No', 1, 3], ['Kode', 1, 3], ['Kabupaten/Kota', 1, 3], ["Jumlah Publikasi Dijadwalkan Rilis bulan {$namaBulan[$bulan]} {$tahun}", 1, 3],
              ["Jumlah Publikasi Dijadwalkan Rilis 1 Januari s.d. {$akhirBulan} {$namaBulan[$bulan]} {$tahun}", 1, 3],
              ['Rilis di Website', 8, 1], ["Publikasi non ARC {$tahun}", 1, 3]],
             [null, null, null, null, null, ['Rilis On Time', 2, 1], ['Rilis Tidak On Time', 2, 1], ['Tidak Rilis', 2, 1], ['Sudah Sesuai Standar', 2, 1]],
             [null, null, null, null, null, ['Jumlah'], ['%'], ['Jumlah'], ['%'], ['Jumlah'], ['%'], ['Jumlah'], ['%']]],
            array_map(fn($b, $i) => [$i + 1, $b['kode'], $b['nama'], $b['jadwal_bulan'], $b['jadwal_sd'],
                $b['on_time'], $pct($b['on_time'], $b['jadwal_sd']), $b['tidak_on_time'], $pct($b['tidak_on_time'], $b['jadwal_sd']),
                $b['tidak_rilis'], $pct($b['tidak_rilis'], $b['jadwal_sd']), $b['sesuai'], $pct($b['sesuai'], $b['jadwal_sd']), $b['non_arc']],
                $baris, array_keys($baris)),
            [['Total', 3], $t['jadwal_bulan'], $t['jadwal_sd'], $t['on_time'], $pct($t['on_time'], $t['jadwal_sd']),
             $t['tidak_on_time'], $pct($t['tidak_on_time'], $t['jadwal_sd']), $t['tidak_rilis'], $pct($t['tidak_rilis'], $t['jadwal_sd']),
             $t['sesuai'], $pct($t['sesuai'], $t['jadwal_sd']), $t['non_arc']],
            [6, 8, 24, 16, 18, 9, 9, 9, 9, 9, 9, 9, 9, 12],
            fn($b) => $b['sesuai'] !== null && $b['sesuai'] < self::BATAS_SESUAI ? [12, 13] : [], $baris);

        $ss->setActiveSheetIndex(0);
        return $ss;
    }

    /**
     * Satu sheet: judul, baris kepala (sel = [teks, lebarKolom, tinggiBaris] atau null bila tertutup gabungan),
     * baris nomor kolom, data, baris total (sel pertama [teks, jumlahKolomDigabung]).
     *
     * @param  ?callable $sorot fn(baris sumber) → nomor kolom (1-based) yang diwarnai merah
     */
    private function sheet(Spreadsheet $ss, string $nama, string $judul, array $kepala, array $data, ?array $total,
                           array $lebar, ?callable $sorot = null, array $sumber = [], bool $teksPanjang = false): void
    {
        $ws = new Worksheet($ss, $nama);
        $ss->addSheet($ws);
        $nKolom = count($lebar);
        $akhir = Coordinate::stringFromColumnIndex($nKolom);
        foreach ($lebar as $i => $w) $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);

        $ws->setCellValue('A1', $judul)->mergeCells("A1:{$akhir}1");
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // kepala tabel (dengan sel gabungan)
        $r0 = 3;
        foreach ($kepala as $di => $sel) {
            $kol = 1;
            foreach ($sel as $s) {
                if ($s !== null) {
                    [$teks, $w, $h] = $s + [1 => 1, 2 => 1];
                    $a = Coordinate::stringFromColumnIndex($kol) . ($r0 + $di);
                    $ws->setCellValue($a, $teks);
                    if ($w > 1 || $h > 1) {
                        $ws->mergeCells($a . ':' . Coordinate::stringFromColumnIndex($kol + $w - 1) . ($r0 + $di + $h - 1));
                    }
                    $kol += $w;
                } else {
                    $kol++;
                }
            }
        }
        $rNo = $r0 + count($kepala);
        foreach (range(1, $nKolom) as $k) $ws->setCellValue(Coordinate::stringFromColumnIndex($k) . $rNo, "({$k})");

        $this->gaya($ws, "A{$r0}:{$akhir}" . ($rNo - 1), self::BIRU, true, 'FFFFFF');
        $this->gaya($ws, "A{$rNo}:{$akhir}{$rNo}", self::MUDA, true);
        $ws->getStyle("A{$r0}:{$akhir}{$rNo}")->getAlignment()->setWrapText(true);

        $r = $rNo + 1;
        foreach ($data as $i => $baris) {
            foreach (array_values($baris) as $k => $v) {
                $nv = $this->nilai($v);
                $ws->setCellValueExplicit(Coordinate::stringFromColumnIndex($k + 1) . $r, $nv,
                    is_int($nv) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $ws->getStyle("A{$r}:{$akhir}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // kolom teks rata kiri: nama kab/kota (kolom 2 atau 3), item & deskripsi temuan
            foreach (array_values($baris) as $k => $v) {
                if (is_string($v) && !preg_match('/^\d{4}$|^…$|^–$/u', $v) && mb_strlen($v) > 3) {
                    $ws->getStyle(Coordinate::stringFromColumnIndex($k + 1) . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
            }
            if ($sorot && isset($sumber[$i])) {
                foreach ($sorot($sumber[$i]) as $k) {
                    $this->gaya($ws, Coordinate::stringFromColumnIndex($k) . $r, self::SOROT);
                }
            }
            $r++;
        }
        if ($teksPanjang && $data) {
            $ws->getStyle("A" . ($rNo + 1) . ":{$akhir}" . ($r - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }
        if (!$data) {
            $ws->setCellValue("A{$r}", 'Tidak ada data')->mergeCells("A{$r}:{$akhir}{$r}");
            $r++;
        }
        if ($total) {
            [$teks, $gabung] = $total[0];
            $ws->setCellValue("A{$r}", $teks)->mergeCells("A{$r}:" . Coordinate::stringFromColumnIndex($gabung) . $r);
            foreach (array_slice($total, 1) as $k => $v) {
                $ws->setCellValue(Coordinate::stringFromColumnIndex($gabung + $k + 1) . $r, $this->nilai($v));
            }
            $this->gaya($ws, "A{$r}:{$akhir}{$r}", self::BIRU, true, 'FFFFFF');
            $ws->getStyle("A{$r}:{$akhir}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
        }
        $ws->getStyle("A{$r0}:{$akhir}" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("A{$r0}:{$akhir}" . ($r - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle("A{$r0}:{$akhir}" . ($rNo - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Pedoman BPS: nol → "–", tidak tersedia → "…"; persen dengan koma desimal. */
    private function nilai($v)
    {
        if ($v === null) return '…';
        if ($v === 0) return '–';
        if (is_float($v)) return number_format($v, 2, ',', '.');
        return $v;
    }

    private function gaya(Worksheet $ws, string $rentang, string $warna, bool $tebal = false, ?string $huruf = null): void
    {
        $s = $ws->getStyle($rentang);
        $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
        if ($tebal) $s->getFont()->setBold(true);
        if ($huruf) $s->getFont()->getColor()->setRGB($huruf);
    }
}
