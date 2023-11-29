<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReadKgosTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('read_kgos', function (Blueprint $table) {
            $table->id();
            $table->string('kgo');
            $table->foreignId('telegram_id')->on('telegrams')->referance('id')->onDelete('cascade');
            $table->boolean('is_update')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('read_kgos');
    }
};
