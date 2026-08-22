<?php

namespace App\Support;

/**
 * Scales an image up by a whole number, one source pixel to one solid block.
 *
 * Exists because the icons are 8-16px and have to be shown at a readable size
 * somewhere that cannot be trusted to keep a stylesheet -- itch.io sanitises
 * description HTML, so `image-rendering: pixelated` may be dropped. Scaling
 * ahead of publication means the markup needs no CSS at all.
 *
 * Deliberately free of Laravel and Statamic: it takes paths, so it can be
 * tested without booting anything.
 */
final class Upscale
{
    /**
     * @param  int  $factor  whole-number scale, at least 1
     * @return array{width: int, height: int}
     */
    public function write(string $destination, string $source, int $factor): array
    {
        if ($factor < 1) {
            throw new \InvalidArgumentException(
                "The upscale factor must be at least 1, got [{$factor}]."
            );
        }

        $image = $this->read($source);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $width = $sourceWidth * $factor;
        $height = $sourceHeight * $factor;

        $scaled = imagecreatetruecolor($width, $height);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));

        // imagecopyresized samples nearest neighbour, so at a whole-number
        // factor every source pixel becomes an exact factor x factor block
        // with its alpha verbatim. imagecopyresampled would interpolate, which
        // is precisely the mush this class exists to prevent.
        imagecopyresized(
            $scaled,
            $image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        // Suppressed so a failure surfaces as the exception below rather than
        // as a warning from somewhere inside GD.
        if (! @imagepng($scaled, $destination)) {
            throw new \RuntimeException("Could not write the upscale to [{$destination}].");
        }

        return ['width' => $width, 'height' => $height];
    }

    /** @return \GdImage a source with its alpha preserved as authored */
    private function read(string $source): \GdImage
    {
        $contents = @file_get_contents($source);

        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image) {
            throw new \RuntimeException("Could not read the image at [{$source}].");
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }
}
