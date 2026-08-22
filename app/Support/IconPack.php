<?php

namespace App\Support;

/**
 * Stages the directory butler pushes to itch.io.
 *
 * Butler is handed a directory rather than a zip: it diffs per file, so adding
 * one icon uploads one icon, and itch.io compresses each build server-side so
 * a browser download is still a single archive. Pushing a pre-made zip would
 * both defeat the diffing and trip butler's auto-unzip rule.
 *
 * Deliberately free of Laravel and Statamic: it takes paths, so it can be
 * tested without booting anything.
 */
final class IconPack
{
    public const README = 'README.txt';

    public const ICONS = 'icons';

    public const TILESHEET = 'trivia-tilesheet.png';

    public function __construct(private Files $files) {}

    /**
     * @param  list<array{path: string, title: string}>  $icons  in sheet order
     * @param  string  $tilesheet  absolute path to the regenerated sheet
     * @return int the number of icons staged
     */
    public function write(string $staging, array $icons, string $tilesheet): int
    {
        // The blueprint lets an author pick an icon from any assets folder,
        // but icons are copied to icons/<basename> -- two icons sharing a
        // basename from different folders would collapse onto one file and
        // silently mispair an icon with the wrong fact on the published page.
        $this->assertNoBasenameCollisions($icons);

        // Cleared rather than merged: an icon removed from the collection
        // would otherwise linger from an earlier run and ship to buyers.
        $this->clear($staging);

        $directory = $this->files->directory($staging.'/'.self::ICONS);

        foreach ($icons as $icon) {
            $this->copy($icon['path'], $directory.'/'.basename($icon['path']));
        }

        $this->copy($tilesheet, $staging.'/'.self::TILESHEET);

        $this->files->put($staging.'/'.self::README, $this->readme($icons));

        return count($icons);
    }

    /**
     * Which icon is which. Eleven kebab-case filenames are not self-
     * describing, and a buyer should not have to open all of them to find the
     * one they want.
     *
     * @param  list<array{path: string, title: string}>  $icons
     */
    private function readme(array $icons): string
    {
        $names = array_map(fn (array $icon) => basename($icon['path']), $icons);

        $width = max(array_map('strlen', $names ?: ['']));

        $lines = [
            '1-bit icons from MS-DOS games',
            '',
            'Every icon is white pixels on transparent, at its original size.',
            'trivia-tilesheet.png holds all of them on one grid.',
            '',
        ];

        foreach ($icons as $index => $icon) {
            $lines[] = str_pad($names[$index], $width + 2).$icon['title'];
        }

        return implode("\n", $lines)."\n";
    }

    /** @param  list<array{path: string, title: string}>  $icons */
    private function assertNoBasenameCollisions(array $icons): void
    {
        $seen = [];

        foreach ($icons as $icon) {
            $basename = basename($icon['path']);

            if (isset($seen[$basename]) && $seen[$basename] !== $icon['path']) {
                throw new \RuntimeException(
                    "Two icons share the filename [{$basename}]: ".
                    "[{$seen[$basename]}] and [{$icon['path']}]."
                );
            }

            $seen[$basename] = $icon['path'];
        }
    }

    private function copy(string $source, string $destination): void
    {
        if (! is_file($source)) {
            throw new \RuntimeException("Could not read the icon at [{$source}].");
        }

        if (! @copy($source, $destination)) {
            throw new \RuntimeException("Could not copy [{$source}] to [{$destination}].");
        }
    }

    private function clear(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.'/'.$entry;

            is_dir($child) ? $this->clear($child) : $this->unlink($child);
        }

        if (! @rmdir($path)) {
            throw new \RuntimeException("Could not clear [{$path}].");
        }
    }

    private function unlink(string $path): void
    {
        if (! @unlink($path)) {
            throw new \RuntimeException("Could not remove [{$path}].");
        }
    }
}
