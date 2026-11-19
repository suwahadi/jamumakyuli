<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('custom_text_enabled')->default(false)->after('is_active');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('custom_text', 60)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('custom_text_enabled');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('custom_text');
        });
    }
};
