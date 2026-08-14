# Trivia tilesheet Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate one PNG holding every trivia icon on a fixed pixel grid, publish it as a real asset in the content asset map on every deploy, and show it above the Books section of the resources page.

**Architecture:** Two classes mirroring the existing `SocialImage` / `SocialImageGenerator` split. `App\Support\Tilesheet` is pure PHP — paths in, PNG out, no framework — so the grid maths is unit-tested without booting anything. `App\Support\TilesheetGenerator` is the Statamic half: it queries the trivia collection, resolves each entry's icon to a file path, then writes the sheet, its `.meta` yaml, and a second copy into the SSG output. It is invoked from the `SSG::after` closure in `AppServiceProvider`, next to the social images.

**Tech Stack:** PHP 8.4, Laravel 12, Statamic, `statamic/ssg`, the GD extension, PHPUnit.

Spec: `docs/superpowers/specs/2026-08-14-trivia-tilesheet-design.md`. Read it before starting.

## Global Constraints

- **Cell size** is the largest source width × the largest source height across the icons on the sheet. Nothing is clamped.
- **Ten columns maximum** (`Tilesheet::COLUMNS = 10`), filling left to right then top to bottom.
- **Gutters** are 1px (`Tilesheet::GUTTER = 1`), between cells only — never around the outside. Sheet size is `cols * (cellW + 1) - 1` by `rows * (cellH + 1) - 1`.
- **Placement inside a cell:** `x = x0 + floor((cellW - w) / 2)`, `y = y0 + ceil((cellH - h) / 2)`. Odd leftovers always favour left and bottom.
- **Pixels are never resampled.** `imagecopy` only, never `imagecopyresampled`. No scaling, no filtering, no colour changed.
- **Alpha is preserved verbatim.** `imagealphablending(false)` and `imagesavealpha(true)` on both the sheet and every source; the sheet starts filled with fully transparent black.
- **Fail loudly.** Every write or read failure throws `RuntimeException` naming the path, per commit 6eaa485. Suppress GD's own warnings with `@` so the exception is the only signal (the codebase already does this with `@mkdir`).
- **Asset path:** `public/assets/trivia-tilesheet.png`, at the assets root beside `tilemap.png`. `assets/trivia/` holds source icons only.
- **Temp dirs in tests:** `storage/framework/testing/...`, never `/tmp` — the sandbox may block it. This is the convention in `tests/Unit/SocialImageWriteTest.php`.
- **Test naming:** `test_it_does_the_thing(): void`, as in the existing tests.
- Run `vendor/bin/pint --dirty` before each commit.

## File Structure

| File | Responsibility |
|---|---|
| `app/Support/Tilesheet.php` | Create. Pure grid maths and PNG writing. Knows nothing about Statamic, entries or where the sheet is published. |
| `app/Support/TilesheetGenerator.php` | Create. Resolves the source icons from the trivia collection, then publishes: public asset, `.meta` yaml, SSG output copy. |
| `app/Providers/AppServiceProvider.php` | Modify (`boot()`, the `SSG::after` closure at lines 31–41). Wires the generator into the build. |
| `tests/Unit/TilesheetTest.php` | Create. Grid maths against tiny generated fixtures, asserting exact pixels. |
| `tests/Feature/TilesheetGeneratorTest.php` | Create. Publication and source resolution against the real content. |
| `public/assets/trivia-tilesheet.png` | Create (generated, committed). |
| `public/assets/.meta/trivia-tilesheet.png.yaml` | Create (generated, committed). |
| `content/collections/pages/resources.md` | Modify. One `<img>` above `## Books`. |
| `resources/css/site.css` | Modify (components layer, after the `.palette` rules near line 464). The `.trivia-sheet` scaling rule. |
| `CONTEXT.md` | Modify (Content section, after **Trivia** at line 84–85). Adds the **Trivia Tilesheet** term. |

Task 1 builds `Tilesheet`, Task 2 builds `TilesheetGenerator`, Task 3 wires the build and commits the generated asset, Task 4 puts it on the page. Each task ends with passing tests and a commit.

## Facts already verified

Do not re-derive these; they were checked against the running app while writing this plan.

