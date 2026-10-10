<?php

namespace App\Http\Controllers;

use App\Models\CatatanTambahan;
use App\Models\HasilPemeriksaan;
use App\Services\SipotretService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Catatan tambahan petugas (temuan di luar daftar kriteria) per publikasi. */
class CatatanTambahanController extends Controller
{
    // ── GET /checker/hasil/{hasil}/catatan ──
    public function index(HasilPemeriksaan $hasil): JsonResponse
    {
        return response()->json(['catatan' => $hasil->catatanTambahan->map->toReviewArray()]);
    }

    // ── POST /checker/hasil/{hasil}/catatan ──
    public function store(Request $request, HasilPemeriksaan $hasil): JsonResponse
    {
        $data = $this->validasi($request);
        $catatan = $hasil->catatanTambahan()->create($data + ['dibuat_oleh' => auth()->user()?->name]);

        return response()->json(['catatan' => $catatan->toReviewArray()], 201);
    }

    // ── PATCH /checker/catatan-tambahan/{catatan} ──
    public function update(Request $request, CatatanTambahan $catatan): JsonResponse
    {
        // kategori tidak berubah saat diedit (catatan tetap di kategori asalnya)
        $catatan->update(array_diff_key($this->validasi($request), ['kategori' => 1]));

        return response()->json(['catatan' => $catatan->toReviewArray()]);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'kategori'   => 'nullable|string|max:150',
            'item'       => 'required|string|max:' . CatatanTambahan::MAX_ITEM,
            'keterangan' => 'required|string|max:2000',
            'flag_level' => ['required', Rule::in(CatatanTambahan::FLAG_LEVELS)],
            'halaman'    => 'nullable|integer|min:1|max:65000',
        ], [
            'item.required'       => 'Nama item wajib diisi.',
            'item.max'            => 'Nama item maksimal ' . CatatanTambahan::MAX_ITEM . ' karakter (batas SIPOTRET).',
            'keterangan.required' => 'Keterangan wajib diisi.',
            'flag_level.required' => 'Pilih level: minor, moderate, atau major.',
        ]);
    }

    // ── DELETE /checker/catatan-tambahan/{catatan} ──
    public function destroy(CatatanTambahan $catatan): JsonResponse
    {
        $catatan->delete();
        return response()->json(['ok' => true]);
    }

    // ── GET /checker/catatan-tambahan/saran — nama item yang pernah dipakai (pubchecker & SIPOTRET) ──
    public function saran(SipotretService $sipotret): JsonResponse
    {
        $nama = CatatanTambahan::query()->select('item')->distinct()->limit(300)->pluck('item');

        if ($sipotret->enabled()) {
            try {
                $nama = $nama->merge(
                    DB::connection('sipotret')->table('pasca_rilis_catatan')
                        ->whereNull('item_periksa_id')->whereNotNull('item_periksa_custom')
                        ->select('item_periksa_custom')->distinct()->limit(300)->pluck('item_periksa_custom')
                );
            } catch (\Throwable $e) {
                report($e); // saran hanya pelengkap — tetap jalan tanpa SIPOTRET
            }
        }

        $nama = $nama->map(fn($n) => trim((string) $n))->filter()->unique(fn($n) => mb_strtolower($n))->sort()->values();
        return response()->json(['saran' => $nama]);
    }
}
