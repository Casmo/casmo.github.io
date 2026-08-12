# Terminal-style social image Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the build-time Open Graph image so it reads as a fragment of the site's shell session, with a title big enough to survive a LinkedIn thumbnail.

**Architecture:** Four seams. `Terminal::forEntry()` becomes the single source for an entry's shell path and filename, used by both the site's Blade views and the generator. `SocialImage` is a Laravel-free, Statamic-free renderer: given a title, a prompt string, a domain, a date and asset paths, it writes one 1200×630 PNG. `SocialImageGenerator` walks the published entries that have a URL and drives the renderer. `AppServiceProvider` keeps only the `SSG::after` wiring.

**Tech Stack:** PHP 8.5 (repo floor 8.3), Laravel 13, Statamic 6 + statamic/ssg 4, PHP GD, PHPUnit 12, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-08-12-social-image-design.md`

## Global Constraints

- Output contract is unchanged: a 1200×630 PNG per entry at `{ssg output}/assets/pages/{slug}.png`. Do not touch the `og:image` meta tag in `resources/views/default.blade.php`.
- Palette, verbatim: background `#1f2329`, prompt `#84cc16`, title `#e4e4e7`, identity line `#6b7280`, cursor `#84cc16`.
- Geometry, verbatim: `WIDTH 1200`, `HEIGHT 630`, `CELL 42`, `MARGIN 72`, measure `1056` (= 1200 − 2×72), title band top `105`, title band height `420`, line-height factor `1.35`, prompt baseline `84`, identity baseline `588`, avatar `56`px, identity text `20`pt.
- Title size ladder, verbatim: `[118, 96, 78, 64, 54, 46, 40]`. Prompt line is always `22`pt.
- **GD needs absolute font paths.** `imagettfbbox()` and `imagettftext()` fail with "Could not find/open font" on a relative path. Always pass `resource_path(...)` values or absolute test paths.
- Run tests with `vendor/bin/phpunit`. Format with `vendor/bin/pint` before each commit.
- Never write test artefacts to `/tmp` — the sandbox may block it. Use `storage/framework/testing/` (already gitignored).
- Do not create or modify anything under `content/` — tests read real entries but must not save them.

---

### Task 1: `Terminal::forEntry()` and the Blade call sites

Five Blade views each derive a shell path and filename inline. The generator needs the same values, so the rules move into `Terminal`, which already owns `path()` and `file()`.

**Files:**
- Modify: `app/Support/Terminal.php` (add `forEntry()` after `file()`, around line 55)
- Modify: `resources/views/blog.blade.php:3-15`
- Modify: `resources/views/games.blade.php:29-30`
- Modify: `resources/views/pages/page.blade.php:8-9`
- Modify: `resources/views/pages/blog.blade.php:5-6`
- Modify: `resources/views/pages/reviews.blade.php:5-6`
- Test: `tests/Feature/TerminalForEntryTest.php` (create)

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `\App\Support\Terminal::forEntry(\Statamic\Contracts\Entries\Entry $entry): array` returning `['path' => string, 'file' => string]`, e.g. `['path' => '~/blog/laravel', 'file' => 'recover-deleted-uploadcare-files-in-laravel.txt']`. Task 4 calls this.

**Background the implementer needs:**
- In Blade, `$page` is the `Statamic\Entries\Entry` itself (`Statamic\View\Cascade::hydrateContent()` does `$this->set('page', $this->content)`), so `Terminal::forEntry($page)` works inside these views.
- `$entry->get('categories')` returns raw taxonomy slugs (`['laravel']`), so no augmentation is needed — `Terminal::path()` slugs each segment anyway.
- The three collections in play are `blog`, `games` and `pages`. `books/show.blade.php` is deliberately excluded: its `$page` is a taxonomy Term, not an Entry, and it only calls `Terminal::file()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TerminalForEntryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\Terminal;
use Statamic\Facades\Entry;
use Tests\TestCase;

class TerminalForEntryTest extends TestCase
{
    /** Entries are made, never saved, so nothing is written to content/. */
    private function entry(string $collection, string $slug, array $data)
    {
        return Entry::make()->collection($collection)->slug($slug)->data($data);
    }

    public function test_a_blog_entry_sits_in_its_category_directory(): void
    {
        $entry = $this->entry('blog', 'recover-deleted-uploadcare-files-in-laravel', [
            'title' => 'Recover deleted Uploadcare files in Laravel',
            'categories' => ['laravel'],
        ]);

        $this->assertSame([
            'path' => '~/blog/laravel',
            'file' => 'recover-deleted-uploadcare-files-in-laravel.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_blog_entry_without_a_category_falls_back_to_the_blog_directory(): void
    {
        $entry = $this->entry('blog', 'nostalgia', ['title' => 'Nostalgia']);

        $this->assertSame([
            'path' => '~/blog',
            'file' => 'nostalgia.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_review_sits_in_the_reviews_directory(): void
    {
        $entry = $this->entry('games', 'forest-feeding-frenzy', ['title' => 'Forest Feeding Frenzy']);

        $this->assertSame([
            'path' => '~/reviews',
            'file' => 'forest-feeding-frenzy.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_page_takes_its_directory_from_its_title(): void
    {
        $entry = $this->entry('pages', 'home', ['title' => 'About']);

        $this->assertSame([
            'path' => '~/about',
            'file' => 'home.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_the_overrides_win(): void
    {
        $entry = $this->entry('pages', 'home', [
            'title' => 'About',
            'terminal_path' => '~/somewhere/else',
            'terminal_file' => 'introduction.txt',
        ]);

        $this->assertSame([
            'path' => '~/somewhere/else',
            'file' => 'introduction.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_file_override_without_an_extension_still_gets_one(): void
    {
        $entry = $this->entry('pages', 'resources', [
            'title' => 'Resources',
            'terminal_file' => 'useful-stuff',
        ]);

        $this->assertSame('useful-stuff.txt', Terminal::forEntry($entry)['file']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter TerminalForEntryTest`