- The eight trivia icons and their dimensions, in title-ascending order — the order the sheet must use:

  | # | Icon | Size |
  |---|---|---|
  | 0 | `trivia/1-bit-bio-menace-msdos-game.png` | 9×10 |
  | 1 | `trivia/commander-keen-1bit-flag.png` | 10×14 |
  | 2 | `trivia/jetpack-1bit-dos-game.png` | 9×11 |
  | 3 | `trivia/monster-bash-1bit-ms-dos.png` | 9×9 |
  | 4 | `trivia/quake-1-pixel-logo.png` | 11×10 |
  | 5 | `trivia/scorched-earth-dos-game-1bit.png` | 15×9 |
  | 6 | `trivia/secret-agent-sam-1bit-ms-dos.png` | 9×9 |
  | 7 | `trivia/volfied-1bit-dos-game.png` | 13×9 |

  So the cell is **15×14** and the sheet is **127×14**.
- The icons are white ink (`#FFFFFF`, alpha 0 in GD terms) on fully transparent (alpha 127). Note GD's alpha is inverted from what you may expect: **0 is opaque, 127 is transparent.**
- `Collection::findByHandle('trivia')` is neither `dated()` nor `orderable()`, so its `sortField()` is `title` and `sortDirection()` is `asc`. **A bare `Entry::query()->where('collection', 'trivia')` does NOT return that order** — it returns stache order, which puts commander-keen first. The generator must order explicitly.
- Resolving an entry's icon to an absolute path, exactly as `resources/views/default.blade.php:67-69` does it, then `resolvedPath()`:

  ```php
  $icon = $entry->icon;                    // Statamic\Assets\AssetCollection, or null
  $icon = $icon instanceof Value ? $icon->value() : $icon;
  $icon = is_iterable($icon) ? collect($icon)->first() : $icon;
  $path = $icon?->resolvedPath();          // /abs/path/public/assets/trivia/....png
  ```

  Use the magic getter (`$entry->icon`), **not** `$entry->augmentedValue('icon')` — the latter returns an `OrderedQueryBuilder`, which has no `resolvedPath()`.
- `$entry->icon` is `null` for entries in the `pages` collection — useful for testing the skip path without writing to `content/`.
- `Statamic\Facades\Asset::find('assets::trivia/volfied-1bit-dos-game.png')` returns the `Asset`. The container handle is `assets`.
- `Statamic\Facades\YAML::parse(file_get_contents($path))` reads the `.meta` files.
- `storage/static` is gitignored; `public/assets` is not.

---

### Task 1: `Tilesheet` — the grid

**Files:**
- Create: `app/Support/Tilesheet.php`
- Test: `tests/Unit/TilesheetTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  ```php
  final class Tilesheet
  {
      public const COLUMNS = 10;
      public const GUTTER = 1;

      /**
       * @param  list<string>  $sources  absolute paths to the source PNGs, in tile order
       * @return array{width: int, height: int}|null  null when $sources is empty
       */
      public function write(string $destination, array $sources): ?array
  }
  ```

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/TilesheetTest.php`. The `map()` helper renders a written PNG as one string per row — `#` for opaque white, `.` for fully transparent, `?` for anything else — which makes the placement assertions readable and catches a colour or alpha change at the same time.

