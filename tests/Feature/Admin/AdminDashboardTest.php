<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCatalog;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_dashboard_menampilkan_seluruh_bagian_acuan(): void
    {
        $this->seedCatalog();

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Penjualan')
            ->assertSee('Total Pengguna')
            ->assertSee('Pesanan')
            ->assertSee('Produk Aktif')
            ->assertSee('Statistik Penjualan')
            ->assertSee('Produk Terlaris')
            ->assertSee('Pesanan Terbaru')
            ->assertSee('Stok Menipis');
    }

    public function test_omset_hanya_menghitung_pesanan_lunas_dan_dikirim(): void
    {
        $this->seedCatalog();

        Order::factory()->create(['status' => OrderStatus::PAID, 'grand_total' => 1_000_000]);
        Order::factory()->create(['status' => OrderStatus::SHIPPED, 'grand_total' => 500_000]);
        Order::factory()->create(['status' => OrderStatus::PENDING, 'grand_total' => 9_000_000]);
        Order::factory()->create(['status' => OrderStatus::CANCELLED, 'grand_total' => 7_000_000]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(rupiah(1_500_000))
            ->assertDontSee(rupiah(17_500_000));
    }

    public function test_stok_menipis_hanya_menampilkan_produk_di_bawah_ambang(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);

        Product::factory()->create(['category_id' => $kategori->id, 'name' => 'Part Nyaris Habis', 'stock' => 2, 'is_active' => true]);
        Product::factory()->create(['category_id' => $kategori->id, 'name' => 'Part Melimpah', 'stock' => 999, 'is_active' => true]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Part Nyaris Habis')
            ->assertDontSee('Part Melimpah');
    }

    // Kolom stock pada produk bervarian adalah data mati — totalStock() menjumlah varian
    // dan form admin membiarkannya 0. Panel yang membaca kolom induk menampilkan setiap
    // produk bervarian sebagai "0 tersisa".
    public function test_produk_bervarian_yang_stok_variannya_aman_tidak_masuk_stok_menipis(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);

        $produk = Product::factory()->create([
            'category_id' => $kategori->id,
            'name' => 'Part Bervarian Melimpah',
            'stock' => 0,
            'is_active' => true,
        ]);
        Variant::factory()->create(['product_id' => $produk->id, 'stock' => 50]);
        Variant::factory()->create(['product_id' => $produk->id, 'stock' => 80]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Part Bervarian Melimpah');
    }

    public function test_stok_menipis_membaca_stok_varian_terendah_bukan_kolom_induk(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);

        $produk = Product::factory()->create([
            'category_id' => $kategori->id,
            'name' => 'Part Bervarian Kritis',
            'stock' => 987654,
            'is_active' => true,
            'sku' => 'JMY-KRITIS',
        ]);
        Variant::factory()->create(['product_id' => $produk->id, 'stock' => 3]);
        Variant::factory()->create(['product_id' => $produk->id, 'stock' => 40]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Part Bervarian Kritis')
            ->assertDontSee('987654');
    }

    public function test_produk_terlaris_dihitung_dari_pesanan_berbayar(): void
    {
        $kategori = Category::query()->create(['name' => 'Rem', 'slug' => 'rem', 'is_active' => true]);
        $laris = Product::factory()->create(['category_id' => $kategori->id, 'name' => 'Kampas Paling Laku']);
        $tampilan = Product::factory()->create(['category_id' => $kategori->id, 'name' => 'Angka Tampilan Saja']);
        $batal = Product::factory()->create(['category_id' => $kategori->id, 'name' => 'Hanya Dipesan Batal']);

        foreach ([[$laris, OrderStatus::PAID, 3], [$laris, OrderStatus::SHIPPED, 4], [$batal, OrderStatus::CANCELLED, 50], [$batal, OrderStatus::PENDING, 20]] as [$produk, $status, $qty]) {
            $order = Order::factory()->create(['status' => $status]);
            $order->items()->create(['product_id' => $produk->id, 'variant_id' => null, 'price' => 10_000, 'quantity' => $qty, 'total' => 10_000 * $qty]);
        }

        $terlaris = Livewire::actingAs($this->admin())
            ->test('pages::admin.dashboard')
            ->instance()
            ->produkTerlaris;

        $this->assertSame(['Kampas Paling Laku'], $terlaris->pluck('name')->all());
        $this->assertSame(7, (int) $terlaris->first()->jumlah_terjual);
    }
}
