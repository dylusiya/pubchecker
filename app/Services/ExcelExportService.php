<?php

namespace App\Services;

use App\Models\SesiPemeriksaan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

/**
 * ExcelExportService
 * Install: composer require phpoffice/phpspreadsheet
 */
class ExcelExportService
{
    private array $colors = [
        'header'  => '1F4E79',
        'ok'      => 'C6EFCE',
        'warn'    => 'FFEB9C',
        'err'     => 'FFC7CE',
        'gray'    => 'D9D9D9',
        'white'   => 'FFFFFF',
        'ok_font' => '276221',
        'warn_font'=> '7D5A00',
        'err_font' => '9C0B1D',
    ];

    public function exportSesi(SesiPemeriksaan $sesi): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Rekap Pemeriksaan BPS')
            ->setSubject('Sesi: ' . $sesi->uuid)
            ->setCreator('BPS Publication Checker');

        $this->buildSheetRekap($spreadsheet, $sesi);
        $this->buildSheetDetail($spreadsheet, $sesi);

        return $spreadsheet;
    }

    /** Ekspor dari array hasil (tanpa DB, langsung dari JSON) */
    public function exportFromArray(array $results): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setTitle('Rekap Pemeriksaan BPS');

        $this->buildSheetRekapFromArray($spreadsheet, $results);
        $this->buildSheetDetailFromArray($spreadsheet, $results);

        return $spreadsheet;
    }

    // ── Sheet Rekap ───────────────────────────────────────────
    private function buildSheetRekapFromArray(Spreadsheet $ss, array $results): void
    {
        $ws = $ss->getActiveSheet();
        $ws->setTitle('Rekap');

        $headers = ['No', 'Nama File', 'Total Halaman', 'Metode', '✓ OK', '⚠ Perlu Dicek', '✗ Tidak Ada', '— Tdk Diperiksa', 'Status Akhir'];
        $this->writeHeader($ws, $headers, 1);

        foreach ($results as $i => $r) {
            $row = $i + 2;
            $s   = $r['summary'];
            $ws->fromArray([
                $i + 1,
                $r['filename'],
                $r['total_pages'],
                $r['extraction_method'],
                $s['ok'],
                $s['perlu_dicek'],
                $s['tidak_ada'],
                $s['tidak_diperiksa'],
                strtoupper($s['status_akhir']),
            ], null, "A{$row}");

            $statusColor = match($s['status_akhir']) {
                'masalah'    => $this->colors['err'],
                'perlu_dicek'=> $this->colors['warn'],
                default      => $this->colors['ok'],
            };
            $this->applyRowStyle($ws, $row, 9, $statusColor, [5,6,7,8,9]);
        }

        $ws->setAutoFilter('A1:I1');
        $this->setColumnWidths($ws, ['A'=>6,'B'=>42,'C'=>14,'D'=>12,'E'=>10,'F'=>14,'G'=>12,'H'=>16,'I'=>16]);
        $ws->freezePane('A2');
    }

    private function buildSheetDetailFromArray(Spreadsheet $ss, array $results): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Detail');

        $headers = ['No', 'Nama File', 'Kode', 'Kategori', 'Deskripsi Kriteria', 'Status', 'Catatan'];
        $this->writeHeader($ws, $headers, 1);

        $rowNum = 2;
        foreach ($results as $i => $r) {
            foreach ($r['checks'] as $ch) {
                $ws->fromArray([
                    $i + 1,
                    $r['filename'],
                    $ch['id'] ?? $ch['kriteria_id'] ?? '',
                    $ch['kategori'],
                    $ch['deskripsi'],
                    $ch['status'],
                    $ch['catatan'],
                ], null, "A{$rowNum}");

                $color = match($ch['status']) {
                    'OK'              => $this->colors['ok'],
                    'PERLU DICEK'     => $this->colors['warn'],
                    'TIDAK ADA'       => $this->colors['err'],
                    default           => $this->colors['gray'],
                };
                $this->applyRowStyle($ws, $rowNum, 7, null, [6], $color);
                $rowNum++;
            }
        }

        $ws->setAutoFilter('A1:G1');
        $this->setColumnWidths($ws, ['A'=>6,'B'=>38,'C'=>10,'D'=>22,'E'=>50,'F'=>16,'G'=>55]);
        $ws->freezePane('A2');
    }

    private function buildSheetRekap(Spreadsheet $ss, SesiPemeriksaan $sesi): void
    {
        $results = $sesi->hasilPemeriksaan()->get()->map(fn($h) => [
            'filename'          => $h->nama_file,
            'total_pages'       => $h->total_halaman,
            'extraction_method' => $h->metode_ekstraksi,
            'summary' => [
                'ok'           => $h->total_ok,
                'perlu_dicek'  => $h->total_perlu_dicek,
                'tidak_ada'    => $h->total_tidak_ada,
                'tidak_diperiksa'=> $h->total_tdk_diperiksa,
                'status_akhir' => $h->status_akhir,
            ],
            'checks' => $h->detail->map(fn($d) => [
                'id'       => $d->kriteria_id,
                'kategori' => $d->kategori,
                'deskripsi'=> $d->deskripsi,
                'status'   => $d->status,
                'catatan'  => $d->catatan,
            ])->toArray(),
        ])->toArray();

        $this->buildSheetRekapFromArray($ss, $results);
    }

    private function buildSheetDetail(Spreadsheet $ss, SesiPemeriksaan $sesi): void
    {
        $results = $sesi->hasilPemeriksaan()->with('detail')->get()->map(fn($h) => [
            'filename' => $h->nama_file,
            'summary'  => ['status_akhir' => $h->status_akhir],
            'checks'   => $h->detail->map(fn($d) => [
                'id'       => $d->kriteria_id,
                'kategori' => $d->kategori,
                'deskripsi'=> $d->deskripsi,
                'status'   => $d->status,
                'catatan'  => $d->catatan,
            ])->toArray(),
        ])->toArray();

        $this->buildSheetDetailFromArray($ss, $results);
    }

    // ── Style helpers ─────────────────────────────────────────
    private function writeHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $headers, int $row): void
    {
        $col = 'A';
        foreach ($headers as $h) {
            $cell = $ws->getCell("{$col}{$row}");
            $cell->setValue($h);
            $cell->getStyle()->applyFromArray([
                'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$this->colors['header']]],
                'font'      => ['bold'=>true,'color'=>['rgb'=>$this->colors['white']],'size'=>11],
                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'AAAAAA']]],
            ]);
            $col++;
        }
        $ws->getRowDimension($row)->setRowHeight(36);
    }

    private function applyRowStyle(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        int $row, int $cols,
        ?string $rowColor = null,
        array $colorCols = [],
        ?string $specificColor = null
    ): void {
        $colLetter = range('A', chr(ord('A') + $cols - 1));
        foreach ($colLetter as $idx => $c) {
            $cellRef = "{$c}{$row}";
            $ws->getCell($cellRef)->getStyle()->applyFromArray([
                'alignment' => ['vertical'=>Alignment::VERTICAL_TOP,'wrapText'=>true,'horizontal'=>Alignment::HORIZONTAL_LEFT],
                'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'E0E0E0']]],
            ]);

            $colNum = $idx + 1;
            if ($specificColor && in_array($colNum, $colorCols)) {
                $ws->getCell($cellRef)->getStyle()->getFill()
                   ->setFillType(Fill::FILL_SOLID)
                   ->getStartColor()->setRGB($specificColor);
            } elseif ($rowColor) {
                $ws->getCell($cellRef)->getStyle()->getFill()
                   ->setFillType(Fill::FILL_SOLID)
                   ->getStartColor()->setRGB($rowColor);
            }
        }
    }

    private function setColumnWidths(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $widths): void
    {
        foreach ($widths as $col => $width) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
    }

    public function streamDownload(Spreadsheet $ss, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($ss) {
            $writer = new Xlsx($ss);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}