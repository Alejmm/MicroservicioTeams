<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('city', 120);
            $t->string('logo_url', 255)->nullable();
            $t->timestamps();

            $t->unique(['name', 'city']); // opcional
        });
    }
    public function down(): void {
        Schema::dropIfExists('teams');
    }
};
