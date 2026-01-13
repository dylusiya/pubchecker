<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'nip')) {
                $table->string('nip')->nullable()->after('username');
            }
            if (!Schema::hasColumn('users', 'nip_baru')) {
                $table->string('nip_baru')->nullable();
            }
            if (!Schema::hasColumn('users', 'kode_organisasi')) {
                $table->string('kode_organisasi')->nullable();
            }
            if (!Schema::hasColumn('users', 'kode_provinsi')) {
                $table->string('kode_provinsi')->nullable();
            }
            if (!Schema::hasColumn('users', 'kode_kabupaten')) {
                $table->string('kode_kabupaten')->nullable();
            }
            if (!Schema::hasColumn('users', 'golongan')) {
                $table->string('golongan')->nullable();
            }
            if (!Schema::hasColumn('users', 'jabatan')) {
                $table->string('jabatan')->nullable();
            }
            if (!Schema::hasColumn('users', 'eselon')) {
                $table->string('eselon')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'nip', 'nip_baru', 'kode_organisasi',
                'kode_provinsi', 'kode_kabupaten', 'golongan',
                'jabatan', 'eselon'
            ]);
        });
    }
};