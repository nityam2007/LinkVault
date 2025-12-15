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
        Schema::create('bookmark_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookmark_id')->unique()->constrained()->cascadeOnDelete();
            $table->mediumText('article_text')->nullable();
            $table->mediumText('article_html')->nullable();
            $table->string('html_snapshot_path', 500)->nullable();
            $table->string('author')->nullable();
            $table->datetime('published_date')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedSmallInteger('reading_time_minutes')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('site_name')->nullable();
            $table->string('og_title', 500)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image_path', 500)->nullable();
            $table->string('images_directory', 255)->nullable();
            $table->unsignedSmallInteger('image_count')->default(0);
            $table->string('primary_image_path', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
        });

        // Add fulltext index for article text search
        DB::statement('ALTER TABLE bookmark_archives ADD FULLTEXT INDEX idx_article_text (article_text)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmark_archives');
    }
};
