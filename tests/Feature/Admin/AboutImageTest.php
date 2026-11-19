<?php

namespace Tests\Feature\Admin;

use App\Models\AboutImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AboutImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function gambar(): UploadedFile
    {
        return UploadedFile::fake()->image('sekilas.jpg', 800, 800);
    }

    /** @return list<AboutImage> */
    private function buat(int $jumlah): array
    {
        $dibuat = [];

        for ($i = 0; $i < $jumlah; $i++) {
            $dibuat[] = AboutImage::query()->create([
                'image' => 'about/gambar-'.$i.'.webp',
                'sort_order' => $i,
            ]);
        }

        return $dibuat;
    }

    public function test_halaman_admin_terbuka(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.about-images.index'))
            ->assertOk()
            ->assertSee('Sekilas Tentang');
    }

    public function test_unggah_menambah_gambar_dan_menyimpan_berkasnya(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->set('photo', $this->gambar())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, AboutImage::query()->count());

        $path = AboutImage::query()->value('image');
        $this->assertStringStartsWith('about/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    // Bento grid pada beranda hanya menyediakan lima panel, jadi item keenam
    // harus ditolak alih-alih merusak tata letak.
    public function test_gambar_keenam_ditolak(): void
    {
        $this->buat(AboutImage::MAX_ITEMS);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->set('photo', $this->gambar())
            ->call('save');

        $this->assertSame(AboutImage::MAX_ITEMS, AboutImage::query()->count());
    }

    public function test_tombol_naik_menukar_dengan_tetangga_di_atasnya(): void
    {
        [$a, $b, $c] = $this->buat(3);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->call('naik', $c->id);

        $this->assertSame(
            [$a->id, $c->id, $b->id],
            AboutImage::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all()
        );
    }

    public function test_tombol_turun_menukar_dengan_tetangga_di_bawahnya(): void
    {
        [$a, $b, $c] = $this->buat(3);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->call('turun', $a->id);

        $this->assertSame(
            [$b->id, $a->id, $c->id],
            AboutImage::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all()
        );
    }

    public function test_naik_pada_item_teratas_tidak_mengubah_apa_pun(): void
    {
        [$a, $b] = $this->buat(2);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->call('naik', $a->id);

        $this->assertSame(
            [$a->id, $b->id],
            AboutImage::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all()
        );
    }

    // Nomor urut yang bolong atau kembar berasal dari data lama; penggeseran
    // harus tetap menghasilkan urutan yang rapat.
    public function test_urutan_ditulis_ulang_rapat_setelah_digeser(): void
    {
        $a = AboutImage::query()->create(['image' => 'about/a.webp', 'sort_order' => 7]);
        $b = AboutImage::query()->create(['image' => 'about/b.webp', 'sort_order' => 7]);
        $c = AboutImage::query()->create(['image' => 'about/c.webp', 'sort_order' => 99]);

        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->call('turun', $a->id);

        $this->assertSame([0, 1, 2], AboutImage::query()->orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame(
            [$b->id, $a->id, $c->id],
            AboutImage::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all()
        );
    }

    public function test_hapus_membuang_baris_beserta_berkasnya(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->set('photo', $this->gambar())
            ->call('save');

        $item = AboutImage::query()->firstOrFail();
        $path = $item->image;

        Livewire::actingAs($this->admin())
            ->test('pages::admin.about-images.index')
            ->call('confirmDelete', $item->id)
            ->call('delete');

        $this->assertSame(0, AboutImage::query()->count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_beranda_menampilkan_gambar_sesuai_urutan(): void
    {
        AboutImage::query()->create(['image' => 'about/kedua.webp', 'sort_order' => 5]);
        AboutImage::query()->create(['image' => 'about/pertama.webp', 'sort_order' => 1]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('sekilas-h', $html);
        $this->assertLessThan(
            strpos($html, 'about/kedua.webp'),
            strpos($html, 'about/pertama.webp'),
            'Gambar dengan sort_order lebih kecil harus dirender lebih dahulu.'
        );
    }

    public function test_grid_gambar_sekilas_hilang_ketika_belum_ada_gambar(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('sekilas-h', false)->assertDontSee('auto-rows-[130px]', false);
    }
}
