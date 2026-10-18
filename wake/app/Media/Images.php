<?php

namespace Wake\Media;

use GdImage;
use RuntimeException;
use Wake\Support\Files;

/**
 * Resizes and converts images with PHP's GD extension, which every host has.
 */
class Images
{
    public const FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /**
     * @return list<int>
     */
    public static function widths(): array
    {
        $widths = array_map('intval', (array) config('wake.images.widths', [320, 640, 960, 1280, 1920]));
        sort($widths);

        return $widths;
    }

    /**
     * The smallest configured width that is at least $width.
     */
    public static function nearestWidth(int $width): int
    {
        foreach (static::widths() as $candidate) {
            if ($candidate >= $width) {
                return $candidate;
            }
        }

        return max(static::widths());
    }

    public static function supports(string $format): bool
    {
        return match ($format) {
            'jpg', 'jpeg' => function_exists('imagejpeg'),
            'png' => function_exists('imagepng'),
            'gif' => function_exists('imagegif'),
            'webp' => function_exists('imagewebp'),
            'avif' => function_exists('imageavif'),
            default => false,
        };
    }

    /**
     * Write a version of $source to $target, at most $width pixels wide,
     * in the given format. Never enlarges an image.
     */
    public static function resize(string $source, string $target, ?int $width, string $format): void
    {
        $image = static::open($source);

        if ($width !== null && imagesx($image) > $width) {
            $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
            $resized = imagecreatetruecolor($width, $height);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
            $image = $resized;
        }

        $quality = (int) config('wake.images.quality', 82);

        ob_start();
        $written = match ($format) {
            'jpg', 'jpeg' => imagejpeg($image, null, $quality),
            'png' => imagepng($image, null, 6),
            'gif' => imagegif($image),
            'webp' => imagewebp($image, null, $quality),
            'avif' => imageavif($image, null, $quality),
        };
        $data = (string) ob_get_clean();

        if (! $written) {
            throw new RuntimeException("Cannot encode {$source} as {$format}.");
        }

        Files::write($target, $data);
    }

    protected static function open(string $source): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Cannot read the image {$source}.");
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        // Photos from phones are often stored sideways with a rotation flag.
        if (function_exists('exif_read_data') && in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
            $orientation = @exif_read_data($source)['Orientation'] ?? 1;
            $image = match ((int) $orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            } ?: $image;
        }

        return $image;
    }
}
