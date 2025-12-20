<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPaymentOption;
use App\Models\ProductResalePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        $this->customer = User::factory()->create(['role' => 'USER']);
    }

    /**
     * Create a fake image file for testing (without GD extension).
     */
    protected function createFakeImage(string $name = 'test.jpg'): UploadedFile
    {
        // Create a simple fake file that bypasses GD requirement
        return UploadedFile::fake()->create($name, 100, 'image/jpeg');
    }

    // ==================== Public Endpoints Tests ====================

    public function test_can_list_active_products(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->inactive()->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'status',
                'data' => [
                    'products' => [
                        '*' => ['id', 'title', 'type', 'price', 'in_stock'],
                    ],
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonPath('data.pagination.total', 5);
    }

    public function test_can_filter_products_by_type(): void
    {
        Product::factory()->create(['type' => 'phones', 'is_active' => true]);
        Product::factory()->create(['type' => 'laptops', 'is_active' => true]);
        Product::factory()->count(2)->create(['type' => 'phones', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?type=phones');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_can_filter_products_by_stock_status(): void
    {
        Product::factory()->count(3)->create(['in_stock' => true, 'is_active' => true]);
        Product::factory()->count(2)->outOfStock()->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products?in_stock=true');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_can_search_products_by_title(): void
    {
        Product::factory()->create(['title' => 'iPhone 15 Pro Max', 'is_active' => true]);
        Product::factory()->create(['title' => 'Samsung Galaxy S24', 'is_active' => true]);
        Product::factory()->create(['title' => 'iPhone 14', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=iPhone');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_can_paginate_products(): void
    {
        Product::factory()->count(20)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(10, 'data.products');
    }

    public function test_can_get_product_details(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'status',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'type',
                    'price',
                    'in_stock',
                    'main_image_url',
                    'images',
                    'payment_options',
                    'resale_plans',
                ],
            ])
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.title', $product->title);
    }

    public function test_cannot_get_inactive_product_details(): void
    {
        $product = Product::factory()->inactive()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(404)
            ->assertJsonPath('status', '404');
    }

    public function test_cannot_get_nonexistent_product(): void
    {
        $response = $this->getJson('/api/v1/products/99999');

        $response->assertStatus(404)
            ->assertJsonPath('status', '404');
    }

    public function test_can_get_product_types(): void
    {
        Product::factory()->create(['type' => 'phones', 'is_active' => true]);
        Product::factory()->create(['type' => 'laptops', 'is_active' => true]);
        Product::factory()->create(['type' => 'phones', 'is_active' => true]);
        Product::factory()->create(['type' => 'tires', 'is_active' => false]);

        $response = $this->getJson('/api/v1/products/types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'status',
                'data' => ['types'],
            ])
            ->assertJsonCount(2, 'data.types');
    }

    // ==================== Admin Endpoints Tests ====================

    public function test_admin_can_list_all_products_including_inactive(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->inactive()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 8);
    }

    public function test_non_admin_cannot_list_all_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/admin/products');

        $response->assertStatus(403)
            ->assertJsonPath('status', '403');
    }

    public function test_guest_cannot_list_admin_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/products');

        $response->assertStatus(401);
    }

    public function test_admin_can_filter_products_by_active_status(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->inactive()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products?is_active=false');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_admin_can_view_product_details_including_inactive(): void
    {
        $product = Product::factory()->inactive()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_non_admin_cannot_view_admin_product_details(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(403)
            ->assertJsonPath('status', '403');
    }

    public function test_admin_can_create_product(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone model',
                'type' => 'phones',
                'price' => 999.99,
                'in_stock' => true,
                'is_active' => true,
                'main_image' => $this->createFakeImage(),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'status',
                'data' => ['id', 'title', 'type', 'price'],
            ])
            ->assertJsonPath('data.title', 'iPhone 15 Pro');

        $this->assertDatabaseHas('products', [
            'title' => 'iPhone 15 Pro',
            'type' => 'phones',
            'price' => 999.99,
        ]);
    }

    public function test_admin_can_create_product_with_image(): void
    {
        $this->markTestSkipped('Skipping image test due to GD extension requirement');
    }

    public function test_admin_can_create_product_with_payment_options(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'MacBook Pro',
                'type' => 'laptops',
                'price' => 2499.99,
                'main_image' => $this->createFakeImage(),
                'payment_options' => [
                    ['type' => 'cash', 'label' => 'Cash Payment', 'is_active' => true],
                    ['type' => 'installment', 'label' => 'Installment Plan', 'is_active' => true],
                ],
            ]);

        $response->assertStatus(201);

        $product = Product::where('title', 'MacBook Pro')->first();
        $this->assertCount(2, $product->allPaymentOptions);
    }

    public function test_admin_can_create_product_with_resale_plans(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Dell XPS 15',
                'type' => 'laptops',
                'price' => 1899.99,
                'main_image' => $this->createFakeImage(),
                'resale_plans' => [
                    ['months' => 6, 'profit_percentage' => 15, 'is_active' => true],
                    ['months' => 12, 'profit_percentage' => 25, 'is_active' => true],
                ],
            ]);

        $response->assertStatus(201);

        $product = Product::where('title', 'Dell XPS 15')->first();
        $this->assertCount(2, $product->allResalePlans);
    }

    public function test_create_product_requires_title(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'type' => 'phones',
                'price' => 999.99,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_product_requires_type(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'price' => 999.99,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_product_requires_price(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'type' => 'phones',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_create_product_price_must_be_non_negative(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'type' => 'phones',
                'price' => -50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_non_admin_cannot_create_product(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'type' => 'phones',
                'price' => 999.99,
            ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_create_product(): void
    {
        $response = $this->postJson('/api/v1/admin/products', [
            'title' => 'Test Product',
            'type' => 'phones',
            'price' => 999.99,
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create([
            'title' => 'Old Title',
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => 'New Title',
                'type' => $product->type,
                'price' => 150.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.price', '150.00');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'title' => 'New Title',
            'price' => 150.00,
        ]);
    }

    public function test_admin_can_update_product_stock_status(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 50,
            'in_stock' => true,
        ]);

        // Update stock_quantity to 0 to mark as out of stock
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => $product->title,
                'type' => $product->type,
                'price' => $product->price,
                'stock_quantity' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.in_stock', false)
            ->assertJsonPath('data.stock_quantity', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }

    public function test_admin_can_update_product_active_status(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => $product->title,
                'type' => $product->type,
                'price' => $product->price,
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_non_admin_cannot_update_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => 'New Title',
                'type' => $product->type,
                'price' => $product->price,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_non_admin_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }

    public function test_cannot_delete_nonexistent_product(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/v1/admin/products/99999');

        $response->assertStatus(404);
    }

    // ==================== Edge Cases & Integration Tests ====================

    public function test_product_with_relationships_loads_correctly(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        ProductPaymentOption::create([
            'product_id' => $product->id,
            'type' => 'cash',
            'label' => 'Cash Payment',
            'is_active' => true,
        ]);

        ProductResalePlan::create([
            'product_id' => $product->id,
            'months' => 12,
            'profit_percentage' => 15.00,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'payment_options' => [
                        '*' => ['type', 'label'],
                    ],
                    'resale_plans' => [
                        '*' => ['months', 'profit_percentage'],
                    ],
                ],
            ]);
    }

    public function test_only_active_payment_options_shown_in_public_endpoint(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        ProductPaymentOption::create([
            'product_id' => $product->id,
            'type' => 'cash',
            'label' => 'Cash Payment',
            'is_active' => true,
        ]);

        ProductPaymentOption::create([
            'product_id' => $product->id,
            'type' => 'installment',
            'label' => 'Installment Plan',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.payment_options');
    }

    public function test_all_payment_options_shown_in_admin_endpoint(): void
    {
        $product = Product::factory()->create();

        ProductPaymentOption::create([
            'product_id' => $product->id,
            'type' => 'cash',
            'label' => 'Cash Payment',
            'is_active' => true,
        ]);

        ProductPaymentOption::create([
            'product_id' => $product->id,
            'type' => 'installment',
            'label' => 'Installment Plan',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.payment_options');
    }

    public function test_products_ordered_by_created_at_desc(): void
    {
        $oldProduct = Product::factory()->create([
            'title' => 'Old Product',
            'is_active' => true,
            'created_at' => now()->subDays(5),
        ]);

        $newProduct = Product::factory()->create([
            'title' => 'New Product',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);

        $products = $response->json('data.products');
        $this->assertEquals('New Product', $products[0]['title']);
        $this->assertEquals('Old Product', $products[1]['title']);
    }

    public function test_installment_payment_option_requires_months(): void
    {
        // Since payment_options no longer have months/percentage, this test is obsolete
        // Payment options now only have type, label, and is_active
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Test Product',
                'type' => 'phones',
                'price' => 999.99,
                'main_image' => $this->createFakeImage(),
                'payment_options' => [
                    ['type' => 'installment', 'is_active' => true],
                ],
            ]);

        // Should succeed now since months is no longer required
        $response->assertStatus(201);
    }

    // ==================== Stock Quantity Tests ====================

    public function test_product_created_with_stock_quantity(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Product with Stock',
                'type' => 'phones',
                'price' => 999.99,
                'stock_quantity' => 50,
                'main_image' => $this->createFakeImage(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.stock_quantity', 50)
            ->assertJsonPath('data.in_stock', true);

        $this->assertDatabaseHas('products', [
            'title' => 'Product with Stock',
            'stock_quantity' => 50,
            'in_stock' => true,
        ]);
    }

    public function test_product_with_zero_stock_marked_as_out_of_stock(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Out of Stock Product',
                'type' => 'phones',
                'price' => 999.99,
                'stock_quantity' => 0,
                'main_image' => $this->createFakeImage(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.in_stock', false);

        $this->assertDatabaseHas('products', [
            'title' => 'Out of Stock Product',
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }

    public function test_product_without_stock_quantity_defaults_to_zero(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Default Stock Product',
                'type' => 'phones',
                'price' => 999.99,
                'main_image' => $this->createFakeImage(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.in_stock', false);
    }

    public function test_stock_quantity_cannot_be_negative(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'title' => 'Negative Stock Product',
                'type' => 'phones',
                'price' => 999.99,
                'main_image' => $this->createFakeImage(),
                'stock_quantity' => -10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stock_quantity']);
    }

    public function test_admin_can_update_stock_quantity(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 50,
            'in_stock' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => $product->title,
                'type' => $product->type,
                'price' => $product->price,
                'stock_quantity' => 100,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stock_quantity', 100)
            ->assertJsonPath('data.in_stock', true);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 100,
            'in_stock' => true,
        ]);
    }

    public function test_updating_stock_to_zero_marks_product_out_of_stock(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 50,
            'in_stock' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'title' => $product->title,
                'type' => $product->type,
                'price' => $product->price,
                'stock_quantity' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.in_stock', false);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }

    public function test_product_has_stock_method(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->assertTrue($product->hasStock(5));
        $this->assertTrue($product->hasStock(10));
        $this->assertFalse($product->hasStock(11));
    }

    public function test_product_decrease_stock_method(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $result = $product->decreaseStock(3);

        $this->assertTrue($result);
        $this->assertEquals(7, $product->fresh()->stock_quantity);
        $this->assertTrue($product->fresh()->in_stock);
    }

    public function test_decrease_stock_beyond_available_fails(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $result = $product->decreaseStock(10);

        $this->assertFalse($result);
        $this->assertEquals(5, $product->fresh()->stock_quantity);
    }

    public function test_decrease_stock_to_zero_marks_out_of_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $result = $product->decreaseStock(5);

        $this->assertTrue($result);
        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertFalse($product->fresh()->in_stock);
    }

    public function test_product_increase_stock_method(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $product->increaseStock(10);

        $this->assertEquals(15, $product->fresh()->stock_quantity);
        $this->assertTrue($product->fresh()->in_stock);
    }

    public function test_increase_stock_on_out_of_stock_product(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->assertEquals(0, $product->stock_quantity);
        $this->assertFalse($product->in_stock);

        $product->increaseStock(20);

        $this->assertEquals(20, $product->fresh()->stock_quantity);
        $this->assertTrue($product->fresh()->in_stock);
    }

    public function test_product_update_stock_method(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 50]);

        $product->updateStock(100);

        $this->assertEquals(100, $product->fresh()->stock_quantity);
        $this->assertTrue($product->fresh()->in_stock);
    }

    public function test_update_stock_to_zero(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 50]);

        $product->updateStock(0);

        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertFalse($product->fresh()->in_stock);
    }

    public function test_admin_show_includes_stock_quantity(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 25]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.stock_quantity', 25);
    }
}
