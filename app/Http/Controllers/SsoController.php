<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Login via SSO BPS (Keycloak accounts.bps.go.id, OpenID Connect Authorization Code Flow).
 * Sama dengan sandaran-hati:
 *
 * 1. redirect(): kirim browser ke halaman login Keycloak dengan `state` acak.
 * 2. callback(): Keycloak kembali membawa `code` → tukar jadi token → ambil /userinfo
 *    → login user lokal (dicocokkan lewat username, lalu email).
 * 3. logout(): akhiri sesi lokal + sesi Keycloak (id_token_hint), kembali lewat callback.
 *
 * Keycloak menolak redirect ke localhost, jadi instance lokal memakai callback production
 * dengan state berawalan "<key>~"; callback production meneruskannya balik (forwardTarget()).
 */
class SsoController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('services.sso.client_id');
    }

    public static function endpoint(string $name): string
    {
        $base = rtrim(config('services.sso.keycloak_url'), '/');

        return "{$base}/realms/".config('services.sso.realm')."/protocol/openid-connect/{$name}";
    }

    public static function redirectUri(): string
    {
        return config('services.sso.redirect_uri') ?: route('sso.callback');
    }

    /**
     * State acak, berawalan "<key>~" bila SSO_STATE_PREFIX diisi agar callback production
     * tahu harus meneruskan respons ke instance tersebut (lihat forwardTarget()).
     */
    public static function newState(): string
    {
        $prefix = config('services.sso.state_prefix');

        return ($prefix ? $prefix.'~' : '').Str::random(40);
    }

    /**
     * URL logout Keycloak (RP-initiated). Kembali lewat callback (tanpa `code`), yang lalu
     * meneruskan/mengarahkan ke halaman login — cukup satu URL yang di-whitelist di Keycloak.
     */
    public static function logoutUrl(?string $idToken): string
    {
        return self::endpoint('logout').'?'.http_build_query(array_filter([
            'post_logout_redirect_uri' => self::redirectUri(),
            'id_token_hint' => $idToken,
            'client_id' => config('services.sso.client_id'),
            'state' => self::newState(),
        ]));
    }

    /**
     * Homepage - redirect berdasarkan status login
     */
    public function index()
    {
        return redirect()->route(Auth::check() ? 'dashboard' : 'login');
    }

    /**
     * Halaman Login
     */
    public function login()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Redirect ke halaman login Keycloak
     */
    public function redirect(Request $request)
    {
        abort_unless(self::enabled(), 404);

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // State acak per login, dicek di callback untuk mencegah login CSRF
        $state = self::newState();
        $request->session()->put('sso_state', $state);

        return redirect()->away(self::endpoint('auth').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => self::redirectUri(),
            'scope' => config('services.sso.scope'),
            'state' => $state,
        ]));
    }

    /**
     * Callback dari Keycloak
     */
    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);

        // Respons milik instance lain (mis. localhost) → teruskan ke sana
        if ($target = $this->forwardTarget((string) $request->input('state'))) {
            return redirect()->away($target.'/sso/callback?'.$request->getQueryString());
        }

        // Tanpa code/error = kembali dari logout Keycloak
        if (! $request->filled('code') && ! $request->filled('error')) {
            return redirect()->route('login');
        }

        if ($request->filled('error')) {
            return $this->fail('Login SSO dibatalkan atau ditolak: '.$request->input('error_description', $request->input('error')));
        }

        $expectedState = $request->session()->pull('sso_state', '');
        if ($expectedState === '' || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return $this->fail('Sesi login SSO tidak valid atau kedaluwarsa. Silakan ulangi login.');
        }

        try {
            $token = Http::asForm()->timeout(15)->post(self::endpoint('token'), [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
                'code' => $request->input('code'),
                'redirect_uri' => self::redirectUri(),
            ]);

            if (! $token->successful() || ! $token->json('access_token')) {
                Log::warning('SSO token exchange failed', ['status' => $token->status(), 'body' => $token->body()]);

                return $this->fail('Gagal mendapatkan token dari SSO BPS.');
            }

            $userinfo = Http::withToken($token->json('access_token'))->timeout(15)->get(self::endpoint('userinfo'));

            if (! $userinfo->successful()) {
                Log::warning('SSO userinfo failed', ['status' => $userinfo->status(), 'body' => $userinfo->body()]);

                return $this->fail('Gagal mengambil profil pengguna dari SSO BPS.');
            }
        } catch (\Throwable $e) {
            Log::error('SSO request error: '.$e->getMessage());

            return $this->fail('Tidak dapat terhubung ke server SSO BPS.');
        }

        $profile = $userinfo->json() ?: [];

        // /userinfo SSO baru hanya berisi nama/username/email; atribut pegawai (NIP, jabatan,
        // wilayah, ...) diambil dari API Pegawai. Gagal → tetap login, data lama tidak ditimpa.
        $username = $profile['preferred_username'] ?? $profile['username'] ?? null;
        $pegawai = $username ? $this->fetchPegawai($username) : [];
        Log::info('SSO login', ['username' => $username, 'api_pegawai' => $pegawai !== []]);

        $user = $this->findOrCreateUser(array_merge($profile, $pegawai));

        if (! $user) {
            return $this->fail('Gagal membuat/update user dari akun SSO. Silakan hubungi administrator.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        // Disimpan agar logout juga mengakhiri sesi Keycloak (id_token_hint)
        $request->session()->put('sso_id_token', $token->json('id_token'));

        Log::info('User logged in via SSO', ['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role]);

        if ($user->isPending()) {
            return redirect()->route('pending');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Login berhasil! Selamat datang '.$user->name);
    }

    /**
     * Temukan (username, lalu email) atau buat user baru, dan sinkronkan profil dari Keycloak.
     */
    protected function findOrCreateUser(array $profile): ?User
    {
        try {
            $username = $profile['username'] ?? $profile['preferred_username'] ?? null;
            $email = strtolower(trim($profile['email'] ?? '')) ?: null;

            if (! $username) {
                Log::error('SSO profile has no username', ['keys' => array_keys($profile)]);

                return null;
            }

            $user = User::where('username', $username)->first()
                ?? ($email ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null);

            $data = [
                'name' => $profile['name'] ?? $username,
                'first_name' => $profile['first-name'] ?? $profile['given_name'] ?? null,
                'last_name' => $profile['last-name'] ?? $profile['family_name'] ?? null,
                'email' => $email,
                'username' => $username,
            ];

            if (isset($profile['nip-lama'])) {
                $data['nip'] = $profile['nip-lama'];
            }
            if (isset($profile['nip'])) {
                $data['nip_baru'] = $profile['nip'];
            }

            // Kode organisasi → kode provinsi/kabupaten. Mis. 6301 → prov 6300, kab 6301;
            // 6300 → prov 6300, kab null (reset bila user sebelumnya di kabupaten).
            // Hanya disentuh bila klaim ada, agar data wilayah tidak terhapus kalau SSO tidak mengirimnya.
            if (! empty($profile['organisasi'])) {
                $kodeOrg = (string) $profile['organisasi'];
                $data['kode_organisasi'] = $kodeOrg;
                $data['kode_provinsi'] = substr($kodeOrg, 0, 2).'00';
                $data['kode_kabupaten'] = substr($kodeOrg, 2, 2) !== '00' ? substr($kodeOrg, 0, 4) : null;
            }

            foreach (['provinsi', 'kabupaten'] as $field) {
                if (isset($profile[$field])) {
                    $data[$field] = $profile[$field];
                }
            }

            foreach (['golongan' => 'golongan', 'jabatan' => 'jabatan', 'foto' => 'foto', 'alamat-kantor' => 'alamat_kantor'] as $claim => $column) {
                if (isset($profile[$claim])) {
                    $data[$column] = $profile[$claim];
                }
            }
            if (isset($profile['eselon'])) {
                $data['eselon'] = in_array($profile['eselon'], ['-', ''], true) ? null : $profile['eselon'];
            }

            if (! $user) {
                $data['password'] = bcrypt(Str::random(40)); // tidak dipakai; user SSO login lewat Keycloak
                // Belum didaftarkan admin → 'pending', hanya bisa melihat halaman tunggu
                // (EnsureUserApproved) sampai admin menyetujui di Manajemen User.
                $data['role'] = 'pending';
                $user = User::create($data);
                Log::info('New SSO user created', ['id' => $user->id, 'username' => $username]);
            } else {
                $user->update($data);
            }

            return $user;
        } catch (\Throwable $e) {
            Log::error('Error creating/updating SSO user', ['message' => $e->getMessage()]);

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

        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Log::warning('Local login failed', ['username' => $request->username]);

            return back()
                ->with('error', 'Username atau password salah')
                ->withInput($request->only('username'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Login berhasil! Selamat datang '.$user->name);
    }

    /**
     * Logout lokal; bila masuk lewat SSO, akhiri juga sesi Keycloak.
     */
    public function logout(Request $request)
    {
        // Dibaca sebelum session di-invalidate
        $ssoIdToken = $request->session()->get('sso_id_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($ssoIdToken && self::enabled()) {
            return redirect()->away(self::logoutUrl($ssoIdToken));
        }

        return redirect()->route('login')->with('success', 'Anda telah logout');
    }

    /**
     * Base URL instance lain pemilik respons ini, dari allowlist tetap
     * services.sso.forward_targets (tidak pernah dari request — mencegah open redirect).
     */
    private function forwardTarget(string $state): ?string
    {
        $key = Str::contains($state, '~') ? Str::before($state, '~') : '';

        if ($key === '' || $key === (string) config('services.sso.state_prefix')) {
            return null;
        }

        $target = config('services.sso.forward_targets')[$key] ?? null;

        return $target ? rtrim($target, '/') : null;
    }

    /**
     * Atribut pegawai dari API Pegawai BPS (GET /realms/{realm}/api/username/{username}),
     * memakai token service account client ini (grant client_credentials). Hasilnya
     * dipetakan ke nama klaim SSO lama agar findOrCreateUser() tetap sama.
     * Contoh atribut: employeeID 340057045, employeeNumber 1992..., org 92000,
     * provinceCode 63, cityCode 00 → kode_organisasi 630000092000 (format SSO lama).
     */
    protected function fetchPegawai(string $username): array
    {
        try {
            $base = rtrim(config('services.sso.keycloak_url'), '/').'/realms/'.config('services.sso.realm');

            $token = Http::asForm()->timeout(15)
                ->withBasicAuth(config('services.sso.client_id'), config('services.sso.client_secret'))
                ->post("{$base}/protocol/openid-connect/token", ['grant_type' => 'client_credentials'])
                ->json('access_token');

            if (! $token) {
                Log::warning('API Pegawai: gagal mengambil token service account');

                return [];
            }

            $res = Http::withToken($token)->acceptJson()->timeout(15)
                ->get("{$base}/api/username/".rawurlencode($username));

            $attr = collect($res->json() ?: [])->firstWhere('username', $username)['attributes'] ?? null;

            if (! $res->successful() || ! $attr) {
                Log::warning('API Pegawai: data tidak ditemukan', ['username' => $username, 'status' => $res->status()]);

                return [];
            }
        } catch (\Throwable $e) {
            Log::warning('API Pegawai error: '.$e->getMessage());

            return [];
        }

        $get = fn (string $key) => isset($attr[$key]) ? (string) (is_array($attr[$key]) ? ($attr[$key][0] ?? '') : $attr[$key]) : null;

        $prov = $get('provinceCode');
        $city = $get('cityCode');
        $org = $get('org');

        return array_filter([
            'first-name' => $get('firstName'),
            'last-name' => $get('lastName'),
            'nip-lama' => $get('employeeID'),
            'nip' => $get('employeeNumber'),
            'organisasi' => ($prov !== null && $city !== null && $org !== null)
                ? str_pad($prov, 2, '0', STR_PAD_LEFT).str_pad($city, 2, '0', STR_PAD_LEFT).str_pad($org, 8, '0', STR_PAD_LEFT)
                : null,
            'provinsi' => $get('province'),
            'kabupaten' => $get('city'),
            'jabatan' => $get('jobTitle'),
            'golongan' => $get('jobGrade'),
            'eselon' => $get('echelon'),
            'foto' => $get('photo'),
            'alamat-kantor' => $get('officeAddress'),
        ], fn ($v) => $v !== null);
    }

    private function fail(string $message)
    {
        return redirect()->route('login')->with('error', $message);
    }
}
