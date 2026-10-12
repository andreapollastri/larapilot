<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('late_cancelled')->default(false)->after('cancelled_at');
            $table->boolean('credit_forfeited')->default(false)->after('late_cancelled');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['late_cancelled', 'credit_forfeited']);
        });
    }
};
