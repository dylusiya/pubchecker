<?php

namespace App\Http\Controllers;

use App\Services\LaporanArcService;
use App\Services\LaporanArcExcel;
use Illuminate\Http\Request;

/** Laporan pemantauan ARC publikasi BPS kab/kota (5 tabel) + unduh Excel. */
class LaporanArcController extends Controller
{
    private const BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'];

    /** Periode laporan; bawaan bulan lalu (laporan September dibuat Oktober). */
    private function periode(Request $request): array
    {
        $request->validate([
            'tahun' => 'nullable|integer|between:2020,2100',
            'bulan' => 'nullable|integer|between:1,12',
        ]);
        $lalu = now()->subMonthNoOverflow();
        return [$request->integer('tahun') ?: $lalu->year, $request->integer('bulan') ?: $lalu->month];
    }

    public function index(Request $request, LaporanArcService $arc)
    {
        [$tahun, $bulan] = $this->periode($request);
        $laporan = null;
        $error = null;
        try {
            $laporan = $arc->laporan($tahun, $bulan, $request->boolean('segarkan'));
        } catch (\Throwable $e) {
            report($e);
            $error = 'Laporan tidak bisa dibuat: ' . $e->getMessage();
        }
        if ($request->boolean('segarkan')) {
            // muat ulang tanpa ?segarkan supaya refresh browser tidak mengambil ulang dari Web API
            return redirect()->route('laporan.arc', ['tahun' => $tahun, 'bulan' => $bulan]);
        }
        $namaBulan = self::BULAN;
        return view('laporan-arc', compact('tahun', 'bulan', 'laporan', 'error', 'namaBulan'));
    }

    public function excel(Request $request, LaporanArcService $arc)
    {
        [$tahun, $bulan] = $this->periode($request);
        $laporan = $arc->laporan($tahun, $bulan);
        $ss = (new LaporanArcExcel())->buat($laporan, self::BULAN);
        $nama = sprintf('Laporan ARC %s %d.xlsx', self::BULAN[$bulan], $tahun);
        return response()->streamDownload(function () use ($ss) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        }, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
