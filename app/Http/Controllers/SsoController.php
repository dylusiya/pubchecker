<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SsoController extends Controller
{
    protected $baseUrl;
    protected $realm;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->baseUrl = config('services.sso.base_url');
        $this->realm = config('services.sso.realm');
        $this->clientId = config('services.sso.client_id');
        $this->clientSecret = config('services.sso.client_secret');
        $this->redirectUri = config('services.sso.redirect_uri');
    }

    /**
     * Homepage - redirect berdasarkan status login
     */
    public function index()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return redirect()->route('login');
    }

    /**
     * Halaman Login
     */
    public function login()
    {
        // Jika sudah login, redirect ke dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Tampilkan halaman login
        return view('auth.login');
    }

    /**
     * Redirect ke SSO BPS
     * URL: https://kalsel.web.bps.go.id/sso_new/?app=skm
     */
    public function redirect()
    {
        // Jika sudah login, redirect ke dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Redirect ke sso_new dengan parameter app=skm
        $ssoUrl = 'https://kalsel.web.bps.go.id/sso_new/?app=skm';
        
        Log::info('Redirecting to SSO', ['url' => $ssoUrl]);
        
        return redirect($ssoUrl);
    }

    /**
     * Callback dari sso_new controller CodeIgniter
     * Menerima: username, token, nip, nama, email dari sso_new
     */
    public function callback(Request $request)
    {
        try {
            // Log SEMUA data yang diterima dari sso_new
            Log::info('=== SSO CALLBACK RECEIVED ===');
            Log::info('All Request Data:', $request->all());
            Log::info('Request Method:', ['method' => $request->method()]);
            Log::info('Request Headers:', $request->headers->all());
            
            // Validasi parameter yang dikirim dari sso_new
            if (!$request->has('username') || !$request->has('token')) {
                Log::error('Missing required parameters', [
                    'has_username' => $request->has('username'),
                    'has_token' => $request->has('token'),
                    'all_params' => array_keys($request->all())
                ]);
                return redirect()->route('login')->with('error', 'Parameter tidak lengkap dari SSO');
            }
    
            $username = $request->username;
            $token = $request->token;
            
            Log::info('Processing SSO login', [
                'username' => $username,
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
    
            // Verifikasi token ke Keycloak untuk keamanan
            $userInfoUrl = $this->baseUrl . '/realms/' . $this->realm . '/protocol/openid-connect/userinfo';
            
            Log::info('Verifying token to Keycloak', ['url' => $userInfoUrl]);
            
            $userResponse = Http::withToken($token)
                ->timeout(10)
                ->get($userInfoUrl);
    
            if (!$userResponse->successful()) {
                Log::error('Token verification failed', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body(),
                    'headers' => $userResponse->headers()
                ]);
                return redirect()->route('login')
                    ->with('error', 'Token tidak valid atau sudah expired. Silakan login ulang.');
            }
    
            $userData = $userResponse->json();
            
            // Log data lengkap dari Keycloak
            Log::info('=== DATA FROM KEYCLOAK ===');
            Log::info('Full User Data:', $userData);
            
            // Buat atau update user
            $user = $this->findOrCreateUser($userData, $request);
    
            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
                
                Log::info('User logged in successfully', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name
                ]);
                
                return redirect()->route('dashboard')->with('success', 'Login berhasil! Selamat datang ' . $user->name);
            }
    
            Log::error('Failed to create/update user');
            return redirect()->route('login')->with('error', 'Gagal membuat/update user. Silakan hubungi administrator.');
    
        } catch (\Exception $e) {
            Log::error('SSO Callback Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('login')
                ->with('error', 'Terjadi kesalahan saat proses login SSO: ' . $e->getMessage());
        }
    }

    /**
     * Temukan atau buat user baru
     */
    protected function findOrCreateUser($userData, $request)
    {
        try {
            $username = $userData['username'] ?? $request->username;
            $email = $userData['email'] ?? $request->email ?? null;

            if (!$username) {
                Log::error('Username not found in user data');
                return null;
            }

            Log::info('Finding or creating user', ['username' => $username]);

            $user = User::where('username', $username)->first();

            // Siapkan data untuk disimpan - ambil SEMUA data dari Keycloak
            $userDataToSave = [
                'name' => $userData['name'] ?? $request->nama ?? $username,
                'first_name' => $userData['first-name'] ?? null,
                'last_name' => $userData['last-name'] ?? null,
                'email' => $email,
                'username' => $username,
            ];

            // NIP
            if (isset($userData['nip-lama'])) {
                $userDataToSave['nip'] = $userData['nip-lama'];
            }

            if (isset($userData['nip'])) {
                $userDataToSave['nip_baru'] = $userData['nip'];
            }

            // Kode Organisasi dan extract Kode Provinsi/Kabupaten
            if (isset($userData['organisasi'])) {
                $kodeOrg = $userData['organisasi'];
                $userDataToSave['kode_organisasi'] = $kodeOrg;
                
                // Extract kode provinsi (2 digit pertama + "00")
                // Contoh: 6301 → 6300, 6371 → 6300
                $userDataToSave['kode_provinsi'] = substr($kodeOrg, 0, 2) . '00';
                
                // Extract kode kabupaten dari kode organisasi
                // Format: 6301 (63 = provinsi, 01 = kabupaten)
                // Jika 2 digit terakhir = 00, berarti provinsi (tidak ada kabupaten)
                $lastTwoDigits = substr($kodeOrg, 2, 2);
                
                if ($lastTwoDigits != '00') {
                    // Ada kabupaten: simpan 4 digit penuh
                    $userDataToSave['kode_kabupaten'] = substr($kodeOrg, 0, 4);
                } else {
                    // 👇 TAMBAHAN: Provinsi saja: PAKSA set kode_kabupaten = null
                    // Ini penting untuk reset jika user sebelumnya kabupaten
                    $userDataToSave['kode_kabupaten'] = null;
                }
            } else {
                // 👇 TAMBAHAN: Jika tidak ada organisasi sama sekali, reset semua
                $userDataToSave['kode_organisasi'] = null;
                $userDataToSave['kode_provinsi'] = null;
                $userDataToSave['kode_kabupaten'] = null;
            }

            // Nama Provinsi dan Kabupaten
            if (isset($userData['provinsi'])) {
                $userDataToSave['provinsi'] = $userData['provinsi'];
            } else {
                // 👇 TAMBAHAN: Reset provinsi jika tidak ada
                $userDataToSave['provinsi'] = null;
            }

            if (isset($userData['kabupaten'])) {
                $userDataToSave['kabupaten'] = $userData['kabupaten'];
            } else {
                // 👇 TAMBAHAN: Reset kabupaten jika tidak ada
                $userDataToSave['kabupaten'] = null;
            }

            // Golongan
            if (isset($userData['golongan'])) {
                $userDataToSave['golongan'] = $userData['golongan'];
            }

            // Jabatan
            if (isset($userData['jabatan'])) {
                $userDataToSave['jabatan'] = $userData['jabatan'];
            }

            // Eselon - ubah "-" jadi null
            if (isset($userData['eselon'])) {
                $userDataToSave['eselon'] = $userData['eselon'] == '-' ? null : $userData['eselon'];
            }

            // Foto
            if (isset($userData['foto'])) {
                $userDataToSave['foto'] = $userData['foto'];
            }

            // Alamat Kantor
            if (isset($userData['alamat-kantor'])) {
                $userDataToSave['alamat_kantor'] = $userData['alamat-kantor'];
            }

            // Log data yang akan disimpan
            Log::info('=== DATA TO SAVE ===', $userDataToSave);

            if (!$user) {
                // Buat user baru
                $userDataToSave['password'] = bcrypt(bin2hex(random_bytes(16)));
                $user = User::create($userDataToSave);
                Log::info('New user created', [
                    'id' => $user->id,
                    'username' => $username,
                    'name' => $user->name,
                    'kode_organisasi' => $user->kode_organisasi,
                    'kode_provinsi' => $user->kode_provinsi,
                    'kode_kabupaten' => $user->kode_kabupaten
                ]);
            } else {
                // 👇 PENTING: Update user yang sudah ada
                // Gunakan update() untuk REPLACE semua field (termasuk set null)
                $user->update($userDataToSave);
                
                Log::info('User updated', [
                    'id' => $user->id,
                    'username' => $username,
                    'name' => $user->name,
                    'kode_organisasi_old' => $user->getOriginal('kode_organisasi'),
                    'kode_organisasi_new' => $user->kode_organisasi,
                    'kode_kabupaten_old' => $user->getOriginal('kode_kabupaten'),
                    'kode_kabupaten_new' => $user->kode_kabupaten
                ]);
            }

            return $user;

        } catch (\Exception $e) {
            Log::error('Error creating/updating user', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Login Local (Backup jika SSO down)
     */
    public function loginLocal(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username harus diisi',
            'password.required' => 'Password harus diisi',
        ]);

        try {
            Log::info('Local login attempt', ['username' => $request->username]);

            // Cari user berdasarkan username
            $user = User::where('username', $request->username)->first();

            // Cek apakah user ada
            if (!$user) {
                Log::warning('Login local failed: User not found', ['username' => $request->username]);
                return back()
                    ->with('error', 'Username atau password salah')
                    ->withInput($request->only('username'));
            }

            // Cek password
            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Login local failed: Wrong password', [
                    'username' => $request->username,
                    'user_id' => $user->id
                ]);
                return back()
                    ->with('error', 'Username atau password salah')
                    ->withInput($request->only('username'));
            }

            // Login berhasil
            Auth::login($user);
            $request->session()->regenerate();

            Log::info('User logged in via local login', [
                'user_id' => $user->id,
                'username' => $user->username,
                'name' => $user->name
            ]);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Login berhasil! Selamat datang ' . $user->name);

        } catch (\Exception $e) {
            Log::error('Local login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Terjadi kesalahan saat login. Silakan coba lagi.');
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        // Safety check
        if (!Auth::check()) {
            Log::warning('Logout attempt but user not logged in');
            return redirect()->route('login')->with('info', 'Anda sudah logout');
        }
        
        $username = Auth::user()->username ?? null;
        $name = Auth::user()->name ?? null;
        
        Log::info('User logging out', [
            'username' => $username,
            'name' => $name
        ]);
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('User logged out successfully', ['username' => $username]);

        // Cek environment
        $isLocal = config('app.env') === 'local' || 
                   request()->getHost() === 'localhost' || 
                   request()->getHost() === '127.0.0.1';
        
        if ($isLocal) {
            return redirect()->route('login')->with('success', 'Anda telah logout');
        }

        // Production: Redirect ke SSO logout
        $ssoLogoutUrl = 'https://kalsel.web.bps.go.id/sso_new/?app=skm&logout=1';
        
        Log::info('Redirecting to SSO logout', ['url' => $ssoLogoutUrl]);
        
        return redirect($ssoLogoutUrl);
    }
}