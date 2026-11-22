<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_chat_topics', function (Blueprint $table): void {
            $table->string('topic')->primary();
            $table->unsignedBigInteger('question_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_chat_topics');
    }
};
