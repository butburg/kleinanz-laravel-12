<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ads')->where('status', 'Entwurf')->update(['status' => 'Draft']);
        DB::table('ads')->where('status', 'Archiviert')->update(['status' => 'Archived']);
        DB::table('ads')->where('status', 'Verkauft')->update(['status' => 'Sold']);

        Schema::table('ads', function (Blueprint $table): void {
            $table->string('status', 20)->default('Draft')->change();
        });
    }

    public function down(): void
    {
        DB::table('ads')->where('status', 'Draft')->update(['status' => 'Entwurf']);
        DB::table('ads')->where('status', 'Archived')->update(['status' => 'Archiviert']);
        DB::table('ads')->where('status', 'Sold')->update(['status' => 'Verkauft']);

        Schema::table('ads', function (Blueprint $table): void {
            $table->string('status', 20)->default('Entwurf')->change();
        });
    }
};
