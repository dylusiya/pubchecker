<?php

namespace App\Http\Controllers;

use App\Models\SurveyResponse;
use App\Models\SurveyDataEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Penting untuk debugging error 500
use Illuminate\Support\Str;

class SurveyController extends Controller
{
    /**
     * Display survey form
     */
    public function index(Request $request)
    {
        $sessionId = $request->cookie('survey_session_id');
        $draft = null;

        if ($sessionId) {
            $draft = SurveyResponse::where('session_id', $sessionId)
                ->where('status', 'draft')
                ->with('dataEntries')
                ->first();
        }

        return view('survey.index', compact('draft'));
    }

    /**
     * Auto-save as draft
     * Handle dengan fleksibel (tanpa validasi ketat) karena data belum lengkap
     */
    public function autosave(Request $request)
    {
        try {
            // 1. Identifikasi Session
            $sessionId = $request->input('session_id') ?? $request->cookie('survey_session_id');
            if (!$sessionId || $sessionId === 'null') {
                $sessionId = Str::uuid()->toString();
            }

            DB::beginTransaction();

            // 2. Cari Draft atau Buat Baru
            // Gunakan lockForUpdate untuk mencegah race condition jika autosave berjalan cepat
            $draft = SurveyResponse::where('session_id', $sessionId)
                ->where('status', 'draft')
                ->lockForUpdate() 
                ->first();

            if (!$draft) {
                $draft = new SurveyResponse();
                $draft->session_id = $sessionId;
                $draft->status = 'draft';
            }

            // 3. Update Metadata
            $draft->last_page = $request->input('current_page', 1);
            $draft->ip_address = $request->ip();
            $draft->user_agent = $request->userAgent();

            // 4. Simpan Field Reguler (Hanya jika ada di request)
            // Catatan: Pastikan kolom database Anda 'nullable' untuk field yang belum diisi
            $fieldsToSave = [
                'nama', 'email', 'nomor_hp', 'jenis_kelamin', 'kelompok_umur',
                'pendidikan', 'pekerjaan', 'pekerjaan_lainnya', 'kategori_instansi',
                'kategori_instansi_lainnya', 'nama_instansi', 'penyandang_disabilitas',
                'jenis_disabilitas', 'tujuan_penggunaan', 'tujuan_lainnya',
                'jenis_layanan', 'sarana_layanan', 'sarana_lainnya', 'catatan_tambahan'
            ];

            foreach ($fieldsToSave as $field) {
                if ($request->has($field)) {
                    // Simpan value, jika kosong string set null (opsional, tergantung struktur DB)
                    $draft->$field = $request->input($field);
                }
            }

            // 5. Simpan Ratings
            $ratingFields = [
                'informasi_pelayanan', 'persyaratan', 'prosedur', 'jangka_waktu',
                'biaya', 'produk', 'sarana', 'akses_data', 'respons_petugas',
                'informasi_petugas', 'fasilitas_pengaduan', 'diskriminasi',
                'kecurangan', 'gratifikasi', 'pungli', 'percaloan'
            ];

            foreach ($ratingFields as $field) {
                if ($request->has($field . '_kepentingan')) {
                    $draft->{$field . '_kepentingan'} = $request->input($field . '_kepentingan');
                }
                if ($request->has($field . '_kepuasan')) {
                    $draft->{$field . '_kepuasan'} = $request->input($field . '_kepuasan');
                }
            }

            $draft->save();

            // 6. Simpan Data Entries (Hapus lama, insert baru untuk draft ini)
            if ($request->has('data_entries')) {
                // Decode JSON aman
                $entriesData = $request->input('data_entries');
                if (is_string($entriesData)) {
                    $entriesData = json_decode($entriesData, true);
                }

                if (is_array($entriesData)) {
                    // Hapus data entry lama milik draft ini
                    SurveyDataEntry::where('survey_response_id', $draft->id)->delete();

                    foreach ($entriesData as $entry) {
                        // Skip jika data kosong total
                        if (empty($entry['nama_data']) && empty($entry['tahun'])) continue;

                        SurveyDataEntry::create([
                            'survey_response_id' => $draft->id,
                            'tahun' => $entry['tahun'] ?? null,
                            'nama_data' => $entry['nama_data'] ?? null,
                            'status_perolehan' => $entry['status_perolehan'] ?? null,
                            'jenis_sumber' => $entry['jenis_sumber'] ?? null,
                            'digunakan_pembangunan' => $entry['digunakan_pembangunan'] ?? null,
                            'tingkat_kepuasan' => $entry['tingkat_kepuasan'] ?? 0,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'draft_id' => $draft->id,
            ])->cookie('survey_session_id', $sessionId, 60 * 24 * 7); // Cookie 7 hari

        } catch (\Exception $e) {
            DB::rollBack();
            // Log error agar terlihat di server (storage/logs/laravel.log)
            Log::error('Autosave Error: ' . $e->getMessage() . ' - Line: ' . $e->getLine());

            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit final (mark as completed)
     */
    public function store(Request $request)
    {
        // 1. Validasi Ketat untuk Final Submit
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'nomor_hp' => 'required|string|max:20',
            'jenis_kelamin' => 'required',
            'kelompok_umur' => 'required',
            'pendidikan' => 'required',
            'pekerjaan' => 'required',
            'kategori_instansi' => 'required',
            'nama_instansi' => 'required|string|max:255',
            'penyandang_disabilitas' => 'required',
            'tujuan_penggunaan' => 'required',
            'jenis_layanan' => 'required',
            'sarana_layanan' => 'required',
            'catatan_tambahan' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            // 2. Ambil Draft jika ada
            $sessionId = $request->input('session_id') ?? $request->cookie('survey_session_id');
            $surveyResponse = null;

            if ($sessionId) {
                $surveyResponse = SurveyResponse::where('session_id', $sessionId)
                    ->where('status', 'draft')
                    ->first();
            }

            if (!$surveyResponse) {
                $surveyResponse = new SurveyResponse();
                $surveyResponse->session_id = $sessionId ?? Str::uuid()->toString();
            }

            // 3. Isi Data Utama
            $surveyResponse->fill($validated);
            
            // Handle field opsional yang mungkin tidak ada di $validated jika logic frontend kompleks
            $opsional = ['pekerjaan_lainnya', 'kategori_instansi_lainnya', 'jenis_disabilitas', 'tujuan_lainnya', 'sarana_lainnya'];
            foreach($opsional as $opt) {
                if($request->has($opt)) $surveyResponse->$opt = $request->input($opt);
            }

            $surveyResponse->status = 'completed';
            $surveyResponse->tanggal_submit = now();
            $surveyResponse->ip_address = $request->ip();
            $surveyResponse->user_agent = $request->userAgent();

            // 4. Simpan Rating (Looping manual untuk memastikan 0 jika null)
            $ratingFields = [
                'informasi_pelayanan', 'persyaratan', 'prosedur', 'jangka_waktu',
                'biaya', 'produk', 'sarana', 'akses_data', 'respons_petugas',
                'informasi_petugas', 'fasilitas_pengaduan', 'diskriminasi',
                'kecurangan', 'gratifikasi', 'pungli', 'percaloan'
            ];

            foreach ($ratingFields as $field) {
                $surveyResponse->{$field . '_kepentingan'} = $request->input($field . '_kepentingan', 0);
                $surveyResponse->{$field . '_kepuasan'} = $request->input($field . '_kepuasan', 0);
            }

            $surveyResponse->save();

            // 5. Simpan Data Entries Final
            if ($request->has('data_entries')) {
                // Bersihkan data lama draft
                SurveyDataEntry::where('survey_response_id', $surveyResponse->id)->delete();

                $entriesData = $request->input('data_entries');
                if (is_string($entriesData)) {
                    $entriesData = json_decode($entriesData, true);
                }

                if (is_array($entriesData)) {
                    foreach ($entriesData as $entry) {
                         // Skip entry kosong
                        if (empty($entry['nama_data'])) continue;

                        SurveyDataEntry::create([
                            'survey_response_id' => $surveyResponse->id,
                            'tahun' => $entry['tahun'] ?? null,
                            'nama_data' => $entry['nama_data'] ?? null,
                            'status_perolehan' => $entry['status_perolehan'] ?? null,
                            'jenis_sumber' => $entry['jenis_sumber'] ?? null,
                            'digunakan_pembangunan' => $entry['digunakan_pembangunan'] ?? null,
                            'tingkat_kepuasan' => $entry['tingkat_kepuasan'] ?? 0,
                        ]);
                    }
                }
            }

            DB::commit();

            // Hapus Cookie Session agar user bisa isi baru nanti
            $cookie = cookie()->forget('survey_session_id');

            return response()->json([
                'success' => true,
                'message' => 'Terima kasih, survei Anda berhasil dikirim!',
            ])->withCookie($cookie);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Submit Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim survey. Silakan coba lagi. (' . $e->getMessage() . ')'
            ], 500);
        }
    }

    /**
     * Get total submissions count
     */
    public function getCount()
    {
        $count = SurveyResponse::where('status', 'completed')->count();
        return response()->json(['count' => $count]);
    }
}