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
        // Only admin can access
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Akses ditolak. Hanya admin yang dapat mengelola Master SLS.');
            }
            return $next($request);
        });
    }

    /**
     * Display list of Master SLS
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $kdkab = $request->get('kdkab');

        $query = MasterSls::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('idsls', 'like', "%{$search}%")
                  ->orWhere('nmsls', 'like', "%{$search}%")
                  ->orWhere('nama_ketua', 'like', "%{$search}%");
            });
        }

        if ($kdkab) {
            $query->where('kdkab', $kdkab);
        }

        $data = $query->orderBy('kdkab')
                     ->orderBy('kdkec')
                     ->orderBy('kddesa')
                     ->orderBy('kdsls')
                     ->paginate(50);

        // Get kabupaten list for filter
        $kabupatenList = MasterSls::select('kdkab', 'nmkab')
                                  ->distinct()
                                  ->orderBy('nmkab')
                                  ->get();

        $stats = [
            'total' => MasterSls::count(),
            'active' => MasterSls::where('is_active', true)->count(),
            'sls' => MasterSls::where('jenis', 'SLS')->count(),
            'non_sls' => MasterSls::where('jenis', 'Non SLS')->count(),
        ];

        return view('master-sls.index', compact('data', 'kabupatenList', 'stats', 'search', 'kdkab'));
    }

    /**
     * Show import form
     */
    public function importForm()
    {
        return view('master-sls.import');
    }

    /**
     * Import Master SLS from CSV/Excel
     */
    public function import(Request $request)
    {
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
                    // Expected columns: idsls, nmsls, nama_ketua, jenis, kdprov, nmprov, kdkab, nmkab, 
                    //                   kdkec, nmkec, kddesa, nmdesa, kdsls, latitude, longitude
                    
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
        $data = MasterSls::orderBy('kdkab')
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
            
            // Header - URUTAN BARU
            fputcsv($file, [
                'idsls', 'nmsls', 'nama_ketua', 'jenis', 'kdprov', 'nmprov', 'kdkab', 'nmkab',
                'kdkec', 'nmkec', 'kddesa', 'nmdesa', 'kdsls', 'latitude', 'longitude'
            ]);

            // Data - URUTAN BARU
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
     * Download template Excel
     */
    public function exportTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Master SLS');

            // Header - URUTAN BARU
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
                ['5. kdprov (2 digit) - wajib'],
                ['   Contoh: 63'],
                [''],
                ['6. nmprov (nama provinsi) - wajib'],
                ['   Contoh: KALIMANTAN SELATAN'],
                [''],
                ['7. kdkab (2 digit) - wajib'],
                ['   Contoh: 01 untuk Tanah Laut'],
                [''],
                ['8. nmkab (nama kabupaten) - wajib'],
                ['   Contoh: TANAH LAUT'],
                [''],
                ['9. kdkec (3 digit) - wajib'],
                ['   Contoh: 010 untuk Panyipatan'],
                [''],
                ['10. nmkec (nama kecamatan) - wajib'],
                ['   Contoh: PANYIPATAN'],
                [''],
                ['11. kddesa (3 digit) - wajib'],
                ['   Contoh: 001 untuk Batakan'],
                [''],
                ['12. nmdesa (nama desa) - wajib'],
                ['   Contoh: BATAKAN'],
                [''],
                ['13. kdsls (4 digit) - wajib'],
                ['   Contoh: 0001, 0002, 9999 (untuk Non SLS)'],
                [''],
                ['14. latitude (koordinat GPS) - opsional'],
                ['15. longitude (koordinat GPS) - opsional'],
                [''],
                ['CATATAN PENTING:'],
                ['- idsls = kdprov + kdkab + kdkec + kddesa + kdsls'],
                ['- kdprov: 2 digit (63)'],
                ['- kdkab: 2 digit (01, 02, dst)'],
                ['- kdkec: 3 digit (010, 020, dst)'],
                ['- kddesa: 3 digit (001, 002, dst)'],
                ['- kdsls: 4 digit (0001-9999)'],
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
        try {
            $sls = MasterSls::findOrFail($id);

            // Check if has related status
            $hasStatus = \App\Models\StatusDaerahSulit::where('master_sls_id', $id)->exists();

            if ($hasStatus) {
                return back()->with('error', 'SLS tidak bisa dihapus karena sudah memiliki data status daerah sulit');
            }

            $sls->delete();

            Log::info('Master SLS deleted', [
                'user' => auth()->user()->username,
                'idsls' => $sls->idsls,
            ]);

            return back()->with('success', 'Data SLS berhasil dihapus');

        } catch (\Exception $e) {
            Log::error('Error deleting Master SLS: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus data');
        }
    }
}