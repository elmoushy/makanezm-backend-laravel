<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * A User with role 'USER' IS a Customer - they are the same entity.
 * Customer information (mobile, city, national_id, bank details) is stored directly in the users table.
 * A User with role 'ADMIN' is an administrator.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'city',
        'national_id',
        'national_id_type',
        'bank_iban',
        'bank_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /**
     * Check if user is a customer (regular user).
     * A User with role 'USER' IS a Customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'USER';
    }

    /**
     * Alias for isCustomer() for backward compatibility.
     */
    public function isUser(): bool
    {
        return $this->isCustomer();
    }

    /**
     * Check if user has completed their profile (has customer info).
     */
    public function hasCompleteProfile(): bool
    {
        return $this->mobiles()->exists() &&
               ! empty($this->city) &&
               ! empty($this->national_id);
    }

    /**
     * Check if user has bank information for withdrawals.
     */
    public function hasBankInfo(): bool
    {
        return ! empty($this->bank_iban) && ! empty($this->bank_name);
    }

    /**
     * Get the mobile numbers for the user.
     */
    public function mobiles()
    {
        return $this->hasMany(UserMobile::class);
    }

    /**
     * Get the primary mobile number for the user.
     */
    public function primaryMobile()
    {
        return $this->hasOne(UserMobile::class)->where('is_primary', true);
    }

    /**
     * Get the wallet associated with the user.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get or create wallet for the user.
     */
    public function getOrCreateWallet(): Wallet
    {
        return $this->wallet ?? $this->wallet()->create(['balance' => 0]);
    }

    /**
     * Get the cart items for the user.
     */
    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the product favorites for the user.
     */
    public function productFavorites()
    {
        return $this->hasMany(ProductFavorite::class);
    }

    /**
     * Get the favorite products for the user.
     */
    public function favoriteProducts()
    {
        return $this->belongsToMany(Product::class, 'product_favorites')->withTimestamps();
    }

    /**
     * Check if user has favorited a product.
     */
    public function hasFavoritedProduct(Product $product): bool
    {
        return $this->productFavorites()->where('product_id', $product->id)->exists();
    }
}
