<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table): void {
            $table->string('name')->after('id');
            $table->string('role')->nullable()->after('name');
            $table->unsignedTinyInteger('rating')->default(5)->after('role');
            $table->text('content')->nullable()->after('rating');
            $table->string('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table): void {
            $table->dropColumn(['name', 'role', 'rating', 'content']);
        });
    }
};
