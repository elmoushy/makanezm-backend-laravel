<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can get their cart items.
     */
    public function test_user_can_get_cart_items(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/cart');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'status',
                'data' => [
                    'cart_items' => [
                        '*' => [
                            'id',
                            'product_id',
                            'quantity',
                            'title',
                            'description',
                            'price',
                            'total_price',
                        ],
                    ],
                    'summary' => [
                        'total_items',
                        'total_price',
                        'items_count',
                    ],
                ],
            ]);
    }

    /**
     * Test user can add product to cart.
     */
    public function test_user_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.quantity', 2);

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    /**
     * Test adding product that already exists in cart increases quantity.
     */
    public function test_adding_existing_product_increases_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 20]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 5);

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test user can update cart item quantity.
     */
    public function test_user_can_update_cart_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/cart/{$product->id}", [
            'quantity' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 5);
    }

    /**
     * Test user can increase cart item quantity.
     */
    public function test_user_can_increase_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/cart/{$product->id}/increase");

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 3);
    }

    /**
     * Test user can decrease cart item quantity.
     */
    public function test_user_can_decrease_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/cart/{$product->id}/decrease");

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 2);
    }

    /**
     * Test decreasing quantity to zero removes item from cart.
     */
    public function test_decrease_to_zero_removes_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/cart/{$product->id}/decrease");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Product removed from cart.');

        $this->assertDatabaseMissing('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Test user can remove product from cart.
     */
    public function test_user_can_remove_product_from_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/cart/{$product->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Test user can clear entire cart.
     */
    public function test_user_can_clear_cart(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(3)->create();

        foreach ($products as $product) {
            Cart::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        $response = $this->actingAs($user)->deleteJson('/api/v1/cart');

        $response->assertStatus(200)
            ->assertJsonPath('data.deleted_count', 3);

        $this->assertDatabaseCount('carts', 0);
    }

    /**
     * Test cannot add out of stock product to cart.
     */
    public function test_cannot_add_out_of_stock_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->outOfStock()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('data.error.code', 'OUT_OF_STOCK');
    }

    /**
     * Test cannot exceed stock quantity.
     */
    public function test_cannot_exceed_stock_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $response = $this->actingAs($user)->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('data.error.code', 'OUT_OF_STOCK');
    }

    /**
     * Test unauthenticated user cannot access cart.
     */
    public function test_unauthenticated_user_cannot_access_cart(): void
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertStatus(401);
    }
}
