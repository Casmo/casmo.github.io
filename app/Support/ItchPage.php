<?php

namespace App\Support;

/**
 * Renders the itch.io page description.
 *
 * itch.io has no API for a page's description, so this is pasted into the
 * editor's HTML mode by hand. That editor sanitises what it is given and
 * mangles content on round-trip, so the markup here is the canonical copy and
 * is deliberately plain: no class, no style, no width, no height. Anything
 * the sanitiser might drop would otherwise be load-bearing, and the page
 * would degrade silently.
 *
 * Each row links its icon where the site already serves it, at the size it was
 * drawn. Only the tilesheet is a derived image, because it is the hero and
 * 169x29 is too small to read as artwork.
 *
 * Deliberately free of Laravel and Statamic: arrays and strings in, a string
 * out, so what it produces is asserted directly.
 */
final class ItchPage
{
    /**
     * @param  list<array{path: string, title: string}>  $icons  in sheet order
     * @param  string  $baseUrl  the site the images are served from
     * @param  string  $tilesheet  path to the sheet, for its published name
     * @param  string  $publicPath  the document root the icon paths sit under
     */
    public function render(
        array $icons,
        string $baseUrl,
        string $tilesheet,
        string $publicPath,
    ): string {
        $base = rtrim($baseUrl, '/');

        $sheet = $base.'/'.ItchAssetsGenerator::DIRECTORY
            .'/'.ItchAssetsGenerator::filename($tilesheet);

        $lines = [
            '<p><img src="'.$this->escape($sheet).'" alt="'
                .$this->escape('Every icon in the pack on one grid').'" /></p>',
        ];

        if ($icons !== []) {
            $lines[] = '<h2>Every icon</h2>';

            // One paragraph of <br />-separated rows rather than a <ul>. itch's
            // editor gives list items their own spacing and bullets, which
            // reads as a bulleted list of sentences; the icons are the bullets
            // here, so plain rows sit closer together and look intentional.
            $lines[] = '<p>';

            foreach ($icons as $icon) {
                $source = $base.'/'.$this->relative($icon['path'], $publicPath);

                // alt is empty on purpose: the fact beside it is the label.
                $lines[] = '<img src="'.$this->escape($source).'" alt="" /> '
                    .$this->escape($icon['title']).'<br />';
            }

            $lines[] = '</p>';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Where the site serves an icon, derived from where it sits on disk.
     *
     * The whole path is kept rather than the basename, so two icons with the
     * same filename in different asset folders stay two distinct URLs instead
     * of collapsing onto one and putting the wrong picture beside a fact.
     *
     * An icon outside the document root cannot be served at all, so that
     * throws rather than emitting a URL that is certain to 404.
     */
    private function relative(string $path, string $publicPath): string
    {
        $root = rtrim($publicPath, '/').'/';

        if (! str_starts_with($path, $root)) {
            throw new \RuntimeException(
                "The icon at [{$path}] is not under [{$root}], so it has no public URL."
            );
        }

        return substr($path, strlen($root));
    }

    /**
     * One escape for both attributes and text. ENT_QUOTES covers the quote
     * that would break out of an attribute and ENT_SUBSTITUTE keeps malformed
     * UTF-8 from emptying the string, so splitting this in two would be two
     * identical methods.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
