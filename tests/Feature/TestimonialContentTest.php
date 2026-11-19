<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestimonialContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_testimoni_menyimpan_kutipan_nama_peran_dan_rating(): void
    {
        $testimonial = Testimonial::query()->create([
            'name' => 'Budi Santoso',
            'role' => 'Pelanggan Setia',
            'rating' => 5,
            'content' => 'Partnya sesuai dan sampai cepat.',
        ]);

        $this->assertSame('Budi Santoso', $testimonial->name);
        $this->assertSame('Pelanggan Setia', $testimonial->role);
        $this->assertSame(5, $testimonial->rating);
        $this->assertSame('Partnya sesuai dan sampai cepat.', $testimonial->content);
        $this->assertNull($testimonial->image);
    }

    public function test_rating_bawaan_lima_bila_tidak_diisi(): void
    {
        $testimonial = Testimonial::query()->create([
            'name' => 'Siti',
            'content' => 'Pelayanan ramah.',
        ]);

        $this->assertSame(5, $testimonial->refresh()->rating);
    }

    public function test_menghapus_testimoni_tanpa_gambar_tidak_melempar_error(): void
    {
        $testimonial = Testimonial::query()->create([
            'name' => 'Andi',
            'content' => 'Harga bersaing.',
        ]);

        $testimonial->delete();

        $this->assertSame(0, Testimonial::query()->count());
    }

    public function test_admin_dapat_menambah_testimoni_teks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test('pages::admin.testimonials.index')
            ->set('name', 'Budi Santoso')
            ->set('role', 'Pelanggan Setia')
            ->set('rating', 5)
            ->set('content', 'Partnya sesuai dan sampai cepat.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('testimonials', [
            'name' => 'Budi Santoso',
            'role' => 'Pelanggan Setia',
            'rating' => 5,
        ]);
    }

    public function test_admin_dapat_menyimpan_rating_string_dan_peran_kosong(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test('pages::admin.testimonials.index')
            ->set('name', 'Siti Aminah')
            ->set('role', null)
            ->set('rating', '3')
            ->set('content', 'Pelayanan ramah dan cepat.')
            ->call('save')
            ->assertHasNoErrors();

        $testimonial = Testimonial::query()->firstOrFail();

        $this->assertNull($testimonial->role);
        $this->assertSame(3, $testimonial->rating);
    }

    public function test_admin_wajib_mengisi_nama_dan_kutipan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test('pages::admin.testimonials.index')
            ->set('name', '')
            ->set('content', '')
            ->call('save')
            ->assertHasErrors(['name', 'content']);
    }
}
