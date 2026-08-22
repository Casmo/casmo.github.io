<?php

namespace App\Support;

/**
 * The filesystem writes the generators share.
 *
 * Small on purpose. It exists so the same mkdir-and-check and the same
 * write-and-check are not restated in every class that publishes a file, and
 * so a failure always names the path that failed.
 */
final class Files
{
    /** @return string the directory, created if it was missing */
    public function directory(string $directory): string
    {
        // The is_dir() re-check tolerates a directory created concurrently
        // between the first check and the mkdir() call.
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Could not create directory [{$directory}].");
        }

        return $directory;
    }

    public function put(string $path, string $contents): void
    {
        // Suppressed so a failure surfaces as the exception rather than as a
        // warning from somewhere inside the filesystem layer.
        if (@file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Could not write [{$path}].");
        }
    }
}
