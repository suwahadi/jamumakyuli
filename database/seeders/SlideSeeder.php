<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Slide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SlideSeeder extends Seeder
{
    private const SLIDES = [
        ['image' => 'kunyit.webp', 'title' => 'Rempah kering pilihan, siap direbus di rumah', 'content' => 'Racikan Jamu Mak Yuli sejak 2020.'],
        ['image' => 'bumbu-rempah.webp', 'title' => 'Ikhtiar program hamil bersama pasangan', 'content' => 'Pilih Paket Promil Ideal atau Gemoy sesuai berat badan istri.'],
        ['image' => 'kekayaan-rempah.webp', 'title' => 'Kirim ke seluruh Indonesia', 'content' => 'Lewat JNE dan J&T, ongkir dihitung otomatis saat checkout.'],
    ];

    public function run(): void
    {
        if (Slide::query()->exists()) {
            return;
        }

        foreach (self::SLIDES as $order => $slide) {
            $path = 'slides/'.$slide['image'];
            Storage::disk('public')->put($path, (string) file_get_contents(database_path('data/images/slides/'.$slide['image'])));

            Slide::query()->create([
                'title' => $slide['title'],
                'content' => $slide['content'],
                'image' => $path,
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }
    }
}
