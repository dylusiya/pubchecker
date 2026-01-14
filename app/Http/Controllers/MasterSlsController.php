<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MasterSls;

class MasterSlsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Semua user bisa akses, tapi admin bisa full CRUD
        // Non-admin hanya bisa view sesuai wilayahnya
    }

    /**
     * Display list of Master SLS
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $kdkab = $request->get('kdkab');
        $kdkec = $request->get('kdkec');
        $kddesa = $request->get('kddesa');
        $search = $request->get('search');
        $sort = $request->get('sort', 'idsls');
        $order = $request->get('order', 'asc');
        $perPage = $request->get('per_page', 50);
        
        // Base query
        $query = MasterSls::query();
        
        // Access control & Filter Wilayah
        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                // Akses kabupaten spesifik
                $query->where('kdkab', $user->kdkab); // gunakan accessor
                
            } elseif ($user->kode_provinsi) {
                // Akses semua kabupaten di provinsi
                $query->where('kdprov', $user->kdprov);
                };
            
        }

        if ($kdkec) {
            $query->where('kdkec', $kdkec);
        }

        if ($kddesa) {
            $query->where('kddesa', $kddesa);
        }

        // Search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('idsls', 'like', "%{$search}%")
                ->orWhere('nmsls', 'like', "%{$search}%")
                ->orWhere('nmkab', 'like', "%{$search}%")
                ->orWhere('nmkec', 'like', "%{$search}%")
                ->orWhere('nmdesa', 'like', "%{$search}%")
                ->orWhere('nama_ketua', 'like', "%{$search}%");
            });
        }
        
        // Sorting
        $query->orderBy($sort, $order);
        
        // Pagination
        if ($perPage == 'all') {
            $data = $query->get();
            $data = new \Illuminate\Pagination\LengthAwarePaginator(
                $data,
                $data->count(),
                $data->count(),
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $data = $query->paginate($perPage)->appends($request->query());
        }
        
        // Kabupaten list
        $kabupatenList = MasterSls::select('kdkab', 'nmkab')
            ->when(!$user->isAdmin() && $user->kode_kabupaten, function($q) use ($user) {
                $q->where('kdkab', $user->kode_kabupaten);
            })
            ->distinct()
            ->orderBy('kdkab')
            ->get();
        
        // Stats
        $statsQuery = MasterSls::query();
        if (!$user->isAdmin() && $user->kode_kabupaten) {
            $statsQuery->where('kdkab', $user->kode_kabupaten);
        }
        
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('is_active', true)->count(),
            'sls' => (clone $statsQuery)->where('jenis', 'SLS')->count(),
            'non_sls' => (clone $statsQuery)->where('jenis', '!=', 'SLS')->count(),
        ];
        
        return view('master-sls.index', compact('data', 'kdkab', 'kdkec', 'kddesa', 'search', 'kabupatenList', 'stats', 'sort', 'order', 'perPage'));
    }

    /**
     * Show import form
     */
    public function importForm()
    {
        // Only admin can import
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Hanya admin yang dapat mengimport Master SLS');
        }

        return view('master-sls.import');
    }

    /**
     * Import Master SLS from CSV/Excel
     */
    public function import(Request $request)
    {
        // Only admin can import
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Hanya admin yang dapat mengimport Master SLS');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ], [
            'file.required' => 'File harus diupload',
            'file.mimes' => 'File harus berformat CSV, TXT, atau Excel',
            'file.max' => 'Ukuran file maksimal 10MB',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            if (in_array($extension, ['csv', 'txt'])) {
                $result = $this->importFromCsv($file);
            } else {
                $result = $this->importFromExcel($file);
            }

            DB::commit();

            Log::info('Master SLS import completed', [
                'user' => auth()->user()->username,
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'failed' => $result['failed'],
            ]);

            return redirect()->route('master-sls.index')
                ->with('success', "Import berhasil! {$result['inserted']} data baru, {$result['updated']} data diupdate, {$result['failed']} gagal.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Master SLS import error: ' . $e->getMessage());
            return back()->with('error', 'Gagal import data: ' . $e->getMessage());
        }
    }

    /**
     * Import from CSV file
     */
    private function importFromCsv($file)
    {
        $inserted = 0;
        $updated = 0;
        $failed = 0;

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            // Skip header row
            $header = fgetcsv($handle, 1000, ',');
            
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                try {
                    if (count($row) < 13) {
                        $failed++;
                        continue;
                    }

                    $data = [
                        'idsls' => trim($row[0]),
                        'nmsls' => trim($row[1]),
                        'nama_ketua' => trim($row[2]) ?: null,
                        'jenis' => trim($row[3]),
                        'kdprov' => trim($row[4]),
                        'nmprov' => trim($row[5]),
                        'kdkab' => trim($row[6]),
                        'nmkab' => trim($row[7]),
                        'kdkec' => trim($row[8]),
                        'nmkec' => trim($row[9]),
                        'kddesa' => trim($row[10]),
                        'nmdesa' => trim($row[11]),
                        'kdsls' => trim($row[12]),
                        'latitude' => !empty($row[13]) ? floatval($row[13]) : null,
                        'longitude' => !empty($row[14]) ? floatval($row[14]) : null,
                        'is_active' => true,
                    ];

                    $exists = MasterSls::where('idsls', $data['idsls'])->first();

                    if ($exists) {
                        $exists->update($data);
                        $updated++;
                    } else {
                        MasterSls::create($data);
                        $inserted++;
                    }

                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to import row: ' . implode(',', $row) . ' - ' . $e->getMessage());
                }
            }

            fclose($handle);
        }

        return compact('inserted', 'updated', 'failed');
    }

    /**
     * Import from Excel file
     */
    private function importFromExcel($file)
    {
        $inserted = 0;
        $updated = 0;
        $failed = 0;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip header
            array_shift($rows);

            foreach ($rows as $row) {
                try {
                    if (count($row) < 13 || empty($row[0])) {
                        continue;
                    }

                    $data = [
                        'idsls' => trim($row[0]),
                        'nmsls' => trim($row[1]),
                        'nama_ketua' => trim($row[2]) ?: null,
                        'jenis' => trim($row[3]),
                        'kdprov' => trim($row[4]),
                        'nmprov' => trim($row[5]),
                        'kdkab' => trim($row[6]),
                        'nmkab' => trim($row[7]),
                        'kdkec' => trim($row[8]),
                        'nmkec' => trim($row[9]),
                        'kddesa' => trim($row[10]),
                        'nmdesa' => trim($row[11]),
                        'kdsls' => trim($row[12]),
                        'latitude' => !empty($row[13]) ? floatval($row[13]) : null,
                        'longitude' => !empty($row[14]) ? floatval($row[14]) : null,
                        'is_active' => true,
                    ];

                    $exists = MasterSls::where('idsls', $data['idsls'])->first();

                    if ($exists) {
                        $exists->update($data);
                        $updated++;
                    } else {
                        MasterSls::create($data);
                        $inserted++;
                    }

                } catch (\Exception $e) {
                    $failed++;
                }
            }

        } catch (\Exception $e) {
            throw $e;
        }

        return compact('inserted', 'updated', 'failed');
    }

    /**
     * Export Master SLS to CSV
     */
    public function export()
    {
        $user = auth()->user();
        
        $query = MasterSls::query();
        
        // Filter by user's kabupaten
        if (!$user->isAdmin() && $user->kode_kabupaten) {
            $query->where('kdkab', $user->kode_kabupaten);
        }
        
        $data = $query->orderBy('kdkab')
                     ->orderBy('kdkec')
                     ->orderBy('kddesa')
                     ->orderBy('kdsls')
                     ->get();

        $filename = 'master_sls_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, [
                'idsls', 'nmsls', 'nama_ketua', 'jenis', 'kdprov', 'nmprov', 'kdkab', 'nmkab',
                'kdkec', 'nmkec', 'kddesa', 'nmdesa', 'kdsls', 'latitude', 'longitude'
            ]);

            // Data
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->idsls,
                    $row->nmsls,
                    $row->nama_ketua,
                    $row->jenis,
                    $row->kdprov,
                    $row->nmprov,
                    $row->kdkab,
                    $row->nmkab,
                    $row->kdkec,
                    $row->nmkec,
                    $row->kddesa,
                    $row->nmdesa,
                    $row->kdsls,
                    $row->latitude,
                    $row->longitude,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Master SLS to Excel
     */
    public function exportExcel()
    {
        $user = auth()->user();
        
        $query = MasterSls::query();
        
        // Filter by user's kabupaten
        if (!$user->isAdmin() && $user->kode_kabupaten) {
            $query->where('kdkab', $user->kode_kabupaten);
        }
        
        $data = $query->orderBy('kdkab')
                    ->orderBy('kdkec')
                    ->orderBy('kddesa')
                    ->orderBy('kdsls')
                    ->get();

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Master SLS');

            // Header
            $headers = [
                'ID SLS', 'Nama SLS', 'Nama Ketua', 'Jenis', 
                'Kode Provinsi', 'Provinsi', 'Kode Kabupaten', 'Kabupaten',
                'Kode Kecamatan', 'Kecamatan', 'Kode Desa', 'Desa',
                'Kode SLS', 'Latitude', 'Longitude', 'Status'
            ];

            $sheet->fromArray($headers, null, 'A1');

            // Style header
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4B49AC']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ]
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ]
            ];
            $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

            // Data rows
            $row = 2;
            foreach ($data as $sls) {
                $sheet->fromArray([
                    $sls->idsls,
                    $sls->nmsls,
                    $sls->nama_ketua,
                    $sls->jenis_display,
                    $sls->kdprov,
                    $sls->nmprov,
                    $sls->kdkab,
                    $sls->nmkab,
                    $sls->kdkec,
                    $sls->nmkec,
                    $sls->kddesa,
                    $sls->nmdesa,
                    $sls->kdsls,
                    $sls->latitude,
                    $sls->longitude,
                    $sls->is_active ? 'Aktif' : 'Tidak Aktif',
                ], null, 'A' . $row);
                
                $row++;
            }

            // Auto size columns
            foreach (range('A', 'P') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set column A (ID SLS) as text to preserve leading zeros
            $sheet->getStyle('A2:A' . ($row - 1))->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            // Add borders to all data
            $sheet->getStyle('A1:P' . ($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]);

            // Generate filename
            $kabupaten = $user->isAdmin() ? 'Semua' : ($user->kabupaten ?? 'Data');
            $filename = 'master_sls_' . str_replace(' ', '_', $kabupaten) . '_' . date('Ymd_His') . '.xlsx';

            // Output
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            Log::error('Error exporting Master SLS to Excel: ' . $e->getMessage());
            return back()->with('error', 'Gagal export data ke Excel: ' . $e->getMessage());
        }
    }
    
    /**
     * Download template Excel
     */
    public function exportTemplate()
    {
        // Only admin can download template
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Hanya admin yang dapat mendownload template');
        }

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Master SLS');

            // Header
            $headers = [
                'idsls', 'nmsls', 'nama_ketua', 'jenis', 'kdprov', 'nmprov', 'kdkab', 'nmkab',
                'kdkec', 'nmkec', 'kddesa', 'nmdesa', 'kdsls', 'latitude', 'longitude'
            ];

            $sheet->fromArray($headers, null, 'A1');

            // Style header
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ]
                ]
            ];
            $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

            $sampleData = [
                ['63010100010001', 'RT 001 RW 004', 'IDERUS', 'SLS', '63', 'KALIMANTAN SELATAN', '01', 'TANAH LAUT',
                '010', 'PANYIPATAN', '001', 'BATAKAN', '0001', '', ''],
                ['63010100010002', 'RT 002 RW 005', 'MASTAN', 'SLS', '63', 'KALIMANTAN SELATAN', '01', 'TANAH LAUT',
                '010', 'PANYIPATAN', '001', 'BATAKAN', '0002', '', ''],
                ['63010100019999', 'Hutan Bakau', '', 'NONSLS_BUKAN_PEMUKIMAN', '63', 'KALIMANTAN SELATAN', '01', 'TANAH LAUT',
                '010', 'PANYIPATAN', '001', 'BATAKAN', '9999', '', ''],
                ['63010100029991', 'Lahan Pertanian', '', 'NONSLS_PERTANIAN', '63', 'KALIMANTAN SELATAN', '01', 'TANAH LAUT',
                '010', 'PANYIPATAN', '002', 'BUNATI', '9991', '', ''],
                ['63020100019998', 'Area Perairan', '', 'NONSLS_PERAIRAN', '63', 'KALIMANTAN SELATAN', '02', 'KOTABARU',
                '010', 'PULAU LAUT UTARA', '001', 'SEKATAP', '9998', '', ''],
            ];

            $sheet->fromArray($sampleData, null, 'A2');

            // Auto size columns
            foreach (range('A', 'O') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add instructions sheet
            $instructionSheet = $spreadsheet->createSheet();
            $instructionSheet->setTitle('Petunjuk');
            
            $instructions = [
                ['PETUNJUK PENGISIAN'],
                [''],
                ['Urutan Kolom:'],
                ['1. idsls (14 digit, unique) - wajib'],
                ['   Format: kdprov (2) + kdkab (2) + kdkec (3) + kddesa (3) + kdsls (4)'],
                ['   Contoh: 63010100010001'],
                [''],
                ['2. nmsls (nama SLS/RT) - wajib'],
                ['   Contoh: RT 001 RW 004'],
                [''],
                ['3. nama_ketua (nama ketua RT/RW) - opsional'],
                [''],
                ['4. jenis - wajib, pilihan:'],
                ['   - SLS'],
                ['   - NONSLS_BUKAN_PEMUKIMAN'],
                ['   - NONSLS_BUKAN_PERTANIAN'],
                ['   - NONSLS_LAHAN_TERBUKA'],
                ['   - NONSLS_PEMUKIMAN'],
                ['   - NONSLS_PERAIRAN'],
                ['   - NONSLS_PERTANIAN'],
                [''],
                ['5-15. Kode wilayah & koordinat'],
                [''],
                ['CATATAN PENTING:'],
                ['- idsls = kdprov + kdkab + kdkec + kddesa + kdsls'],
                ['- Semua kode harus sesuai format yang ditentukan'],
            ];

            $instructionSheet->fromArray($instructions, null, 'A1');
            $instructionSheet->getColumnDimension('A')->setWidth(60);

            $spreadsheet->setActiveSheetIndex(0);

            // Generate file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'template_master_sls_' . date('Y-m-d') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            Log::error('Error generating Excel template: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat template Excel');
        }
    }

    /**
     * Delete Master SLS
     */
    public function destroy($id)
    {
        // Only admin can delete
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Hanya admin yang dapat menghapus Master SLS');
        }

        DB::beginTransaction();
        try {
            $sls = MasterSls::findOrFail($id);

            // Check if has related status
            $hasStatus = \App\Models\StatusDaerahSulit::where('master_sls_id', $id)->exists();

            if ($hasStatus) {
                return back()->with('error', 'SLS tidak bisa dihapus karena sudah memiliki data status daerah sulit');
            }

            $sls->delete();

            DB::commit();

            Log::info('Master SLS deleted', [
                'user' => auth()->user()->username,
                'idsls' => $sls->idsls,
            ]);

            return back()->with('success', 'Data SLS berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting Master SLS: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus data');
        }
    }
}