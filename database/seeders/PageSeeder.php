<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            Page::query()->firstOrCreate(
                ['slug' => $slug],
                ['title' => $page['title'], 'content' => $page['content'], 'is_active' => true],
            );
        }
    }

    private function pages(): array
    {
        $resellerWa = e((string) wa_href('Halo Jamu Mak Yuli, saya ingin mendaftar menjadi reseller.'));
        $tanyaWa = e((string) wa_href());

        return [
            'about' => [
                'title' => 'Tentang Kami',
                'content' => <<<'HTML'
                    <p>Jamu Mak Yuli adalah ramuan jamu rempah tradisional yang berfokus pada kesehatan dan program hamil. Usaha ini berawal dari jamu rumahan pada tahun 2020 di Desa Munggur, Mojogedang, Karanganyar.</p>
                    <p>Sudah banyak pelanggan yang dikaruniai buah hati setelah berikhtiar dengan jamu Mak Yuli. Karena permintaan datang dari berbagai daerah di Indonesia, kami menyajikannya dalam bentuk rempah kering yang bisa Anda rebus sendiri di rumah.</p>
                    <p>Sampai hari ini lebih dari 1000 pelanggan sudah berbelanja di Jamu Mak Yuli. Semangat menjemput buah hati.</p>
                    <p><small>Foto rempah di beranda: Simon A. Eugster (CC BY-SA 3.0), Galeri ega (CC BY-SA 4.0), dan Toriqun Nuril Anwar (CC BY-SA 4.0), dari Wikimedia Commons.</small></p>
                    HTML,
            ],
            'faq' => [
                'title' => 'Pertanyaan Umum',
                'content' => <<<HTML
                    <p><strong>Bagaimana cara merebus jamunya?</strong><br>Cuci bersih semua bahan rempah, lalu rebus satu bungkus ramuan dengan air sesuai petunjuk di label kemasan sampai mendidih. Matikan kompor, biarkan dingin, saring, lalu simpan air rebusannya dalam botol di kulkas.</p>
                    <p><strong>Berapa kali jamu diminum dalam sehari?</strong><br>Dua kali sehari, pagi dan sore, sebelum makan. Takarannya tertulis di label setiap kemasan.</p>
                    <p><strong>Saya pilih Paket Promil Ideal atau Gemoy?</strong><br>Pilih Paket Promil Ideal bila berat badan istri di bawah 60 kg, dan Paket Promil Gemoy bila di atas 60 kg.</p>
                    <p><strong>Apakah jamu ini obat?</strong><br>Bukan. Jamu Mak Yuli adalah ramuan tradisional untuk menemani ikhtiar Anda. Bila sedang menjalani pengobatan, tanyakan dulu ke dokter Anda.</p>
                    <p><strong>Ke mana saja pesanan bisa dikirim?</strong><br>Ke seluruh Indonesia lewat JNE atau J&amp;T. Ongkos kirim dihitung otomatis saat checkout sesuai berat paket dan alamat tujuan.</p>
                    <p><strong>Bagaimana cara membayar?</strong><br>Setelah pesanan dibuat, Anda diarahkan ke halaman pembayaran Midtrans. Pilih metode pembayaran yang tersedia di sana.</p>
                    <p><strong>Bagaimana saya tahu pesanan sudah dikirim?</strong><br>Kami mengirim email berisi nomor resi saat paket diserahkan ke kurir. Status pesanan juga bisa dicek di halaman Lacak Pesanan dengan nomor pesanan Anda.</p>
                    <p><strong>Bisakah saya bertanya lewat WhatsApp dulu?</strong><br>Bisa. Hubungi kami di <a href="{$tanyaWa}">WhatsApp 087879865789</a>, Senin sampai Sabtu pukul 08.00 sampai 17.00 WIB.</p>
                    <p><strong>Bagaimana cara menjadi reseller?</strong><br>Baca syaratnya di halaman <a href="/reseller-terms">Syarat Jadi Reseller</a>, lalu daftar lewat WhatsApp.</p>
                    HTML,
            ],
            'shipping-policy' => [
                'title' => 'Kebijakan Pengiriman',
                'content' => <<<'HTML'
                    <p>Pesanan dikirim dari Karanganyar, Jawa Tengah, ke seluruh Indonesia lewat JNE atau J&amp;T. Ongkos kirim dihitung otomatis saat checkout berdasarkan berat paket dan alamat tujuan, sampai tingkat kelurahan.</p>
                    <p>Pesanan yang sudah dibayar kami proses pada jam operasional, Senin sampai Sabtu pukul 08.00 sampai 17.00 WIB. Saat paket diserahkan ke kurir, kami mengirim email berisi nomor resi.</p>
                    <p>Saat ini kami belum melayani pengiriman ke luar negeri lewat website. Untuk pesanan luar negeri, hubungi kami lewat WhatsApp.</p>
                    HTML,
            ],
            'return-policy' => [
                'title' => 'Kebijakan Pengembalian',
                'content' => <<<'HTML'
                    <p>Jamu adalah produk konsumsi, jadi kami hanya menerima pengembalian atau penggantian bila:</p>
                    <ul>
                        <li>paket tiba dalam keadaan rusak,</li>
                        <li>produk yang diterima tidak sesuai pesanan, atau</li>
                        <li>jumlah barang kurang.</li>
                    </ul>
                    <p>Ajukan lewat WhatsApp paling lambat 2 x 24 jam sejak paket diterima, dengan menyertakan nomor pesanan dan video saat membuka paket. Produk yang kemasannya sudah dibuka dan dipakai tidak dapat dikembalikan.</p>
                    <p>Setelah pengajuan kami periksa, kami akan mengganti produk atau mengembalikan dana sesuai kesepakatan.</p>
                    HTML,
            ],
            'privacy-policy' => [
                'title' => 'Kebijakan Privasi',
                'content' => <<<'HTML'
                    <p>Saat Anda berbelanja di Jamu Mak Yuli, kami mencatat nama, email, nomor HP, dan alamat pengiriman Anda. Data ini kami pakai untuk memproses pesanan, mengirim paket, dan mengabari status pesanan lewat email atau WhatsApp.</p>
                    <p>Nama, nomor HP, dan alamat Anda kami serahkan ke kurir (JNE atau J&amp;T) agar paket bisa sampai. Pembayaran diproses oleh Midtrans, sehingga data kartu atau rekening Anda tidak tersimpan di sistem kami.</p>
                    <p>Kami tidak menjual atau membagikan data Anda untuk keperluan lain. Bila ingin data Anda dihapus, hubungi kami lewat email jamumakyulionline@gmail.com atau WhatsApp 087879865789.</p>
                    HTML,
            ],
            'reseller-terms' => [
                'title' => 'Syarat Jadi Reseller',
                'content' => <<<HTML
                    <p>Ingin menjual Jamu Mak Yuli di daerah Anda? Berikut syarat umum reseller. Syarat ini bisa berubah, jadi pastikan Anda menanyakan versi terbaru saat mendaftar.</p>
                    <ul>
                        <li>Jumlah pembelian minimal dan harga khusus reseller kami sampaikan lewat WhatsApp.</li>
                        <li>Harga jual ke pembeli tidak boleh di bawah harga di website ini.</li>
                        <li>Produk dijual dalam kemasan asli, tanpa diubah atau dikemas ulang.</li>
                        <li>Foto dan nama Jamu Mak Yuli hanya dipakai untuk mempromosikan produk asli dari kami.</li>
                    </ul>
                    <p><a href="{$resellerWa}">Daftar jadi reseller lewat WhatsApp</a></p>
                    HTML,
            ],
        ];
    }
}
