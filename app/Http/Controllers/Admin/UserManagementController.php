<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    /**
     * Cek apakah user adalah admin
     */
    private function checkAdmin()
    {
        if (!auth()->check()) {
            abort(403, 'Silakan login terlebih dahulu.');
        }

        if (auth()->user()->role !== 'admin') {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator.');
        }
    }

    /**
     * Aturan password lokal (juga password cadangan user SSO): minimal 8 karakter,
     * mengandung huruf besar, huruf kecil, dan angka.
     */
    private static function passwordRules(string $presence): array
    {
        return [$presence, 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'confirmed'];
    }

    private static function passwordMessages(): array
    {
        return [
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.regex'     => 'Password harus mengandung huruf besar, huruf kecil, dan angka.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->checkAdmin();
        
        // User 'pending' (login SSO, belum disetujui) tampil paling atas
        $users = User::when(request('role'), fn ($q, $role) => $q->where('role', $role))
            ->orderByRaw("role = 'pending' DESC")
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        // jumlah per role untuk kartu statistik (seluruh user, tidak terpengaruh filter)
        $stats = User::selectRaw('role, COUNT(*) AS jml')->groupBy('role')->pluck('jml', 'role');
        $pendingCount = (int) ($stats['pending'] ?? 0);

        return view('admin.users.index', compact('users', 'pendingCount', 'stats'));
    }

    /**
     * Setujui user SSO 'pending' menjadi user biasa.
     */
    public function approve($id)
    {
        $this->checkAdmin();

        $user = User::findOrFail($id);
        if ($user->isPending()) {
            $user->update(['role' => 'user']);
        }

        return back()->with('success', "User {$user->username} disetujui dan sekarang bisa memakai aplikasi.");
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->checkAdmin();
        
        return view('admin.users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->checkAdmin();
        
        // Pegawai BPS (SSO): cukup username + role — nama, email, NIP, jabatan, satker, dll. diisi
        // otomatis dari SSO saat pengguna login pertama kali (SsoController::findOrCreateUser
        // mencocokkan username & memperbarui profil tanpa mengubah role).
        if ($request->input('jenis', 'sso') === 'sso') {
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users',
                'role'     => 'required|in:admin,pemeriksa,user',
                // opsional: password cadangan untuk Login Lokal bila SSO sedang bermasalah
                'password' => self::passwordRules('nullable'),
            ], [
                'username.unique' => 'Username ini sudah terdaftar.',
            ] + self::passwordMessages());

            $username  = trim($validated['username']);
            $cadangan  = !empty($validated['password']);
            User::create([
                'username' => $username,
                'name'     => $username, // sementara, diganti nama lengkap dari SSO saat login
                'role'     => $validated['role'],
                // tanpa password cadangan: password acak yang tidak diketahui siapa pun (hanya bisa lewat SSO)
                'password' => Hash::make($cadangan ? $validated['password'] : bin2hex(random_bytes(16))),
            ]);

            return redirect()->route('admin.users.index')
                ->with('success', "User {$username} ditambahkan. Nama, email, NIP, dan data lainnya akan terisi dari SSO saat ia login pertama kali."
                    . ($cadangan ? ' Password cadangan aktif untuk Login Lokal.' : ''));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => self::passwordRules('required'),
            'role' => 'required|in:admin,pemeriksa,user',
            'provinsi' => 'nullable|string|max:255',
        ], self::passwordMessages());

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $this->checkAdmin();
        
        $user = User::findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $this->checkAdmin();
        
        $user = User::findOrFail($id);

        // username tidak bisa diubah di form edit (kunci pencocokan SSO), jadi tidak divalidasi;
        // email opsional — user yang ditambahkan lewat username baru punya email setelah login SSO
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
            'password' => self::passwordRules('nullable'),
            'role' => 'required|in:admin,pemeriksa,user',
            'provinsi' => 'nullable|string|max:255',
        ], self::passwordMessages());

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diupdate!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->checkAdmin();
        
        $user = User::findOrFail($id);

        // Tidak bisa hapus diri sendiri
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Anda tidak dapat menghapus akun Anda sendiri!');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus!');
    }
}