```php
<?php

namespace Tests\Unit;

use App\Support\Tilesheet;
use PHPUnit\Framework\TestCase;

class TilesheetTest extends TestCase
{
    private string $directory;

    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $this->directory = dirname(__DIR__, 2).'/storage/framework/testing/tilesheet';

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $this->destination = $this->directory.'/sheet.png';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*.png') as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    /**
     * A source PNG of the given size, filled with opaque white ink except for
     * the pixels named in $blank, which stay transparent.
     *
     * @param  list<array{int, int}>  $blank
     */
    private function source(string $name, int $width, int $height, array $blank = []): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $ink = imagecolorallocatealpha($image, 255, 255, 255, 0);
        $void = imagecolorallocatealpha($image, 0, 0, 0, 127);

        imagefill($image, 0, 0, $ink);

        foreach ($blank as [$x, $y]) {
            imagesetpixel($image, $x, $y, $void);
        }

        $path = $this->directory.'/'.$name.'.png';
        imagepng($image, $path);

        return $path;
    }

    /**
     * The written sheet as one string per row: '#' opaque white, '.' fully
     * transparent, '?' anything else.
     *
     * @return list<string>
     */
    private function map(string $path): array
    {
        $image = imagecreatefrompng($path);
        $rows = [];

        for ($y = 0; $y < imagesy($image); $y++) {
            $row = '';

            for ($x = 0; $x < imagesx($image); $x++) {
                $colour = imagecolorat($image, $x, $y);
                $alpha = ($colour >> 24) & 0x7F;

                $row .= match (true) {
                    $alpha === 127 => '.',
                    $alpha === 0 && ($colour & 0xFFFFFF) === 0xFFFFFF => '#',
                    default => '?',
                };
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function test_it_takes_the_cell_from_the_largest_source_in_both_axes(): void
    {
        // Widest is 3, tallest is 3, so the cell is 3x3 even though neither
        // source is that shape. Two cells plus one gutter is 7 wide.
        $written = (new Tilesheet)->write($this->destination, [
            $this->source('wide', 3, 2),
            $this->source('tall', 2, 3),
        ]);

        $this->assertSame(['width' => 7, 'height' => 3], $written);

        [$width, $height] = getimagesize($this->destination);
        $this->assertSame(7, $width);
        $this->assertSame(3, $height);
    }

    public function test_it_biases_an_odd_leftover_left_and_down(): void
    {
        // The 3x3 source is only there to set the cell size: a 2x2 source on
        // its own would give a 2x2 cell and nothing to bias. In a 3x3 cell the
        // 2x2 tile leaves one row and one column spare -- the empty column
        // goes right, the empty row goes on top.
        (new Tilesheet)->write($this->destination, [
            $this->source('small', 2, 2),
            $this->source('big', 3, 3),
        ]);

        $this->assertSame([
            '....###',
            '##..###',
            '##..###',
        ], $this->map($this->destination));
    }

    public function test_it_centres_an_even_leftover(): void
    {
        // A 2x2 source in a 4x4 cell leaves two spare rows and columns: one
        // each side, no bias to apply.
        (new Tilesheet)->write($this->destination, [
            $this->source('small', 2, 2),
            $this->source('big', 4, 4),
        ]);

        $this->assertSame([
            '.....####',
            '.##..####',
            '.##..####',
            '.....####',
        ], $this->map($this->destination));
    }

    public function test_it_wraps_after_ten_columns(): void
    {
        // 12 sources of 2x2: ten in the first row, two in the second.
        $sources = [];

        for ($i = 0; $i < 12; $i++) {
            $sources[] = $this->source('tile-'.$i, 2, 2);
        }

        $written = (new Tilesheet)->write($this->destination, $sources);

        // 10 * (2 + 1) - 1 = 29 wide, 2 * (2 + 1) - 1 = 5 tall.
        $this->assertSame(['width' => 29, 'height' => 5], $written);

        // The gutter row between the two rows of tiles is empty, and the
        // second row holds exactly two tiles.
        $map = $this->map($this->destination);
        $this->assertSame(str_repeat('.', 29), $map[2]);
        $this->assertSame('##.##'.str_repeat('.', 24), $map[3]);
    }

    public function test_it_copies_ink_and_transparency_verbatim(): void
    {
        // One transparent pixel inside an otherwise inked source has to
        // survive the copy, and the ink has to stay exactly opaque white.
        (new Tilesheet)->write($this->destination, [
            $this->source('holed', 3, 3, blank: [[1, 1]]),
        ]);

        $this->assertSame([
            '###',
            '#.#',
            '###',
        ], $this->map($this->destination));

        $image = imagecreatefrompng($this->destination);
        $ink = imagecolorat($image, 0, 0);

        $this->assertSame(0, ($ink >> 24) & 0x7F, 'ink should be fully opaque');
        $this->assertSame(0xFFFFFF, $ink & 0xFFFFFF, 'ink should be pure white');
    }

    public function test_it_writes_nothing_when_there_are_no_sources(): void
    {
        $this->assertNull((new Tilesheet)->write($this->destination, []));
        $this->assertFileDoesNotExist($this->destination);
    }

    public function test_it_throws_when_a_source_cannot_be_read(): void
    {
        $missing = $this->directory.'/absent.png';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($missing);

        (new Tilesheet)->write($this->destination, [$missing]);
    }

    public function test_it_throws_when_the_sheet_cannot_be_written(): void
    {
        $destination = $this->directory.'/no-such-directory/sheet.png';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($destination);

        (new Tilesheet)->write($destination, [$this->source('small', 2, 2)]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/TilesheetTest.php`
