<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSlugFieldTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function newProductForm(string $name, string $slug = ''): Testable
    {
        return Livewire::actingAs($this->admin())->test('pages::admin.products.form')
            ->set('category_id', Category::factory()->create()->id)
            ->set('name', $name)
            ->set('slug', $slug)
            ->set('sku', 'JMY-'.strtoupper(substr(md5($name.$slug), 0, 8)))
            ->set('original_price', 50000)
            ->set('weight', 500)
            ->set('stock', 10)
            ->set('description', '<p>Ramuan rempah.</p>');
    }

    public function test_form_shows_name_and_slug_then_sku_and_category(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products.create'))
            ->assertOk()
            ->assertSeeInOrder(['Nama Produk', 'Slug', 'SKU Produk', 'Kategori'])
            ->assertSee('wire:model="slug"', false)
            ->assertSee('Otomatis dari nama produk');
    }

    public function test_empty_slug_on_create_is_generated_from_the_name(): void
    {
        $this->newProductForm('Jamu Kunyit Asam Segar')->call('save')->assertHasNoErrors();

        $this->assertTrue(Product::query()->where('slug', 'jamu-kunyit-asam-segar')->exists());
    }

    public function test_custom_slug_on_create_is_normalised(): void
    {
        $this->newProductForm('Jamu Beras Kencur', 'Beras Kencur Spesial!')->call('save')->assertHasNoErrors();

        $this->assertTrue(Product::query()->where('slug', 'beras-kencur-spesial')->exists());
    }

    public function test_slug_used_by_another_product_is_rejected_inline(): void
    {
        Product::factory()->create(['slug' => 'jamu-sirih']);

        $this->newProductForm('Jamu Sirih Baru', 'jamu-sirih')
            ->call('save')
            ->assertHasErrors(['slug' => 'unique']);

        $this->assertFalse(Product::query()->where('name', 'Jamu Sirih Baru')->exists());
    }

    public function test_slug_can_be_changed_freely_when_editing(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Temulawak', 'slug' => 'jamu-temulawak']);

        Livewire::actingAs($this->admin())->test('pages::admin.products.form', ['product' => $product])
            ->assertSet('slug', 'jamu-temulawak')
            ->set('slug', 'temulawak-asli-karanganyar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('temulawak-asli-karanganyar', $product->fresh()->slug);
    }

    public function test_renaming_keeps_the_slug_shown_in_the_form(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Lama', 'slug' => 'jamu-lama']);

        Livewire::actingAs($this->admin())->test('pages::admin.products.form', ['product' => $product])
            ->set('name', 'Jamu Nama Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('jamu-lama', $product->fresh()->slug);
    }

    public function test_clearing_the_slug_when_editing_regenerates_it_from_the_name(): void
    {
        $product = Product::factory()->create(['name' => 'Jamu Lama', 'slug' => 'jamu-lama']);

        Livewire::actingAs($this->admin())->test('pages::admin.products.form', ['product' => $product])
            ->set('name', 'Jamu Nama Baru')
            ->set('slug', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('jamu-nama-baru', $product->fresh()->slug);
    }
}
