## Bahasa dan keputusan

- Semua komunikasi di proyek ini memakai Bahasa Indonesia.
- Jika butuh konfirmasi atau ada yang perlu diputuskan, langsung tanyakan dalam mode wizard (pertanyaan pilihan), bukan uraian panjang.

## Konvensi

- Kode, slug, URL, nama class, kolom, dan param dalam bahasa Inggris. Teks yang dilihat pengguna dalam Bahasa Indonesia.
- Format uang `Rp 125.000` dan tanggal `Kamis, 8 Okt 2026 16:22` lewat `App\Support\Format`.
- Logika di controller dan service layer. Livewire hanya untuk tampilan baca.
- Tanpa soft delete.
- Form: Jangan pernah pakai tambahan TextHelper di field form (placeholder singkat text boleh), error inline di kolom, toast seperlunya, tombol "Memproses..." saat request berjalan, dua tombol sebaris harus 50:50.

## Aturan 1: Kode bersih tanpa komentar

Berlaku untuk setiap file kode aplikasi yang dibuat atau diubah: PHP, Blade, HTML, CSS, JS, file konfigurasi, `.htaccess`, dan kode kustom lain, termasuk file bawaan Laravel dan stub hasil `artisan make`.

**Dilarang, tanpa kecuali:**

- Komentar baris `//` dan `#`, komentar blok `/* ... */`.
- PHPDoc dan JSDoc `/** ... */`, termasuk `@var`, `@param`, `@return`, `@property`, dan `@use`.
- Komentar Blade `{{-- ... --}}` dan komentar HTML `<!-- ... -->`, termasuk penanda section.
- Tag dan catatan dev seperti `TODO`, `NOTE`, `FIX`, `HACK`, `added by AI`, serta pemisah dekoratif.

**Pengecualian:**

- File test Pest di `tests/` boleh berisi komentar seperlunya.
- `.env.example` boleh berisi komentar singkat.

**Hapus dengan aman:**

- Hanya menghapus komentar. Perilaku, logika, output, fungsi, dan fitur tidak boleh berubah.
- Yang mirip komentar tetapi bagian dari kode tetap dipertahankan: atribut PHP `#[...]`, shebang `#!`, warna `#fff` di CSS, URL `https://`, serta string yang berisi `//`, `#`, atau `/*`.
- Logika yang tidak langsung jelas dijelaskan lewat nama method, variabel, atau class yang jelas, bukan lewat komentar.
- `vendor/`, `node_modules/`, dan hasil build tidak disentuh.

**Sebelum selesai:**

1. Cari sisa komentar di semua file yang dibuat atau diubah, dan pastikan tidak ada lagi.
2. Jalankan test untuk memastikan perilaku tidak berubah.

## Git

- Repo: https://github.com/suwahadi/jamumakyuli, branch `main`. Commit langsung ke `main`.
- Semua commit atas nama `suwahadi <suwahadi@gmail.com>`, sebagai author dan committer.
- Tanpa atribusi AI dalam bentuk apa pun: tanpa trailer `Co-Authored-By`, tanpa "Generated with AI", tanpa nama Claude, Anthropic, atau AI lain di pesan commit, deskripsi PR, maupun kode.
- Deskripsi PR tanpa atribusi dan tanpa URL sesi.
- Dikunci di `.claude/settings.json`: atribusi commit dan PR dikosongkan, identitas git diisi lewat `env`. Repo lokal juga diset `git config user.name "suwahadi"` dan `git config user.email "suwahadi@gmail.com"`.

## Playwright Browser Constraints

When running checks or tests using the Playwright browser, always open the browser in full-width/maximized window mode. Do not resize or shrink the browser window unless explicitly required to test mobile or specific responsive viewports.
