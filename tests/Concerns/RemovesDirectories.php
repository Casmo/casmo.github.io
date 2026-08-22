<?php

namespace Tests\Concerns;

/**
 * Recursive delete for the throwaway directories these tests write into.
 *
 * A plain trait rather than a base class, so the unit tests that use it stay
 * on PHPUnit's TestCase and never boot Laravel.
 */
trait RemovesDirectories
{
    protected function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.'/'.$entry;

            is_dir($child) ? $this->remove($child) : unlink($child);
        }

        rmdir($path);
    }
}
