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
            $table->unsignedBigInteger('request_count')->default(0)->after('fetched_at');
            $table->index('request_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('favicons', function (Blueprint $table) {
            $table->dropIndex(['request_count']);
            $table->dropColumn('request_count');
        });
    }
};
