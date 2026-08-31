<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class AutoSkuTest extends TestCase
{
    public function test_product_without_sku_gets_auto_generated_sku(): void
    {
        $product = Product::create([
            'name' => 'Producto Sin SKU '.rand(10000, 99999),
            'type' => 'product',
            'track_inventory' => true,
        ]);

        $this->assertNotEmpty($product->sku);
        $this->assertMatchesRegularExpression('/^SKU-\d{6}$/', $product->sku);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => $product->sku,
        ]);
    }

    public function test_provided_sku_is_respected(): void
    {
        $sku = 'CUSTOM-'.strtoupper(bin2hex(random_bytes(4)));

        $product = Product::create([
            'name' => 'Producto Con SKU '.rand(10000, 99999),
            'sku' => $sku,
            'type' => 'service',
            'track_inventory' => false,
        ]);

        $this->assertSame($sku, $product->fresh()->sku);
    }

    public function test_generated_skus_do_not_collide(): void
    {
        $first = Product::create([
            'name' => 'Colisión A '.rand(10000, 99999),
            'type' => 'product',
        ]);
        $second = Product::create([
            'name' => 'Colisión B '.rand(10000, 99999),
            'type' => 'product',
        ]);

        $this->assertNotSame($first->sku, $second->sku);
    }

    public function test_landing_page_is_public_and_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Punto de venta');
    }
}
