<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Survei Kepuasan Masyarakat BPS Kalsel</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F3F4F6; }
        
        /* Smooth Fade In/Out */
        .step-content { display: none; animation-duration: 0.4s; }
        .step-content.active { display: block; }

        /* Custom Scrollbar hide */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Input styling to prevent iOS zoom */
        input, select, textarea { font-size: 16px !important; }

        /* Custom Radio/Checkbox Card Styling */
        .option-card:checked + .option-label {
            border-color: #2563EB;
            background-color: #EFF6FF;
            color: #1E40AF;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1), 0 2px 4px -1px rgba(37, 99, 235, 0.06);
        }
        .option-card:checked + .option-label .check-icon { opacity: 1; transform: scale(1); }
        
        /* Floating Label Effect */
        .floating-input:focus ~ label,
        .floating-input:not(:placeholder-shown) ~ label {
            top: -0.5rem;
            left: 0.75rem;
            font-size: 0.75rem;
            padding: 0 0.25rem;
            background-color: white;
            color: #2563EB;
        }

        /* Star Rating Styles */
        .star-rating button {
            transition: all 0.2s;
        }
        .star-rating button:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col text-slate-800">

    <!-- Fixed Progress Bar -->
    <div class="fixed top-0 left-0 w-full z-50 bg-white/90 backdrop-blur-md border-b border-gray-200">
        <div class="max-w-2xl mx-auto px-4 py-3">
            <div class="flex justify-between items-center mb-2">
                <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Progress Survei</span>
                <span class="text-xs font-bold text-slate-500"><span id="current-step-text">1</span>/4</span>
            </div>
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div id="progress-bar" class="h-full bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-500 ease-out rounded-full" style="width: 25%"></div>
            </div>
        </div>
    </div>

    <main class="flex-grow pt-24 pb-12 px-4">
        <div class="max-w-2xl mx-auto">
            
            <!-- Header Introduction -->
            <div id="header-intro" class="text-center mb-8 animate__animated animate__fadeIn">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg mb-4">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg/1200px-Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg.png" alt="Logo BPS" class="w-10 h-10 object-contain">
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-2">Survei Kepuasan Masyarakat</h1>
                <p class="text-slate-500">BPS Provinsi Kalimantan Selatan</p>
            </div>

            <form id="survey-form">
                @csrf
                
                <!-- PAGE 1: Data Responden & Informasi Layanan -->
                <div id="page-1" class="step-content active animate__animated animate__fadeIn">
                    
                    <!-- Section A: Data Responden -->
                    <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-6">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-600 font-bold text-sm">A</span>
                            <h2 class="text-lg font-bold">Data Responden</h2>
                        </div>

                        <div class="space-y-5">
                            <!-- Nama -->
                            <div class="relative">
                                <input type="text" id="nama" name="nama" required class="floating-input peer w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none transition-colors bg-transparent placeholder-transparent" placeholder="Nama Lengkap">
                                <label for="nama" class="absolute left-4 top-3.5 text-gray-400 transition-all pointer-events-none">Nama Lengkap <span class="text-red-500">*</span></label>
                            </div>

                            <!-- Email & No HP -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="relative">
                                    <input type="email" id="email" name="email" required class="floating-input peer w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none transition-colors bg-transparent placeholder-transparent" placeholder="Email">
                                    <label for="email" class="absolute left-4 top-3.5 text-gray-400 transition-all pointer-events-none">Alamat Email <span class="text-red-500">*</span></label>
                                </div>
                                <div class="relative">
                                    <input type="tel" id="nomor_hp" name="nomor_hp" required class="floating-input peer w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none transition-colors bg-transparent placeholder-transparent" placeholder="No HP/WA">
                                    <label for="nomor_hp" class="absolute left-4 top-3.5 text-gray-400 transition-all pointer-events-none">No. WhatsApp <span class="text-red-500">*</span></label>
                                </div>
                            </div>

                            <!-- Jenis Kelamin -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Jenis Kelamin <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="cursor-pointer group">
                                        <input type="radio" name="jenis_kelamin" value="Laki-laki" class="option-card hidden" required>
                                        <div class="option-label flex flex-col items-center justify-center p-4 border-2 border-gray-100 rounded-2xl bg-white hover:bg-gray-50 transition-all h-full text-center">
                                            <span class="text-3xl mb-2 grayscale group-hover:grayscale-0 transition-all">👨</span>
                                            <span class="font-semibold text-sm">Laki-laki</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer group">
                                        <input type="radio" name="jenis_kelamin" value="Perempuan" class="option-card hidden">
                                        <div class="option-label flex flex-col items-center justify-center p-4 border-2 border-gray-100 rounded-2xl bg-white hover:bg-gray-50 transition-all h-full text-center">
                                            <span class="text-3xl mb-2 grayscale group-hover:grayscale-0 transition-all">👩</span>
                                            <span class="font-semibold text-sm">Perempuan</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Kelompok Umur & Pendidikan -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Kelompok Umur <span class="text-red-500">*</span></label>
                                    <select name="kelompok_umur" id="kelompok_umur" required class="w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl bg-white focus:border-blue-500 focus:outline-none appearance-none">
                                        <option value="">Pilih Kelompok Umur</option>
                                        <option value="< 17 tahun">&lt; 17 tahun</option>
                                        <option value="17 - 25 tahun">17 - 25 tahun</option>
                                        <option value="26 - 34 tahun">26 - 34 tahun</option>
                                        <option value="35 - 44 tahun">35 - 44 tahun</option>
                                        <option value="45 - 54 tahun">45 - 54 tahun</option>
                                        <option value="55 - 65 tahun">55 - 65 tahun</option>
                                        <option value="> 65 tahun">&gt; 65 tahun</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Pendidikan Tertinggi <span class="text-red-500">*</span></label>
                                    <select name="pendidikan" id="pendidikan" required class="w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl bg-white focus:border-blue-500 focus:outline-none appearance-none">
                                        <option value="">Pilih Pendidikan</option>
                                        <option value="≤ SLTA/Sederajat">≤ SLTA/Sederajat</option>
                                        <option value="D1/D2/D3">D1/D2/D3</option>
                                        <option value="D4/S1">D4/S1</option>
                                        <option value="S2">S2</option>
                                        <option value="S3">S3</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Pekerjaan -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Pekerjaan Utama <span class="text-red-500">*</span></label>
                                <select id="pekerjaan" name="pekerjaan" required class="w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl bg-white focus:border-blue-500 focus:outline-none appearance-none mb-3">
                                    <option value="">Pilih Pekerjaan</option>
                                    <option value="Pelajar/Mahasiswa">Pelajar/Mahasiswa</option>
                                    <option value="Peneliti/Dosen">Peneliti/Dosen</option>
                                    <option value="ASN/TNI/Polri">ASN/TNI/Polri</option>
                                    <option value="Pegawai BUMN/BUMD">Pegawai BUMN/BUMD</option>
                                    <option value="Pegawai Swasta">Pegawai Swasta</option>
                                    <option value="Wiraswasta">Wiraswasta</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                <input type="text" id="pekerjaan_lainnya" name="pekerjaan_lainnya" class="hidden w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none" placeholder="Sebutkan pekerjaan...">
                            </div>

                            <!-- Kategori Instansi & Nama Instansi -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Kategori Instansi <span class="text-red-500">*</span></label>
                                    <select id="kategori_instansi" name="kategori_instansi" required class="w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl bg-white focus:border-blue-500 focus:outline-none appearance-none mb-2">
                                        <option value="">Pilih Kategori</option>
                                        <option value="Lembaga Negara">Lembaga Negara</option>
                                        <option value="Kementerian & Lembaga Pemerintah">Kementerian & Lembaga Pemerintah</option>
                                        <option value="TNI/Polri/BIN/Kejaksaan">TNI/Polri/BIN/Kejaksaan</option>
                                        <option value="Pemerintah Daerah">Pemerintah Daerah</option>
                                        <option value="Lembaga Internasional">Lembaga Internasional</option>
                                        <option value="Lembaga Penelitian & Pendidikan">Lembaga Penelitian & Pendidikan</option>
                                        <option value="BUMN/BUMD">BUMN/BUMD</option>
                                        <option value="Swasta">Swasta</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                    <input type="text" id="kategori_instansi_lainnya" name="kategori_instansi_lainnya" class="hidden w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none text-sm" placeholder="Sebutkan kategori...">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Nama Instansi <span class="text-red-500">*</span></label>
                                    <input type="text" name="nama_instansi" id="nama_instansi" required class="w-full px-4 py-3.5 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none" placeholder="Nama Instansi">
                                </div>
                            </div>

                            <!-- Penyandang Disabilitas -->
                            <div class="p-4 bg-amber-50 rounded-xl border-l-4 border-amber-400">
                                <label class="block text-sm font-semibold text-gray-800 mb-3">Apakah Anda penyandang disabilitas? <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="cursor-pointer">
                                        <input type="radio" id="disabilitas_ya" name="penyandang_disabilitas" value="Ya" required class="option-card hidden">
                                        <div class="option-label flex items-center justify-center p-3 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all text-center">
                                            <span class="font-semibold text-sm">✅ Ya</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" id="disabilitas_tidak" name="penyandang_disabilitas" value="Tidak" class="option-card hidden">
                                        <div class="option-label flex items-center justify-center p-3 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all text-center">
                                            <span class="font-semibold text-sm">❌ Tidak</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Jenis Disabilitas (conditional) -->
                            <div id="jenis-disabilitas-container" class="hidden p-4 bg-blue-50 rounded-xl border-l-4 border-blue-400">
                                <label class="block text-sm font-semibold text-gray-800 mb-4">Jenis Disabilitas <span class="text-red-500">*</span></label>
                                <div class="space-y-3">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" id="disabilitas_1" name="jenis_disabilitas[]" value="Disabilitas Fisik" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Disabilitas Fisik</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" id="disabilitas_2" name="jenis_disabilitas[]" value="Disabilitas Intelektual" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Disabilitas Intelektual</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" id="disabilitas_3" name="jenis_disabilitas[]" value="Disabilitas Mental" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Disabilitas Mental</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" id="disabilitas_4" name="jenis_disabilitas[]" value="Disabilitas Sensorik" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Disabilitas Sensorik</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section B: Informasi Layanan -->
                    <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-6">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 font-bold text-sm">B</span>
                            <h2 class="text-lg font-bold">Informasi Layanan</h2>
                        </div>

                        <!-- Tujuan Penggunaan Data -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Tujuan Penggunaan Data <span class="text-red-500">*</span> <span class="text-gray-400 font-normal text-xs ml-1">(Boleh pilih > 1)</span></label>
                            <div class="space-y-3">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="tujuan_1" name="tujuan[]" value="Tugas Sekolah/Kuliah" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">📚 Tugas Sekolah/Kuliah</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="tujuan_2" name="tujuan[]" value="Pemerintahan" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">🏛️ Pemerintahan</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="tujuan_3" name="tujuan[]" value="Komersial" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">💼 Komersial</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="tujuan_4" name="tujuan[]" value="Penelitian" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">📊 Penelitian</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="tujuan_5" name="tujuan[]" value="Lainnya" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">📝 Lainnya</span>
                                </label>
                            </div>
                            <input type="text" id="tujuan_lainnya" name="tujuan_lainnya" class="hidden mt-3 w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none text-sm" placeholder="Sebutkan tujuan lainnya">
                        </div>

                        <!-- Jenis Layanan -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Layanan yang Digunakan <span class="text-red-500">*</span> <span class="text-gray-400 font-normal text-xs ml-1">(Boleh pilih > 1)</span></label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="cursor-pointer relative">
                                    <input type="checkbox" id="layanan_1" name="layanan[]" value="Perpustakaan" class="option-card hidden">
                                    <div class="option-label p-4 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all h-full flex flex-col items-center justify-center text-center gap-2">
                                        <span class="text-3xl">📖</span>
                                        <span class="text-sm font-semibold leading-tight">Perpustakaan</span>
                                        <div class="check-icon absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full text-white flex items-center justify-center opacity-0 transition-all transform scale-50 text-[10px]">✓</div>
                                    </div>
                                </label>
                                
                                <label class="cursor-pointer relative">
                                    <input type="checkbox" id="layanan_2" name="layanan[]" value="Pembelian Produk Statistik Berbayar" class="option-card hidden">
                                    <div class="option-label p-4 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all h-full flex flex-col items-center justify-center text-center gap-2">
                                        <span class="text-3xl">💳</span>
                                        <span class="text-sm font-semibold leading-tight">Pembelian Produk Statistik Berbayar</span>
                                        <span class="text-[10px] text-gray-500 leading-tight">Publikasi BPS/Data Mikro/Peta</span>
                                        <div class="check-icon absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full text-white flex items-center justify-center opacity-0 transition-all transform scale-50 text-[10px]">✓</div>
                                    </div>
                                </label>
                                
                                <label class="cursor-pointer relative">
                                    <input type="checkbox" id="layanan_3" name="layanan[]" value="Akses produk statistik pada Website BPS" class="option-card hidden">
                                    <div class="option-label p-4 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all h-full flex flex-col items-center justify-center text-center gap-2">
                                        <span class="text-3xl">🌐</span>
                                        <span class="text-sm font-semibold leading-tight">Akses produk statistik pada Website BPS</span>
                                        <div class="check-icon absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full text-white flex items-center justify-center opacity-0 transition-all transform scale-50 text-[10px]">✓</div>
                                    </div>
                                </label>
                                
                                <label class="cursor-pointer relative">
                                    <input type="checkbox" id="layanan_4" name="layanan[]" value="Konsultasi Statistik" class="option-card hidden">
                                    <div class="option-label p-4 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all h-full flex flex-col items-center justify-center text-center gap-2">
                                        <span class="text-3xl">💬</span>
                                        <span class="text-sm font-semibold leading-tight">Konsultasi Statistik</span>
                                        <div class="check-icon absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full text-white flex items-center justify-center opacity-0 transition-all transform scale-50 text-[10px]">✓</div>
                                    </div>
                                </label>
                                
                                <label class="cursor-pointer relative col-span-1 sm:col-span-2">
                                    <input type="checkbox" id="layanan_5" name="layanan[]" value="Rekomendasi Kegiatan Statistik" class="option-card hidden">
                                    <div class="option-label p-4 border-2 border-gray-200 rounded-xl bg-white hover:bg-gray-50 transition-all h-full flex flex-col items-center justify-center text-center gap-2">
                                        <span class="text-3xl">✅</span>
                                        <span class="text-sm font-semibold leading-tight">Rekomendasi Kegiatan Statistik</span>
                                        <div class="check-icon absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full text-white flex items-center justify-center opacity-0 transition-all transform scale-50 text-[10px]">✓</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Sarana Layanan -->
                        <div class="mb-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Sarana yang Digunakan <span class="text-red-500">*</span> <span class="text-gray-400 font-normal text-xs ml-1">(Boleh pilih > 1)</span></label>
                            <div class="space-y-3">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="sarana_1" name="sarana[]" value="PST Datang Langsung" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">🏢 Pelayanan Statistik Terpadu Datang Langsung</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="sarana_2" name="sarana[]" value="PST Online" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">🖥️ Pelayanan Statistik Terpadu online (pst.bps.go.id)</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="sarana_3" name="sarana[]" value="Website BPS/AllStats" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">🌐 Website BPS/AllStats</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="sarana_4" name="sarana[]" value="Surat/E-mail" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">✉️ Surat/E-mail</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="sarana_5" name="sarana[]" value="Aplikasi Chat" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">💬 Aplikasi Chat</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="sarana_6" name="sarana[]" value="Lainnya" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">📝 Lainnya (misal: Meteor)</span>
                                </label>
                            </div>
                            <input type="text" id="sarana_lainnya" name="sarana_lainnya" class="hidden mt-3 w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:outline-none text-sm" placeholder="Sebutkan sarana lainnya">
                        </div>
                    </div>

                    <!-- Navigation -->
                    <button type="button" class="btn-next w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all active:scale-95 flex items-center justify-center gap-2" data-next="2">
                        Lanjut ke Penilaian
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>

                <!-- PAGE 2: Rating Kepentingan & Kepuasan -->
                <div id="page-2" class="step-content animate__animated animate__fadeIn">
                    <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 text-purple-600 font-bold text-sm">C</span>
                                <h2 class="text-lg font-bold">Penilaian Layanan</h2>
                            </div>
                            <button type="button" id="btn-quick-fill" class="text-xs bg-green-50 text-green-600 px-3 py-1.5 rounded-lg font-bold border border-green-200 hover:bg-green-100 transition-colors">⚡ Isi Cepat 10</button>
                        </div>
                        
                        <div class="p-4 bg-blue-50 rounded-xl mb-6 border border-blue-100 space-y-2">
                            <p class="text-sm text-blue-800 font-semibold text-center mb-2">📌 Panduan Penilaian:</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-blue-700">
                                <div class="bg-white rounded-lg p-3">
                                    <p class="font-bold mb-1">Tingkat Kepentingan:</p>
                                    <p>1 = Sangat Tidak Penting</p>
                                    <p>10 = Sangat Penting</p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="font-bold mb-1">Tingkat Kepuasan:</p>
                                    <p>1 = Sangat Tidak Puas</p>
                                    <p>10 = Sangat Puas</p>
                                </div>
                            </div>
                        </div>

                        <div id="rating-items-container" class="space-y-8">
                            <!-- Rating items will be dynamically inserted here -->
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="flex gap-3">
                        <button type="button" class="btn-prev w-1/3 bg-white border-2 border-slate-200 text-slate-600 font-bold py-4 rounded-xl hover:bg-slate-50 transition-all" data-prev="1">← Kembali</button>
                        <button type="button" class="btn-next w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all" data-next="3">Lanjut →</button>
                    </div>
                </div>

                <!-- PAGE 3: Data yang Diakses -->
                <div id="page-3" class="step-content animate__animated animate__fadeIn">
                    <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-6">
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-100 text-orange-500 mb-3">
                                <span class="text-2xl">📂</span>
                            </div>
                            <h2 class="text-xl font-bold">Data yang Anda Akses</h2>
                            <p class="text-slate-500 text-sm">Sebutkan data spesifik yang Anda cari (Maks. 3)</p>
                        </div>

                        <div id="data-entries-container" class="space-y-4 mb-6">
                            <!-- Data entries will be dynamically inserted here -->
                        </div>

                        <button type="button" id="btn-add-data" class="w-full border-2 border-dashed border-blue-300 bg-blue-50 text-blue-600 font-bold py-3 rounded-xl hover:bg-blue-100 transition-all flex items-center justify-center gap-2">
                            <span>➕ Tambah Data</span>
                        </button>
                        <p class="text-center text-xs text-slate-400 mt-2">Terisi: <span id="current-data-count">0</span>/3</p>
                    </div>

                    <!-- Navigation -->
                    <div class="flex gap-3">
                        <button type="button" class="btn-prev w-1/3 bg-white border-2 border-slate-200 text-slate-600 font-bold py-4 rounded-xl hover:bg-slate-50 transition-all" data-prev="2">← Kembali</button>
                        <button type="button" class="btn-next w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all" data-next="4">Lanjut →</button>
                    </div>
                </div>

                <!-- PAGE 4: Catatan Tambahan -->
                <div id="page-4" class="step-content animate__animated animate__fadeIn">
                    <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-6">
                        <div class="text-center mb-6">
                            <span class="text-4xl mb-2 block">💭</span>
                            <h2 class="text-xl font-bold">Saran & Masukan</h2>
                            <p class="text-slate-500 text-sm">Pendapat Anda sangat berarti bagi kami</p>
                        </div>

                        <textarea id="catatan_tambahan" name="catatan_tambahan" class="w-full px-4 py-4 border-2 border-gray-200 rounded-2xl focus:border-blue-500 focus:outline-none transition-all resize-none bg-slate-50 focus:bg-white" rows="6" placeholder="Tuliskan saran perbaikan atau apresiasi Anda di sini..." maxlength="500"></textarea>
                        <p class="text-xs text-gray-500 mt-2">Maksimal 500 karakter</p>
                    </div>

                    <div id="form-message" class="hidden mb-4 p-4 rounded-xl text-center font-medium"></div>

                    <!-- Navigation -->
                    <div class="flex gap-3">
                        <button type="button" class="btn-prev w-1/3 bg-white border-2 border-slate-200 text-slate-600 font-bold py-4 rounded-xl hover:bg-slate-50 transition-all" data-prev="3">← Kembali</button>
                        <button type="submit" id="submit-btn" class="w-2/3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                            <span id="submit-text">Kirim Survei</span>
                            <svg id="submit-spinner" class="hidden w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- PAGE 5: Thank You -->
                <div id="page-5" class="step-content animate__animated animate__zoomIn">
                    <div class="min-h-[60vh] flex flex-col items-center justify-center text-center p-6">
                        <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-6 animate__animated animate__bounceIn delay-100">
                            <span class="text-4xl">🎉</span>
                        </div>
                        <h2 class="text-3xl font-extrabold text-slate-900 mb-2">Terima Kasih!</h2>
                        <p class="text-slate-500 mb-8 max-w-xs mx-auto">Masukan Anda telah kami terima dan akan digunakan untuk meningkatkan pelayanan BPS Kalsel</p>
                        
                        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 w-full max-w-sm mb-6">
                            <p class="text-sm font-semibold text-green-600 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Data Berhasil Disimpan
                            </p>
                        </div>

                        <button type="button" id="btn-fill-new" class="px-8 py-3 bg-slate-900 text-white font-semibold rounded-xl hover:bg-slate-800 transition-all">
                            Isi Formulir Baru
                        </button>
                    </div>
                </div>

                <!-- Hidden field for data entries JSON -->
                <input type="hidden" name="data_entries" id="data_entries_json">
            </form>
            
            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-xs text-slate-300">© {{ date('Y') }} BPS Provinsi Kalimantan Selatan</p>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    @include('survey.partials.script')

    <script>
        // Progress Bar Logic
        function updateProgress(step) {
            const progress = (step / 4) * 100;
            $('#progress-bar').css('width', progress + '%');
            $('#current-step-text').text(step <= 4 ? step : 4);
            
            // Auto scroll top when changing pages
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Hide/Show Header Intro
            if(step > 1) {
                $('#header-intro').slideUp();
            } else {
                $('#header-intro').slideDown();
            }
        }

        // Logic toggle untuk Select Lainnya
        $('#pekerjaan').change(function() {
            if($(this).val() === 'Lainnya') {
                $('#pekerjaan_lainnya').removeClass('hidden').focus();
            } else {
                $('#pekerjaan_lainnya').addClass('hidden');
            }
        });

        $('#kategori_instansi').change(function() {
            if($(this).val() === 'Lainnya') {
                $('#kategori_instansi_lainnya').removeClass('hidden').focus();
            } else {
                $('#kategori_instansi_lainnya').addClass('hidden');
            }
        });

        $('#tujuan_5').change(function() {
            if($(this).is(':checked')) {
                $('#tujuan_lainnya').removeClass('hidden').focus();
            } else {
                $('#tujuan_lainnya').addClass('hidden');
            }
        });

        $('#sarana_6').change(function() {
            if($(this).is(':checked')) {
                $('#sarana_lainnya').removeClass('hidden').focus();
            } else {
                $('#sarana_lainnya').addClass('hidden');
            }
        });

        // Toggle Jenis Disabilitas
        $('input[name="penyandang_disabilitas"]').change(function() {
            if($(this).val() === 'Ya') {
                $('#jenis-disabilitas-container').removeClass('hidden').addClass('animate__animated animate__fadeIn');
            } else {
                $('#jenis-disabilitas-container').addClass('hidden');
                // Uncheck all disability types
                $('input[name="jenis_disabilitas[]"]').prop('checked', false);
            }
        });

        // Intercept tombol Next/Prev dari script asli untuk update UI
        $(document).on('click', '.btn-next, .btn-prev', function() {
            // Tunggu sebentar agar logic asli (validasi/pindah page) jalan dulu
            setTimeout(() => {
                // Cari page yang aktif
                const activePageId = $('.step-content.active').attr('id');
                if(activePageId) {
                    const stepNum = parseInt(activePageId.replace('page-', ''));
                    updateProgress(stepNum);
                }
            }, 100);
        });

        // Add active state to checkbox cards immediately on click
        $(document).on('change', '.option-card', function() {
            $(this).closest('label').addClass('animate__animated animate__pulse');
            setTimeout(() => {
                $(this).closest('label').removeClass('animate__animated animate__pulse');
            }, 500);
        });
    </script>
</body>
</html>