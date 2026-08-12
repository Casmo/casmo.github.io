<?php

namespace App\Support;

use Statamic\Facades\Entry;

/**
 * Writes a social image for every entry a reader can actually reach. Entries
 * without a URL -- the trivia fortunes, which have no route -- are skipped:
 * nothing would ever link to their image.
 */
class SocialImageGenerator
{
    public const DOMAIN = 'mathieuderuiter.nl';

    public function __construct(
        private SocialImage $image,
        private string $outputPath,
    ) {}

    /** @return int the number of images written */
    public function generate(): int
    {
        $directory = $this->outputPath.'/assets/pages';

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            // The is_dir() re-check tolerates a directory created concurrently
            // between the first check and the mkdir() call.
            throw new \RuntimeException("Could not create directory [{$directory}].");
        }

        $entries = Entry::query()
            ->where('published', true)
            ->get()
            ->filter(fn ($entry) => filled($entry->url()));

        $written = 0;

        foreach ($entries as $entry) {
            ['path' => $path, 'file' => $file] = Terminal::forEntry($entry);

            $this->image->write(
                $directory.'/'.$entry->slug().'.png',
                (string) $entry->value('title'),
                $this->image->prompt(Terminal::host($this->author($entry)), $path, $file),
                self::DOMAIN,
                $entry->date()?->format('F j, Y'),
            );

            $written++;
        }

        return $written;
    }

    /** The author's name, for the user half of the prompt. */
    private function author($entry): ?string
    {
        $author = $entry->augmentedValue('author')->value();

        return is_object($author) && method_exists($author, 'name') ? $author->name() : null;
    }
}
