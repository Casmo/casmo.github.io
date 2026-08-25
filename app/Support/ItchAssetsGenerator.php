<?php

namespace App\Support;

/**
 * Publishes the upscaled tilesheet the itch.io page hotlinks.
 *
 * Only the sheet. The page links the individual icons at their authored size,
 * straight out of assets/trivia, so there is nothing per-icon to derive. The
 * sheet is the exception because at 169x29 it is the page's hero image and
 * would otherwise be a smudge.
 *
 * Written into the SSG output and nowhere else. public/assets is a Statamic
 * asset container, so anything written there wants a .meta yaml committed
 * beside it or the control panel generates one on first view. This image is
 * never shown on the site; it exists only for itch.io to fetch. Regenerating
 * it every deploy also means it cannot go stale the way the committed
 * tilesheet did.
 */
class ItchAssetsGenerator
{
    /** Whole-number scale. 169x29 sheet becomes 676x116. */
    public const FACTOR = 4;

    public const DIRECTORY = 'itch';

    public const SUFFIX = '-4x';

    public function __construct(
        private Upscale $upscale,
        private Files $files,
        private string $tilesheet,
        private string $outputPath,
    ) {}

    /**
     * The published name for the sheet. ItchPage builds its URL from this
     * rather than restating the suffix, so the two cannot drift apart into a
     * broken hero image.
     */
    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME).self::SUFFIX.'.png';
    }

    /** @return array{width: int, height: int} the published sheet's size */
    public function generate(): array
    {
        $root = $this->files->directory($this->outputPath.'/'.self::DIRECTORY);

        return $this->upscale->write(
            $root.'/'.self::filename($this->tilesheet),
            $this->tilesheet,
            self::FACTOR,
        );
    }
}
