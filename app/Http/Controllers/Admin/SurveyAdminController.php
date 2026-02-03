<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SurveyResponse;
use App\Models\SurveyDataEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SurveyAdminController extends Controller
{
    /**
     * Display a listing of survey responses
     */
    public function index(Request $request)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu');
        }

        $query = SurveyResponse::with('dataEntries')
            ->orderBy('updated_at', 'desc');

        // Filter Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter Tanggal
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('kategori_instansi')) {
            $query->where('kategori_instansi', $request->kategori_instansi);
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $responses = $query->paginate($perPage)->withQueryString();

        // Statistik
        $statistics = $this->getStatistics($request);

        return view('admin.survey.index', compact('responses', 'statistics'));
    }

    /**
     * Display the specified survey response
     */
    public function show($id)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu');
        }

        $response = SurveyResponse::with('dataEntries')->findOrFail($id);

        return view('admin.survey.show', compact('response'));
    }

    /**
     * Remove the specified survey response
     */
    public function destroy($id)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu');
        }

        try {
            $response = SurveyResponse::findOrFail($id);
            $response->delete();

            return redirect()->route('admin.survey.index')
                ->with('success', 'Data survey berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->route('admin.survey.index')
                ->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    /**
     * Export survey responses to Excel
     */
    public function export(Request $request)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu');
        }

        // Implementasi export akan dibuat terpisah
        return redirect()->route('admin.survey.index')
            ->with('info', 'Fitur export sedang dalam pengembangan');
    }

    /**
     * Get statistics
     */
    private function getStatistics($request)
    {
        
        $query = SurveyResponse::query();

        // Terapkan filter yang sama
        if ($request->filled('start_date')) $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->filled('end_date')) $query->whereDate('created_at', '<=', $request->end_date);
        if ($request->filled('kategori_instansi')) $query->where('kategori_instansi', $request->kategori_instansi);
        if ($request->filled('jenis_kelamin')) $query->where('jenis_kelamin', $request->jenis_kelamin);
        if ($request->filled('status')) $query->where('status', $request->status);

        // Khusus rata-rata kepuasan, HANYA hitung yang completed agar tidak merusak nilai dengan angka 0 dari draft
        $completedQuery = clone $query;
        $avgKepuasan = $completedQuery->where('status', 'completed')->avg(DB::raw('(
            COALESCE(informasi_pelayanan_kepuasan, 0) + 
            COALESCE(persyaratan_kepuasan, 0) + 
            COALESCE(prosedur_kepuasan, 0) + 
            COALESCE(jangka_waktu_kepuasan, 0) + 
            COALESCE(biaya_kepuasan, 0) + 
            COALESCE(produk_kepuasan, 0) + 
            COALESCE(sarana_kepuasan, 0) + 
            COALESCE(akses_data_kepuasan, 0) + 
            COALESCE(respons_petugas_kepuasan, 0) + 
            COALESCE(informasi_petugas_kepuasan, 0) + 
            COALESCE(fasilitas_pengaduan_kepuasan, 0) + 
            COALESCE(diskriminasi_kepuasan, 0) + 
            COALESCE(kecurangan_kepuasan, 0) + 
            COALESCE(gratifikasi_kepuasan, 0) + 
            COALESCE(pungli_kepuasan, 0) + 
            COALESCE(percaloan_kepuasan, 0)
        ) / 16'));

        return [
            'total_responses' => $query->count(),
            'avg_kepuasan' => round($avgKepuasan, 2),
            'by_gender' => $query->select('jenis_kelamin', DB::raw('count(*) as total'))
                ->whereNotNull('jenis_kelamin') // Hanya hitung yang sudah isi gender
                ->groupBy('jenis_kelamin')
                ->pluck('total', 'jenis_kelamin')
                ->toArray(),
            'by_instansi' => $query->select('kategori_instansi', DB::raw('count(*) as total'))
                ->whereNotNull('kategori_instansi')
                ->groupBy('kategori_instansi')
                ->orderBy('total', 'desc')
                ->take(5)
                ->pluck('total', 'kategori_instansi')
                ->toArray(),
        ];
    }

    /**
     * Dashboard with charts
     */
    public function dashboard(Request $request)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu');
        }

        $startDate = $request->input('start_date', now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        // Data untuk chart
        $chartData = $this->getChartData($startDate, $endDate);
        $statistics = $this->getStatistics($request);

        return view('admin.survey.dashboard', compact('chartData', 'statistics', 'startDate', 'endDate'));
    }

    /**
     * Get data for charts
     */
    private function getChartData($startDate, $endDate)
    {
        $responses = SurveyResponse::where('status', 'completed')
            ->whereBetween('tanggal_submit', [$startDate, $endDate])
            ->get();

        $ratingItems = [
            'informasi_pelayanan' => 'Informasi Pelayanan',
            'persyaratan' => 'Persyaratan',
            'prosedur' => 'Prosedur',
            'jangka_waktu' => 'Jangka Waktu',
            'biaya' => 'Biaya',
            'produk' => 'Produk',
            'sarana' => 'Sarana',
            'akses_data' => 'Akses Data',
            'respons_petugas' => 'Respons Petugas',
            'informasi_petugas' => 'Informasi Petugas',
            'fasilitas_pengaduan' => 'Fasilitas Pengaduan',
            'diskriminasi' => 'Diskriminasi',
            'kecurangan' => 'Kecurangan',
            'gratifikasi' => 'Gratifikasi',
            'pungli' => 'Pungli',
            'percaloan' => 'Percaloan',
        ];

        $averages = [];
        foreach ($ratingItems as $key => $label) {
            $averages[$label] = [
                'kepentingan' => round($responses->avg($key . '_kepentingan'), 2),
                'kepuasan' => round($responses->avg($key . '_kepuasan'), 2),
            ];
        }

        return [
            'averages' => $averages,
            'labels' => array_values($ratingItems),
        ];
    }
}