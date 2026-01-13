<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MasterSls;
use App\Models\StatusDaerahSulit;
use App\Models\HistoryStatusDaerahSulit;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display dashboard
     */
    public function index()
    {
        $tahunAktif = date('Y');
        $user = auth()->user();

        // Statistics
        $stats = $this->getStatistics($tahunAktif, $user);
        
        // Recent data
        $recentData = $this->getRecentData($tahunAktif, $user);
        
        // Stats per kabupaten
        $statsKabupaten = $this->getStatsPerKabupaten($tahunAktif, $user);
        
        // Progress per kabupaten
        $progressKabupaten = $this->getProgressPerKabupaten($tahunAktif, $user);
        
        // Recent history
        $historyRecent = $this->getRecentHistory($user);

        return view('dashboard', compact(
            'tahunAktif',
            'stats',
            'recentData',
            'statsKabupaten',
            'progressKabupaten',
            'historyRecent'
        ));
    }

    /**
     * Get main statistics
     */
    private function getStatistics($tahun, $user)
    {
        $totalSls = $user->accessibleSls()->count();
        
        $jumlahSulit = $user->accessibleStatusDaerahSulit($tahun)
            ->where('is_daerah_sulit', true)
            ->count();
        
        $totalBiaya = $user->accessibleStatusDaerahSulit($tahun)
            ->where('is_daerah_sulit', true)
            ->sum('perkiraan_biaya');
        
        $sudahInput = $user->accessibleStatusDaerahSulit($tahun)
            ->distinct('master_sls_id')
            ->count('master_sls_id');
        
        $belumInput = $totalSls - $sudahInput;

        return compact('totalSls', 'jumlahSulit', 'totalBiaya', 'belumInput');
    }

    /**
     * Get recent status data
     */
    private function getRecentData($tahun, $user)
    {
        return $user->accessibleStatusDaerahSulit($tahun)
            ->with(['masterSls'])
            ->latest('updated_at')
            ->take(10)
            ->get();
    }

    /**
     * Get statistics per kabupaten
     */
    private function getStatsPerKabupaten($tahun, $user)
    {
        $query = DB::table('master_sls as ms')
            ->select(
                'ms.kdkab',
                'ms.nmkab',
                DB::raw("'{$tahun}' as tahun_anggaran"),
                DB::raw('COUNT(DISTINCT ms.id) as total_sls'),
                DB::raw('SUM(CASE WHEN sds.is_daerah_sulit = TRUE THEN 1 ELSE 0 END) as jumlah_sulit'),
                DB::raw('SUM(CASE WHEN sds.is_daerah_sulit = FALSE THEN 1 ELSE 0 END) as jumlah_tidak_sulit'),
                DB::raw('SUM(CASE WHEN sds.id IS NULL THEN 1 ELSE 0 END) as belum_diinput'),
                DB::raw('SUM(CASE WHEN sds.is_daerah_sulit = TRUE THEN sds.perkiraan_biaya ELSE 0 END) as total_biaya'),
                DB::raw('AVG(CASE WHEN sds.is_daerah_sulit = TRUE THEN sds.perkiraan_biaya ELSE NULL END) as rata_rata_biaya')
            )
            ->leftJoin('status_daerah_sulit as sds', function($join) use ($tahun) {
                $join->on('ms.id', '=', 'sds.master_sls_id')
                    ->where('sds.tahun_anggaran', '=', $tahun)
                    ->whereNull('sds.deleted_at');
            })
            ->where('ms.is_active', true)
            ->groupBy('ms.kdkab', 'ms.nmkab');

        // Filter by user's kabupaten if not admin
        if (!$user->isAdmin() && $user->isKabupaten()) {
            $query->where('ms.kdkab', $user->kode_kabupaten);
        }

        return $query->orderByDesc('jumlah_sulit')->get();
    }
    /**
     * Get progress per kabupaten
     */
    private function getProgressPerKabupaten($tahun, $user)
    {
        $query = DB::table('master_sls')
            ->select(
                'kdkab',
                'nmkab',
                DB::raw('COUNT(*) as total_sls'),
                DB::raw('COUNT(DISTINCT CASE WHEN sds.id IS NOT NULL THEN master_sls.id END) as sudah_input')
            )
            ->leftJoin('status_daerah_sulit as sds', function($join) use ($tahun) {
                $join->on('master_sls.id', '=', 'sds.master_sls_id')
                     ->where('sds.tahun_anggaran', '=', $tahun)
                     ->whereNull('sds.deleted_at');
            })
            ->where('master_sls.is_active', true);

        // Filter by user's kabupaten if not admin
        if (!$user->isAdmin() && $user->isKabupaten()) {
            $query->where('master_sls.kdkab', $user->kode_kabupaten);
        }

        return $query->groupBy('kdkab', 'nmkab')
            ->orderBy('nmkab')
            ->get();
    }

    /**
     * Get recent history
     */
    private function getRecentHistory($user)
    {
        $query = HistoryStatusDaerahSulit::with(['masterSls', 'user'])
            ->latest('created_at')
            ->take(10);

        // Filter by user's kabupaten if not admin
        if (!$user->isAdmin() && $user->isKabupaten()) {
            $query->whereHas('masterSls', function($q) use ($user) {
                $q->where('kdkab', $user->kode_kabupaten);
            });
        }

        return $query->get();
    }
}