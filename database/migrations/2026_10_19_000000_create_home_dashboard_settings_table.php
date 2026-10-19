<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_dashboard_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // Encrypted with APP_KEY by the model; calendar feed URLs are credentials.
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_dashboard_settings');
    }
};
