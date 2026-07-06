<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 32)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->nullable();
            $table->string('status')->default('draft'); // draft | published | closed
            $table->boolean('require_email_verification')->default(false);
            $table->boolean('notify_on_response')->default(false);
            $table->unsignedInteger('max_responses')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('retention_days')->nullable(); // null = global default
            $table->text('success_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // text | textarea | email | number | date | choice | checkboxes | dropdown | file | info
            $table->string('label', 1000);
            $table->text('description')->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable(); // choices list, file constraints, text limits…
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_sections');
        Schema::dropIfExists('forms');
    }
};
