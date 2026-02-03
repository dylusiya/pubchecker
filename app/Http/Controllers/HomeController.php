<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SurveyResponse;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index()
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu');
        }

        // Get survey statistics
        $totalSurvey = SurveyResponse::count();
        $todaySurvey = SurveyResponse::whereDate('tanggal_submit', today())->count();
        $avgKepuasan = SurveyResponse::selectRaw('AVG((
            informasi_pelayanan_kepuasan + 
            persyaratan_kepuasan + 
            prosedur_kepuasan + 
            jangka_waktu_kepuasan + 
            biaya_kepuasan + 
            produk_kepuasan + 
            sarana_kepuasan + 
            akses_data_kepuasan + 
            respons_petugas_kepuasan + 
            informasi_petugas_kepuasan + 
            fasilitas_pengaduan_kepuasan + 
            diskriminasi_kepuasan + 
            kecurangan_kepuasan + 
            gratifikasi_kepuasan + 
            pungli_kepuasan + 
            percaloan_kepuasan
        ) / 16) as avg_kepuasan')->value('avg_kepuasan');

        $recentSurveys = SurveyResponse::orderBy('tanggal_submit', 'desc')
            ->take(5)
            ->get();

        return view('home', compact('totalSurvey', 'todaySurvey', 'avgKepuasan', 'recentSurveys'));
    }
}