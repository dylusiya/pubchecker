<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\MasterSls;

class AdminController extends Controller
{
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
     * Tampilkan halaman manajemen user
     */
    public function index(Request $request)
    {
        // 1. Parameter Filter & Sort
        $search = $request->get('search');
        $role = $request->get('role');
        $kdkab = $request->get('kdkab');
        $sort = $request->get('sort', 'name');
        $order = $request->get('order', 'asc');
        $perPage = $request->get('per_page', 20);

        // 2. Statistik untuk Card
        $stats = [
            'admin' => User::where('role', 'admin')->count(),
            'user' => User::where('role', 'user')->count(),
            'approver' => User::where('role', 'approver')->count(),
        ];

        // 3. Query dengan Filter
        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($kdkab) {
            // Filter wilayah mendukung 4 digit SSO
            $query->where('kode_kabupaten', $kdkab);
        }

        // 4. Pagination
        if ($perPage === 'all') {
            $users = $query->orderBy($sort, $order)->paginate($query->count() ?: 1)->withQueryString();
        } else {
            $users = $query->orderBy($sort, $order)->paginate($perPage)->withQueryString();
        }

        // 5. Data Dropdown Wilayah (4 Digit untuk value SSO)
        $kabupatenList = MasterSls::select('kdkab', 'nmkab')
            ->distinct()
            ->orderBy('kdkab')
            ->get();

        return view('admin.index', compact('users', 'stats', 'kabupatenList', 'search', 'role', 'kdkab'));
    }

    public function create()
    {
        return view('admin.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username|max:50',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/|confirmed',
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'role' => 'required|in:user,approver,admin',
            'kode_provinsi' => 'nullable|string|max:2',
            'kode_kabupaten' => 'nullable|string|max:2',
        ]);

        DB::beginTransaction();
        try {
            $userData = [
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
            ];

            // Simpan dalam format SSO (4 digit)
            if ($request->role !== 'admin') {
                if ($request->kode_provinsi) {
                    // Gabungkan provinsi (2) + kabupaten (2) = 4 digit
                    $kdKab = $request->kode_kabupaten ?? '00';
                    $userData['kode_provinsi'] = $request->kode_provinsi . $kdKab;
                    
                    if ($request->kode_kabupaten) {
                        // Ada kabupaten: simpan format 4 digit (6301)
                        $userData['kode_kabupaten'] = $request->kode_provinsi . $request->kode_kabupaten;
                    } else {
                        // Hanya provinsi: NULL (akses semua kabupaten)
                        $userData['kode_kabupaten'] = null;
                    }
                    
                    // Auto-fill nama provinsi & kabupaten
                    $prov = MasterSls::where('kdprov', $request->kode_provinsi)->first();
                    $userData['provinsi'] = $prov ? $prov->nmprov : null;
                    
                    if ($request->kode_kabupaten) {
                        $kab = MasterSls::where('kdkab', $request->kode_kabupaten)->first();
                        $userData['kabupaten'] = $kab ? $kab->nmkab : null;
                    }
                }
            }

            $user = User::create($userData);

            DB::commit();

            Log::info('Admin created new user', [
                'admin' => auth()->user()->username,
                'new_user' => $request->username,
                'role' => $request->role,
                'kode_provinsi' => $userData['kode_provinsi'] ?? null,
                'kode_kabupaten' => $userData['kode_kabupaten'] ?? null,
            ]);

            return redirect()->route('admin.index')
                ->with('success', 'User berhasil ditambahkan');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan user: ' . $e->getMessage())->withInput();
        }
    }


    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.edit', compact('user'));
    }


    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'role' => 'required|in:user,approver,admin',
            'kode_provinsi' => 'nullable|string|max:2',
            'kode_kabupaten' => 'nullable|string|max:2',
            'password' => 'nullable|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        ]);

        DB::beginTransaction();
        try {
            $user->role = $request->role;
            
            // Simpan dalam format SSO (4 digit)
            if ($request->role !== 'admin') {
                if ($request->kode_provinsi) {
                    $kdKab = $request->kode_kabupaten ?? '00';
                    $user->kode_provinsi = $request->kode_provinsi . $kdKab;
                    
                    if ($request->kode_kabupaten) {
                        $user->kode_kabupaten = $request->kode_provinsi . $request->kode_kabupaten;
                    } else {
                        $user->kode_kabupaten = null;
                    }
                    
                    // Auto-fill names
                    $prov = MasterSls::where('kdprov', $request->kode_provinsi)->first();
                    $user->provinsi = $prov ? $prov->nmprov : null;
                    
                    if ($request->kode_kabupaten) {
                        $kab = MasterSls::where('kdkab', $request->kode_kabupaten)->first();
                        $user->kabupaten = $kab ? $kab->nmkab : null;
                    } else {
                        $user->kabupaten = null;
                    }
                } else {
                    $user->kode_provinsi = null;
                    $user->kode_kabupaten = null;
                    $user->provinsi = null;
                    $user->kabupaten = null;
                }
            } else {
                // Admin = reset semua
                $user->kode_provinsi = null;
                $user->kode_kabupaten = null;
                $user->provinsi = null;
                $user->kabupaten = null;
            }
            
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            
            $user->save();
            
            DB::commit();

            Log::info('Admin updated user', [
                'admin' => auth()->user()->username,
                'user' => $user->username,
                'role' => $request->role,
                'kode_provinsi' => $user->kode_provinsi,
                'kode_kabupaten' => $user->kode_kabupaten,
            ]);

            return redirect()->route('admin.index')
                ->with('success', 'User berhasil diupdate');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user: ' . $e->getMessage());
            return back()->with('error', 'Gagal update user: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri');
        }

        $user->delete();
        return redirect()->route('admin.index')->with('success', 'User berhasil dihapus');
    }

    /**
     * API untuk AJAX load kabupaten (tetap pakai 2 digit kdprov)
     */
    public function getKabupaten($kdprov)
    {
        // Jika kdprov yang masuk adalah 4 digit SSO (misal 6300), ambil 2 digit awal
        $cleanKdProv = strlen($kdprov) > 2 ? substr($kdprov, 0, 2) : $kdprov;

        $kabupaten = MasterSls::select('kdkab', 'nmkab')
            ->where('kdprov', $cleanKdProv)
            ->distinct()
            ->orderBy('kdkab')
            ->get();
        
        return response()->json($kabupaten);
    }
}