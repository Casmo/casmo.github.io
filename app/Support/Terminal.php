<?php

namespace App\Support;

use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry;
use Statamic\Fields\Value;

/**
 * The site is dressed as a single shell session, so every page needs a prompt:
 * a directory to sit in and a file to cat. Both are derived from the entry, and
 * both can be overridden per entry with the terminal_path / terminal_file
 * fields, because a few pages have hand-picked filenames that no field implies
 * (introduction.txt, useful-stuff.txt).
 */
class Terminal
{
    /** Where the session appears to be running. */
    public const HOST = 'laptop';

    /** Falls back to this when an entry has no author. */
    public const USER = 'mathieu';

    /**
     * A shell path such as "~/blog/laravel". The override wins when set,
     * otherwise the segments are joined. Always lowercase: directories are.
     */
    public static function path(mixed $override = null, array $segments = []): string
    {
        $override = static::plain($override);

        $parts = $override !== null
            ? preg_split('#/+#', ltrim($override, '~/'))
            : $segments;

        $parts = collect($parts)
            ->map(fn ($part) => Str::slug((string) static::plain($part)))
            ->filter()
            ->all();

        return '~'.($parts ? '/'.implode('/', $parts) : '');
    }

    /**
     * The file being cat'd, such as "scorched-earth.txt". An override may or may
     * not carry its own extension, so only add one when it is missing.
     */
    public static function file(mixed $override = null, mixed $slug = null): string
    {
        $name = static::plain($override) ?? static::plain($slug) ?? 'index';

        return Str::endsWith($name, '.txt') ? $name : $name.'.txt';
    }

    /**
     * The path and file for an entry, so a page and anything that describes it
     * from the outside -- the social image -- cannot disagree about where the
     * entry lives.
     *
     * @return array{path: string, file: string}
     */
    public static function forEntry(Entry $entry): array
    {
        // collectionHandle() rather than collection()->handle(): pages routed
        // through a structure (the "pages" collection) arrive here as a
        // Statamic\Structures\Page, whose own collection() resolves the
        // *mounted* collection -- often null -- instead of forwarding to the
        // wrapped entry the way collectionHandle() does.
        $segments = match ($entry->collectionHandle()) {
            'blog' => ['blog', collect($entry->get('categories') ?? [])->first()],
            'games' => ['reviews'],
            default => [$entry->value('title')],
        };

        return [
            'path' => static::path($entry->get('terminal_path'), $segments),
            'file' => static::file($entry->get('terminal_file'), $entry->slug()),
        ];
    }

    /**
     * Whether a nav URL is the directory the reader is currently standing in, for
     * highlighting one entry of the `ls` listing.
     *
     * Entry routes do not live under their section's URL -- a post is at
     * /read/{slug} while the section page is /blog -- so the first path segment is
     * mapped back to its nav URL rather than compared directly.
     */
    public static function inSection(string $url, ?string $path = null): bool
    {
        $path = '/'.trim($path ?? request()->path(), '/');

        return $url === match (Str::before(ltrim($path, '/'), '/')) {
            'read' => '/blog',
            'reviews' => '/reviews',
            default => $path,
        };
    }

    /** The "user@host" half of a prompt. */
    public static function host(mixed $user = null): string
    {
        $user = static::plain($user);

        return (Str::slug((string) $user) ?: static::USER).'@'.static::HOST;
    }

    /**
     * Unwrap a Statamic field value and normalise blanks to null, so callers can
     * pass raw entry fields without checking what they got first.
     */
    protected static function plain(mixed $value): ?string
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        return trim((string) $value) ?: null;
    }
}
