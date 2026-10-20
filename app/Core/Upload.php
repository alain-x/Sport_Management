<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validated image uploads stored under public/uploads/<subdir>/.
 *
 * Returns a [path, error] tuple so controllers can push errors into the
 * regular validation flow: [null, null] means "no file was submitted".
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
     * @param array<string, mixed>|null $file    Entry from $_FILES.
     * @param string                    $subdir  Folder under public/uploads (e.g. "players").
     * @param string|null               $oldPath Previously stored relative path to replace.
     *
     * @return array{0:?string,1:?string} [stored relative path, error message]
     */
    public static function image(?array $file, string $subdir, ?string $oldPath = null): array
    {
        if (!preg_match('#^[a-z0-9_-]+$#', $subdir)) {
            return [null, 'Invalid upload destination.'];
        }

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($file === [] || $error === UPLOAD_ERR_NO_FILE) {
            return [null, null]; // Nothing submitted: keep the current image.
        }
        if ($error !== UPLOAD_ERR_OK) {
            return [null, 'The upload failed (error code ' . (int) $error . ').'];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return [null, 'Image must be 3 MB or smaller.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $info = @getimagesize($tmp);
        if ($info === false) {
            return [null, 'The uploaded file is not a valid image.'];
        }

        $mime = (string) ($info['mime'] ?? '');
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            return [null, 'Only JPEG, PNG, WebP or GIF images are allowed.'];
        }

        $dir = BASE_PATH . '/public/uploads/' . $subdir;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return [null, 'Could not create the upload directory.'];
        }

        $name = $subdir . '_' . bin2hex(random_bytes(8)) . '.' . self::MIME_EXTENSIONS[$mime];
        $dest = $dir . '/' . $name;

        // Prefer a downscaled copy (keeps profiles snappy); fall back to the raw file.
        if (!self::process($tmp, $dest, $mime)) {
            if (!move_uploaded_file($tmp, $dest)) {
                return [null, 'Could not store the uploaded image.'];
            }
        }

        self::delete($oldPath);

        return [$subdir . '/' . $name, null];
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

        $full = BASE_PATH . '/public/uploads/' . ltrim($relative, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    /** Resize via GD when available; returns false to signal "use the original file". */
    private static function process(string $src, string $dest, string $mime): bool
    {
        if (!extension_loaded('gd')) {
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
