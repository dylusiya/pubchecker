<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\MasterSls;
use App\Models\StatusDaerahSulit;
use App\Models\HistoryStatusDaerahSulit;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DaerahSulitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of status daerah sulit
     */
    public function index(Request $request)
    {
        $tahunAktif = $request->get('tahun', date('Y'));
        $kdkab = $request->get('kdkab');
        $kdkec = $request->get('kdkec');
        $kddesa = $request->get('kddesa');
        $approval = $request->get('approval', 'disetujui');
        $search = $request->get('search');
        $sort = $request->get('sort', 'idsls');
        $order = $request->get('order', 'asc');
        $perPage = $request->get('per_page', 20);
        
        $user = auth()->user();
        
        // Get latest status IDs first
        $latestIds = StatusDaerahSulit::selectRaw('MAX(id) as latest_id')
            ->where('tahun_anggaran', $tahunAktif)
            ->whereNull('deleted_at')
            ->groupBy('master_sls_id')
            ->pluck('latest_id');
        
        // Build query
        $query = StatusDaerahSulit::with(['masterSls'])
            ->whereIn('status_daerah_sulit.id', $latestIds);
        
        // Filter by kabupaten/kecamatan/desa
        if ($kdkab) {
            $query->whereHas('masterSls', function($q) use ($kdkab, $kdkec, $kddesa) {
                $q->where('kdkab', $kdkab);
                if ($kdkec) {
                    $q->where('kdkec', $kdkec);
                }
                if ($kddesa) {
                    $q->where('kddesa', $kddesa);
                }
            });
        }
        
        //Filter by status approval
        if ($approval) {
            $query->where('status_daerah_sulit.status_approval', $approval);
        }
        
        // Search
        if ($search) {
            $query->whereHas('masterSls', function($q) use ($search) {
                $q->where('idsls', 'like', "%{$search}%")
                ->orWhere('nmsls', 'like', "%{$search}%")
                ->orWhere('nmkab', 'like', "%{$search}%")
                ->orWhere('nmkec', 'like', "%{$search}%")
                ->orWhere('nmdesa', 'like', "%{$search}%");
            });
        }
        
        // Access control
        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                // Akses kabupaten spesifik
                $query->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdkab', $user->kdkab); // gunakan accessor
                });
            } elseif ($user->kode_provinsi) {
                // Akses semua kabupaten di provinsi
                $query->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdprov', $user->kdprov);
                });
            }
        }
        
        // Sorting
        if ($sort == 'idsls' || $sort == 'kdkab') {
            $query->join('master_sls', 'status_daerah_sulit.master_sls_id', '=', 'master_sls.id')
                ->select('status_daerah_sulit.*')
                ->orderBy("master_sls.{$sort}", $order);
        } else {
            $query->orderBy("status_daerah_sulit.{$sort}", $order);
        }
        
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
        
        // Get kabupaten list
        $kabupatenList = \App\Models\MasterSls::select('kdkab', 'nmkab')
                                    ->distinct()
                                    ->orderBy('kdkab')
                                    ->get();
        
        // Calculate stats
        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                $latestIds = StatusDaerahSulit::whereIn('id', $latestIds)
                    ->whereHas('masterSls', function($q) use ($user) {
                        $q->where('kdkab', $user->kdkab);
                    })
                    ->pluck('id');
            } elseif ($user->kode_provinsi) {
                $latestIds = StatusDaerahSulit::whereIn('id', $latestIds)
                    ->whereHas('masterSls', function($q) use ($user) {
                        $q->where('kdprov', $user->kdprov);
                    })
                    ->pluck('id');
            }
        }

        $stats = [
            'sulit' => StatusDaerahSulit::whereIn('id', $latestIds)
                        ->where('status_approval', 'disetujui')
                        ->where('is_daerah_sulit', true)
                        ->count(),
            'total_biaya' => StatusDaerahSulit::whereIn('id', $latestIds)
                        ->where('status_approval', 'disetujui')
                        ->where('is_daerah_sulit', true)
                        ->sum('perkiraan_biaya'),
        ];
        
        $tahun = $tahunAktif;
        
        return view('daerah-sulit.index', compact(
            'data', 
            'tahunAktif',
            'tahun',
            'kdkab',
            'kdkec',
            'kddesa',
            'approval',
            'search',
            'sort',
            'order',
            'perPage',
            'kabupatenList',
            'stats'
        ));
    }

    /**
     * Show the form for creating a new status
     */
    public function create(Request $request)
    {
        $tahun = $request->get('tahun', date('Y'));
        $user = auth()->user();

        $slsList = $user->accessibleSls()
            ->where('is_active', true)
            ->orderBy('kdkab')
            ->orderBy('kdkec')
            ->orderBy('kddesa')
            ->orderBy('kdsls')
            ->get();

        return view('daerah-sulit.create', compact('slsList', 'tahun'));
    }

    /**
     * Store a newly created status
     */
    public function store(Request $request)
    {
        $rules = [
            'master_sls_id' => 'required|exists:master_sls,id',
            'tahun_anggaran' => 'required|integer|min:2020|max:2100',
            'is_daerah_sulit' => 'required|boolean',
            'kegiatan' => 'required|string|max:255',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'waktu_tempuh_menit' => 'nullable|integer|min:0',
        ];

        // Jika Daerah Sulit (is_daerah_sulit == 1)
        if ($request->is_daerah_sulit == '1') {
            $rules['keterangan'] = 'required|string|min:20';
            $rules['perkiraan_biaya'] = 'required|numeric|min:0';
            $rules['metode_transportasi'] = 'required|string|max:100';
        } else {
            // Jika Tidak Sulit, keterangan boleh kosong
            $rules['keterangan'] = 'nullable|string';
            $rules['perkiraan_biaya'] = 'nullable|numeric';
            $rules['metode_transportasi'] = 'nullable|string';
        }

        $messages = [
            'master_sls_id.required' => 'SLS harus dipilih',
            'tahun_anggaran.required' => 'Tahun anggaran harus diisi',
            'is_daerah_sulit.required' => 'Status kesulitan harus dipilih',
            'keterangan.required' => 'Keterangan wajib diisi untuk Daerah Sulit',
            'keterangan.min' => 'Keterangan minimal 20 karakter',
            'perkiraan_biaya.required' => 'Perkiraan biaya wajib diisi untuk Daerah Sulit',
            'metode_transportasi.required' => 'Metode transportasi wajib dipilih untuk Daerah Sulit',
            'file_pendukung.mimes' => 'File harus berformat PDF, JPG, JPEG, atau PNG',
            'file_pendukung.max' => 'Ukuran file maksimal 2MB',
        ];

        $request->validate($rules, $messages);

        DB::beginTransaction();
        try {
            // Handle file upload
            $filePath = null;
            if ($request->hasFile('file_pendukung')) {
                $filePath = $this->uploadFile($request->file('file_pendukung'), $request->tahun_anggaran);
            }

            // Create status
            $status = StatusDaerahSulit::create([
                'master_sls_id' => $request->master_sls_id,
                'tahun_anggaran' => $request->tahun_anggaran,
                'is_daerah_sulit' => $request->is_daerah_sulit,
                'perkiraan_biaya' => $request->is_daerah_sulit ? $request->perkiraan_biaya : 0,
                'metode_transportasi' => $request->is_daerah_sulit ? $request->metode_transportasi : null,
                'waktu_tempuh_menit' => $request->is_daerah_sulit ? $request->waktu_tempuh_menit : null,
                'keterangan' => $request->keterangan,
                'kegiatan' => $request->kegiatan,
                'file_pendukung' => $filePath,
                'status_approval' => 'draft',
                'created_by' => auth()->id(),
            ]);

            // Log history
            HistoryStatusDaerahSulit::logHistory(
                $status,
                'create',
                null,
                null,
                $status->toArray(),
                'Data awal dibuat',
                $filePath
            );

            DB::commit();

            Log::info('Status daerah sulit created', [
                'user' => auth()->user()->username,
                'sls_id' => $status->masterSls->idsls,
                'tahun' => $status->tahun_anggaran,
            ]);

            return redirect()->route('daerah-sulit.show', $status->id)
                ->with('success', 'Status daerah sulit berhasil ditambahkan');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating status daerah sulit: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan status: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * AJAX search SLS for dropdown
     */
    public function searchSls(Request $request)
    {
        $search = $request->get('q');
        $tahun = $request->get('tahun');

        if (!$tahun) {
            return response()->json([]);
        }
        
        $user = auth()->user();
        
        $query = $user->accessibleSls()->where('is_active', true);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('idsls', 'like', "%{$search}%")
                ->orWhere('nmsls', 'like', "%{$search}%")
                ->orWhere('nmkab', 'like', "%{$search}%")
                ->orWhere('nmkec', 'like', "%{$search}%")
                ->orWhere('nmdesa', 'like', "%{$search}%");
            });
        }
        
        $results = $query->select('id', 'idsls', 'nmsls', 'nmkab', 'nmkec', 'nmdesa')
                        ->orderBy('kdkab')
                        ->orderBy('kdkec')
                        ->orderBy('kddesa')
                        ->limit(50)
                        ->get();
        
        return response()->json($results);
    }

    /**
     * Menampilkan halaman form import
     */
    public function importForm()
    {
        return view('daerah-sulit.import');
    }

    /**
     * Import Data Daerah Sulit
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'tahun_import' => 'required|integer',
            'kegiatan_import' => 'required|string|max:255',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048'
        ]);

        $tahun = $request->tahun_import;
        $kegiatan = $request->kegiatan_import;
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        // Load Data
        if (in_array($extension, ['csv', 'txt'])) {
            $rows = [];
            if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                fgetcsv($handle); // Skip header
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        } else {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // Skip header
        }

        $inserted = 0;
        $importErrors = []; // Array untuk menampung detail error per baris

        DB::beginTransaction();
        try {
            $pathPendukung = null;
            if ($request->hasFile('file_pendukung')) {
                $pathPendukung = $this->uploadFile($request->file('file_pendukung'), $tahun);
            }

            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2; // +2 karena index mulai 0 dan ada header
                
                if (empty($row[0])) continue;

                $idsls = trim($row[0]);
                $masterSls = MasterSls::where('idsls', $idsls)->first();

                // Validasi: Master SLS tidak ditemukan
                if (!$masterSls) {
                    $importErrors[] = "Baris $lineNumber: IDSLS ($idsls) tidak ditemukan di database Master SLS.";
                    continue;
                }
                
                // Validasi: Duplikat
                $alreadyExists = StatusDaerahSulit::where('master_sls_id', $masterSls->id)
                    ->where('tahun_anggaran', $tahun)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($alreadyExists) {
                    $importErrors[] = "Baris $lineNumber: SLS ($idsls) sudah pernah diinput untuk tahun $tahun (Duplikat).";
                    continue;
                }

                // Validasi: Kelengkapan jika sulit
                $isSulit = ($row[1] == '1');
                    if ($isSulit && empty($row[2])) {
                        $importErrors[] = "Baris $lineNumber: Perkiraan biaya wajib diisi jika status sulit.";
                        continue;
                    }

                StatusDaerahSulit::create([
                    'master_sls_id' => $masterSls->id,
                    'tahun_anggaran' => $tahun,
                    'kegiatan' => $kegiatan,
                    'is_daerah_sulit' => $isSulit,
                    'perkiraan_biaya' => $isSulit ? ($row[2] ?? 0) : 0,
                    'metode_transportasi' => $isSulit ? ($row[3] ?? null) : null,
                    'waktu_tempuh_menit' => $isSulit ? ($row[4] ?? null) : null,
                    'keterangan' => $row[5] ?? ($isSulit ? 'Import Kolektif' : '-'),
                    'file_pendukung' => $pathPendukung,
                    'status_approval' => 'draft',
                    'created_by' => auth()->id(),
                ]);
                $inserted++;
            }

            DB::commit();

            // Redirect dengan informasi sukses dan daftar error jika ada
            if (count($importErrors) > 0) {
                return redirect()->route('daerah-sulit.index')
                    ->with('success', "$inserted data berhasil diimport.")
                    ->with('importErrors', $importErrors);
            }

            return redirect()->route('daerah-sulit.index')->with('success', "$inserted data berhasil diimport.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    /**
     * Download template CSV untuk import daerah sulit
     */
    public function downloadTemplate()
    {
        $filename = "template_daerah_sulit.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            // Header kolom sesuai instruksi import
            fputcsv($file, ['idsls', 'is_sulit', 'perkiraan_biaya', 'metode_transportasi', 'waktu_tempuh_menit', 'keterangan']);
            
            // Baris Contoh (Sample Data)
            fputcsv($file, ['63010100010001', '1', '500000', 'Perahu', '120', 'Akses sungai deras']);
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download template Excel (.xlsx) untuk import
     */
    public function downloadTemplateExcel()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import');

        // 1. Definisikan Header
        $headers = ['idsls', 'is_sulit', 'perkiraan_biaya', 'metode_transportasi', 'waktu_tempuh_menit', 'keterangan'];
        $sheet->fromArray($headers, NULL, 'A1');

        // 2. Styling Header (Biar rapi)
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4B49AC'] // Warna biru sesuai tema template admin Anda
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        // 3. Tambahkan Contoh Data (Sample)
        $sampleData = [
            ['63010100010021', 1, 150000, 'Perahu', 120, 'Menggunakan ojek sepeda motor trail untuk geotagging batas ke sls (Pulau Obi) dan menghitung LKM'],
        ];
        $sheet->fromArray($sampleData, NULL, 'A2');

        // 4. Set Format Kolom IDSLS sebagai TEXT (PENTING!)
        // Agar angka 0 di depan tidak hilang
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // 5. Auto-size kolom
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 6. Proses Download
        $filename = 'template_daerah_sulit_' . date('Ymd') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Display the specified status
     */
    public function show($id)
    {
        $status = StatusDaerahSulit::with(['masterSls', 'creator', 'updater', 'approver', 'histories.user'])
            ->findOrFail($id);

        // Check access
        $this->authorizeAccess($status);

        return view('daerah-sulit.show', compact('status'));
    }

    /**
     * Show the form for editing the specified status
     */
    public function edit($id)
    {
        $status = StatusDaerahSulit::with(['masterSls'])->findOrFail($id);

        // Check access
        $this->authorizeAccess($status);

        // Check if can edit
        if (!$status->canEdit()) {
            return back()->with('error', 'Status ini tidak bisa diedit karena sudah ' . $status->status_display);
        }

        return view('daerah-sulit.edit', compact('status'));
    }

    /**
     * Update the specified status
     */
    public function update(Request $request, $id)
    {
        $status = StatusDaerahSulit::findOrFail($id);

        // Check access
        $this->authorizeAccess($status);

        // Check if can edit
        if (!$status->canEdit()) {
            return back()->with('error', 'Status ini tidak bisa diedit');
        }

        $request->validate([
            'is_daerah_sulit' => 'required|boolean',
            'perkiraan_biaya' => 'nullable|numeric|min:0',
            'metode_transportasi' => 'nullable|string|max:100',
            'waktu_tempuh_menit' => 'nullable|integer|min:0',
            'keterangan' => 'required|string|min:20',
            'kegiatan' => 'nullable|string|max:255',
            'alasan_perubahan' => 'required|string|min:20',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // ✅ Ubah jadi nullable
        ], [
            'alasan_perubahan.required' => 'Alasan perubahan harus diisi',
            'alasan_perubahan.min' => 'Alasan perubahan minimal 20 karakter',
            'keterangan.required' => 'Keterangan harus diisi',
            'keterangan.min' => 'Keterangan minimal 20 karakter',
            'file_pendukung.mimes' => 'File harus berformat PDF, JPG, JPEG, atau PNG',
            'file_pendukung.max' => 'Ukuran file maksimal 2MB',
        ]);

        DB::beginTransaction();
        try {
            // Store before state
            $before = $status->toArray();

            // ✅ Handle file upload (hanya jika ada file baru)
            $filePath = $status->file_pendukung; // Keep existing file
            if ($request->hasFile('file_pendukung')) {
                $filePath = $this->uploadFile($request->file('file_pendukung'), $status->tahun_anggaran);
                
                // ✅ Optional: Delete old file
                if ($status->file_pendukung && Storage::disk('public')->exists($status->file_pendukung)) {
                    Storage::disk('public')->delete($status->file_pendukung);
                }
            }

            // Detect changed fields
            $changedFields = [];
            if ($status->is_daerah_sulit != $request->is_daerah_sulit) $changedFields[] = 'is_daerah_sulit';
            if ($status->perkiraan_biaya != $request->perkiraan_biaya) $changedFields[] = 'perkiraan_biaya';
            if ($status->metode_transportasi != $request->metode_transportasi) $changedFields[] = 'metode_transportasi';
            if ($status->waktu_tempuh_menit != $request->waktu_tempuh_menit) $changedFields[] = 'waktu_tempuh_menit';
            if ($status->keterangan != $request->keterangan) $changedFields[] = 'keterangan';
            if ($status->kegiatan != $request->kegiatan) $changedFields[] = 'kegiatan';
            if ($request->hasFile('file_pendukung')) $changedFields[] = 'file_pendukung';

            // Update status
            $status->update([
                'is_daerah_sulit' => $request->is_daerah_sulit,
                'perkiraan_biaya' => $request->is_daerah_sulit ? $request->perkiraan_biaya : 0,
                'metode_transportasi' => $request->is_daerah_sulit ? $request->metode_transportasi : null,
                'waktu_tempuh_menit' => $request->is_daerah_sulit ? $request->waktu_tempuh_menit : null,
                'keterangan' => $request->keterangan,
                'kegiatan' => $request->kegiatan,
                'file_pendukung' => $filePath, // ✅ Gunakan file baru atau file lama
                'updated_by' => auth()->id(),
            ]);

            // Log history
            HistoryStatusDaerahSulit::logHistory(
                $status,
                'update',
                implode(',', $changedFields),
                $before,
                $status->fresh()->toArray(),
                $request->alasan_perubahan,
                $request->hasFile('file_pendukung') ? $filePath : null // ✅ Log file hanya jika diupdate
            );

            DB::commit();

            Log::info('Status daerah sulit updated', [
                'user' => auth()->user()->username,
                'status_id' => $status->id,
                'changed_fields' => implode(',', $changedFields),
            ]);

            return redirect()->route('daerah-sulit.show', $status->id)
                ->with('success', 'Status berhasil diupdate');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating status: ' . $e->getMessage());
            return back()->with('error', 'Gagal update status: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified status
     */
    public function destroy($id)
    {
        $status = StatusDaerahSulit::findOrFail($id);

        // Check access
        $this->authorizeAccess($status);

        // Check if can delete
        if (!$status->canDelete()) {
            return back()->with('error', 'Hanya status draft yang bisa dihapus');
        }

        DB::beginTransaction();
        try {
            // Log before delete
            HistoryStatusDaerahSulit::logHistory(
                $status,
                'delete',
                null,
                $status->toArray(),
                null,
                'Data dihapus oleh ' . auth()->user()->name,
                null
            );

            // Soft delete
            $status->delete();

            DB::commit();

            Log::info('Status daerah sulit deleted', [
                'user' => auth()->user()->username,
                'status_id' => $status->id,
            ]);

            return redirect()->route('daerah-sulit.index')
                ->with('success', 'Status berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting status: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus status');
        }
    }

    /**
     * Submit for review
     */
    public function submit($id)
    {
        try {
            $status = StatusDaerahSulit::with('masterSls')->findOrFail($id);

            $this->authorizeAccess($status);

            if (!$status->canSubmit()) {
                return back()->with('error', 'Status ini tidak bisa disubmit. Status saat ini: ' . $status->status_approval);
            }

            DB::beginTransaction();
            try {
                $before = $status->toArray();

                $status->status_approval = 'pending';
                $status->updated_by = auth()->id();
                $status->save();

                // ✅ Gunakan aksi 'update' untuk submit (atau buat ENUM baru 'submit')
                HistoryStatusDaerahSulit::logHistory(
                    $status,
                    'update',  // Atau tambah 'submit' ke ENUM jika perlu
                    'status_approval',
                    $before,
                    $status->fresh()->toArray(),
                    'Status disubmit untuk review',
                    null
                );

                DB::commit();

                Log::info('Status submitted successfully', [
                    'status_id' => $status->id,
                    'user' => auth()->user()->username
                ]);

                return redirect()->route('daerah-sulit.show', $status->id)
                    ->with('success', 'Status berhasil disubmit untuk review');

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error in submit transaction: ' . $e->getMessage());
                return back()->with('error', 'Gagal submit status: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            Log::error('Error in submit method: ' . $e->getMessage());
            return back()->with('error', 'Gagal submit status: ' . $e->getMessage());
        }
    }

    /**
     * Approve status
     */
    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        
        // ✅ Check if user can approve
        if (!$user->canApproveDaerahSulit()) {
            abort(403, 'Anda tidak memiliki akses untuk approve data');
        }

        $status = StatusDaerahSulit::findOrFail($id);

        if (!$status->canApprove()) {
            return back()->with('error', 'Status ini tidak bisa diapprove');
        }

        $request->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $before = $status->toArray();

            $status->update([
                'status_approval' => 'disetujui',
                'catatan_approval' => $request->catatan_approval,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            HistoryStatusDaerahSulit::logHistory(
                $status,
                'approval',
                'status_approval',
                $before,
                $status->fresh()->toArray(),
                'Status disetujui' . ($request->catatan_approval ? ': ' . $request->catatan_approval : ''),
                null
            );

            DB::commit();

            Log::info('Status approved', [
                'status_id' => $status->id,
                'user' => auth()->user()->username
            ]);

            return back()->with('success', 'Status berhasil disetujui');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving status: ' . $e->getMessage());
            return back()->with('error', 'Gagal approve status');
        }
    }

    /**
     * Reject status
     */
    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        
        // ✅ Check if user can reject
        if (!$user->canApproveDaerahSulit()) {
            abort(403, 'Anda tidak memiliki akses untuk reject data');
        }

        $status = StatusDaerahSulit::findOrFail($id);

        if (!$status->canApprove()) {
            return back()->with('error', 'Status ini tidak bisa ditolak');
        }

        $request->validate([
            'catatan_approval' => 'required|string|min:20',
        ], [
            'catatan_approval.required' => 'Alasan penolakan harus diisi',
            'catatan_approval.min' => 'Alasan penolakan minimal 20 karakter',
        ]);

        DB::beginTransaction();
        try {
            $before = $status->toArray();

            $status->update([
                'status_approval' => 'ditolak',
                'catatan_approval' => $request->catatan_approval,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            HistoryStatusDaerahSulit::logHistory(
                $status,
                'rejection',
                'status_approval',
                $before,
                $status->fresh()->toArray(),
                'Status ditolak: ' . $request->catatan_approval,
                null
            );

            DB::commit();

            Log::info('Status rejected', [
                'status_id' => $status->id,
                'user' => auth()->user()->username
            ]);

            return back()->with('success', 'Status berhasil ditolak');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rejecting status: ' . $e->getMessage());
            return back()->with('error', 'Gagal reject status');
        }
    }


    /**
     * Show draft list for bulk submit
     */
    public function pendingSubmit(Request $request)
    {
        $user = auth()->user();
        $tahun = $request->get('tahun', date('Y'));
        $kdkab = $request->get('kdkab'); 
        $kdkec = $request->get('kdkec');
        $kddesa = $request->get('kddesa');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 20);
        $statusFilter = $request->get('status_filter');
        $sort = $request->get('sort', 'idsls');
        $order = $request->get('order', 'asc');
        
        $query = StatusDaerahSulit::with(['masterSls'])
            ->whereIn('status_approval', ['draft', 'ditolak'])
            ->where('tahun_anggaran', $tahun);
        
        // Filter by status
        if ($statusFilter) {
            $query->where('status_approval', $statusFilter);
        }
        
        // Filter by kabupaten/kecamatan/desa
        if ($kdkab) {
            $query->whereHas('masterSls', function($q) use ($kdkab, $kdkec, $kddesa) {
                $q->where('kdkab', $kdkab);
                if ($kdkec) {
                    $q->where('kdkec', $kdkec);
                }
                if ($kddesa) {
                    $q->where('kddesa', $kddesa);
                }
            });
        }
        
        // Filter by user's kabupaten if not admin
        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                $query->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdkab', $user->kdkab);
                });
            } elseif ($user->kode_provinsi) {
                $query->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdprov', $user->kdprov);
                });
            }
        }
        
        // Search
        if ($search) {
            $query->whereHas('masterSls', function($q) use ($search) {
                $q->where('idsls', 'like', "%{$search}%")
                ->orWhere('nmsls', 'like', "%{$search}%")
                ->orWhere('nmkab', 'like', "%{$search}%")
                ->orWhere('nmkec', 'like', "%{$search}%")
                ->orWhere('nmdesa', 'like', "%{$search}%");
            });
        }
        
        // ✅ Sorting
        if ($sort == 'idsls' || $sort == 'kdkab') {
            $query->join('master_sls', 'status_daerah_sulit.master_sls_id', '=', 'master_sls.id')
                ->select('status_daerah_sulit.*')
                ->orderBy("master_sls.{$sort}", $order);
        } else {
            $query->orderBy($sort, $order);
        }
        
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
        
        // Stats
        $statsQuery = StatusDaerahSulit::whereIn('status_approval', ['draft', 'ditolak'])
            ->where('tahun_anggaran', $tahun);
        
        if ($kdkab) {
            $statsQuery->whereHas('masterSls', function($q) use ($kdkab) {
                $q->where('kdkab', $kdkab);
            });
        }
        
        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                $statsQuery->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdkab', $user->kdkab);
                });
            } elseif ($user->kode_provinsi) {
                $statsQuery->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdprov', $user->kdprov);
                });
            }
        }
        
        $stats = [
            'total_draft' => (clone $statsQuery)->where('status_approval', 'draft')->count(),
            'total_ditolak' => (clone $statsQuery)->where('status_approval', 'ditolak')->count(),
        ];
        
        return view('daerah-sulit.pending-submit', compact(
            'data', 
            'tahun',
            'kdkab',
            'kdkec',
            'kddesa',
            'search', 
            'stats', 
            'perPage',
            'sort',
            'order'
        ));
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:status_daerah_sulit,id',
        ]);
        
        $user = auth()->user();
        
        DB::beginTransaction();
        try {
            $deleted = 0;
            
            foreach ($request->ids as $id) {
                $status = StatusDaerahSulit::with('masterSls')->find($id);
                
                if ($status && $status->canDelete()) {
                    // Check access
                    if (!$user->isAdmin()) {
                        if ($user->kode_kabupaten && $status->masterSls->kdkab !== $user->kdkab) {
                            continue;
                        } elseif ($user->kode_provinsi && $status->masterSls->kdprov !== $user->kdprov) {
                            continue;
                        }
                    }
                    
                    // Log history before delete
                    HistoryStatusDaerahSulit::logHistory(
                        $status,
                        'delete',
                        null,
                        $status->toArray(),
                        null,
                        'Bulk delete oleh ' . auth()->user()->name,
                        null
                    );
                    
                    $status->delete();
                    $deleted++;
                }
            }
            
            DB::commit();
            
            Log::info('Bulk delete', [
                'user' => auth()->user()->username,
                'deleted_count' => $deleted,
            ]);
            
            return redirect()->route('daerah-sulit.pending-submit')
                ->with('success', "$deleted data berhasil dihapus");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk delete: ' . $e->getMessage());
            return back()->with('error', 'Gagal hapus: ' . $e->getMessage());
        }
    }

    /**
     * Bulk submit
     */
    public function bulkSubmit(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:status_daerah_sulit,id',
        ]);
        
        $user = auth()->user();
        
        DB::beginTransaction();
        try {
            $submitted = 0;
            
            foreach ($request->ids as $id) {
                $status = StatusDaerahSulit::with('masterSls')->find($id);
                
                if ($status && $status->canSubmit()) {
                    // Check access
                    if (!$user->isAdmin()) {
                        if ($user->kode_kabupaten && $status->masterSls->kdkab !== $user->kdkab) {
                            continue;
                        } elseif ($user->kode_provinsi && $status->masterSls->kdprov !== $user->kdprov) {
                            continue;
                        }
                    }
                    
                    $before = $status->toArray();
                    
                    $status->status_approval = 'pending';
                    $status->updated_by = auth()->id();
                    $status->save();
                    
                    HistoryStatusDaerahSulit::logHistory(
                        $status,
                        'update',
                        'status_approval',
                        $before,
                        $status->fresh()->toArray(),
                        'Bulk submit untuk review',
                        null
                    );
                    
                    $submitted++;
                }
            }
            
            DB::commit();
            
            Log::info('Bulk submit', [
                'user' => auth()->user()->username,
                'submitted_count' => $submitted,
            ]);
            
            return redirect()->route('daerah-sulit.pending-submit')
                ->with('success', "$submitted data berhasil disubmit untuk review");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk submit: ' . $e->getMessage());
            return back()->with('error', 'Gagal submit: ' . $e->getMessage());
        }
    }

    /**
     * Show pending approval list (for admin/approver)
     */
    public function pendingApproval(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isApprover()) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini');
        }
        
        $tahun = $request->get('tahun', date('Y'));
        $kdkab = $request->get('kdkab');
        $kdkec = $request->get('kdkec');
        $kddesa = $request->get('kddesa');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 20);
        $sort = $request->get('sort', 'idsls');
        $order = $request->get('order', 'asc');
        
        $query = StatusDaerahSulit::with(['masterSls', 'creator'])
            ->where('status_approval', 'pending')
            ->where('tahun_anggaran', $tahun);
        
        // Filter by kabupaten/kecamatan/desa
        if ($kdkab) {
            $query->whereHas('masterSls', function($q) use ($kdkab, $kdkec, $kddesa) {
                $q->where('kdkab', $kdkab);
                if ($kdkec) {
                    $q->where('kdkec', $kdkec);
                }
                if ($kddesa) {
                    $q->where('kddesa', $kddesa);
                }
            });
        }
        
        if ($search) {
            $query->whereHas('masterSls', function($q) use ($search) {
                $q->where('idsls', 'like', "%{$search}%")
                ->orWhere('nmsls', 'like', "%{$search}%")
                ->orWhere('nmkab', 'like', "%{$search}%")
                ->orWhere('nmkec', 'like', "%{$search}%")
                ->orWhere('nmdesa', 'like', "%{$search}%");
            });
        }
        
        // Sorting
        if ($sort == 'idsls' || $sort == 'kdkab') {
            $query->join('master_sls', 'status_daerah_sulit.master_sls_id', '=', 'master_sls.id')
                ->select('status_daerah_sulit.*')
                ->orderBy("master_sls.{$sort}", $order);
        } else {
            $query->orderBy($sort, $order);
        }
        
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
        
        // sort by kdkab
        $kabupatenList = \App\Models\MasterSls::select('kdkab', 'nmkab')
                                    ->distinct()
                                    ->orderBy('kdkab')
                                    ->get();
        
        $stats = [
            'total_pending' => StatusDaerahSulit::where('status_approval', 'pending')
                                ->where('tahun_anggaran', $tahun)
                                ->count(),
            'total_sulit' => StatusDaerahSulit::where('status_approval', 'pending')
                                ->where('tahun_anggaran', $tahun)
                                ->where('is_daerah_sulit', true)
                                ->count(),
        ];
        
        return view('daerah-sulit.pending-approval', compact(
            'data', 
            'tahun', 
            'kdkab',
            'kdkec',
            'kddesa',
            'search', 
            'kabupatenList', 
            'stats', 
            'perPage',
            'sort', 
            'order' 
        ));
    }

    /**
     * Bulk approve
     */
    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isApprover()) {
            abort(403, 'Anda tidak memiliki akses');
        }
        
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:status_daerah_sulit,id',
            'catatan_approval' => 'nullable|string|max:500',
        ]);
        
        DB::beginTransaction();
        try {
            $approved = 0;
            
            foreach ($request->ids as $id) {
                $status = StatusDaerahSulit::find($id);
                
                if ($status && $status->canApprove()) {
                    $before = $status->toArray();
                    
                    $status->update([
                        'status_approval' => 'disetujui',
                        'catatan_approval' => $request->catatan_approval,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    
                    HistoryStatusDaerahSulit::logHistory(
                        $status,
                        'approval',
                        'status_approval',
                        $before,
                        $status->fresh()->toArray(),
                        'Bulk approval: ' . ($request->catatan_approval ?? 'Disetujui secara massal'),
                        null
                    );
                    
                    $approved++;
                }
            }
            
            DB::commit();
            
            Log::info('Bulk approval', [
                'user' => auth()->user()->username,
                'approved_count' => $approved,
            ]);
            
            return redirect()->route('daerah-sulit.pending-approval')
                ->with('success', "$approved data berhasil disetujui");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk approve: ' . $e->getMessage());
            return back()->with('error', 'Gagal approve: ' . $e->getMessage());
        }
    }

    /**
     * Bulk reject
     */
    public function bulkReject(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin() && !$user->isApprover()) {
            abort(403, 'Anda tidak memiliki akses');
        }
        
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:status_daerah_sulit,id',
            'catatan_approval' => 'required|string|min:20',
        ], [
            'catatan_approval.required' => 'Alasan penolakan harus diisi',
            'catatan_approval.min' => 'Alasan penolakan minimal 20 karakter',
        ]);
        
        DB::beginTransaction();
        try {
            $rejected = 0;
            
            foreach ($request->ids as $id) {
                $status = StatusDaerahSulit::find($id);
                
                if ($status && $status->canApprove()) {
                    $before = $status->toArray();
                    
                    $status->update([
                        'status_approval' => 'ditolak',
                        'catatan_approval' => $request->catatan_approval,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    
                    HistoryStatusDaerahSulit::logHistory(
                        $status,
                        'rejection',
                        'status_approval',
                        $before,
                        $status->fresh()->toArray(),
                        'Bulk rejection: ' . $request->catatan_approval,
                        null
                    );
                    
                    $rejected++;
                }
            }
            
            DB::commit();
            
            Log::info('Bulk rejection', [
                'user' => auth()->user()->username,
                'rejected_count' => $rejected,
            ]);
            
            return redirect()->route('daerah-sulit.pending-approval')
                ->with('success', "$rejected data berhasil ditolak");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk reject: ' . $e->getMessage());
            return back()->with('error', 'Gagal reject: ' . $e->getMessage());
        }
    }

    /**
     * View history
     */
    public function history(Request $request)
    {
        $user = auth()->user();
        $tahun = $request->get('tahun', date('Y'));

        $query = HistoryStatusDaerahSulit::with(['masterSls', 'user', 'statusDaerahSulit'])
            ->where('tahun_anggaran', $tahun);

        // Filter by user's kabupaten if not admin
        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                $query->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdkab', $user->kdkab);
                });
            } elseif ($user->kode_provinsi) {
                $query->whereHas('masterSls', function($q) use ($user) {
                    $q->where('kdprov', $user->kdprov);
                });
            }
        }

        $histories = $query->latest('created_at')->paginate(50);
        $tahunList = $this->getTahunList();

        return view('daerah-sulit.history', compact('histories', 'tahun', 'tahunList'));
    }

    /**
     * Copy status from previous year
     */
    public function copyFromPreviousYear(Request $request)
    {
        $request->validate([
            'tahun_lama' => 'required|integer',
            'tahun_baru' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            DB::statement('CALL sp_copy_status_tahun_sebelumnya(?, ?, ?)', [
                $request->tahun_lama,
                $request->tahun_baru,
                auth()->id()
            ]);

            DB::commit();

            return back()->with('success', 'Data berhasil dicopy dari tahun ' . $request->tahun_lama . ' ke ' . $request->tahun_baru);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error copying status: ' . $e->getMessage());
            return back()->with('error', 'Gagal copy data: ' . $e->getMessage());
        }
    }

    // Dipanggil via AJAX dari browser, WAJIB public dan BUTUH route
    public function getKecamatan(Request $request)
    {
        $kecamatan = MasterSls::where('kdkab', $request->kdkab)
            ->select('kdkec', 'nmkec')
            ->distinct()
            ->orderBy('kdkec')
            ->get();
        return response()->json($kecamatan);
    }

    // Dipanggil via AJAX dari browser, WAJIB public dan BUTUH route
    public function getDesa(Request $request)
    {
        $desa = MasterSls::where('kdkab', $request->kdkab)
            ->where('kdkec', $request->kdkec)
            ->select('kddesa', 'nmdesa')
            ->distinct()
            ->orderBy('kddesa')
            ->get();
        return response()->json($desa);
    }

    /**
     * Upload file
     */
    private function uploadFile($file, $tahun)
    {
        $user = auth()->user();
        $kabupaten = $user->kode_kabupaten ?? '00';
        
        $filename = 'sls_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = "daerah_sulit/{$tahun}/{$kabupaten}";
        
        return $file->storeAs($path, $filename, 'public');
    }

    /**
     * Get kabupaten list for filter
     */
    private function getKabupatenList($user)
    {
        $query = MasterSls::select('kdkab', 'nmkab')
            ->distinct();

        if (!$user->isAdmin()) {
            if ($user->kode_kabupaten) {
                $query->where('kdkab', $user->kdkab);
            } elseif ($user->kode_provinsi) {
                $query->where('kdprov', $user->kdprov);
            }
        }

        return $query->orderBy('kdkab')->get();
    }

    /**
     * Get tahun list
     */
    private function getTahunList()
    {
        return StatusDaerahSulit::select('tahun_anggaran')
            ->distinct()
            ->orderBy('tahun_anggaran', 'desc')
            ->pluck('tahun_anggaran');
    }

    /**
     * Check user access to status
     */
    private function authorizeAccess($status)
    {
        $user = auth()->user();
        
        if ($user->isAdmin()) {
            return;
        }

        // Check kabupaten access
        if ($user->kode_kabupaten) {
            if ($status->masterSls->kdkab !== $user->kdkab) {
                abort(403, 'Anda tidak memiliki akses ke data kabupaten ini');
            }
        } elseif ($user->kode_provinsi) {
            // Check provinsi access
            if ($status->masterSls->kdprov !== $user->kdprov) {
                abort(403, 'Anda tidak memiliki akses ke data provinsi ini');
            }
        }
    }
}