<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    /** @use HasFactory<\Database\Factories\ProductImagesFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'image_path', 'is_primary', 'sort_order'];

    protected $appends = ['image_url'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute()
    {
        if (! $this->image_path) {
            return null;
        }

        // ----------------new-----------------
        $disk = config('filesystems.product_upload_disk');

        return Storage::disk($disk)->url($this->image_path);
        // ----------------
        // return rtrim(env('SUPABASE_PUBLIC_BASE_URL'), '/').'/'.ltrim($this->image_path, '/');
    }
}