Expected: FAIL — `Call to undefined method App\Support\Terminal::forEntry()`

- [ ] **Step 3: Implement `forEntry()`**

Add the import `use Statamic\Contracts\Entries\Entry;` alongside the existing `use Statamic\Fields\Value;`, then add this method after `file()`:

```php
    /**
     * The path and file for an entry, so a page and anything that describes it
     * from the outside -- the social image -- cannot disagree about where the
     * entry lives.
     *
     * @return array{path: string, file: string}
     */
    public static function forEntry(Entry $entry): array
    {
        $segments = match ($entry->collection()->handle()) {
            'blog' => ['blog', collect($entry->get('categories') ?? [])->first()],
            'games' => ['reviews'],
            default => [$entry->value('title')],
        };

        return [
            'path' => static::path($entry->get('terminal_path'), $segments),
            'file' => static::file($entry->get('terminal_file'), $entry->slug()),
        ];
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter TerminalForEntryTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Write the failing regression test for the views**

The views must keep rendering exactly the prompts they render today. Create `tests/Feature/PromptLineTest.php`:

Note the `#[DataProvider]` attribute: PHPUnit 12 ignores the `@dataProvider` doc-comment annotation, and a test using it errors with `ArgumentCountError: Too few arguments`.

```php
<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromptLineTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'blog post' => ['/read/recover-deleted-uploadcare-files-in-laravel', '~/blog/laravel', 'recover-deleted-uploadcare-files-in-laravel.txt'],
            'review' => ['/reviews/forest-feeding-frenzy', '~/reviews', 'forest-feeding-frenzy.txt'],
            'blog index' => ['/blog', '~/blog', 'notes.txt'],
            'reviews index' => ['/reviews', '~/reviews', 'reviews.txt'],
            'resources' => ['/resources', '~/resources', 'useful-stuff.txt'],
            'home' => ['/', '~/about', 'introduction.txt'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_prompt_names_its_own_path_and_file(string $url, string $path, string $file): void
    {
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('>:'.$path.'<', false);
        $response->assertSee('cat '.$file, false);
    }
}
```

- [ ] **Step 6: Run it to verify it passes against the current views**

Run: `vendor/bin/phpunit --filter PromptLineTest`
Expected: PASS (6 tests). This test is the safety net for Step 7 — it must pass **before** the views change. If it fails now, stop: the assertion strings are wrong, not the views.

- [ ] **Step 7: Point the views at `forEntry()`**

