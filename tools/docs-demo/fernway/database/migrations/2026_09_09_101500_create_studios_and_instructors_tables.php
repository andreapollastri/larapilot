<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('timezone')->default('Europe/London');
            $table->unsignedSmallInteger('late_cancel_minutes')->default(120);
            $table->string('brand_color', 7)->default('#2f6b55');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('display_name');
            $table->string('role')->default('instructor');
            $table->text('bio')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructors');
        Schema::dropIfExists('studios');
    }
};
