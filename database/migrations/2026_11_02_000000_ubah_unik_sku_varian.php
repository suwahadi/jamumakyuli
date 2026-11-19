<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variants', function (Blueprint $table): void {
            $table->dropUnique(['sku']);
            $table->index('sku');
            $table->unique(['product_id', 'sku', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table): void {
            $table->dropUnique(['product_id', 'sku', 'name']);
            $table->dropIndex(['sku']);
            $table->unique('sku');
        });
    }
};
