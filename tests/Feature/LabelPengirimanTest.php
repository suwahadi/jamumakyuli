<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Variant;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LabelPengirimanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
        Queue::fake();
    }

    private function pesanan(OrderStatus $status = OrderStatus::PAID): Order
    {
        $polos = Product::factory()->create([
            'name' => 'Jamu Kunyit Asam Segar', 'sku' => 'JMY-KNY-J01', 'weight' => 800,
            'is_active' => true, 'stock' => 10, 'original_price' => 350000, 'promo_price' => null,
        ]);
        $bervarian = Product::factory()->create(['name' => 'Jamu Beras Kencur Botol', 'sku' => 'JMY-KCR-INDUK', 'is_active' => true, 'original_price' => 450000, 'promo_price' => null]);
        $varian = Variant::factory()->create(['product_id' => $bervarian->id, 'name' => 'Kiri', 'sku' => 'JMY-KCR-K9', 'price' => 450000, 'promo_price' => null, 'stock' => 5, 'weight' => 400]);

        $order = app(OrderService::class)->checkout([
            'customer' => ['name' => 'Budi Santoso', 'email' => 'budi@email.com', 'phone' => '081234567890'],
            'shipping' => [
                'destination_id' => 17473, 'destination_label' => 'Mojogedang, Karanganyar, Jawa Tengah', 'address' => 'Jl. Melati No. 1',
                'courier' => 'jne', 'service' => 'REG', 'cost' => 20000,
            ],
            'items' => [
                ['product_id' => $polos->id, 'quantity' => 2],
                ['product_id' => $bervarian->id, 'variant_id' => $varian->id, 'quantity' => 1],
            ],
        ], (string) Str::uuid());

        if ($status !== OrderStatus::PENDING) {
            app(OrderService::class)->markAsPaid($order->fresh(), 'Admin');
        }

        return $order->fresh();
    }

    public function test_checkout_menyimpan_total_berat_pesanan(): void
    {
        $this->assertSame(2000, $this->pesanan(OrderStatus::PENDING)->total_weight);
    }

    public function test_label_memuat_data_pengiriman_tanpa_harga(): void
    {
        Setting::query()->updateOrCreate(['key' => 'site_name'], ['value' => 'Jamu Mak Yuli']);
        Setting::query()->updateOrCreate(['key' => 'site_address'], ['value' => 'Dk. Pilangbangu, Ds. Munggur']);
        Setting::query()->updateOrCreate(['key' => 'site_phone'], ['value' => '081212795576']);
        $order = $this->pesanan();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSee('100mm 150mm', false)
            ->assertSeeInOrder(['Pengirim', 'Jamu Mak Yuli', '081212795576', 'Dk. Pilangbangu, Ds. Munggur'])
            ->assertSeeInOrder(['Penerima', 'Budi Santoso', '081234567890', 'Jl. Melati No. 1', 'Mojogedang, Karanganyar, Jawa Tengah'])
            ->assertSee($order->order_number)
            ->assertSee($order->paid_at->locale('id')->isoFormat('D MMM YYYY'))
            ->assertSeeInOrder(['Jamu Kunyit Asam Segar', 'JMY-KNY-J01', '2'])
            ->assertSeeInOrder(['Jamu Beras Kencur Botol', 'JMY-KCR-K9', 'Kiri', '1'])
            ->assertSee('2 kg')
            ->assertDontSee('Rp');
    }

    public function test_pesanan_lama_tanpa_total_berat_dihitung_dari_berat_barang(): void
    {
        $order = $this->pesanan();
        $order->update(['total_weight' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSee('2 kg');
    }

    public function test_checkout_mencatat_sku_dan_nama_varian_tiap_barang(): void
    {
        $items = $this->pesanan(OrderStatus::PENDING)->items()->orderBy('id')->get();

        $this->assertSame(['JMY-KNY-J01', 'JMY-KCR-K9'], $items->pluck('sku')->all());
        $this->assertSame([null, 'Kiri'], $items->pluck('variant_name')->all());
    }

    public function test_varian_yang_sudah_dihapus_tetap_tampil_dari_catatan_checkout(): void
    {
        $order = $this->pesanan();
        $item = $order->items()->whereNotNull('variant_id')->firstOrFail();
        Variant::factory()->create(['product_id' => $item->product_id, 'name' => 'Kanan', 'sku' => 'JMY-KCR-N9']);
        $item->variant->delete();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSeeInOrder(['Jamu Beras Kencur Botol', 'JMY-KCR-K9', 'Kiri'])
            ->assertDontSee('Varian tidak diketahui')
            ->assertDontSee('JMY-KCR-INDUK');

        Livewire::actingAs($admin)
            ->test('pages::admin.orders.show', ['order' => $order->fresh()])
            ->assertSee('Varian: Kiri');

        $this->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('JMY-KCR-K9')
            ->assertSee('Kiri');
    }

    public function test_pesanan_lama_tanpa_catatan_varian_yang_variannya_dihapus_ditandai_tidak_diketahui(): void
    {
        $order = $this->pesanan();
        $item = $order->items()->whereNotNull('variant_id')->firstOrFail();
        $item->update(['sku' => null, 'variant_name' => null]);
        Variant::factory()->create(['product_id' => $item->product_id, 'name' => 'Kanan', 'sku' => 'JMY-KCR-N9']);
        $item->variant->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSee('Varian tidak diketahui')
            ->assertDontSee('JMY-KCR-INDUK');
    }

    public function test_label_memuat_catatan_custom_pembeli(): void
    {
        $order = $this->pesanan();
        $order->items()->firstOrFail()->update(['custom_text' => 'Ukir nama BUDI']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSee('Catatan: Ukir nama BUDI');
    }

    public function test_halaman_label_punya_tombol_unduh_dan_cetak(): void
    {
        $order = $this->pesanan();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSeeInOrder(['Kembali ke pesanan', '>Unduh<', '>Cetak<'], false)
            ->assertSeeInOrder(['data-unduh-pdf=', 'bg-brand-green', '>Unduh<', 'bg-white', '>Cetak<'], false)
            ->assertSee('data-unduh-pdf="label-'.$order->order_number.'.pdf"', false)
            ->assertSee('data-ukuran-mm="100x150"', false)
            ->assertSee('class="mx-auto box-border min-h-[150mm] w-[100mm] max-w-full', false)
            ->assertDontSee('Cetak Label');
    }

    public function test_label_hanya_untuk_admin(): void
    {
        $order = $this->pesanan();

        $this->actingAs(User::factory()->create())->get(route('admin.orders.label', $order))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.orders.label', $order))->assertRedirect(route('login'));
    }

    public function test_pesanan_belum_lunas_tidak_punya_label(): void
    {
        $order = $this->pesanan(OrderStatus::PENDING);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.label', $order))->assertNotFound();
    }

    public function test_detail_pesanan_admin_punya_tombol_cetak_label(): void
    {
        $order = $this->pesanan();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.orders.show', ['order' => $order])
            ->assertSee('Cetak Label')
            ->assertSee(route('admin.orders.label', $order), false);
    }
}
