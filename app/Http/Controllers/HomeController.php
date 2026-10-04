<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SesiPemeriksaan;
use App\Models\HasilPemeriksaan;

class HomeController extends Controller
{
    public function index()
    {
        // Pastikan sudah login
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        return view('home');
    }
}