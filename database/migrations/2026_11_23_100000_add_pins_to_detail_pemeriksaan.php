<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lebih dari satu penanda di halaman PDF per kriteria Tidak Sesuai: pins = [{hal, x, y}, …] (x/y pecahan halaman).
 * pin_halaman/pin_x/pin_y tetap diisi penanda pertama (kompatibel dengan data & kode lama); penanda lama ikut disalin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_pemeriksaan', function (Blueprint $table) {
            $table->text('pins')->nullable()->after('pin_y'); // JSON
        });
        DB::update("UPDATE detail_pemeriksaan
            SET pins = CONCAT('[{\"hal\":', pin_halaman, ',\"x\":', pin_x, ',\"y\":', pin_y, '}]')
            WHERE pin_halaman IS NOT NULL AND pin_x IS NOT NULL AND pin_y IS NOT NULL AND pins IS NULL");
    }

    public function down(): void
    {
        Schema::table('detail_pemeriksaan', function (Blueprint $table) {
            $table->dropColumn('pins');
        });
    }
};
