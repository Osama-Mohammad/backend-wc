<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\ImageWebpConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConvertProductImagesToWebp extends Command
{
    protected $signature = 'products:convert-to-webp
        {--dry-run : Preview changes without writing}
        {--keep-original : Do not delete the original file after conversion}';

    protected $description = 'Re-encode all existing product images as compressed WebP files.';

    public function handle(): int
    {
        $disk = config('filesystems.product_upload_disk', 'public');
        $dryRun = (bool) $this->option('dry-run');
        $keepOriginal = (bool) $this->option('keep-original');

        $images = ProductImage::query()->orderBy('id')->get();

        if ($images->isEmpty()) {
            $this->info('No product images found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Processing %d image(s) on disk "%s"%s.',
            $images->count(),
            $disk,
            $dryRun ? ' (dry run)' : '',
        ));

        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        foreach ($images as $image) {
            $oldPath = $image->image_path;

            if (! $oldPath) {
                $skipped++;

                continue;
            }

            if (str_ends_with(strtolower($oldPath), '.webp')) {
                $skipped++;
                $this->line("  skip (already webp): {$oldPath}");

                continue;
            }

            try {
                if (! Storage::disk($disk)->exists($oldPath)) {
                    $this->warn("  missing on disk: {$oldPath}");
                    $skipped++;

                    continue;
                }

                $original = Storage::disk($disk)->get($oldPath);
                $originalSize = strlen($original);

                $webpBytes = ImageWebpConverter::encode($original);
                $newSize = strlen($webpBytes);

                $bytesBefore += $originalSize;
                $bytesAfter += $newSize;

                $newPath = $this->replaceExtension($oldPath, 'webp');

                if ($newPath === $oldPath) {
                    $newPath = preg_replace('/\.[^.]+$/', '', $oldPath).'.webp';
                }

                $this->line(sprintf(
                    '  %s  %s -> %s  (%s -> %s)',
                    $dryRun ? '[dry]' : '[ok]',
                    $oldPath,
                    $newPath,
                    $this->humanBytes($originalSize),
                    $this->humanBytes($newSize),
                ));

                if ($dryRun) {
                    $converted++;

                    continue;
                }

                Storage::disk($disk)->put($newPath, $webpBytes, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);

                $image->update(['image_path' => $newPath]);

                if (! $keepOriginal && $newPath !== $oldPath) {
                    Storage::disk($disk)->delete($oldPath);
                }

                $converted++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("  failed: {$oldPath} — ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. converted=%d  skipped=%d  failed=%d',
            $converted,
            $skipped,
            $failed,
        ));

        if ($bytesBefore > 0) {
            $saved = $bytesBefore - $bytesAfter;
            $pct = $bytesBefore > 0 ? round($saved * 100 / $bytesBefore, 1) : 0;
            $this->info(sprintf(
                'Total: %s -> %s  (saved %s, %s%%)',
                $this->humanBytes($bytesBefore),
                $this->humanBytes($bytesAfter),
                $this->humanBytes(max(0, $saved)),
                $pct,
            ));
        }

        return self::SUCCESS;
    }

    private function replaceExtension(string $path, string $newExt): string
    {
        if (! str_contains(basename($path), '.')) {
            return $path.'.'.$newExt;
        }

        return preg_replace('/\.[^.\/\\\\]+$/', '.'.$newExt, $path);
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
