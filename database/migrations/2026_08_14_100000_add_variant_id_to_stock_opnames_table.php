<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table): void {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('variants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
