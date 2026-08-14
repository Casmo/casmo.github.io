<?php

namespace App\Support;

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Statamic\Fields\Value;

/**
 * Publishes one tilesheet of every trivia icon.
 *
 * It writes three files: the asset under public/, the .meta yaml that puts
 * the asset in Statamic's asset map, and a copy in the SSG output. The third
 * exists because the SSG copies public/assets into the output *before* it
 * runs the after hook, so writing only to public/ would ship the new sheet
 * one deploy late.
 */
class TilesheetGenerator
{
    public const FILENAME = 'trivia-tilesheet.png';

    public function __construct(
        private Tilesheet $tilesheet,
        private string $publicPath,
        private string $outputPath,
    ) {}

    /** @return array{width: int, height: int}|null null when no entry has an icon */
    public function generate(): ?array
    {
        $asset = $this->directory($this->publicPath.'/assets').'/'.self::FILENAME;

        $written = $this->tilesheet->write($asset, $this->sources($this->entries()));

        if ($written === null) {
            return null;
        }

        $this->writeMeta($asset, $written);
        $this->copyToOutput($asset);

        return $written;
    }

    /**
     * The icons, in tile order, deduped: the sheet is the set of icons, not
     * one tile per entry.
     *
     * @param  iterable<object>  $entries
     * @return list<string>
     */
    public function sources(iterable $entries): array
    {
        $paths = [];

        foreach ($entries as $entry) {
            // The same unwrapping the fortune list does in default.blade.php:
            // the field is a Value holding an AssetCollection.
            $icon = $entry->icon;
            $icon = $icon instanceof Value ? $icon->value() : $icon;
            $icon = is_iterable($icon) ? collect($icon)->first() : $icon;

            $path = $icon?->resolvedPath();

            if ($path && ! in_array($path, $paths, strict: true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Published trivia entries in the collection's own order -- title
     * ascending, since trivia is neither dated nor orderable -- so the sheet
     * reads left to right in the order the fortunes do. The sort has to be
     * explicit: a bare query returns stache order.
     */
    private function entries(): \Illuminate\Support\Collection
    {
        $collection = Collection::findByHandle('trivia');

        return Entry::query()
            ->where('collection', 'trivia')
            ->where('published', true)
            ->orderBy($collection->sortField(), $collection->sortDirection())
            ->get();
    }

    /**
     * The asset's meta, in the shape the container already writes, so the
     * control panel sees a fully known asset instead of generating meta on
     * first view. Written after the PNG: size and mtime describe the file
     * that is actually on disk.
     *
     * @param  array{width: int, height: int}  $written
     */
    private function writeMeta(string $asset, array $written): void
    {
        $directory = $this->directory(dirname($asset).'/.meta');

        $this->put($directory.'/'.basename($asset).'.yaml', YAML::dump([
            'data' => [],
            'size' => filesize($asset),
            'last_modified' => filemtime($asset),
            'width' => $written['width'],
            'height' => $written['height'],
            'mime_type' => 'image/png',
            'duration' => null,
        ]));
    }

    private function copyToOutput(string $asset): void
    {
        $destination = $this->directory($this->outputPath.'/assets').'/'.self::FILENAME;

        if (! @copy($asset, $destination)) {
            throw new \RuntimeException("Could not copy the tilesheet to [{$destination}].");
        }
    }

    private function put(string $path, string $contents): void
    {
        if (@file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Could not write [{$path}].");
        }
    }

    /** @return string the directory, created if it was missing */
    private function directory(string $directory): string
    {
        // The is_dir() re-check tolerates a directory created concurrently
        // between the first check and the mkdir() call.
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Could not create directory [{$directory}].");
        }

        return $directory;
    }
}
