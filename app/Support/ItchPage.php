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
 * would degrade silently. The images arrive already scaled instead.
 *
 * Deliberately free of Laravel and Statamic: arrays and a string in, a string
 * out, so what it produces is asserted directly.
 */
final class ItchPage
{
    /**
     * @param  list<array{path: string, title: string}>  $icons  in sheet order
     * @param  string  $baseUrl  where the upscales are published
     * @param  string  $tilesheet  path to the sheet, for its published name
     */
    public function render(array $icons, string $baseUrl, string $tilesheet): string
    {
        $base = rtrim($baseUrl, '/').'/'.ItchAssetsGenerator::DIRECTORY;

        $sheet = $base.'/'.ItchAssetsGenerator::filename($tilesheet);

        $lines = [
            '<p><img src="'.$this->escape($sheet).'" alt="'
                .$this->escape('Every icon in the pack on one grid').'" /></p>',
        ];

        if ($icons !== []) {
            $lines[] = '<h2>Every icon</h2>';
            $lines[] = '<ul>';

            foreach ($icons as $icon) {
                $source = $base.'/'.IconPack::ICONS.'/'
                    .ItchAssetsGenerator::filename($icon['path']);

                // alt is empty on purpose: the fact beside it is the label.
                $lines[] = '<li><img src="'.$this->escape($source).'" alt="" /> '
                    .$this->escape($icon['title']).'</li>';
            }

            $lines[] = '</ul>';
        }

        return implode("\n", $lines)."\n";
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
