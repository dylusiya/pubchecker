<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nama item catatan tambahan dilebarkan dari VARCHAR(50) ke TEXT (validasi maks. 2000 karakter). Dulu nama item master dari acuan
 * (item_nama SIPOTRET, s.d. 150) terpotong 50 karakter. Di SIPOTRET nama lengkap item custom
 * tersimpan di kolom catatan (TEXT).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE catatan_tambahan MODIFY item TEXT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE catatan_tambahan SET item = LEFT(item, 50) WHERE CHAR_LENGTH(item) > 50');
        DB::statement('ALTER TABLE catatan_tambahan MODIFY item VARCHAR(50) NOT NULL');
    }
};
