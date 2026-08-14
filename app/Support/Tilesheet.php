<?php

namespace App\Support;

/**
 * Lays a set of small PNGs out on one grid and writes the sheet.
 *
 * The cell is the largest source in each axis, so every tile is addressable
 * by index alone. Nothing is resampled: the sources are pixel art, and they
 * are copied at integer offsets with their alpha untouched.
 *
 * Deliberately free of Laravel and Statamic -- it takes paths, so the grid
 * can be tested without booting anything.
 */
final class Tilesheet
{
    /** Tiles per row before wrapping to the next one. */
    public const COLUMNS = 10;

    /**
     * Transparent pixels between cells, and only between them: no border
     * around the outside. Same arithmetic as public/assets/tilemap.png,
     * whose 339px is 20 tiles of 16px plus 19 gutters.
     */
    public const GUTTER = 1;

    /**
     * @param  list<string>  $sources  absolute paths to the source PNGs, in tile order
     * @return array{width: int, height: int}|null null when there is nothing to draw
     */
    public function write(string $destination, array $sources): ?array
    {
        if ($sources === []) {
            return null;
        }

        $tiles = array_map(fn (string $source) => $this->read($source), $sources);

        $cellWidth = max(array_map(fn ($tile) => imagesx($tile), $tiles));
        $cellHeight = max(array_map(fn ($tile) => imagesy($tile), $tiles));

        $columns = min(count($tiles), self::COLUMNS);
        $rows = (int) ceil(count($tiles) / self::COLUMNS);

        $width = $columns * ($cellWidth + self::GUTTER) - self::GUTTER;
        $height = $rows * ($cellHeight + self::GUTTER) - self::GUTTER;

        $sheet = imagecreatetruecolor($width, $height);
        imagealphablending($sheet, false);
        imagesavealpha($sheet, true);
        imagefill($sheet, 0, 0, imagecolorallocatealpha($sheet, 0, 0, 0, 127));

        foreach ($tiles as $index => $tile) {
            $tileWidth = imagesx($tile);
            $tileHeight = imagesy($tile);

            $left = ($index % self::COLUMNS) * ($cellWidth + self::GUTTER);
            $top = intdiv($index, self::COLUMNS) * ($cellHeight + self::GUTTER);

            // An odd leftover pixel goes to the right of the tile and above
            // it, which reads as biased left and down.
            imagecopy(
                $sheet,
                $tile,
                $left + intdiv($cellWidth - $tileWidth, 2),
                $top + (int) ceil(($cellHeight - $tileHeight) / 2),
                0,
                0,
                $tileWidth,
                $tileHeight,
            );
        }

        // Suppressed so a failure surfaces as the exception below rather than
        // as a warning from somewhere inside GD.
        $ok = @imagepng($sheet, $destination);

        if (! $ok) {
            throw new \RuntimeException("Could not write the tilesheet to [{$destination}].");
        }

        return ['width' => $width, 'height' => $height];
    }

    /** @return \GdImage a source with its alpha preserved as authored */
    private function read(string $source): \GdImage
    {
        $image = @imagecreatefrompng($source);

        if (! $image) {
            throw new \RuntimeException("Could not read the icon at [{$source}].");
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }
}
