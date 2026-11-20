<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'site_name' => 'Jamu Mak Yuli',
            'site_tagline' => 'Semangat menjemput buah hati ❤️',
            'site_description' => 'Jamu Mak Yuli. Ramuan rempah kering untuk ikhtiar program hamil pasutri, direbus sendiri di rumah. Kirim ke seluruh Indonesia.',
            'site_keywords' => 'jamu promil, jamu program hamil, ramuan rempah kering, jamu pasutri, jamu tradisional, Jamu Mak Yuli',
            'site_email' => 'jamumakyulionline@gmail.com',
            'site_phone' => '087879865789',
            'site_whatsapp' => '087879865789',
            'site_address' => 'Dk. Pilangbangu, Ds. Munggur, Kec. Mojogedang, Kab. Karanganyar',
            'site_maps_url' => 'https://www.google.com/maps/search/?api=1&query=rumah+mak+yuli+jamu',
            'site_hours' => 'Senin sampai Sabtu, 08.00 sampai 17.00 WIB',
            'origin_district_id' => '62050',
            'expiry_order' => '1440',
            'site_footer_about' => 'Ramuan rempah kering untuk ikhtiar program hamil, racikan rumahan sejak 2020.',
            'site_facebook' => '',
            'site_instagram' => '',
            'site_tiktok' => '',
            'site_twitter' => '',
            'site_threads' => '',
            'site_tokopedia' => '',
            'site_shopee' => '',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
