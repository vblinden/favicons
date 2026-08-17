<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('favicons', function (Blueprint $table) {
            $table->string('theme')->default('default')->after('domain');
        });

        Schema::table('favicons', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->unique(['domain', 'theme']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('favicons', function (Blueprint $table) {
            $table->dropUnique(['domain', 'theme']);
            $table->unique(['domain']);
            $table->dropColumn('theme');
        });
    }
};
