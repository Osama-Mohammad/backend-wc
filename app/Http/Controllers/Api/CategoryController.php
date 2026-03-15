<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * GET /api/store/categories (public)
     * GET /api/admin/categories (admin)
     */
    public function index()
    {
        $categories = Category::all();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * POST /api/admin/categories (admin)
     */ // /
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:50|unique:categories,name',
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'category' => $category,
        ], 201);
    }

    /**
     * GET /api/store/categories/{category:slug} (public)
     * GET /api/admin/categories/{category} (admin) - by ID through apiResource
     */
    public function show(Category $category)
    {
        return response()->json([
            'category' => $category,
        ]);
    }

    /**
     * GET /api/store/categories/{category:slug}/products (public)
     */
    public function products(Category $category)
    {
        // Only return active products publicly
        $products = $category->products()
            ->where('is_active', true)
            ->get();

        return response()->json([
            'category' => $category,
            'products' => $products,
        ]);
    }

    /**
     * PUT /api/admin/categories/{category} (admin) - by ID
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 2;

        while (
            Category::where('slug', $slug)
                ->where('id', '!=', $category->id)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'category' => $category->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/categories/{category} (admin) - by ID
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Deleted Category Successfully',
        ]);
    }
}
