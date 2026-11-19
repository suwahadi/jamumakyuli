<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BuktiKirimTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'pages::admin.orders.show';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeShippingQuote();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function pesanan(OrderStatus $status, ?User $pemilik = null): Order
    {
        $product = Product::factory()->create([
            'is_active' => true, 'stock' => 10, 'original_price' => 100000, 'promo_price' => null,
        ]);

        $order = app(OrderService::class)->checkout([
            'customer' => ['name' => 'Budi', 'email' => 'budi@email.com', 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473, 'address' => 'Jl. Melati 1',
                'courier' => 'jne', 'service' => 'REG', 'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], (string) Str::uuid());

        $order->update(['user_id' => $pemilik?->id]);

        if ($status !== OrderStatus::PENDING) {
            app(OrderService::class)->markAsPaid($order->fresh(), 'Admin');
        }

        if ($status === OrderStatus::SHIPPED) {
            app(OrderService::class)->markAsShipped($order->fresh(), 'Admin', 'JP1234567890');
        }

        return $order->fresh();
    }

    private function foto(string $nama = 'resi.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 1200, 1600);
    }

    private function denganBukti(?User $pemilik = null): Order
    {
        $order = $this->pesanan(OrderStatus::SHIPPED, $pemilik);
        app(OrderService::class)->simpanBuktiKirim($order, $this->foto());

        return $order->fresh();
    }

    public function test_tandai_dikirim_dengan_foto_menyimpan_webp_bernama_acak_di_folder_privat(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN, ['order' => $order])
            ->set('tracking_number', 'JP1234567890')
            ->set('bukti_kirim', $this->foto())
            ->call('markShipped')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::SHIPPED, $order->status);
        $this->assertMatchesRegularExpression('#^bukti-kirim/[a-z0-9]{15}\.webp$#', (string) $order->shipping_proof_path);
        Storage::disk('local')->assertExists($order->shipping_proof_path);
        Storage::disk('public')->assertMissing($order->shipping_proof_path);
        $this->assertSame('image/webp', Storage::disk('local')->mimeType($order->shipping_proof_path));
    }

    public function test_tandai_dikirim_tanpa_foto_tetap_bisa(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN, ['order' => $order])
            ->set('tracking_number', 'JP1234567890')
            ->call('markShipped')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
        $this->assertNull($order->fresh()->shipping_proof_path);
    }

    public function test_nomor_resi_tetap_wajib_walau_foto_diisi(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN, ['order' => $order])
            ->set('tracking_number', '')
            ->set('bukti_kirim', $this->foto())
            ->call('markShipped')
            ->assertHasErrors('tracking_number');

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('bukti-kirim'));
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN, ['order' => $order])
            ->set('tracking_number', 'JP1234567890')
            ->set('bukti_kirim', UploadedFile::fake()->create('resi.pdf', 100, 'application/pdf'))
            ->call('markShipped')
            ->assertHasErrors('bukti_kirim');

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_foto_bisa_diunggah_setelah_dikirim_dan_foto_lama_terhapus_saat_diganti(): void
    {
        $order = $this->pesanan(OrderStatus::SHIPPED);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(self::ADMIN, ['order' => $order])
            ->set('bukti_kirim', $this->foto())
            ->assertHasNoErrors();
        $pertama = $order->fresh()->shipping_proof_path;
        Storage::disk('local')->assertExists($pertama);

        Livewire::actingAs($admin)->test(self::ADMIN, ['order' => $order->fresh()])
            ->set('bukti_kirim', $this->foto('resi-baru.png'))
            ->assertHasNoErrors();
        $kedua = $order->fresh()->shipping_proof_path;

        $this->assertNotSame($pertama, $kedua);
        Storage::disk('local')->assertExists($kedua);
        Storage::disk('local')->assertMissing($pertama);
    }

    public function test_foto_belum_tersimpan_saat_pesanan_masih_lunas_sampai_ditandai_dikirim(): void
    {
        $order = $this->pesanan(OrderStatus::PAID);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN, ['order' => $order])
            ->set('bukti_kirim', $this->foto());

        $this->assertNull($order->fresh()->shipping_proof_path);
    }

    public function test_pemilik_pesanan_dapat_membuka_bukti_kirim(): void
    {
        $pemilik = User::factory()->create();
        $order = $this->denganBukti($pemilik);

        $this->actingAs($pemilik)->get(route('orders.bukti-kirim', $order))
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');
    }

    public function test_admin_dapat_membuka_bukti_kirim(): void
    {
        $order = $this->denganBukti(User::factory()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('orders.bukti-kirim', $order))
            ->assertOk();
    }

    public function test_pengguna_lain_tidak_dapat_membuka_bukti_kirim(): void
    {
        $order = $this->denganBukti(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('orders.bukti-kirim', $order))
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $order = $this->denganBukti();

        $this->get(route('orders.bukti-kirim', $order))->assertRedirect(route('login'));
    }

    public function test_pesanan_tanpa_bukti_kirim_menjawab_404(): void
    {
        $pemilik = User::factory()->create();
        $order = $this->pesanan(OrderStatus::SHIPPED, $pemilik);

        $this->actingAs($pemilik)->get(route('orders.bukti-kirim', $order))->assertNotFound();
    }

    public function test_halaman_pesanan_pemilik_menampilkan_tombol_lihat_bukti_kirim(): void
    {
        $pemilik = User::factory()->create();
        $order = $this->denganBukti($pemilik);

        $this->actingAs($pemilik)->get(route('dashboard.orders.show', $order))
            ->assertOk()
            ->assertSee('Lihat Bukti Kirim')
            ->assertSee(route('orders.bukti-kirim', $order), false);
    }

    public function test_tombol_tidak_muncul_bila_belum_ada_bukti_kirim(): void
    {
        $pemilik = User::factory()->create();
        $order = $this->pesanan(OrderStatus::SHIPPED, $pemilik);

        $this->actingAs($pemilik)->get(route('dashboard.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Lihat Bukti Kirim');
    }

    public function test_halaman_pesanan_tamu_tidak_menampilkan_bukti_kirim(): void
    {
        $order = $this->denganBukti(User::factory()->create());

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertDontSee('Lihat Bukti Kirim')
            ->assertDontSee(route('orders.bukti-kirim', $order), false);
    }

    public function test_foto_bukti_kirim_terhapus_saat_pesanan_dihapus(): void
    {
        $order = $this->denganBukti();
        $path = $order->shipping_proof_path;
        Storage::disk('local')->assertExists($path);

        $order->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_panel_admin_menampilkan_foto_bukti_kirim(): void
    {
        $order = $this->denganBukti();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::ADMIN, ['order' => $order])
            ->assertSee(route('orders.bukti-kirim', $order), false)
            ->assertSee('Ganti foto');
    }
}
