<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/store/products
     * GET /api/admin/products
     */
    public function index(Request $request)
    {
        $isAdmin = Str::startsWith($request->path(), 'api/admin');

        $perPage = (int) $request->get('per_page', 30);
        $perPage = $perPage > 0 ? min($perPage, 100) : 30;

        $query = Product::with($this->productRelations());

        if (! $isAdmin) {
            $query->where('is_active', true);
        }

        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'products' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/products
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'required|string|max:500',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,jfif|max:2048',
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'price' => $validated['price'],
            'slug' => $slug,
            'is_active' => true,
        ]);

        $disk = $this->uploadDisk();

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->storePublicly('products', $disk);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        $product->load($this->productRelations());

        return response()->json([
            'product' => $product,
        ], 201);
    }

    /**
     * GET /api/store/products/{product:slug}
     * GET /api/admin/products/{product}
     */
    public function show(Request $request, Product $product)
    {
        $isAdmin = Str::startsWith($request->path(), 'api/admin');

        if (! $isAdmin && ! $product->is_active) {
            abort(404);
        }

        $product->load($this->productRelations());

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * PUT /api/admin/products/{product}
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'required|string|max:500',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',

            'existing_image_ids' => 'nullable|array',
            'existing_image_ids.*' => 'integer|exists:product_images,id',

            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,jfif|max:2048',
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 2;

        while (
            Product::where('slug', $slug)
                ->where('id', '!=', $product->id)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'price' => $validated['price'],
            'is_active' => $validated['is_active'],
            'slug' => $slug,
        ]);

        $disk = $this->uploadDisk();

        $keepIds = collect($validated['existing_image_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        $product->images()
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(function ($image) use ($disk) {
                Storage::disk($disk)->delete($image->image_path);
                $image->delete();
            });

        $currentMaxSort = $product->images()->max('sort_order');
        $nextSort = is_null($currentMaxSort) ? 0 : ((int) $currentMaxSort + 1);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                $path = $imageFile->storePublicly('products', $disk);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => false,
                    'sort_order' => $nextSort,
                ]);

                $nextSort++;
            }
        }

        $remainingImages = $product->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($remainingImages->isNotEmpty()) {
            $firstId = $remainingImages->first()->id;

            $product->images()->update([
                'is_primary' => false,
            ]);

            $product->images()->where('id', $firstId)->update([
                'is_primary' => true,
            ]);
        }

        return response()->json([
            'product' => $product->fresh()->load($this->productRelations()),
        ]);
    }

    /**
     * DELETE /api/admin/products/{product}
     */
    public function destroy(Product $product)
    {
        $disk = $this->uploadDisk();

        $product->images()->get()->each(function ($image) use ($disk) {
            Storage::disk($disk)->delete($image->image_path);
            $image->delete();
        });

        $product->delete();

        return response()->json([
            'message' => 'Product Deleted Successfully',
        ]);
    }

    private function uploadDisk(): string
    {
        return config('filesystems.product_upload_disk', 'public');
    }

    private function productRelations(): array
    {
        return [
            'category',
            'images' => function ($q) {
                $q->orderBy('sort_order')->orderBy('id');
            },
            'primaryImage',
        ];
    }
}
