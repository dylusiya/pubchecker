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

    public function index()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        return view('welcome');
    }

    /**
     * Redirect ke SSO BPS
     * URL: https://kalsel.web.bps.go.id/sso_new/?app=daerahsulit
     */
    public function redirect()
    {
        // Redirect ke sso_new dengan parameter app=daerahsulit
        return redirect('https://kalsel.web.bps.go.id/sso_new/?app=daerahsulit');
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
            
            // Validasi parameter yang dikirim dari sso_new
            if (!$request->has('username') || !$request->has('token')) {
                Log::error('Missing required parameters');
                return redirect('/')->with('error', 'Parameter tidak lengkap');
            }
    
            $username = $request->username;
            $token = $request->token;
            
            Log::info('Processing SSO login', [
                'username' => $username,
                'token' => substr($token, 0, 20) . '...'
            ]);
    
            // Verifikasi token ke Keycloak untuk keamanan
            $userInfoUrl = $this->baseUrl . '/realms/' . $this->realm . '/protocol/openid-connect/userinfo';
            
            $userResponse = Http::withToken($token)->get($userInfoUrl);
    
            if (!$userResponse->successful()) {
                Log::error('Token verification failed', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body()
                ]);
                return redirect('/')->with('error', 'Token tidak valid atau sudah expired');
            }
    
            $userData = $userResponse->json();
            
            // Log data lengkap dari Keycloak
            Log::info('=== DATA FROM KEYCLOAK ===');
            Log::info('Full User Data:', $userData);
            
            // Buat atau update user
            $user = $this->findOrCreateUser($userData, $request);
    
            if ($user) {
                Auth::login($user);
                Log::info('User logged in successfully: ' . $user->id);
                
                return redirect('/dashboard')->with('success', 'Login berhasil!');
            }
    
            Log::error('Failed to create/update user');
            return redirect('/')->with('error', 'Gagal membuat user');
    
        } catch (\Exception $e) {
            Log::error('SSO Callback Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect('/')->with('error', 'Terjadi kesalahan saat proses login. Silakan coba lagi.');
        }
    }

    protected function findOrCreateUser($userData, $request)
    {
        try {
            $username = $userData['username'] ?? $request->username;
            $email = $userData['email'] ?? $request->email ?? null;

            if (!$username) {
                Log::error('Username not found');
                return null;
            }

            $user = User::where('username', $username)->first();

            // Siapkan data untuk disimpan - ambil SEMUA data dari Keycloak
            $userDataToSave = [
                'name' => $userData['name'] ?? $request->nama ?? 'User',
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
                
                // Extract kode provinsi (4 digit pertama)
                $userDataToSave['kode_provinsi'] = substr($kodeOrg, 0, 4);
                
                // Extract kode kabupaten - hanya jika bukan provinsi
                $kodeKab = substr($kodeOrg, 0, 4);
                if (substr($kodeKab, 2, 2) != '00') {
                    $userDataToSave['kode_kabupaten'] = $kodeKab;
                }
            }

            // Nama Provinsi dan Kabupaten
            if (isset($userData['provinsi'])) {
                $userDataToSave['provinsi'] = $userData['provinsi'];
            }

            if (isset($userData['kabupaten'])) {
                $userDataToSave['kabupaten'] = $userData['kabupaten'];
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
                Log::info('New user created', ['id' => $user->id, 'username' => $username]);
            } else {
                // Update user yang sudah ada
                $user->update($userDataToSave);
                Log::info('User updated', ['id' => $user->id, 'username' => $username]);
            }

            return $user;

        } catch (\Exception $e) {
            Log::error('Error creating/updating user: ' . $e->getMessage());
            Log::error('Exception details:', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage()
            ]);
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    public function login()
    {
        // Jika sudah login, redirect ke dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Jika environment local atau localhost, tampilkan welcome page
        if (config('app.env') === 'local' || request()->getHost() === 'localhost') {
            return view('welcome');
        }

        // Production: Redirect ke sso_new dengan parameter app=daerahsulit
        return redirect('https://kalsel.web.bps.go.id/sso_new/?app=daerahsulit');
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
            // Cari user berdasarkan username
            $user = User::where('username', $request->username)->first();

            // Cek apakah user ada
            if (!$user) {
                Log::warning('Login local failed: User not found', ['username' => $request->username]);
                return back()->with('error', 'Username atau password salah')->withInput($request->only('username'));
            }
            // Cek password
            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Login local failed: Wrong password', ['username' => $request->username]);
                return back()->with('error', 'Username atau password salah')->withInput($request->only('username'));
            }

            // Login berhasil
            Auth::login($user);
            $request->session()->regenerate();

            Log::info('User logged in via local login', ['user_id' => $user->id, 'username' => $user->username]);

            return redirect()->intended('/dashboard')->with('success', 'Login berhasil!');

        } catch (\Exception $e) {
            Log::error('Local login error: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat login');
        }
    }

    public function logout(Request $request)
{
    // Safety check
    if (!Auth::check()) {
        return redirect('/')->with('error', 'Anda sudah logout');
    }
    
    $username = Auth::user()->username ?? null;
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('User logged out', ['username' => $username]);

        
        if (config('app.env') === 'local' || request()->getHost() === 'localhost') {
            return redirect('/')->with('success', 'Anda telah logout');
        }

        return redirect('https://kalsel.web.bps.go.id/sso_new/?app=daerahsulit&logout=1');
    }

    
}