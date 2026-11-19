<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrackPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_tidak_ditemukan_menampilkan_pesan_inline(): void
    {
        Livewire::test('pages::storefront.track')
            ->set('order_number', 'jmy-tidak-ada')
            ->call('track')
            ->assertSet('track_error', 'Pesanan tidak ditemukan. Periksa kembali nomor pesanan Anda.')
            ->assertSee('Pesanan tidak ditemukan')
            ->assertNoRedirect();
    }

    public function test_nomor_kosong_menampilkan_pesan_inline(): void
    {
        Livewire::test('pages::storefront.track')
            ->call('track')
            ->assertSet('track_error', 'Masukkan nomor pesanan terlebih dahulu.');
    }

    public function test_nomor_valid_diarahkan_ke_halaman_pesanan(): void
    {
        $order = Order::factory()->create(['order_number' => 'JMY-123']);

        Livewire::test('pages::storefront.track')
            ->set('order_number', 'jmy-123')
            ->call('track')
            ->assertSet('track_error', null)
            ->assertRedirect(route('checkout.success', ['order' => $order->uuid]));
    }
}