Expected: FAIL — `Class "App\Support\Tilesheet" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/Tilesheet.php`:

```php
<?php

namespace App\Support;

/**
 * Lays a set of small PNGs out on one grid and writes the sheet.
 *
 * The cell is the largest source in each axis, so every tile is addressable
 * by index alone. Nothing is resampled: the sources are pixel art, and they
 * are copied at integer offsets with their alpha untouched.
 *
 * Deliberately free of Laravel and Statamic -- it takes paths, so the grid
 * can be tested without booting anything.
 */
final class Tilesheet
{
    /** Tiles per row before wrapping to the next one. */
    public const COLUMNS = 10;

    /**
     * Transparent pixels between cells, and only between them: no border
     * around the outside. Same arithmetic as public/assets/tilemap.png,
     * whose 339px is 20 tiles of 16px plus 19 gutters.
     */
    public const GUTTER = 1;

    /**
     * @param  list<string>  $sources  absolute paths to the source PNGs, in tile order
     * @return array{width: int, height: int}|null  null when there is nothing to draw
     */
    public function write(string $destination, array $sources): ?array
    {
        if ($sources === []) {
            return null;
        }

        $tiles = array_map(fn (string $source) => $this->read($source), $sources);

        $cellWidth = max(array_map(fn ($tile) => imagesx($tile), $tiles));
        $cellHeight = max(array_map(fn ($tile) => imagesy($tile), $tiles));

        $columns = min(count($tiles), self::COLUMNS);
        $rows = (int) ceil(count($tiles) / self::COLUMNS);

        $width = $columns * ($cellWidth + self::GUTTER) - self::GUTTER;
        $height = $rows * ($cellHeight + self::GUTTER) - self::GUTTER;

        $sheet = imagecreatetruecolor($width, $height);
        imagealphablending($sheet, false);
        imagesavealpha($sheet, true);
        imagefill($sheet, 0, 0, imagecolorallocatealpha($sheet, 0, 0, 0, 127));

        foreach ($tiles as $index => $tile) {
            $tileWidth = imagesx($tile);
            $tileHeight = imagesy($tile);

            $left = ($index % self::COLUMNS) * ($cellWidth + self::GUTTER);
            $top = intdiv($index, self::COLUMNS) * ($cellHeight + self::GUTTER);

            // An odd leftover pixel goes to the right of the tile and above
            // it, which reads as biased left and down.
            imagecopy(
                $sheet,
                $tile,
                $left + intdiv($cellWidth - $tileWidth, 2),
                $top + (int) ceil(($cellHeight - $tileHeight) / 2),
                0,
                0,
                $tileWidth,
                $tileHeight,
            );

            imagedestroy($tile);
        }

        // Suppressed so a failure surfaces as the exception below rather than
        // as a warning from somewhere inside GD.
        $ok = @imagepng($sheet, $destination);
        imagedestroy($sheet);

        if (! $ok) {
            throw new \RuntimeException("Could not write the tilesheet to [{$destination}].");
        }

        return ['width' => $width, 'height' => $height];
    }

    /** @return \GdImage a source with its alpha preserved as authored */
    private function read(string $source): \GdImage
    {
        $image = @imagecreatefrompng($source);

        if (! $image) {
            throw new \RuntimeException("Could not read the icon at [{$source}].");
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/TilesheetTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Support/Tilesheet.php tests/Unit/TilesheetTest.php
git commit -m "Lay the trivia icons out on one tilesheet grid"
```

---

### Task 2: `TilesheetGenerator` — publication

**Files:**
- Create: `app/Support/TilesheetGenerator.php`
- Test: `tests/Feature/TilesheetGeneratorTest.php`

