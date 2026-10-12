<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind'); // pack | unlimited
            $table->unsignedSmallInteger('credits')->nullable();
            $table->unsignedSmallInteger('valid_days');
            $table->unsignedInteger('price_pence');
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedSmallInteger('credits_left')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
    }
};
