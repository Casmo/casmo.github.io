<?php

namespace Tests\Feature;

use App\Support\TriviaIcons;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Tests\TestCase;

class TriviaIconsTest extends TestCase
{
    private function icons(): TriviaIcons
    {
        return new TriviaIcons;
    }

    /** An entry-shaped stub: resolve() only ever reads ->icon and ->title. */
    private function entry(?string $path, string $title = 'untitled'): object
    {
        return new class($path === null ? null : Asset::find('assets::'.$path), $title)
        {
            public function __construct(public $icon, public $title) {}
        };
    }

    public function test_it_pairs_every_icon_with_its_own_title(): void
    {
        $pairs = $this->icons()->all();

        // 12 published trivia entries today (2026-08-25), each with an icon.
        $this->assertCount(12, $pairs);

        foreach ($pairs as $pair) {
            $this->assertFileExists($pair['path']);
            $this->assertStringStartsWith(base_path('public/assets/trivia/'), $pair['path']);
            $this->assertNotSame('', $pair['title']);
        }
    }

    public function test_it_orders_the_pairs_by_title_ascending(): void
    {
        // Trivia is neither dated nor orderable, so the collection sorts by
        // title -- which is what the fortune list renders in. A bare query
        // would return stache order, so this pins both ends.
        $pairs = $this->icons()->all();

        $titles = array_column($pairs, 'title');
        $sorted = $titles;
        sort($sorted, SORT_NATURAL);

        $this->assertSame($sorted, $titles);
        $this->assertStringStartsWith('Arkanoid', $pairs[0]['title']);
        $this->assertStringStartsWith('Volfied', $pairs[11]['title']);
    }

    public function test_the_title_belongs_to_the_path_beside_it(): void
    {
        // Presence alone would pass if the two columns were zipped out of
        // step, which is the bug that would put the wrong fact beside an icon
        // on the published page.
        $pairs = $this->icons()->all();

        $byBasename = [];

        foreach ($pairs as $pair) {
            $byBasename[basename($pair['path'])] = $pair['title'];
        }

        $this->assertStringContainsString(
            'snake',
            $byBasename['volfied-1bit-dos-game.png'],
        );
        $this->assertStringContainsString(
            'Lemmings',
            $byBasename['lemmings-1bit-dos-game.png'],
        );
    }

    public function test_it_skips_entries_that_have_no_icon(): void
    {
        // Pages carry no icon field at all.
        $pages = Entry::query()->where('collection', 'pages')->get();

        $this->assertNotEmpty($pages, 'expected the pages collection to still hold entries');

        $this->assertSame([], $this->icons()->resolve($pages));
    }

    public function test_it_collapses_two_entries_sharing_one_icon(): void
    {
        $pairs = $this->icons()->resolve([
            $this->entry('trivia/volfied-1bit-dos-game.png', 'first'),
            $this->entry('trivia/quake-1-pixel-logo.png', 'second'),
            $this->entry('trivia/volfied-1bit-dos-game.png', 'third'),
            $this->entry(null, 'fourth'),
        ]);

        // The first title wins: the sheet is the set of icons, not one tile
        // per entry, so the duplicate has no cell of its own to label.
        $this->assertSame([
            ['path' => base_path('public/assets/trivia/volfied-1bit-dos-game.png'), 'title' => 'first'],
            ['path' => base_path('public/assets/trivia/quake-1-pixel-logo.png'), 'title' => 'second'],
        ], $pairs);
    }
}
