<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('creator')->after('email');
            $table->string('azure_id')->nullable()->unique()->after('remember_token');
            $table->string('avatar', 2048)->nullable()->after('azure_id');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'azure_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
