<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'expiry_order'],
            ['value' => '1440', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void {}
};
