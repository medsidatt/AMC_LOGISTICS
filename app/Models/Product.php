<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Product Catalog — single source of truth for workshop / maintenance products
 * & consumables. `name_key` (lower-trimmed) enforces case-insensitive uniqueness
 * so the same product is never created twice.
 */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->name = trim((string) $product->name);
            $product->name_key = mb_strtolower($product->name);
        });
    }

    /** Resolve (or create) a product by free-text name — case-insensitive. */
    public static function resolveByName(string $name, array $attributes = []): self
    {
        $name = trim($name);
        return static::firstOrCreate(
            ['name_key' => mb_strtolower($name)],
            array_merge(['name' => $name, 'is_active' => true], $attributes),
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