`resources/views/blog.blade.php` — replace lines 3–15 (the `@php($category = ...)` line and the header's `:path` / `:file` attributes; `$category` has no other use in the file):

```blade
@php($terminal = \App\Support\Terminal::forEntry($page))

@section('terminal-header')
  <x-terminal.header
    :path="$terminal['path']"
    :file="$terminal['file']"
    :user="$author->name ?? null"
    :rows="[
      'Title' => $title,
      'Date' => $date->format('F j, Y'),
      'Tags' => $categories->pluck('title')->join(', '),
    ]"
  />
@endsection
```

`resources/views/games.blade.php` already opens with an `@php ... @endphp` block (lines 3–25). Add one line to the end of it, just before `@endphp`:

```blade
    $terminal = \App\Support\Terminal::forEntry($page);
```

`resources/views/pages/page.blade.php`, `resources/views/pages/blog.blade.php` and `resources/views/pages/reviews.blade.php` have no `@php` block; add one directly under `@extends('default')`:

```blade
@php($terminal = \App\Support\Terminal::forEntry($page))
```

Then in all four files, replace the two attribute lines with:

```blade
    :path="$terminal['path']"
    :file="$terminal['file']"
```

In `pages/page.blade.php`, delete the three-line `{{-- A page is its own directory... --}}` comment above the header: the rule it describes now lives in `Terminal::forEntry()`, and a comment repeating it in one of the three views that share the rule will rot.

- [ ] **Step 8: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS. `PromptLineTest` proves the six rendered prompts are byte-identical to before the refactor.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint app/Support/Terminal.php tests/Feature/TerminalForEntryTest.php tests/Feature/PromptLineTest.php
git add app/Support/Terminal.php resources/views tests/Feature/TerminalForEntryTest.php tests/Feature/PromptLineTest.php
git commit -m "Derive an entry's terminal path and file in one place"
```

---

### Task 2: The bold face and the title size ladder

The renderer's one piece of real logic: choosing the largest size at which a title fits its band. It is pure measurement, so it is tested without drawing anything.

**Files:**
- Create: `resources/fonts/IBMPlexMono-Bold.ttf` (downloaded)
- Create: `app/Support/SocialImage.php`
- Test: `tests/Unit/SocialImageFitTest.php` (create)

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces:
  - `new \App\Support\SocialImage(string $titleFont, string $chromeFont, string $artwork, string $avatar)` — all four are absolute paths. `$titleFont` is the bold TTF, `$chromeFont` the regular TTF.
  - `$image->fit(string $title): array` returning `['size' => int, 'lines' => list<string>]`.
  - Public constants `SocialImage::WIDTH = 1200` and `SocialImage::HEIGHT = 630`.
  - Task 3 adds `write()` to this same class; Task 4 constructs it.

**Background the implementer needs:**
- The site loads IBM Plex Mono as `.woff2`, which GD cannot read, hence a TTF. The regular TTF is already in `resources/fonts/` for exactly this reason.
- Bold and regular have the same advances (992px vs 990px for a 13-character string at 96pt — it is a monospace family), so the measurements below hold, but `fit()` must still measure with the **bold** face, because that is what gets drawn.
- `maxRows($size)` is `floor(420 / ($size * 1.35))`: 2 rows at 118pt, 3 at 96pt, 4 at 78pt and 64pt, 5 at 54pt, 6 at 46pt, 7 at 40pt.

- [ ] **Step 1: Add the bold face**

```bash
curl -sL -o resources/fonts/IBMPlexMono-Bold.ttf \
  "https://github.com/IBM/plex/raw/v6.4.0/IBM-Plex-Mono/fonts/complete/ttf/IBMPlexMono-Bold.ttf"
ls -l resources/fonts/IBMPlexMono-Bold.ttf
```

Expected: a 157924-byte file. Verify GD can open it (note the absolute path):

```bash
php -r '$b=imagettfbbox(96,0,getcwd()."/resources/fonts/IBMPlexMono-Bold.ttf","The interface"); echo abs($b[2]-$b[0]), "\n";'
```

Expected: `992`

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/SocialImageFitTest.php`. Every expected value below was produced by rendering the real titles; do not adjust them to match an implementation.

```php
<?php

namespace Tests\Unit;

use App\Support\SocialImage;
use PHPUnit\Framework\TestCase;

class SocialImageFitTest extends TestCase
{
    private function image(): SocialImage
    {
        $resources = dirname(__DIR__, 2).'/resources';

        return new SocialImage(
            titleFont: $resources.'/fonts/IBMPlexMono-Bold.ttf',
            chromeFont: $resources.'/fonts/IBMPlexMono-Regular.ttf',
            artwork: $resources.'/img/og-background.png',
            avatar: $resources.'/img/casmo.png',
        );
    }

    public function test_a_one_word_title_takes_the_top_of_the_ladder(): void
    {
        $this->assertSame(
            ['size' => 118, 'lines' => ['Nostalgia']],
            $this->image()->fit('Nostalgia'),
        );
    }

    public function test_a_short_title_stays_at_the_top_of_the_ladder_over_two_rows(): void
    {
        $this->assertSame(
            ['size' => 118, 'lines' => ['Game flow', 'of Kabonk!']],
            $this->image()->fit('Game flow of Kabonk!'),
        );
    }

    public function test_a_medium_title_drops_one_step(): void
    {
        $this->assertSame(
            ['size' => 96, 'lines' => ['The interface', 'of the future']],
            $this->image()->fit('The interface of the future'),
        );
    }

    public function test_a_long_title_drops_further(): void
    {
        $this->assertSame(
            ['size' => 64, 'lines' => ['Build your Statamic', 'static website via', 'GitHub actions']],
            $this->image()->fit('Build your Statamic static website via GitHub actions'),
        );
    }

    public function test_the_longest_real_title_still_clears_the_floor(): void
    {
        $this->assertSame(
            [
                'size' => 54,
                'lines' => [
                    'Generate default Open',
                    'Graph images for each',
                    'Entry in your your',
                    'Statamic site',
                ],
            ],
            $this->image()->fit('Generate default Open Graph images for each Entry in your your Statamic site'),
        );
    }

    public function test_an_absurd_title_is_truncated_at_the_floor(): void
    {
        $result = $this->image()->fit(trim(str_repeat('lorem ipsum dolor sit amet ', 12)));

        $this->assertSame(40, $result['size']);
        $this->assertCount(7, $result['lines']);
        $this->assertSame('ipsum dolor sit amet lorem ipsum…', end($result['lines']));
    }

    public function test_a_word_wider_than_the_measure_is_hard_broken(): void
    {
        $result = $this->image()->fit(str_repeat('a', 200));

        $this->assertSame(40, $result['size']);
        $this->assertCount(7, $result['lines']);
        $this->assertSame(str_repeat('a', 33), $result['lines'][0]);
        $this->assertSame('aa', end($result['lines']));
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SocialImageFitTest`
Expected: FAIL — `Class "App\Support\SocialImage" not found`

- [ ] **Step 4: Implement the class and `fit()`**

Create `app/Support/SocialImage.php`:

```php
<?php

namespace App\Support;

/**
 * Renders one 1200x630 social image: a prompt line, the entry's title as the
 * output of `cat`, and an identity line. Deliberately free of Laravel and
 * Statamic -- it takes strings and absolute paths, so the layout can be tested
 * without booting anything.
 *
 * Sizes are the points GD's TTF functions take, not pixels.
 */
class SocialImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** The character cell every position is a multiple of, as on the site. */
    private const CELL = 42;

    private const MARGIN = 72;

    private const MEASURE = self::WIDTH - self::MARGIN * 2;

    private const TITLE_BAND_TOP = self::CELL * 2.5;

    private const TITLE_BAND_HEIGHT = self::CELL * 10;

    private const LINE_HEIGHT = 1.35;

    /** Largest first: the first size the title fits at wins. */
    private const LADDER = [118, 96, 78, 64, 54, 46, 40];

    public function __construct(
        private string $titleFont,
        private string $chromeFont,
        private string $artwork,
        private string $avatar,
    ) {}

    /**
     * The largest size at which the title fits the band, and the rows it breaks
     * into. At the floor the title is truncated rather than allowed to overrun.
     *
     * @return array{size: int, lines: list<string>}
     */
    public function fit(string $title): array
    {
        foreach (self::LADDER as $size) {
            $lines = $this->wrap($title, $size);

            if (count($lines) <= $this->maxRows($size)) {
                return ['size' => $size, 'lines' => $lines];
            }
        }

        $size = self::LADDER[count(self::LADDER) - 1];
        $lines = array_slice($this->wrap($title, $size), 0, $this->maxRows($size));
        $last = array_pop($lines);

        while ($last !== '' && $this->width($this->titleFont, $size, $last.'…') > self::MEASURE) {
            $last = mb_substr($last, 0, -1);
        }

        $lines[] = $last.'…';

        return ['size' => $size, 'lines' => $lines];
    }

    /** How many rows of a given size the title band holds. */
    private function maxRows(int $size): int
    {
        return (int) floor(self::TITLE_BAND_HEIGHT / ($size * self::LINE_HEIGHT));
    }

    /**
     * Greedy word wrap to the measure. A single word too wide to ever fit is
     * broken mid-word, because the alternative is a line running off the canvas.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $size): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) as $word) {
            while ($this->width($this->titleFont, $size, $word) > self::MEASURE) {
                $head = $word;

                while (mb_strlen($head) > 1 && $this->width($this->titleFont, $size, $head) > self::MEASURE) {
                    $head = mb_substr($head, 0, -1);
                }

                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                $lines[] = $head;
                $word = mb_substr($word, mb_strlen($head));
            }

            $try = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->width($this->titleFont, $size, $try) > self::MEASURE) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** Rendered width of a string. Font paths must be absolute; GD ignores relative ones. */
    private function width(string $font, int $size, string $text): float
    {
        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            throw new \RuntimeException("Could not measure text with font [{$font}].");
        }

        return abs($box[2] - $box[0]);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter SocialImageFitTest`
Expected: PASS (7 tests)

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Support/SocialImage.php tests/Unit/SocialImageFitTest.php
git add resources/fonts/IBMPlexMono-Bold.ttf app/Support/SocialImage.php tests/Unit/SocialImageFitTest.php
git commit -m "Add the social image title size ladder"
```

---

### Task 3: Draw the image

The three bands, the artwork, the avatar and the cursor.

**Files:**
- Modify: `app/Support/SocialImage.php` (add `write()`, `prompt()` and their private drawing helpers)
- Test: `tests/Unit/SocialImageWriteTest.php` (create)

**Interfaces:**
- Consumes: `fit()` and the constructor from Task 2.
- Produces:
  - `$image->prompt(string $host, string $path, string $file): string` — the prompt line, with the filename elided until it fits.
  - `$image->write(string $destination, string $title, string $prompt, string $domain, ?string $date): void` — writes the PNG.
  - `$image->cursorFits(int $size, string $lastLine): bool` — whether the block cursor has room after the final row.
  - Task 4 calls `prompt()` and `write()`.

**Background the implementer needs:**
- Drawing order matters: background colour, artwork, veil, then text. The veil is a `#1f2329` rectangle at 45% opacity over the whole canvas — GD's alpha runs 0 (opaque) to 127 (transparent), so 45% opacity is `imagecolorallocatealpha(..., 70)`.
- Call `imagealphablending($image, true)` before compositing the avatar, or its transparent corners overwrite the artwork instead of blending with it.
- Baselines, not tops: `imagettftext()`'s `$y` is the text baseline.
- The block cursor is decoration and must never change the wrap or the chosen size. Draw it only when it fits inside the right margin.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SocialImageWriteTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\SocialImage;
use PHPUnit\Framework\TestCase;

class SocialImageWriteTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $directory = dirname(__DIR__, 2).'/storage/framework/testing/social-images';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->destination = $directory.'/card.png';
    }

    protected function tearDown(): void
    {
        if (is_file($this->destination)) {
            unlink($this->destination);
        }

        parent::tearDown();
    }

    private function image(): SocialImage
    {
        $resources = dirname(__DIR__, 2).'/resources';

        return new SocialImage(
            titleFont: $resources.'/fonts/IBMPlexMono-Bold.ttf',
            chromeFont: $resources.'/fonts/IBMPlexMono-Regular.ttf',
            artwork: $resources.'/img/og-background.png',
            avatar: $resources.'/img/casmo.png',
        );
    }

    /** @return array<int, int> colour => pixel count */
    private function colourCounts(string $path): array
    {
        $image = imagecreatefrompng($path);
        $counts = [];

        for ($y = 0; $y < SocialImage::HEIGHT; $y++) {
            for ($x = 0; $x < SocialImage::WIDTH; $x++) {
                $colour = imagecolorat($image, $x, $y);
                $counts[$colour] = ($counts[$colour] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function test_it_writes_an_open_graph_sized_png(): void
    {
        $this->image()->write(
            $this->destination,
            'The interface of the future',
            'mathieu@laptop:~/blog/design$ cat the-interface-of-the-future.txt',
            'mathieuderuiter.nl',
            'August 1, 2026',
        );

        $this->assertFileExists($this->destination);

        [$width, $height, $type] = getimagesize($this->destination);

        $this->assertSame(SocialImage::WIDTH, $width);
        $this->assertSame(SocialImage::HEIGHT, $height);
        $this->assertSame(IMAGETYPE_PNG, $type);
    }

    public function test_it_paints_every_band_in_its_own_colour(): void
    {
        $this->image()->write(
            $this->destination,
            'The interface of the future',
            'mathieu@laptop:~/blog/design$ cat the-interface-of-the-future.txt',
            'mathieuderuiter.nl',
            'August 1, 2026',
        );

        $counts = $this->colourCounts($this->destination);

        // Glyph interiors land on the exact allocated colour; antialiased edges do not.
        $this->assertGreaterThan(10000, $counts[0xe4e4e7] ?? 0, 'title');
        $this->assertGreaterThan(1000, $counts[0x84cc16] ?? 0, 'prompt line');
        $this->assertGreaterThan(500, $counts[0x6b7280] ?? 0, 'identity line');
        $this->assertGreaterThan(500000, $counts[0x1f2329] ?? 0, 'background');
    }

    public function test_it_writes_a_card_without_a_date(): void
    {
        $this->image()->write(
            $this->destination,
            'Nostalgia',
            'mathieu@laptop:~/blog$ cat nostalgia.txt',
            'mathieuderuiter.nl',
            null,
        );

        [$width, $height] = getimagesize($this->destination);

        $this->assertSame(SocialImage::WIDTH, $width);
        $this->assertSame(SocialImage::HEIGHT, $height);
    }

    public function test_a_short_prompt_is_left_alone(): void
    {
        $this->assertSame(
            'mathieu@laptop:~/reviews$ cat volfied.txt',
            $this->image()->prompt('mathieu@laptop', '~/reviews', 'volfied.txt'),
        );
    }

    public function test_a_long_filename_is_elided_to_fit(): void
    {
        $this->assertSame(
            'mathieu@laptop:~/blog/php$ cat composer-update-hanging-on….txt',
            $this->image()->prompt(
                'mathieu@laptop',
                '~/blog/php',
                'composer-update-hanging-on-loading-composer-repositories-with-package-information.txt',
            ),
        );
    }

    public function test_the_cursor_is_drawn_when_the_last_row_leaves_room(): void
    {
        $image = $this->image();
        $fit = $image->fit('Nostalgia');

        $this->assertTrue($image->cursorFits($fit['size'], end($fit['lines'])));
    }

    public function test_the_cursor_is_dropped_when_it_would_cross_the_margin(): void
    {
        $image = $this->image();
        $fit = $image->fit('The interface of the future');

        $this->assertFalse($image->cursorFits($fit['size'], end($fit['lines'])));
    }

    public function test_a_missing_asset_fails_loudly(): void
    {
        $resources = dirname(__DIR__, 2).'/resources';

        $image = new SocialImage(
            titleFont: $resources.'/fonts/IBMPlexMono-Bold.ttf',
            chromeFont: $resources.'/fonts/IBMPlexMono-Regular.ttf',
            artwork: $resources.'/img/does-not-exist.png',
            avatar: $resources.'/img/casmo.png',
        );

        $this->expectException(\RuntimeException::class);

        $image->write($this->destination, 'Nostalgia', 'mathieu@laptop:~$ cat nostalgia.txt', 'mathieuderuiter.nl', null);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SocialImageWriteTest`
Expected: FAIL — `Call to undefined method App\Support\SocialImage::write()`

- [ ] **Step 3: Implement `write()` and `prompt()`**

Add the constants to the existing block in `app/Support/SocialImage.php`:

```php
    private const PROMPT_SIZE = 22;

    private const IDENTITY_SIZE = 20;

    private const AVATAR_SIZE = 56;

    private const BACKGROUND = '#1f2329';

    private const ACCENT = '#84cc16';

    private const TITLE = '#e4e4e7';

    private const MUTED = '#6b7280';
```

Then add these methods:

```php
    /**
     * The prompt line. It never wraps and never shrinks -- a top line that
     * changes size from post to post reads as an accident -- so a filename too
     * long for the measure is elided instead.
     */
    public function prompt(string $host, string $path, string $file): string
    {
        $prefix = $host.':'.$path.'$ cat ';
        $line = $prefix.$file;
        $base = preg_replace('/\.txt$/', '', $file);

        while ($this->width($this->chromeFont, self::PROMPT_SIZE, $line) > self::MEASURE && mb_strlen($base) > 1) {
            $base = mb_substr($base, 0, -1);
            $line = $prefix.$base.'….txt';
        }

        return $line;
    }

    public function write(string $destination, string $title, string $prompt, string $domain, ?string $date): void
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($image, true);
        imagefill($image, 0, 0, $this->colour($image, self::BACKGROUND));

        $this->drawBackground($image);
        $this->drawPrompt($image, $prompt);
        $this->drawTitle($image, $title);
        $this->drawIdentity($image, $domain, $date);

        imagepng($image, $destination);
        imagedestroy($image);
    }

    /** The constellation artwork, veiled so it never competes with the title. */
    private function drawBackground($image): void
    {
        $artwork = $this->load($this->artwork);

        imagecopyresampled(
            $image, $artwork,
            0, 0, 0, 0,
            self::WIDTH, self::HEIGHT,
            imagesx($artwork), imagesy($artwork),
        );
        imagedestroy($artwork);

        // Alpha 70 of 127 is a 45% veil, leaving the lines at roughly 55%.
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $this->colour($image, self::BACKGROUND, 70));
    }

    /** The site's chrome glows; a flat renderer approximates it with a one-pixel halo. */
    private function drawPrompt($image, string $prompt): void
    {
        $baseline = self::CELL * 2;
        $halo = $this->colour($image, self::ACCENT, 105);

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
            imagettftext($image, self::PROMPT_SIZE, 0, self::MARGIN + $dx, $baseline + $dy, $halo, $this->chromeFont, $prompt);
        }

        imagettftext($image, self::PROMPT_SIZE, 0, self::MARGIN, $baseline, $this->colour($image, self::ACCENT), $this->chromeFont, $prompt);
    }

    private function drawTitle($image, string $title): void
    {
        ['size' => $size, 'lines' => $lines] = $this->fit($title);

        $lineHeight = $size * self::LINE_HEIGHT;
        $capHeight = $size * 0.98;
        $block = count($lines) * $lineHeight;

        // Centred in the band: a one-word title should not leave a hole.
        $baseline = self::TITLE_BAND_TOP + (self::TITLE_BAND_HEIGHT - $block) / 2 + $capHeight;
        $colour = $this->colour($image, self::TITLE);
        $end = self::MARGIN;

        foreach ($lines as $index => $line) {
            imagettftext($image, $size, 0, self::MARGIN, (int) $baseline, $colour, $this->titleFont, $line);

            if ($index === count($lines) - 1) {
                $end = self::MARGIN + $this->width($this->titleFont, $size, $line);
                break;
            }

            $baseline += $lineHeight;
        }

        $advance = $this->width($this->titleFont, $size, 'M');

        if ($this->cursorFits($size, end($lines))) {
            imagefilledrectangle(
                $image,
                (int) ($end + $advance * 0.5),
                (int) ($baseline - $capHeight * 0.78),
                (int) ($end + $advance * 1.4),
                (int) $baseline,
                $this->colour($image, self::ACCENT),
            );
        }
    }

    /**
     * The cursor is decoration: it never influenced the wrap or the chosen size,
     * so when there is no room for it after the last row it is dropped rather
     * than allowed to cross the margin.
     */
    public function cursorFits(int $size, string $lastLine): bool
    {
        $end = self::MARGIN + $this->width($this->titleFont, $size, $lastLine);
        $advance = $this->width($this->titleFont, $size, 'M');

        return $end + $advance * 1.4 <= self::WIDTH - self::MARGIN;
    }

    private function drawIdentity($image, string $domain, ?string $date): void
    {
        $baseline = self::CELL * 14;
        $colour = $this->colour($image, self::MUTED);

        $avatar = $this->load($this->avatar);
        $scaled = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled(
            $scaled, $avatar,
            0, 0, 0, 0,
            self::AVATAR_SIZE, self::AVATAR_SIZE,
            imagesx($avatar), imagesy($avatar),
        );

        // Optically centred on the text rather than aligned to its baseline.
        $top = (int) ($baseline - self::IDENTITY_SIZE * 0.72 - (self::AVATAR_SIZE - self::IDENTITY_SIZE) / 2 - 6);
        imagealphablending($image, true);
        imagecopy($image, $scaled, self::MARGIN, $top, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagedestroy($scaled);
        imagedestroy($avatar);

        imagettftext(
            $image, self::IDENTITY_SIZE, 0,
            self::MARGIN + self::AVATAR_SIZE + 20, $baseline,
            $colour, $this->chromeFont, $domain,
        );

        if ($date === null || $date === '') {
            return;
        }

        // Flush right: both ends anchored to the margins is what makes it read as aligned.
        $x = self::WIDTH - self::MARGIN - $this->width($this->chromeFont, self::IDENTITY_SIZE, $date);
        imagettftext($image, self::IDENTITY_SIZE, 0, (int) $x, $baseline, $colour, $this->chromeFont, $date);
    }

    /** A missing asset fails the build rather than shipping a blank card. */
    private function load(string $path)
    {
        $image = is_file($path) ? @imagecreatefrompng($path) : false;

        if ($image === false) {
            throw new \RuntimeException("Could not read image [{$path}].");
        }

        return $image;
    }

    private function colour($image, string $hex, int $alpha = 0)
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter SocialImageWriteTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Look at the result**

Tests prove the pixels are there, not that the image is right. Render one and open it:

```bash
php -r '
require "vendor/autoload.php";
$r = getcwd()."/resources";
$image = new App\Support\SocialImage(
    $r."/fonts/IBMPlexMono-Bold.ttf", $r."/fonts/IBMPlexMono-Regular.ttf",
    $r."/img/og-background.png", $r."/img/casmo.png",
);
$image->write(getcwd()."/storage/framework/testing/preview.png",
    "The interface of the future",
    $image->prompt("mathieu@laptop", "~/blog/design", "the-interface-of-the-future.txt"),
    "mathieuderuiter.nl", "August 1, 2026");
echo "storage/framework/testing/preview.png\n";'
```

Check against the spec: prompt on one line in green at the top, title centred and large, avatar and domain bottom left, date flush bottom right, constellation lines visible but subdued. Reference renders are in `.scratch/og/` if that directory still exists.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Support/SocialImage.php tests/Unit/SocialImageWriteTest.php
git add app/Support/SocialImage.php tests/Unit/SocialImageWriteTest.php
git commit -m "Draw the social image bands"
```

---

### Task 4: Generate one image per entry

Replace the GD block in the service provider with a class that walks the entries.

**Files:**
- Create: `app/Support/SocialImageGenerator.php`
- Modify: `app/Providers/AppServiceProvider.php:29-108` (the whole `SSG::after` closure body)
- Test: `tests/Feature/SocialImageGeneratorTest.php` (create)

**Interfaces:**
- Consumes: `Terminal::forEntry()` (Task 1), `SocialImage::write()` and `SocialImage::prompt()` (Tasks 2–3).
- Produces: `new \App\Support\SocialImageGenerator(SocialImage $image, string $outputPath)` and `$generator->generate(): int`, returning the number of images written.

**Background the implementer needs:**
- **Deviation from the spec:** the spec's file table leaves the entry loop inside `AppServiceProvider::boot()`. This plan puts it in its own class instead, because "which entries get an image" is the requirement most likely to regress silently and a closure registered on `SSG::after` cannot be called from a test. The provider ends up as pure wiring either way, which is what the spec was actually after.
- Filter on `filled($entry->url())`. Measured: 26 published entries have URLs (20 blog + 2 games + 4 pages); the 7 `trivia` entries do not, because that collection has no route, and today they each get a PNG nothing links to.
- The author is `$entry->augmentedValue('author')->value()`, a `Statamic\Auth\File\User` whose `name()` is `Mathieu`. `Terminal::host()` slugs it and appends the host, giving `mathieu@laptop`. Entries with no author fall back to `Terminal::USER`.
- Date format is `F j, Y`, matching the site's readout.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SocialImageGeneratorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\SocialImage;
use App\Support\SocialImageGenerator;
use Statamic\Facades\Entry;
use Tests\TestCase;

class SocialImageGeneratorTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = storage_path('framework/testing/ssg-output');
        $this->deleteOutput();
    }

    protected function tearDown(): void
    {
        $this->deleteOutput();

        parent::tearDown();
    }

    private function deleteOutput(): void
    {
        $directory = $this->output.'/assets/pages';

        if (is_dir($directory)) {
            foreach (glob($directory.'/*.png') as $file) {
                unlink($file);
            }
        }
    }

    private function generator(): SocialImageGenerator
    {
        return new SocialImageGenerator(
            new SocialImage(
                titleFont: resource_path('fonts/IBMPlexMono-Bold.ttf'),
                chromeFont: resource_path('fonts/IBMPlexMono-Regular.ttf'),
                artwork: resource_path('img/og-background.png'),
                avatar: resource_path('img/casmo.png'),
            ),
            $this->output,
        );
    }

    public function test_it_writes_one_image_per_routed_entry(): void
    {
        $expected = Entry::query()->where('published', true)->get()
            ->filter(fn ($entry) => filled($entry->url()))
            ->count();

        $written = $this->generator()->generate();

        $this->assertSame($expected, $written);
        $this->assertCount($written, glob($this->output.'/assets/pages/*.png'));
    }

    public function test_it_names_each_image_after_its_entry_slug(): void
    {
        $this->generator()->generate();

        // Two entries with stable slugs: a post and the home page.
        $this->assertFileExists($this->output.'/assets/pages/nostalgia.png');
        $this->assertFileExists($this->output.'/assets/pages/home.png');

        [$width, $height] = getimagesize($this->output.'/assets/pages/home.png');
        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }

    public function test_it_skips_entries_that_have_no_url(): void
    {
        $unrouted = Entry::query()->where('collection', 'trivia')->get();

        $this->assertNotEmpty($unrouted, 'expected the trivia collection to still hold entries');

        $this->generator()->generate();

        foreach ($unrouted as $entry) {
            $this->assertFileDoesNotExist($this->output.'/assets/pages/'.$entry->slug().'.png');
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SocialImageGeneratorTest`
Expected: FAIL — `Class "App\Support\SocialImageGenerator" not found`

- [ ] **Step 3: Implement the generator**

Create `app/Support/SocialImageGenerator.php`:

```php
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

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $entries = Entry::query()
            ->where('published', true)
            ->get()
            ->filter(fn ($entry) => filled($entry->url()));

        foreach ($entries as $entry) {
            ['path' => $path, 'file' => $file] = Terminal::forEntry($entry);

            $this->image->write(
                $directory.'/'.$entry->slug().'.png',
                (string) $entry->value('title'),
                $this->image->prompt(Terminal::host($this->author($entry)), $path, $file),
                self::DOMAIN,
                $entry->date()?->format('F j, Y'),
            );
        }

        return $entries->count();
    }

    /** The author's name, for the user half of the prompt. */
    private function author($entry): ?string
    {
        $author = $entry->augmentedValue('author')->value();

        return is_object($author) && method_exists($author, 'name') ? $author->name() : null;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter SocialImageGeneratorTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Reduce the provider to wiring**

In `app/Providers/AppServiceProvider.php`, replace the entire `SSG::after(function () { ... });` call — every line of GD from `$pages = Entry::query()` to the closing `});` — with:

```php
        // Every published, routed entry gets its Open Graph image after the
        // static build, keyed by slug to match the og:image tag in default.blade.php.
        SSG::after(function () {
            (new SocialImageGenerator(
                new SocialImage(
                    titleFont: resource_path('fonts/IBMPlexMono-Bold.ttf'),
                    chromeFont: resource_path('fonts/IBMPlexMono-Regular.ttf'),
                    artwork: resource_path('img/og-background.png'),
                    avatar: resource_path('img/casmo.png'),
                ),
                config('statamic.ssg.output_path'),
            ))->generate();
        });
```

Fix the imports at the top of the file: drop `use Statamic\Facades\Entry;` (no longer used there), and add:

```php
use App\Support\SocialImage;
use App\Support\SocialImageGenerator;
```

Keep `use Statamic\StaticSite\SSG;` and the `Blade::directive('content', ...)` line above it exactly as they are.

- [ ] **Step 6: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS — all suites, including `PromptLineTest` and `ExampleTest`.

- [ ] **Step 7: Verify against a real static build**

```bash
php please ssg:generate
ls -l storage/static/assets/pages/ | head
```

Expected: 26 PNGs, no trivia slugs among them. Open two — one short title, one long — and confirm they match the spec. Then check the tag still resolves:

```bash
grep -o 'og:image" content="[^"]*"' storage/static/read/nostalgia/index.html
```

Expected: `og:image" content="https://mathieuderuiter.nl/assets/pages/nostalgia.png"`

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app/Support/SocialImageGenerator.php app/Providers/AppServiceProvider.php tests/Feature/SocialImageGeneratorTest.php
git add app/Support/SocialImageGenerator.php app/Providers/AppServiceProvider.php tests/Feature/SocialImageGeneratorTest.php
git commit -m "Generate a social image for every routed entry"
```

---

## Verification

After Task 4, the whole feature is in place. Confirm, with output in hand:

- [ ] `vendor/bin/phpunit` — every suite passes.
- [ ] `php please ssg:generate` succeeds and writes 26 PNGs to `storage/static/assets/pages/`.
- [ ] `app/Providers/AppServiceProvider.php` is under 45 lines and contains no `imagecreate*` calls.
- [ ] Three images checked by eye against the spec: a one-word title (118pt), a medium one (96pt) and the longest (54pt, four rows).
- [ ] `git log --oneline` shows four commits, one per task.
