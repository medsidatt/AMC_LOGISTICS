<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product Catalog API for the shared ProductSelector — searchable, active-only,
 * ordered; plus idempotent inline creation (resolveByName never duplicates).
 * The only place product options come from; no module hardcodes product names.
 */
class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('search', ''));

        $products = Product::query()
            ->active()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('reference', 'like', "%{$q}%")))
            ->orderByRaw('display_order IS NULL, display_order')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'reference', 'category', 'unit']);

        return response()->json(['products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:32',
            'unit' => 'nullable|string|max:16',
        ]);

        $product = Product::resolveByName($data['name'], [
            'reference' => $data['reference'] ?? null,
            'category' => $data['category'] ?? null,
            'unit' => $data['unit'] ?? null,
        ]);

        return response()->json(['product' => $product->only(['id', 'name', 'reference', 'category', 'unit'])]);
    }
}
