<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Validated image uploads stored under public/uploads/<subdir>/.
 *
 * Ported from the original app so upload semantics (size limit, mime sniffing,
 * GD downscale with a raw-file fallback, old-file replacement) are preserved.
 */
class Upload
{
    public const MAX_BYTES = 3145728; // 3 MB

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const MAX_DIMENSION = 1400;

    /**
     * Validate and store an uploaded image.
     *
     * @param  UploadedFile|null  $file  The uploaded file (null when none submitted).
     * @param  string  $subdir  Folder under public/uploads (e.g. "players").
     * @param  string|null  $oldPath  Previously stored relative path to replace.
     * @return string|null The stored relative path, or null (no file / no change).
     */
    public static function image(?UploadedFile $file, string $subdir, ?string $oldPath = null): ?string
    {
        if (! preg_match('#^[a-z0-9_-]+$#', $subdir)) {
            return null;
        }

        if ($file === null || ! $file->isValid()) {
            return null; // Nothing submitted: keep the current image.
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new UploadException('Image must be 3 MB or smaller.');
        }

        return self::store($file->getRealPath(), $subdir, $oldPath);
    }

    /** Store a local file as a validated image under public/uploads/<subdir>. */
    public static function store(string $tmp, string $subdir, ?string $oldPath = null): string
    {
        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new UploadException('The uploaded file is not a valid image.');
        }

        $mime = (string) ($info['mime'] ?? '');
        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new UploadException('Only JPEG, PNG, WebP or GIF images are allowed.');
        }

        $dir = public_path('uploads/'.$subdir);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new UploadException('Could not create the upload directory.');
        }

        $name = $subdir.'_'.bin2hex(random_bytes(8)).'.'.self::MIME_EXTENSIONS[$mime];
        $dest = $dir.DIRECTORY_SEPARATOR.$name;

        // Prefer a downscaled copy (keeps profiles snappy); fall back to the raw file.
        if (! self::process($tmp, $dest, $mime)) {
            if (! @copy($tmp, $dest)) {
                throw new UploadException('Could not store the uploaded image.');
            }
        }

        self::delete($oldPath);

        return $subdir.'/'.$name;
    }

    /** Delete a previously stored upload (path-traversal safe). */
    public static function delete(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }

        $relative = str_replace('\\', '/', $relative);
        if (str_contains($relative, '..')) {
            return;
        }

        $full = public_path('uploads/'.ltrim($relative, '/'));
        if (is_file($full)) {
            @unlink($full);
        }
    }

    /** Resize via GD when available; returns false to signal "use the original file". */
    private static function process(string $src, string $dest, string $mime): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $data = @file_get_contents($src);
        if ($data === false) {
            return false;
        }

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = self::MAX_DIMENSION;

        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($mime !== 'image/jpeg') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $dest, 85),
            'image/png' => imagepng($image, $dest, 6),
            'image/webp' => imagewebp($image, $dest, 85),
            default => imagegif($image, $dest),
        };
        imagedestroy($image);

        return $saved === true && is_file($dest);
    }
}
