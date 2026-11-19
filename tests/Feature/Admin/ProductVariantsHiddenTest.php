<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantsHiddenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_variant_section_is_hidden_on_create_and_edit_forms(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();

        foreach ([route('admin.products.create'), route('admin.products.edit', $product)] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertDontSee('Varian (opsional)')
                ->assertDontSee('Tambah Varian')
                ->assertDontSee('SKU Produk (induk)')
                ->assertDontSee('Wajib diisi untuk produk tanpa varian.')
                ->assertDontSee('material, dimensi')
                ->assertSee('Harga promo boleh dikosongkan.');
        }
    }

    public function test_variant_input_is_ignored_while_the_feature_is_hidden(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->admin())->test('pages::admin.products.form')
            ->set('category_id', $category->id)
            ->set('name', 'Jamu Kunyit Asam Botol')
            ->set('sku', 'JMY-KNY-BTL')
            ->set('original_price', 45000)
            ->set('weight', 600)
            ->set('stock', 12)
            ->set('description', '<p>Ramuan kunyit asam.</p>')
            ->set('variants', [['id' => null, 'name' => 'Botol', 'sku' => 'JMY-KNY-B1', 'price' => 45000, 'promo_price' => null, 'stock' => 5, 'weight' => 600, 'image_key' => null]])
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'JMY-KNY-BTL')->sole();
        $this->assertSame(0, $product->variants()->count());
        $this->assertSame(12, $product->stock);
    }

    public function test_existing_variants_are_kept_when_a_product_is_saved_while_hidden(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        Variant::factory()->create(['product_id' => $product->id, 'sku' => 'JMY-LAMA-01']);

        Livewire::actingAs($this->admin())->test('pages::admin.products.form', ['product' => $product])
            ->set('name', 'Jamu Lama Diperbarui')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Jamu Lama Diperbarui', $product->fresh()->name);
        $this->assertTrue(Variant::query()->where('sku', 'JMY-LAMA-01')->exists());
    }

    public function test_variant_section_returns_when_the_feature_is_shown_again(): void
    {
        config(['storefront.hidden.product_variants' => false]);

        $this->actingAs($this->admin())->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Varian (opsional)')
            ->assertSee('Tambah Varian');
    }

    public function test_simple_product_page_has_no_variant_picker_and_can_be_bought(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'is_active' => true]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('wire:model.live="variantId"', false)
            ->assertSee('Tambah Keranjang');

        Livewire::test('pages::storefront.product', ['product' => $product])
            ->call('addCurrent')
            ->assertHasNoErrors();

        $this->assertSame(1, app(CartService::class)->items()->first()['quantity']);
    }
}
