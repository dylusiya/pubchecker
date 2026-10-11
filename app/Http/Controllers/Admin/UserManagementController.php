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
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->checkAdmin();
        
        $users = User::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.users.index', compact('users'));
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
                'role'     => 'required|in:admin,user',
                // opsional: password cadangan untuk Login Lokal bila SSO sedang bermasalah
                'password' => 'nullable|string|min:8|confirmed',
            ], [
                'username.unique'    => 'Username ini sudah terdaftar.',
                'password.min'       => 'Password cadangan minimal 8 karakter.',
                'password.confirmed' => 'Konfirmasi password cadangan tidak sama.',
            ]);

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
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,user',
            'provinsi' => 'nullable|string|max:255',
        ]);

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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,user',
            'provinsi' => 'nullable|string|max:255',
        ]);

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