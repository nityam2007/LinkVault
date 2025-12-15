<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 64)->index();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('favicon_path', 500)->nullable();
            $table->string('domain', 255)->nullable()->index();
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->enum('archive_status', ['none', 'pending', 'processing', 'completed', 'failed'])->default('none');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'collection_id']);
            $table->index('created_at');
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'domain']);
        });

        // Add fulltext index for search
        DB::statement('ALTER TABLE bookmarks ADD FULLTEXT INDEX idx_search (title, description, url)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
