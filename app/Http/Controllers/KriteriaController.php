<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KriteriaPemeriksaan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KriteriaController extends Controller
{
    /**
     * Cek apakah user adalah admin
     */
    private function checkAdmin()
    {
        if (!auth()->check()) {
            abort(403, 'Silakan login terlebih dahulu.');
        }

        if (auth()->user()->role !== 'admin') {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator.');
        }
    }

    // ── GET /admin/kriteria ───────────────────────────────────
    public function index(Request $request)
    {
        $this->checkAdmin();

        $q = $request->input('q');

        $items = KriteriaPemeriksaan::query()
            ->when($q, fn($query) => $query
                ->where('kode',       'like', "%$q%")
                ->orWhere('kategori', 'like', "%$q%")
                ->orWhere('deskripsi','like', "%$q%")
            )
            ->orderBy('urutan')
            ->paginate(25)
            ->withQueryString();

        $tipes   = KriteriaPemeriksaan::tipeOptions();
        $targets = KriteriaPemeriksaan::targetOptions();

        return view('admin.kriteria.index', compact('items', 'tipes', 'targets', 'q'));
    }

    // ── GET /admin/kriteria/create ────────────────────────────
    public function create()
    {
        $this->checkAdmin();

        $tipes   = KriteriaPemeriksaan::tipeOptions();
        $targets = KriteriaPemeriksaan::targetOptions();
        $areas   = KriteriaPemeriksaan::areaOptions();

        return view('admin.kriteria.form', compact('tipes', 'targets', 'areas'));
    }

    // ── POST /admin/kriteria ──────────────────────────────────
    public function store(Request $request)
    {
        $this->checkAdmin();

        $data = $this->validated($request);
        KriteriaPemeriksaan::create($data);

        return redirect()->route('admin.kriteria.index')
            ->with('success', 'Kriteria berhasil ditambahkan.');
    }

    // ── GET /admin/kriteria/{id}/edit ─────────────────────────
    public function edit(KriteriaPemeriksaan $kriteria)
    {
        $this->checkAdmin();

        $tipes   = KriteriaPemeriksaan::tipeOptions();
        $targets = KriteriaPemeriksaan::targetOptions();
        $areas   = KriteriaPemeriksaan::areaOptions();

        return view('admin.kriteria.form', compact('kriteria', 'tipes', 'targets', 'areas'));
    }

    // ── PUT /admin/kriteria/{id} ──────────────────────────────
    public function update(Request $request, KriteriaPemeriksaan $kriteria)
    {
        $this->checkAdmin();

        $data = $this->validated($request, $kriteria->id);
        $kriteria->update($data);

        return redirect()->route('admin.kriteria.index')
            ->with('success', 'Kriteria berhasil diperbarui.');
    }

    // ── DELETE /admin/kriteria/{id} ───────────────────────────
    public function destroy(KriteriaPemeriksaan $kriteria)
    {
        $this->checkAdmin();

        $kriteria->delete();

        return redirect()->route('admin.kriteria.index')
            ->with('success', 'Kriteria berhasil dihapus.');
    }

    // ── PATCH /admin/kriteria/{id}/toggle ─────────────────────
    public function toggle(KriteriaPemeriksaan $kriteria)
    {
        $this->checkAdmin();

        $kriteria->update(['aktif' => !$kriteria->aktif]);

        return back()->with('success', 'Status kriteria diperbarui.');
    }

    // ── POST /admin/kriteria/reorder ──────────────────────────
    public function reorder(Request $request)
    {
        $this->checkAdmin();

        $request->validate(['order' => 'required|array']);
        foreach ($request->input('order') as $i => $id) {
            KriteriaPemeriksaan::where('id', $id)->update(['urutan' => $i * 10]);
        }

        return response()->json(['ok' => true]);
    }

    // ── GET /admin/kriteria/import/template ───────────────────
    public function importTemplate()
    {
        $this->checkAdmin();

        $headers = [
            'kode', 'kategori', 'deskripsi', 'tipe_cek', 'target', 'status_gagal',
            'pesan_ok', 'pesan_gagal', 'aktif', 'urutan',
            'param_pattern', 'param_flags', 'param_text', 'param_case',
            'param_word', 'param_area', 'param_min', 'param_max_chars_page2',
        ];

        $examples = [
            ['COVER-01', 'Judul Publikasi', 'Judul publikasi harus ada di kover', 'contains', 'cover', 'TIDAK ADA',
                'Judul ditemukan di kover', 'Judul tidak ditemukan di kover', 1, 10,
                '', '', 'Katalog', 0, '', '', '', ''],
            ['COVER-02', 'Nomor ISSN', 'Nomor ISSN harus berada di kanan atas kover', 'posisi_area', 'cover', 'PERLU DICEK',
                'Posisi ISSN sesuai', 'Posisi ISSN tidak sesuai standar', 1, 20,
                '', '', '', '', 'ISSN', 'top_right', '', ''],
            ['UMUM-01', 'Kelengkapan Halaman', 'Verifikasi manual jumlah & kelengkapan halaman', 'manual', 'all', 'PERLU DICEK',
                '', 'Perlu diperiksa manual oleh petugas', 1, 30,
                '', '', '', '', '', '', '', ''],
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kriteria');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($examples, null, 'A2');
        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'template_import_kriteria.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── POST /admin/kriteria/import ───────────────────────────
    public function import(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getPathname());
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('error', 'File kosong atau tidak memiliki baris data.');
        }

        $header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
        $colIdx = array_flip($header);

        foreach (['kode', 'kategori', 'deskripsi', 'tipe_cek', 'target', 'status_gagal'] as $col) {
            if (!isset($colIdx[$col])) {
                return back()->with('error', "Kolom wajib '{$col}' tidak ditemukan di header file.");
            }
        }

        $validTipe   = array_keys(KriteriaPemeriksaan::tipeOptions());
        $validTarget = array_keys(KriteriaPemeriksaan::targetOptions());

        $get = fn(array $row, string $col) => isset($colIdx[$col])
            ? trim((string) ($row[$colIdx[$col]] ?? ''))
            : '';

        $imported = 0;
        $skipped  = [];

        DB::beginTransaction();
        try {
            foreach (array_slice($rows, 1) as $i => $row) {
                $lineNo = $i + 2;
                $kode   = $get($row, 'kode');
                if ($kode === '') continue;

                $tipe        = strtolower($get($row, 'tipe_cek')) ?: 'manual';
                $target      = strtolower($get($row, 'target')) ?: 'cover';
                $statusGagal = strtoupper($get($row, 'status_gagal')) ?: 'PERLU DICEK';

                if (!in_array($tipe, $validTipe, true)) {
                    $skipped[] = "Baris {$lineNo}: tipe_cek '{$tipe}' tidak valid";
                    continue;
                }
                if (!in_array($target, $validTarget, true)) {
                    $skipped[] = "Baris {$lineNo}: target '{$target}' tidak valid";
                    continue;
                }
                if (!in_array($statusGagal, ['TIDAK ADA', 'PERLU DICEK'], true)) {
                    $skipped[] = "Baris {$lineNo}: status_gagal '{$statusGagal}' tidak valid";
                    continue;
                }

                $aktifRaw = strtolower($get($row, 'aktif'));
                $aktif    = $aktifRaw === '' ? true : in_array($aktifRaw, ['1', 'true', 'ya', 'yes', 'aktif'], true);

                $urutanRaw = $get($row, 'urutan');

                KriteriaPemeriksaan::updateOrCreate(
                    ['kode' => $kode],
                    [
                        'kategori'     => $get($row, 'kategori') ?: '-',
                        'deskripsi'    => $get($row, 'deskripsi') ?: '-',
                        'tipe_cek'     => $tipe,
                        'target'       => $target,
                        'status_gagal' => $statusGagal,
                        'pesan_ok'     => $get($row, 'pesan_ok') ?: null,
                        'pesan_gagal'  => $get($row, 'pesan_gagal') ?: null,
                        'parameter'    => $this->parameterFromRow($tipe, $row, $get),
                        'aktif'        => $aktif,
                        'urutan'       => is_numeric($urutanRaw) ? (int) $urutanRaw : 0,
                    ]
                );
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }

        $msg = "{$imported} kriteria berhasil diimport.";
        if ($skipped) {
            $preview = implode('; ', array_slice($skipped, 0, 5));
            $msg .= ' Dilewati ' . count($skipped) . ' baris (' . $preview . (count($skipped) > 5 ? '; ...' : '') . ')';
        }

        return redirect()->route('admin.kriteria.index')->with('success', $msg);
    }

    private function parameterFromRow(string $tipe, array $row, \Closure $get): ?array
    {
        $param = match ($tipe) {
            'regex', 'not_regex' => array_filter([
                'pattern' => $get($row, 'param_pattern') ?: null,
                'flags'   => $get($row, 'param_flags') ?: null,
            ]),
            'contains', 'not_contains' => array_filter([
                'text' => $get($row, 'param_text') ?: null,
                'case' => in_array(strtolower($get($row, 'param_case')), ['1', 'true', 'ya', 'yes'], true),
            ]),
            'posisi_area' => array_filter([
                'word' => $get($row, 'param_word') ?: null,
                'area' => $get($row, 'param_area') ?: null,
            ]),
            'min_pages' => array_filter([
                'min'             => is_numeric($get($row, 'param_min')) ? (int) $get($row, 'param_min') : null,
                'max_chars_page2' => is_numeric($get($row, 'param_max_chars_page2')) ? (int) $get($row, 'param_max_chars_page2') : null,
            ]),
            default => null,
        };

        return $param ?: null;
    }

    // ─────────────────────────────────────────────────────────
    private function validated(Request $request, ?int $excludeId = null): array
    {
        $data = $request->validate([
            'kode'         => 'required|string|max:20|unique:kriteria_pemeriksaan,kode' . ($excludeId ? ",$excludeId" : ''),
            'kategori'     => 'required|string|max:100',
            'deskripsi'    => 'required|string|max:5000',
            'tipe_cek'     => 'required|in:' . implode(',', array_keys(KriteriaPemeriksaan::tipeOptions())),
            'target'       => 'required|in:' . implode(',', array_keys(KriteriaPemeriksaan::targetOptions())),
            'status_gagal' => 'required|in:TIDAK ADA,PERLU DICEK',
            'pesan_ok'     => 'nullable|string|max:255',
            'pesan_gagal'  => 'nullable|string|max:255',
            'aktif'        => 'boolean',
            'urutan'       => 'integer|min:0',
            'param_pattern'      => 'nullable|string',
            'param_flags'        => 'nullable|string|max:10',
            'param_text'         => 'nullable|string',
            'param_case'         => 'nullable|boolean',
            'param_word'         => 'nullable|string|max:100',
            'param_area'         => 'nullable|string|max:50',
            'param_min'          => 'nullable|integer|min:1',
            'param_max_chars_p2' => 'nullable|integer|min:0',
        ]);

        $tipe  = $data['tipe_cek'];
        $param = match($tipe) {
            'regex', 'not_regex' => array_filter([
                'pattern' => $request->input('param_pattern'),
                'flags'   => $request->input('param_flags') ?: null,
            ]),
            'contains', 'not_contains' => array_filter([
                'text' => $request->input('param_text'),
                'case' => $request->boolean('param_case'),
            ]),
            'posisi_area' => array_filter([
                'word' => $request->input('param_word'),
                'area' => $request->input('param_area'),
            ]),
            'min_pages' => array_filter([
                'min'             => $request->input('param_min') ? (int)$request->input('param_min') : null,
                'max_chars_page2' => $request->input('param_max_chars_p2') ? (int)$request->input('param_max_chars_p2') : null,
            ]),
            default => null,
        };

        $data['parameter'] = $param ?: null;
        $data['aktif']     = $request->boolean('aktif', true);

        foreach (array_keys($data) as $k) {
            if (str_starts_with($k, 'param_')) unset($data[$k]);
        }

        return $data;
    }
}