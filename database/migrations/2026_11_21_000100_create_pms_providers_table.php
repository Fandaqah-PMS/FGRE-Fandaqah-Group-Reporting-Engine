<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();   // fandaqah, nazeel, opera
            $table->string('name');
            $table->boolean('supports_mcp')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_providers');
    }
};
