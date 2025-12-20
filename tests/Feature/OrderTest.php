<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductResalePlan;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can place a sale order.
     */
    public function test_user_can_place_sale_order(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 5000]);
        $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'type' => 'sale',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'shipping_name' => 'Test User',
            'shipping_phone' => '0501234567',
            'shipping_city' => 'Riyadh',
            'shipping_address' => '123 Test Street',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total_amount', '2000.00');

        // Check wallet was charged
        $this->assertEquals(3000, $user->wallet->fresh()->balance);

        // Check stock was reduced
        $this->assertEquals(8, $product->fresh()->stock_quantity);
    }

    /**
     * Test user can place a resale order.
     */
    public function test_user_can_place_resale_order(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 10000]);
        $product = Product::factory()->create(['price' => 5000, 'stock_quantity' => 10]);
        $resalePlan = ProductResalePlan::create([
            'product_id' => $product->id,
            'months' => 6,
            'profit_percentage' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'type' => 'resale',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'resale_plan_id' => $resalePlan->id,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'resale')
            ->assertJsonPath('data.resale.expected_return', '5750.00');

        // Check wallet was charged
        $this->assertEquals(5000, $user->wallet->fresh()->balance);

        // Stock should NOT be reduced for resale orders
        $this->assertEquals(10, $product->fresh()->stock_quantity);
    }

    /**
     * Test cannot place order with insufficient balance.
     */
    public function test_cannot_place_order_with_insufficient_balance(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 100]);
        $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'type' => 'sale',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'shipping_name' => 'Test User',
            'shipping_phone' => '0501234567',
            'shipping_city' => 'Riyadh',
            'shipping_address' => '123 Test Street',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('data.error.code', 'ORDER_FAILED');
    }

    /**
     * Test cannot place sale order for out of stock product.
     */
    public function test_cannot_place_sale_order_for_out_of_stock(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 5000]);
        $product = Product::factory()->outOfStock()->create(['price' => 1000]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'type' => 'sale',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'shipping_name' => 'Test User',
            'shipping_phone' => '0501234567',
            'shipping_city' => 'Riyadh',
            'shipping_address' => '123 Test Street',
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test user can get their orders.
     */
    public function test_user_can_get_orders(): void
    {
        $user = User::factory()->create();
        Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-001',
            'type' => 'sale',
            'status' => 'pending',
            'subtotal' => 100,
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.orders');
    }

    /**
     * Test user can get order details.
     */
    public function test_user_can_get_order_details(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-002',
            'type' => 'sale',
            'status' => 'pending',
            'subtotal' => 100,
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    /**
     * Test user can cancel pending order.
     */
    public function test_user_can_cancel_pending_order(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-003',
            'type' => 'sale',
            'status' => 'pending',
            'subtotal' => 1000,
            'total_amount' => 1000,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Check refund was issued
        $this->assertEquals(1000, $user->wallet->fresh()->balance);
    }

    /**
     * Test user cannot cancel shipped order.
     */
    public function test_cannot_cancel_shipped_order(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-004',
            'type' => 'sale',
            'status' => 'shipped',
            'subtotal' => 1000,
            'total_amount' => 1000,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('data.error.code', 'CANNOT_CANCEL');
    }

    /**
     * Test unauthenticated user cannot place order.
     */
    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $response = $this->postJson('/api/v1/orders', []);

        $response->assertStatus(401);
    }
}
