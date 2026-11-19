# Jamu Mak Yuli

Toko online **Jamu Mak Yuli**: ramuan rempah kering untuk ikhtiar program hamil pasutri, direbus sendiri di rumah. Semangat menjemput buah hati ❤️

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)
![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-38BDF8)

## Fitur

**Toko**
- Beranda dengan slider, sorotan Paket Promil, dan katalog ramuan rempah.
- Katalog dengan pencarian, filter kategori dan harga, serta halaman detail produk dengan sisa stok. Produk yang habis berlabel "Habis".
- Keranjang dan wishlist.
- Checkout dengan alamat bertingkat (provinsi, kota, kecamatan, kelurahan) dan ongkir JNE atau J&T.
- Pembayaran lewat Midtrans.
- Konfirmasi pesanan via WhatsApp, lacak pesanan, dan invoice.
- Halaman Tentang Kami, FAQ, kebijakan, dan syarat reseller.
- Akun pelanggan: riwayat pesanan, notifikasi, wishlist, dan passkey. Checkout juga bisa tanpa akun.

**Panel admin**
- Produk, kategori, pesanan (resi manual, bukti kirim, label pengiriman thermal, invoice PDF), slide, testimoni, halaman, dan pengaturan.
- Stok opname, impor dan ekspor Excel, laporan omzet, dan pengguna.
- Gambar yang diunggah otomatis dikompres ke WebP.

## Teknologi

| Area | Stack |
|------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Livewire 4, Flux UI 2, Alpine.js, Tailwind CSS 4, Vite |
| Database | MySQL |
| Pembayaran | Midtrans Snap |
| Ongkir | RajaOngkir (Komerce) |
| Email | Brevo |
| Test | PHPUnit |

## Instalasi

```bash
git clone https://github.com/suwahadi/jamumakyuli.git
cd jamumakyuli

composer install
cp .env.example .env
php artisan key:generate

php artisan migrate --seed
php artisan storage:link

npm install
npm run build
```

Akun admin hasil seeder untuk lokal: `admin@jamumakyuli.com` / `password`. Ganti di production.

## Konfigurasi `.env`

```env
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=
MIDTRANS_CLIENT_KEY=
MIDTRANS_SERVER_KEY=

RAJAONGKIR_API_KEY=
RAJAONGKIR_BASE_URL=https://rajaongkir.komerce.id/api/v1
RAJAONGKIR_MOCK=false

BREVO_API_KEY=
BREVO_SENDER_NAME="Jamu Mak Yuli"
BREVO_SENDER_EMAIL="no-reply@jamumakyuli.com"
BREVO_ADMIN_EMAIL="jamumakyulionline@gmail.com"
```

## Test

```bash
composer test
```

Test memakai database MySQL terpisah bernama `jamumakyuli_test`. Buat database itu sebelum menjalankan test.

## Lisensi

Proprietary. © Jamu Mak Yuli. Tidak untuk didistribusikan ulang tanpa izin.
