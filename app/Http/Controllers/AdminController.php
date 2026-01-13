<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AdminController extends Controller
{
    
    /**
     * Constructor - Cek apakah user adalah admin
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Akses ditolak. Anda bukan administrator.');
            }
            return $next($request);
        });
    }
    
    /**
     * Tampilkan halaman admin
     */
    public function index()
    {
        $users = User::orderBy('name')->paginate(20);
        return view('admin.index', compact('users'));
    }

    /**
     * Tampilkan form tambah user
     */
    public function create()
    {
        return view('admin.create');
    }

    /**
     * Simpan user baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username|max:50',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'kode_kabupaten' => 'nullable|string|max:4',
            'kabupaten' => 'nullable|string|max:100',
        ], [
            'username.required' => 'Username harus diisi',
            'username.unique' => 'Username sudah terdaftar',
            'password.required' => 'Password harus diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.regex' => 'Password harus mengandung huruf besar, huruf kecil, dan angka',
            'name.required' => 'Nama harus diisi',
            'email.email' => 'Format email tidak valid',
            'role.required' => 'Role harus dipilih',
            'kode_kabupaten.max' => 'Kode kabupaten maksimal 4 karakter',
        ]);

        try {
            $user = User::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'email' => $request->email,
                'kode_kabupaten' => $request->kode_kabupaten,
                'kabupaten' => $request->kabupaten,
                'kode_provinsi' => '63', // Kalimantan Selatan
                'provinsi' => 'Kalimantan Selatan',
            ]);
            // Explicitly assign role for security (not mass assignable)
            $user->role = $request->role;
            $user->save();

            Log::info('Admin created new user', [
                'admin' => auth()->user()->username,
                'new_user' => $request->username,
                'role' => $request->role
            ]);

            return redirect()->route('admin.index')->with('success', 'User berhasil ditambahkan');

        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan user')->withInput();
        }
    }

    /**
     * Tampilkan form edit user
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.edit', compact('user'));
    }

    /**
     * Update user (password dan role)
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password' => 'nullable|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/|confirmed',
            'role' => 'required|in:user,admin',
            'kode_kabupaten' => 'nullable|string|max:4',
            'kabupaten' => 'nullable|string|max:100',
        ], [
            'password.min' => 'Password minimal 8 karakter',
            'password.regex' => 'Password harus mengandung huruf besar, huruf kecil, dan angka',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
        ]);

        try {
            // Update password jika diisi
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // Update role
            $user->role = $request->role;
            // Update wilayah
            $user->kode_kabupaten = $request->kode_kabupaten;
            $user->kabupaten = $request->kabupaten;

            $user->save();

            Log::info('Admin updated user', [
                'admin' => auth()->user()->username,
                'user' => $user->username,
                'role' => $request->role
            ]);

            return redirect()->route('admin.index')->with('success', 'User berhasil diupdate');

        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return back()->with('error', 'Gagal update user');
        }
    }

    /**
     * Hapus user
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Jangan izinkan admin hapus dirinya sendiri
            if ($user->id === auth()->id()) {
                return back()->with('error', 'Tidak bisa menghapus akun sendiri');
            }

            // Cek apakah user sudah pernah input data
            $hasData = \App\Models\StatusDaerahSulit::where('created_by', $user->id)
                ->orWhere('updated_by', $user->id)
                ->orWhere('approved_by', $user->id)
                ->exists();

            if ($hasData) {
                return back()->with('error', 'User tidak bisa dihapus karena sudah memiliki riwayat data');
            }

            $username = $user->username;
            $user->delete();

            Log::info('Admin deleted user', [
                'admin' => auth()->user()->username,
                'deleted_user' => $username
            ]);

            return redirect()->route('admin.index')->with('success', 'User berhasil dihapus');

        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus user');
        }
    }
}