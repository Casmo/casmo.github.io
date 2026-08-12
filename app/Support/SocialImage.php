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

    private const PROMPT_SIZE = 22;

    private const IDENTITY_SIZE = 20;

    private const AVATAR_SIZE = 56;

    private const BACKGROUND = '#1f2329';

    private const ACCENT = '#84cc16';

    private const TITLE = '#e4e4e7';

    private const MUTED = '#6b7280';

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

            $overrunsMeasure = false;

            foreach ($lines as $line) {
                if ($this->width($this->titleFont, $size, $line) > self::MEASURE) {
                    $overrunsMeasure = true;
                    break;
                }
            }

            if (! $overrunsMeasure && count($lines) <= $this->maxRows($size)) {
                return ['size' => $size, 'lines' => $lines];
            }
        }

        $size = self::LADDER[count(self::LADDER) - 1];
        $full = $this->wrap($title, $size, breakWords: true);
        $rowsDropped = count($full) > $this->maxRows($size);
        $lines = array_slice($full, 0, $this->maxRows($size));
        $last = array_pop($lines);

        if ($rowsDropped) {
            while ($last !== '' && $this->width($this->titleFont, $size, $last.'…') > self::MEASURE) {
                $last = mb_substr($last, 0, -1);
            }

            $last .= '…';
        }

        $lines[] = $last;

        return ['size' => $size, 'lines' => $lines];
    }

    /** How many rows of a given size the title band holds. */
    private function maxRows(int $size): int
    {
        return (int) floor(self::TITLE_BAND_HEIGHT / ($size * self::LINE_HEIGHT));
    }

    /**
     * Greedy word wrap to the measure. A single word too wide to ever fit is
     * only broken mid-word when $breakWords is true -- the 40pt floor, where
     * there is no lower rung to drop to. At every other rung, a wrapped line
     * still wider than the measure disqualifies that rung instead.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $size, bool $breakWords = false): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) as $word) {
            while ($breakWords && $this->width($this->titleFont, $size, $word) > self::MEASURE) {
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

    /**
     * The prompt line. It never wraps and never shrinks -- a top line that
     * changes size from post to post reads as an accident -- so an overrun
     * line is elided in up to three stages instead, each run only when the
     * previous one still leaves the line too wide:
     *
     * 1. Shorten the filename to `head….txt`. Enough for every path this
     *    site produces today.
     * 2. Drop the leading path segments, keeping the last -- `~/a/b/c`
     *    becomes `~/…/c` -- then shorten the filename again against that
     *    shorter prefix.
     * 3. Truncate the whole line to the measure with a trailing `…`. This is
     *    what guarantees the line can never cross the margin, whatever the
     *    host or path.
     */
    public function prompt(string $host, string $path, string $file): string
    {
        $prefix = $host.':'.$path.'$ cat ';
        $line = $this->elideFilename($prefix, $file);

        if ($this->fits($line)) {
            return $line;
        }

        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));

        // Fewer than 3 segments means first and last are adjacent or the same --
        // collapsing would insert a `…/` where nothing was dropped, and could
        // widen the line instead of narrowing it.
        if (count($segments) >= 3) {
            $collapsedPrefix = $host.':'.$segments[0].'/…/'.end($segments).'$ cat ';
            $line = $this->elideFilename($collapsedPrefix, $file);

            if ($this->fits($line)) {
                return $line;
            }
        }

        return $this->truncate($line);
    }

    /** Shortens the filename to `head….txt` until the line fits, or the head is spent. */
    private function elideFilename(string $prefix, string $file): string
    {
        $line = $prefix.$file;
        $base = preg_replace('/\.txt$/', '', $file);

        while (! $this->fits($line) && mb_strlen($base) > 1) {
            $base = mb_substr($base, 0, -1);
            $line = $prefix.$base.'….txt';
        }

        return $line;
    }

    /** Last resort: chop the whole line down to the measure with a trailing ellipsis. */
    private function truncate(string $line): string
    {
        while (mb_strlen($line) > 1 && ! $this->fits($line.'…')) {
            $line = mb_substr($line, 0, -1);
        }

        return $line.'…';
    }

    private function fits(string $line): bool
    {
        return $this->width($this->chromeFont, self::PROMPT_SIZE, $line) <= self::MEASURE;
    }

    public function write(string $destination, string $title, string $prompt, string $domain, ?string $date): void
    {
        $this->validateAssets();

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($image, true);
        imagefill($image, 0, 0, $this->colour($image, self::BACKGROUND));

        $this->drawBackground($image);
        $this->drawPrompt($image, $prompt);
        $this->drawTitle($image, $title);
        $this->drawIdentity($image, $domain, $date);

        if (! @imagepng($image, $destination)) {
            throw new \RuntimeException("Could not write image to [{$destination}].");
        }
    }

    /**
     * Fonts and images are all loaded lazily by the draw methods, so a broken
     * font would otherwise only surface if its glyphs happened to be measured
     * first -- which a dateless entry (every `pages` and `games` entry) never
     * does for the chrome font. Check all four up front instead.
     */
    private function validateAssets(): void
    {
        foreach ([$this->titleFont, $this->chromeFont] as $font) {
            if (! is_file($font) || ! is_readable($font)) {
                throw new \RuntimeException("Could not read font [{$font}].");
            }

            if (@imagettfbbox(self::PROMPT_SIZE, 0, $font, 'Ag') === false) {
                throw new \RuntimeException("Could not use font [{$font}].");
            }
        }

        foreach ([$this->artwork, $this->avatar] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new \RuntimeException("Could not read image [{$path}].");
            }
        }
    }

    /** The constellation artwork, veiled so it never competes with the title. */
    private function drawBackground(\GdImage $image): void
    {
        $artwork = $this->load($this->artwork);

        imagecopyresampled(
            $image, $artwork,
            0, 0, 0, 0,
            self::WIDTH, self::HEIGHT,
            imagesx($artwork), imagesy($artwork),
        );

        // Alpha 70 of 127 is a 45% veil, leaving the lines at roughly 55%.
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $this->colour($image, self::BACKGROUND, 70));
    }

    /** The site's chrome glows; a flat renderer approximates it with a one-pixel halo. */
    private function drawPrompt(\GdImage $image, string $prompt): void
    {
        $baseline = self::CELL * 2;
        $halo = $this->colour($image, self::ACCENT, 105);

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
            imagettftext($image, self::PROMPT_SIZE, 0, self::MARGIN + $dx, $baseline + $dy, $halo, $this->chromeFont, $prompt);
        }

        imagettftext($image, self::PROMPT_SIZE, 0, self::MARGIN, $baseline, $this->colour($image, self::ACCENT), $this->chromeFont, $prompt);
    }

    private function drawTitle(\GdImage $image, string $title): void
    {
        ['size' => $size, 'lines' => $lines] = $this->fit($title);

        $lineHeight = $size * self::LINE_HEIGHT;
        $capHeight = $size * 0.98;
        $block = count($lines) * $lineHeight;

        // Centred in the band: a one-word title should not leave a hole.
        $baseline = self::TITLE_BAND_TOP + (self::TITLE_BAND_HEIGHT - $block) / 2 + $capHeight;
        $colour = $this->colour($image, self::TITLE);
        $end = self::MARGIN;

        foreach ($lines as $index => $line) {
            imagettftext($image, $size, 0, self::MARGIN, (int) $baseline, $colour, $this->titleFont, $line);

            if ($index === count($lines) - 1) {
                $end = self::MARGIN + $this->width($this->titleFont, $size, $line);
                break;
            }

            $baseline += $lineHeight;
        }

        $advance = $this->width($this->titleFont, $size, 'M');

        if ($this->cursorFits($size, end($lines))) {
            imagefilledrectangle(
                $image,
                (int) ($end + $advance * 0.5),
                (int) ($baseline - $capHeight * 0.78),
                (int) ($end + $advance * 1.4),
                (int) $baseline,
                $this->colour($image, self::ACCENT),
            );
        }
    }

    /**
     * The cursor is decoration: it never influenced the wrap or the chosen size,
     * so when there is no room for it after the last row it is dropped rather
     * than allowed to cross the margin.
     */
    public function cursorFits(int $size, string $lastLine): bool
    {
        $end = self::MARGIN + $this->width($this->titleFont, $size, $lastLine);
        $advance = $this->width($this->titleFont, $size, 'M');

        return $end + $advance * 1.4 <= self::WIDTH - self::MARGIN;
    }

    private function drawIdentity(\GdImage $image, string $domain, ?string $date): void
    {
        $baseline = self::CELL * 14;
        $colour = $this->colour($image, self::MUTED);

        $avatar = $this->load($this->avatar);
        $scaled = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled(
            $scaled, $avatar,
            0, 0, 0, 0,
            self::AVATAR_SIZE, self::AVATAR_SIZE,
            imagesx($avatar), imagesy($avatar),
        );

        // Optically centred on the text rather than aligned to its baseline.
        $top = (int) ($baseline - self::IDENTITY_SIZE * 0.72 - (self::AVATAR_SIZE - self::IDENTITY_SIZE) / 2 - 6);
        imagealphablending($image, true);
        imagecopy($image, $scaled, self::MARGIN, $top, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE);

        imagettftext(
            $image, self::IDENTITY_SIZE, 0,
            self::MARGIN + self::AVATAR_SIZE + 20, $baseline,
            $colour, $this->chromeFont, $domain,
        );

        if ($date === null || $date === '') {
            return;
        }

        // Flush right: both ends anchored to the margins is what makes it read as aligned.
        $x = self::WIDTH - self::MARGIN - $this->width($this->chromeFont, self::IDENTITY_SIZE, $date);
        imagettftext($image, self::IDENTITY_SIZE, 0, (int) $x, $baseline, $colour, $this->chromeFont, $date);
    }

    /** A missing asset fails the build rather than shipping a blank card. */
    private function load(string $path): \GdImage
    {
        $image = is_file($path) ? @imagecreatefrompng($path) : false;

        if ($image === false) {
            throw new \RuntimeException("Could not read image [{$path}].");
        }

        return $image;
    }

    private function colour(\GdImage $image, string $hex, int $alpha = 0): int
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
    }
}
