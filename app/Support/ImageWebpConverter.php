<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class ImageWebpConverter
{
    public const QUALITY = 82;

    public const MAX_WIDTH = 1600;

    public const MAX_HEIGHT = 1600;

    /**
     * Convert an uploaded image to a WebP file on local disk.
     * Returns the absolute path to the temporary .webp file.
     */
    public static function convertUploaded(UploadedFile $file): string
    {
        $bytes = file_get_contents($file->getRealPath());

        if ($bytes === false) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        $webp = self::encode($bytes);

        $tmpPath = tempnam(sys_get_temp_dir(), 'webp_').'.webp';
        file_put_contents($tmpPath, $webp);

        return $tmpPath;
    }

    /**
     * Encode raw image bytes (jpeg/png/webp/gif) as WebP bytes.
     * Resizes down to MAX_WIDTH/MAX_HEIGHT while preserving aspect ratio.
     */
    public static function encode(string $bytes): string
    {
        $src = @imagecreatefromstring($bytes);

        if (! $src) {
            throw new RuntimeException('Unsupported or corrupt image.');
        }

        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);

            $ratio = min(
                self::MAX_WIDTH / $srcW,
                self::MAX_HEIGHT / $srcH,
                1.0,
            );

            if ($ratio < 1.0) {
                $dstW = (int) round($srcW * $ratio);
                $dstH = (int) round($srcH * $ratio);

                $dst = imagecreatetruecolor($dstW, $dstH);

                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);

                imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

                imagedestroy($src);
                $src = $dst;
            } else {
                imagealphablending($src, false);
                imagesavealpha($src, true);
            }

            ob_start();
            $ok = imagewebp($src, null, self::QUALITY);
            $out = ob_get_clean();

            if (! $ok || $out === false || $out === '') {
                throw new RuntimeException('WebP encoding failed.');
            }

            return $out;
        } finally {
            if (is_resource($src) || $src instanceof \GdImage) {
                imagedestroy($src);
            }
        }
    }

    /**
     * Generate a random unique filename ending in .webp,
     * matching Laravel's storePublicly naming style.
     */
    public static function randomFilename(): string
    {
        return Str::random(40).'.webp';
    }
}