**Interfaces:**
- Consumes: `Tilesheet::write(string $destination, array $sources): ?array` from Task 1.
- Produces:
  ```php
  class TilesheetGenerator
  {
      public const FILENAME = 'trivia-tilesheet.png';

      public function __construct(
          private Tilesheet $tilesheet,
          private string $publicPath,   // public_path()
          private string $outputPath,   // config('statamic.ssg.output_path')
      ) {}

      /** @return array{width: int, height: int}|null */
      public function generate(): ?array

      /**
       * @param  iterable<object>  $entries  anything exposing an ->icon
       * @return list<string>  absolute paths, deduped, order preserved
       */
      public function sources(iterable $entries): array
  }
  ```
  `sources()` is public so the resolution rules — first asset, skip the icon-less, collapse repeats — can be tested against hand-built entries without writing to `content/`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TilesheetGeneratorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Tests\TestCase;

class TilesheetGeneratorTest extends TestCase
{
    private string $public;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway stand-in for public/, so the test never overwrites the
        // committed asset, and for the SSG output directory.
        $this->public = storage_path('framework/testing/tilesheet-public');
        $this->output = storage_path('framework/testing/tilesheet-output');

        $this->deleteDirectories();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectories();

        parent::tearDown();
    }

    private function deleteDirectories(): void
    {
        foreach ([$this->public, $this->output] as $root) {
            foreach ([$root.'/assets/.meta', $root.'/assets', $root] as $directory) {
                if (! is_dir($directory)) {
                    continue;
                }

                foreach (glob($directory.'/*.{png,yaml}', GLOB_BRACE) as $file) {
                    unlink($file);
                }

                @rmdir($directory);
            }
        }
    }

    private function generator(): TilesheetGenerator
    {
        return new TilesheetGenerator(new Tilesheet, $this->public, $this->output);
    }

    private function sheet(): string
    {
        return $this->public.'/assets/trivia-tilesheet.png';
    }

    /** An entry-shaped stub: sources() only ever reads ->icon. */
    private function entryWithIcon(?string $path): object
    {
        return new class($path === null ? null : Asset::find('assets::'.$path))
        {
            public function __construct(public $icon) {}
        };
    }

    public function test_it_writes_the_sheet_for_every_trivia_icon(): void
    {
        // Pinned rather than measured from the icons the generator itself
        // picked, so a broken query can't move both sides of the assertion.
        // 8 icons today (2026-08-14), widest 15px (scorched-earth), tallest
        // 14px (commander-keen): 8 * (15 + 1) - 1 = 127. Update when an icon
        // is added or one of those two extremes changes.
        $written = $this->generator()->generate();

        $this->assertSame(['width' => 127, 'height' => 14], $written);
        $this->assertFileExists($this->sheet());
    }

    public function test_it_writes_the_build_copy_as_well_as_the_asset(): void
    {
        // copyFiles() runs before SSG::after, so the sheet has to be put in
        // the output directory too or it would ship one deploy late.
        $this->generator()->generate();

        $this->assertFileExists($this->sheet());
        $this->assertFileExists($this->output.'/assets/trivia-tilesheet.png');
        $this->assertFileEquals($this->sheet(), $this->output.'/assets/trivia-tilesheet.png');
    }

    public function test_it_writes_asset_meta_describing_the_file_on_disk(): void
    {
        $this->generator()->generate();

        $path = $this->public.'/assets/.meta/trivia-tilesheet.png.yaml';
        $this->assertFileExists($path);

        $meta = YAML::parse(file_get_contents($path));

        $this->assertSame([], $meta['data']);
        $this->assertSame(127, $meta['width']);
        $this->assertSame(14, $meta['height']);
        $this->assertSame('image/png', $meta['mime_type']);
        $this->assertNull($meta['duration']);
        $this->assertSame(filesize($this->sheet()), $meta['size']);
        $this->assertSame(filemtime($this->sheet()), $meta['last_modified']);
    }

    public function test_it_orders_the_tiles_the_way_the_fortunes_are_ordered(): void
    {
        // Trivia is neither dated nor orderable, so the collection sorts by
        // title ascending -- bio-menace first, volfied last. A bare query
        // would return stache order instead, so this pins the first and last
        // tile pixel for pixel against its source.
        $this->generator()->generate();

        $this->assertTileMatches(0, 'public/assets/trivia/1-bit-bio-menace-msdos-game.png');
        $this->assertTileMatches(7, 'public/assets/trivia/volfied-1bit-dos-game.png');
    }

    /** Every pixel of the source appears at tile $index's computed offset. */
    private function assertTileMatches(int $index, string $source): void
    {
        $cellWidth = 15;
        $cellHeight = 14;

        $sheet = imagecreatefrompng($this->sheet());
        $icon = imagecreatefrompng(base_path($source));

        $width = imagesx($icon);
        $height = imagesy($icon);

        $left = $index * ($cellWidth + 1) + intdiv($cellWidth - $width, 2);
        $top = (int) ceil(($cellHeight - $height) / 2);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $this->assertSame(
                    imagecolorat($icon, $x, $y),
                    imagecolorat($sheet, $left + $x, $top + $y),
                    "tile {$index} differs at {$x},{$y}",
                );
            }
        }
    }

    public function test_it_resolves_one_absolute_path_per_icon(): void
    {
        $paths = $this->generator()->sources(
            Entry::query()->where('collection', 'trivia')->get()
        );

        $this->assertCount(8, $paths);

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $this->assertStringStartsWith(base_path('public/assets/trivia/'), $path);
        }
    }

    public function test_it_skips_entries_that_have_no_icon(): void
    {
        // Pages carry no icon field at all.
        $pages = Entry::query()->where('collection', 'pages')->get();

        $this->assertNotEmpty($pages, 'expected the pages collection to still hold entries');

        $this->assertSame([], $this->generator()->sources($pages));
    }

    public function test_it_collapses_two_entries_sharing_one_icon(): void
    {
        $paths = $this->generator()->sources([
            $this->entryWithIcon('trivia/volfied-1bit-dos-game.png'),
            $this->entryWithIcon('trivia/quake-1-pixel-logo.png'),
            $this->entryWithIcon('trivia/volfied-1bit-dos-game.png'),
            $this->entryWithIcon(null),
        ]);

        $this->assertSame([
            base_path('public/assets/trivia/volfied-1bit-dos-game.png'),
            base_path('public/assets/trivia/quake-1-pixel-logo.png'),
        ], $paths);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Feature/TilesheetGeneratorTest.php`
Expected: FAIL — `Class "App\Support\TilesheetGenerator" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/TilesheetGenerator.php`:

```php
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/TilesheetGeneratorTest.php`
Expected: PASS, 7 tests.

If `test_it_writes_asset_meta_describing_the_file_on_disk` fails on `duration`, check whether `YAML::dump` wrote `duration: null` — the assertion wants the key present and null, which `YAML::parse` gives back as `null`.

- [ ] **Step 5: Run the whole suite**

Run: `php artisan test`
Expected: PASS. Nothing else touches these paths, so no existing test should move.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Support/TilesheetGenerator.php tests/Feature/TilesheetGeneratorTest.php
git commit -m "Publish the trivia tilesheet as an asset and a build copy"
```

---

### Task 3: Wire it into the build and commit the asset

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:31-41`

**Interfaces:**
- Consumes: `TilesheetGenerator::__construct(Tilesheet, string $publicPath, string $outputPath)` and `generate()` from Task 2.
- Produces: `public/assets/trivia-tilesheet.png` and `public/assets/.meta/trivia-tilesheet.png.yaml`, both committed. Task 4's `<img src="/assets/trivia-tilesheet.png">` depends on them existing.

- [ ] **Step 1: Add the generator to the SSG hook**

In `app/Providers/AppServiceProvider.php`, add to the imports:

```php
use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
```

Then extend the existing `SSG::after` closure — keep the social images exactly as they are and append:

```php
            // One sheet of every trivia icon, published as an asset and copied
            // into the build, since copyFiles() has already run by now.
            (new TilesheetGenerator(
                new Tilesheet,
                public_path(),
                config('statamic.ssg.output_path'),
            ))->generate();
```

- [ ] **Step 2: Generate the committed asset**

This writes the real files under `public/assets`. The output path is a throwaway directory, because the point here is the asset, not a build:

```bash
php artisan tinker --execute="(new App\Support\TilesheetGenerator(new App\Support\Tilesheet, public_path(), storage_path('framework/testing/tilesheet-output')))->generate();"
```

- [ ] **Step 3: Verify what landed**

```bash
file public/assets/trivia-tilesheet.png
cat public/assets/.meta/trivia-tilesheet.png.yaml
```

Expected: `PNG image data, 127 x 14`, and a yaml carrying `width: 127`, `height: 14`, `mime_type: image/png`, plus a `size` and `last_modified` matching the PNG.

Open the PNG and look at it. Eight icons, one row, each sitting in its own 15×14 cell, nothing clipped, nothing tinted, no icon touching its neighbour.

- [ ] **Step 4: Verify the deploy path end to end**

```bash
php please ssg:generate
file storage/static/assets/trivia-tilesheet.png
```

Expected: the build completes and the sheet is `127 x 14` in the output. If `ssg:generate` fails for reasons unrelated to this change (missing env, no APP_KEY), say so rather than skipping the step silently — the feature test already covers the generator, but this is the only check that the hook actually fires.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Providers/AppServiceProvider.php public/assets/trivia-tilesheet.png public/assets/.meta/trivia-tilesheet.png.yaml
git commit -m "Generate the trivia tilesheet on every build"
```

---

### Task 4: Show it above Books

**Files:**
- Modify: `content/collections/pages/resources.md` (above the `## Books` heading)
- Modify: `resources/css/site.css` (components layer, after the `.palette` rules)
- Modify: `CONTEXT.md` (Content section, after the **Trivia** entry)

**Interfaces:**
- Consumes: the committed `public/assets/trivia-tilesheet.png` from Task 3, served at `/assets/trivia-tilesheet.png`.
- Produces: nothing later tasks depend on. This is the last task.

- [ ] **Step 1: Put the image in the page**

In `content/collections/pages/resources.md`, immediately above the `## Books` heading, add:

```html
<img class="trivia-sheet" src="/assets/trivia-tilesheet.png" alt="Every icon from the fortunes, in a grid" />
```

Inline HTML because `resources/views/pages/page.blade.php` renders the whole body in one `@content($content)` call and the Books heading is inside that string — a Blade component cannot sit between the links list and Books. The book covers on this page are already written this way.

No `width` or `height` attributes: the sheet's dimensions change when an icon is added, and a stale hardcoded width would distort pixel art.

- [ ] **Step 2: Add the scaling rule**

In `resources/css/site.css`, in the `@layer components` block after the `.palette` rules:

```css
  /* The trivia tilesheet: 1-bit art at an exact integer scale, so no pixel is
     ever half a pixel. zoom rather than a width because the sheet grows as
     icons are added and any fixed width would eventually distort it; transform
     would scale the paint and leave the unscaled box in the flow.

     Untinted, like .fortune__icon and for the same reason. */
  .trivia-sheet {
    display: block;
    image-rendering: pixelated;
    zoom: 4;
  }
```

And in the narrow-screen media query region, so a full ten columns (636px at 4×) cannot overflow a phone:

```css
  @media (max-width: 767px) {
    .trivia-sheet {
      zoom: 2;
    }
  }
```

- [ ] **Step 3: Look at it**

```bash
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/resources` and check, above Books: the sheet is crisp with hard pixel edges and no blur; the icons are white on the screen background, not boxed or tinted; it fits the reading column. Then narrow the window below 768px and confirm it shrinks to 2× and stays inside the viewport.

- [ ] **Step 4: Name it in the glossary**

In `CONTEXT.md`, in the Content section after the **Trivia** entry, add:

```markdown
**Trivia Tilesheet**
: Every **Trivia** icon on one generated grid, one cell per icon, sized to the largest
  icon in each axis and shown on the resources page. Distinct from the **Dungeon**'s
  tilemap, which is hand-authored artwork: this one is derived from the collection and
  rewritten on every build.
```

- [ ] **Step 5: Run the suite and commit**

Run: `php artisan test`
Expected: PASS.

```bash
git add content/collections/pages/resources.md resources/css/site.css CONTEXT.md
git commit -m "Show the trivia tilesheet above the books on resources"
```

---

## Done when

- `php artisan test` passes.
- `php please ssg:generate` puts a 127×14 `trivia-tilesheet.png` in `storage/static/assets/`.
- `public/assets/trivia-tilesheet.png` and its `.meta` yaml are committed.
- The resources page shows the sheet above Books, crisp, at 4× on desktop and 2× under 768px.
- Adding a trivia entry with a new icon and rebuilding grows the sheet by one tile, in title order, with no other change needed.
