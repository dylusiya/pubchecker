<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session di database (SESSION_DRIVER=database), menggantikan driver file.
 *
 * Driver file kehilangan session saat banyak permintaan paralel (Periksa Terpilih: unduh PDF lewat proxy,
 * draf, cek kriteria sekaligus): file_put_contents memotong file session dulu baru menulis, sehingga
 * permintaan lain bisa membaca file kosong → Laravel membuat session baru dengan token CSRF baru dan
 * menyimpannya → semua permintaan berikutnya gagal "CSRF token mismatch" (dan petugas ter-logout).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) return;
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
