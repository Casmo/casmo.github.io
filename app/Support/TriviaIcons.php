<?php

namespace App\Support;

use Illuminate\Support\Collection as Support;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;

/**
 * The trivia icon set: which icons there are, in what order, and what fact
 * each one belongs to.
 *
 * One definition, because two things consume it -- the tilesheet and the
 * itch.io pack -- and if they resolved the set separately they could disagree
 * about its order or its dedupe, which would put the wrong fact beside an icon
 * on a published page.
 */
class TriviaIcons
{
    public const COLLECTION = 'trivia';

    /** @return list<array{path: string, title: string}> */
    public function all(): array
    {
        return $this->resolve($this->entries());
    }

    /**
     * The icons, in tile order, deduped: the set is the set of icons, not one
     * entry per icon. Where two entries share an icon the first title wins,
     * since the duplicate has no cell of its own to label.
     *
     * @param  iterable<object>  $entries
     * @return list<array{path: string, title: string}>
     */
    public function resolve(iterable $entries): array
    {
        $pairs = [];
        $seen = [];

        foreach ($entries as $entry) {
            // The same unwrapping the fortune list does in default.blade.php:
            // the field is a Value holding an AssetCollection.
            $icon = $entry->icon;
            $icon = $icon instanceof Value ? $icon->value() : $icon;
            $icon = is_iterable($icon) ? collect($icon)->first() : $icon;

            $path = $icon?->resolvedPath();

            if (! $path || isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;

            $title = $entry->title;
            $title = $title instanceof Value ? $title->value() : $title;

            $pairs[] = ['path' => $path, 'title' => (string) $title];
        }

        return $pairs;
    }

    /**
     * Published trivia entries in the collection's own order -- title
     * ascending, since trivia is neither dated nor orderable -- so the set
     * reads in the order the fortunes do. The sort has to be explicit: a bare
     * query returns stache order.
     */
    private function entries(): Support
    {
        $collection = Collection::findByHandle(self::COLLECTION);

        if (! $collection) {
            throw new \RuntimeException('Could not find the ['.self::COLLECTION.'] collection.');
        }

        return Entry::query()
            ->where('collection', self::COLLECTION)
            ->where('published', true)
            ->orderBy($collection->sortField(), $collection->sortDirection())
            ->get();
    }
}
