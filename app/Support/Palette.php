<?php

namespace App\Support;

/**
 * Renders inline {{ palette ... }} snippets found in content.
 *
 * This is deliberately NOT the Antlers parser: it matches only the
 * `palette` keyword, so every other {{ ... }} sequence in the content
 * (e.g. Blade or GitHub Actions examples inside code blocks) is left
 * completely untouched.
 */
class Palette
{
    /**
     * Matches `{{ palette ... }}`, optionally wrapped in a <p> tag that
     * the markdown parser adds when the snippet sits on its own line.
     */
    private const PATTERN = '/(?:<p>\s*)?\{\{\s*palette\b(?<attrs>.*?)\}\}(?:\s*<\/p>)?/is';

    public static function render(string $html): string
    {
        return preg_replace_callback(self::PATTERN, function (array $match) {
            $attrs = self::parseAttributes($match['attrs']);

            return self::markup(
                src: $attrs['src'] ?? null,
                colors: self::parseColors($attrs['colors'] ?? ''),
                alt: $attrs['alt'] ?? '',
                caption: $attrs['caption'] ?? '',
            );
        }, $html);
    }

    /**
     * Parse key="value" / key='value' pairs in any order.
     *
     * @return array<string, string>
     */
    private static function parseAttributes(string $raw): array
    {
        // The markdown parser HTML-encodes the attribute quotes (" => &quot;).
        // Decode repeatedly so single- or double-encoded entities both resolve.
        $previous = null;
        while ($raw !== $previous) {
            $previous = $raw;
            $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
        }

        preg_match_all('/(\w+)\s*=\s*(["\'])(.*?)\2/s', $raw, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            $attrs[strtolower($match[1])] = trim($match[3]);
        }

        return $attrs;
    }

    /**
     * Normalise a comma-separated colour list into lowercase #hex values,
     * silently skipping anything that isn't a 3-, 6-, or 8-digit hex.
     *
     * @return list<string>
     */
    private static function parseColors(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($color) => ltrim(trim($color), '#'))
            ->filter(fn ($color) => preg_match('/^[0-9a-f]{3}([0-9a-f]{3}([0-9a-f]{2})?)?$/i', $color))
            ->map(fn ($color) => '#'.strtolower($color))
            ->values()
            ->all();
    }

    /**
     * Build the fixed <figure> skeleton. Values are escaped, so malformed
     * input can never inject markup.
     *
     * @param  list<string>  $colors
     */
    private static function markup(?string $src, array $colors, string $alt, string $caption): string
    {
        // Neither an image nor any colours: drop the snippet entirely.
        if (! $src && empty($colors)) {
            return '';
        }

        $parts = ['<figure class="palette">'];

        if ($src) {
            $parts[] = sprintf(
                '<img class="palette__img" src="%s" alt="%s">',
                e($src),
                e($alt),
            );
        }

        if (! empty($colors)) {
            $parts[] = '<div class="palette__swatches">';
            foreach ($colors as $hex) {
                $parts[] = sprintf(
                    '<div class="palette__swatch"><span class="palette__color" style="background:%1$s"></span><span class="palette__hex">%1$s</span></div>',
                    e($hex),
                );
            }
            $parts[] = '</div>';
        }

        if ($caption !== '') {
            $parts[] = sprintf('<figcaption class="palette__caption">%s</figcaption>', e($caption));
        }

        $parts[] = '</figure>';

        return implode('', $parts);
    }
}
