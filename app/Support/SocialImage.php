<?php

namespace App\Support;

/**
 * Renders one 1200x630 social image: a prompt line, the entry's title as the
 * output of `cat`, and an identity line. Deliberately free of Laravel and
 * Statamic -- it takes strings and absolute paths, so the layout can be tested
 * without booting anything.
 *
 * Sizes are the points GD's TTF functions take, not pixels.
 */
class SocialImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** The character cell every position is a multiple of, as on the site. */
    private const CELL = 42;

    private const MARGIN = 72;

    private const MEASURE = self::WIDTH - self::MARGIN * 2;

    private const TITLE_BAND_TOP = self::CELL * 2.5;

    private const TITLE_BAND_HEIGHT = self::CELL * 10;

    private const LINE_HEIGHT = 1.35;

    /** Largest first: the first size the title fits at wins. */
    private const LADDER = [118, 96, 78, 64, 54, 46, 40];

    public function __construct(
        private string $titleFont,
        private string $chromeFont,
        private string $artwork,
        private string $avatar,
    ) {}

    /**
     * The largest size at which the title fits the band, and the rows it breaks
     * into. At the floor the title is truncated rather than allowed to overrun.
     *
     * @return array{size: int, lines: list<string>}
     */
    public function fit(string $title): array
    {
        foreach (self::LADDER as $size) {
            $lines = $this->wrap($title, $size);

            if (count($lines) <= $this->maxRows($size)) {
                return ['size' => $size, 'lines' => $lines];
            }
        }

        $size = self::LADDER[count(self::LADDER) - 1];
        $lines = array_slice($this->wrap($title, $size), 0, $this->maxRows($size));
        $last = array_pop($lines);

        while ($last !== '' && $this->width($this->titleFont, $size, $last.'…') > self::MEASURE) {
            $last = mb_substr($last, 0, -1);
        }

        $lines[] = $last.'…';

        return ['size' => $size, 'lines' => $lines];
    }

    /** How many rows of a given size the title band holds. */
    private function maxRows(int $size): int
    {
        return (int) floor(self::TITLE_BAND_HEIGHT / ($size * self::LINE_HEIGHT));
    }

    /**
     * Greedy word wrap to the measure. A single word too wide to ever fit is
     * broken mid-word, because the alternative is a line running off the canvas.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $size): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) as $word) {
            while ($this->width($this->titleFont, $size, $word) > self::MEASURE) {
                $head = $word;

                while (mb_strlen($head) > 1 && $this->width($this->titleFont, $size, $head) > self::MEASURE) {
                    $head = mb_substr($head, 0, -1);
                }

                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                $lines[] = $head;
                $word = mb_substr($word, mb_strlen($head));
            }

            $try = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->width($this->titleFont, $size, $try) > self::MEASURE) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** Rendered width of a string. Font paths must be absolute; GD ignores relative ones. */
    private function width(string $font, int $size, string $text): float
    {
        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            throw new \RuntimeException("Could not measure text with font [{$font}].");
        }

        return abs($box[2] - $box[0]);
    }
}
