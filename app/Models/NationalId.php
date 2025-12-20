<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NationalId Model
 *
 * Represents a type of national identification document.
 * Admin can manage supported ID types (e.g., Saudi National ID, Iqama, Passport).
 */
class NationalId extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'country_code',
        'format_regex',
        'format_example',
        'length',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'length' => 'integer',
    ];

    /**
     * Get the users that have this national ID type.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'national_id_type_id');
    }

    /**
     * Scope a query to only include active national ID types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Validate a national ID number against this type's format.
     */
    public function validateNumber(string $number): bool
    {
        // Check length if specified
        if ($this->length && strlen($number) !== $this->length) {
            return false;
        }

        // Check regex if specified
        if ($this->format_regex && ! preg_match($this->format_regex, $number)) {
            return false;
        }

        return true;
    }
}
