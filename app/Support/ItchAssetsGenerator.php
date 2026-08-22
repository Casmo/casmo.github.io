<?php

namespace App\Support;

/**
 * Publishes the upscaled images the itch.io page hotlinks.
 *
 * Written into the SSG output and nowhere else. public/assets is a Statamic
 * asset container, so anything written there wants a .meta yaml committed
 * beside it or the control panel generates one on first view -- twelve derived
 * files would mean twelve more files to keep in step. These images are never
 * shown on the site; they exist only for itch.io to fetch. Regenerating them
 * every deploy also means they cannot go stale the way the committed tilesheet
 * did.
 */
class ItchAssetsGenerator
{
    /** Whole-number scale. 169x29 sheet becomes 676x116. */
    public const FACTOR = 4;

    public const DIRECTORY = 'itch';

    public const SUFFIX = '-4x';

    public function __construct(
        private Upscale $upscale,
        private TriviaIcons $icons,
        private Files $files,
        private string $tilesheet,
        private string $outputPath,
    ) {}

    /**
     * The published name for a source icon. ItchPage builds URLs from this
     * rather than restating the suffix, so the two cannot drift apart into a
     * page of broken images.
     */
    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME).self::SUFFIX.'.png';
    }

    /** @return int the number of files written, the sheet included */
    public function generate(): int
    {
        $root = $this->files->directory($this->outputPath.'/'.self::DIRECTORY);

        $this->upscale->write(
            $root.'/'.self::filename($this->tilesheet),
            $this->tilesheet,
            self::FACTOR,
        );

        $icons = $this->files->directory($root.'/'.IconPack::ICONS);

        $written = 1;

        foreach ($this->icons->all() as $icon) {
            $this->upscale->write(
                $icons.'/'.self::filename($icon['path']),
                $icon['path'],
                self::FACTOR,
            );

            $written++;
        }

        return $written;
    }
}
