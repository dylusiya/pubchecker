<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\ContohKategori;
use App\Models\KriteriaPemeriksaan;

/**
 * Gambar contoh yang benar per kategori kriteria.
 * Admin mengunggah/menghapus; semua petugas melihatnya di panel tinjauan.
 */
class ContohKategoriController extends Controller
{
    private const DIR = 'contoh-kategori';

    private function checkAdmin()
    {
        if (!auth()->check()) {
            abort(403, 'Silakan login terlebih dahulu.');
        }

        if (auth()->user()->role !== 'admin') {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator.');
        }
    }

    /** Kategori yang dipakai kriteria, urut sesuai urutan kriteria. */
    private function kategoriList(): array
    {
        return KriteriaPemeriksaan::query()
            ->selectRaw('kategori, MIN(urutan) AS urut')
            ->groupBy('kategori')
            ->orderBy('urut')
            ->pluck('kategori')
            ->all();
    }

    // ── GET /admin/kriteria/contoh ────────────────────────────
    public function index()
    {
        $this->checkAdmin();

        $kategoriList = $this->kategoriList();
        $contoh       = ContohKategori::urut()->get()->groupBy('kategori');

        return view('admin.kriteria.contoh', compact('kategoriList', 'contoh'));
    }

    // ── POST /admin/kriteria/contoh ───────────────────────────
    public function store(Request $request)
    {
        $this->checkAdmin();

        $data = $request->validate([
            'kategori'   => ['required', 'string', Rule::in($this->kategoriList())],
            'gambar'     => 'required|array|min:1|max:20',
            'gambar.*'   => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'keterangan' => 'nullable|string|max:255',
        ], [
            'gambar.*.mimes' => 'File harus berupa gambar (JPG, PNG, WEBP) atau PDF.',
            'gambar.*.max'   => 'Ukuran file maksimal 10 MB.',
        ]);

        $urutan = (int) ContohKategori::where('kategori', $data['kategori'])->max('urutan');
        foreach ($request->file('gambar') as $file) {
            ContohKategori::create([
                'kategori'   => $data['kategori'],
                'path'       => $file->store(self::DIR, ContohKategori::DISK),
                'keterangan' => $data['keterangan'] ?? null,
                'urutan'     => ++$urutan,
            ]);
        }

        return redirect()->route('admin.kriteria.contoh.index')
            ->with('success', count($request->file('gambar')) . ' contoh ditambahkan ke kategori ' . $data['kategori'] . '.');
    }

    // ── PATCH /admin/kriteria/contoh/{contoh} ─────────────────
    public function update(Request $request, ContohKategori $contoh)
    {
        $this->checkAdmin();

        $data = $request->validate(['keterangan' => 'nullable|string|max:255']);
        $contoh->update(['keterangan' => $data['keterangan'] ?? null]);

        return back()->with('success', 'Keterangan contoh diperbarui.');
    }

    // ── DELETE /admin/kriteria/contoh/{contoh} ────────────────
    public function destroy(ContohKategori $contoh)
    {
        $this->checkAdmin();

        Storage::disk(ContohKategori::DISK)->delete($contoh->path);
        $contoh->delete();

        return back()->with('success', 'Contoh dihapus.');
    }

    // ── GET /checker/contoh/{contoh}/gambar ───────────────────
    public function gambar(ContohKategori $contoh)
    {
        abort_unless(Storage::disk(ContohKategori::DISK)->exists($contoh->path), 404);

        $headers = ['Cache-Control' => 'private, max-age=86400'];
        if ($contoh->isPdf()) {
            // Tampil di browser / viewer pdf.js, bukan diunduh
            $headers += ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="contoh.pdf"'];
        }

        return response()->file(Storage::disk(ContohKategori::DISK)->path($contoh->path), $headers);
    }

    // ── GET /checker/contoh-kategori ──────────────────────────
    // Dipakai panel tinjauan: { kategori: [{ url, keterangan, tipe: image|pdf }] }
    public function json(): JsonResponse
    {
        $data = ContohKategori::urut()->get()
            ->groupBy('kategori')
            ->map(fn($items) => $items->map(fn($c) => [
                'url'        => route('checker.contoh.gambar', $c) . '?v=' . $c->updated_at?->timestamp,
                'keterangan' => $c->keterangan,
                'tipe'       => $c->isPdf() ? 'pdf' : 'image',
            ])->values());

        return response()->json((object) $data->all());
    }
}
