<?php

namespace App\Support;

use Statamic\Facades\YAML;

/**
 * Publishes one tilesheet of every trivia icon.
 *
 * It writes three files: the asset under public/, the .meta yaml that
 * pre-caches its metadata so the control panel does not have to generate it
 * on first view, and a copy in the SSG output. The third exists because the
 * SSG copies public/assets into the output *before* it runs the after hook,
 * so writing only to public/ would ship the new sheet one deploy late.
 */
class TilesheetGenerator
{
    public const FILENAME = 'trivia-tilesheet.png';

    public function __construct(
        private Tilesheet $tilesheet,
        private TriviaIcons $icons,
        private string $publicPath,
        private string $outputPath,
    ) {}

    /** @return array{width: int, height: int}|null null when no entry has an icon */
    public function generate(): ?array
    {
        $asset = $this->directory($this->publicPath.'/assets').'/'.self::FILENAME;
        $temp = $asset.'.tmp';

        try {
            $written = $this->tilesheet->write(
                $temp,
                array_column($this->icons->all(), 'path'),
            );

            if ($written === null) {
                return null;
            }

            $meta = dirname($asset).'/.meta/'.basename($asset).'.yaml';

            // filemtime() moves on every render even when the bytes don't, so
            // rewriting the asset unconditionally would dirty the committed
            // PNG and its meta on every build. Only replace them when the
            // rendered bytes actually differ from what's already there.
            if (! is_file($asset) || ! is_file($meta) || ! $this->identical($temp, $asset)) {
                $this->replace($temp, $asset);
                $this->writeMeta($asset, $written);
            }
        } finally {
            // write() can throw partway through (a bad source, an unwritable
            // temp path), and replace() leaves the temp file in place if the
            // rename fails -- either way, never leave it on disk.
            if (is_file($temp)) {
                $this->unlink($temp);
            }
        }

        // Unconditional: the output directory is cleared by every build, so
        // the build copy has to be written every time, not just when the
        // asset itself changed.
        $this->copyToOutput($asset);

        return $written;
    }

    /**
     * The icons, in tile order, deduped. Kept as a thin pass-through to
     * TriviaIcons so callers that already hold a list of entries -- the tests
     * do -- don't have to go through the collection query.
     *
     * @param  iterable<object>  $entries
     * @return list<string>
     */
    public function sources(iterable $entries): array
    {
        return array_column($this->icons->resolve($entries), 'path');
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

    /** Byte-for-byte: this is what decides whether the committed asset moves. */
    private function identical(string $left, string $right): bool
    {
        return filesize($left) === filesize($right)
            && file_get_contents($left) === file_get_contents($right);
    }

    private function replace(string $temp, string $asset): void
    {
        if (! @rename($temp, $asset)) {
            throw new \RuntimeException("Could not replace [{$asset}].");
        }
    }

    private function unlink(string $path): void
    {
        if (! @unlink($path)) {
            throw new \RuntimeException("Could not remove [{$path}].");
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
