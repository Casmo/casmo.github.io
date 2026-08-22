# itch.io icon pack Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A GitHub Action that, whenever the trivia icons change, pushes a fresh download to the itch.io page and produces the page markup to paste into it.

**Architecture:** One extraction plus three pure-PHP units and two pieces of wiring. `TriviaIcons` becomes the single definition of "the icon set, in order" — `TilesheetGenerator` already had it, half-private and title-less, so it moves out and both the sheet and the pack read the same thing. `Upscale`, `IconPack` and `ItchPage` are framework-free (paths and arrays in, files or a string out) so they test without booting Laravel. `ItchAssetsGenerator` publishes 4× images into the SSG output for itch to hotlink, and `trivia:pack` builds the staging directory butler pushes.

**Tech Stack:** PHP 8.4 (8.5 locally), Laravel 13, Statamic 6, `statamic/ssg`, GD, PHPUnit 12, GitHub Actions, `butler`.

Spec: `docs/superpowers/specs/2026-08-22-itch-icon-pack-design.md`. Read it before starting.

## Global Constraints

- **The suite is red before you start.** Six pre-existing failures. Task 1 fixes them and nothing else may begin until `php artisan test` is green.
- **Test base classes:** unit tests extend `PHPUnit\Framework\TestCase` (no Laravel boot); feature tests extend `Tests\TestCase`.
- **Temp directories in tests:** always under `storage/framework/testing/<name>`. Never `/tmp` — the sandbox may block it. This is an existing convention, see `tests/Unit/TilesheetTest.php`.
- **Never `imagecopyresampled`.** It interpolates. Pixel art is scaled with `imagecopyresized` only, at integer factors.
- **Every GD destination** gets `imagealphablending($img, false)` and `imagesavealpha($img, true)`, and is filled with `imagecolorallocatealpha($img, 0, 0, 0, 127)` before anything is drawn. Verified: this preserves alpha exactly through `imagecopyresized`.
- **Every write failure throws `RuntimeException` naming the path.** Existing convention in `TilesheetGenerator`.
- **Shared plumbing, introduced in Task 4.** `App\Support\Files` owns `directory()` and `put()`; `Tests\Concerns\RemovesDirectories` owns the recursive test cleanup. Tasks 5 and 7 inject/use them rather than restating either. Do not add a private `directory()` or `put()` to any new class. `TilesheetGenerator` and `SocialImageGenerator` keep their own copies — they are tested and passing, and churning them is out of scope.
- **Upscale factor is 4.** Sheet 169×29 → 676×116.
- **Nothing this feature produces is committed.** All artefacts are derived on demand.
- **The generated HTML carries no `class`, no `style`, no `<style>`, no `width`, no `height`.** itch.io sanitises description HTML and any of those may be dropped.
- **Icons ship at 1×, byte-identical to source.** The upscales are for the page only.
- Run a single test with `php artisan test --filter=<TestClassName>`.
- **Commands auto-register.** `app/Console/Commands/` does not exist yet, and
  `bootstrap/app.php` never calls `withCommands()`. Verified empirically anyway: a
  class dropped into `app/Console/Commands/` is discovered with no bootstrap
  change. Do not edit `bootstrap/app.php`.

---

### Task 1: Restore a green baseline

Six tests fail on `main`, from two unrelated content drifts. Nothing else in this plan is judgeable until they pass, because every later task's gate is "run the suite and see only your new test fail".

**Files:**
- Modify: `public/assets/trivia-tilesheet.png` (regenerated, 127×14 → 169×29)
- Modify: `public/assets/.meta/trivia-tilesheet.png.yaml` (regenerated)
- Modify: `tests/Feature/TilesheetGeneratorTest.php:73-84,131-147,149-185,187-199`
- Modify: `tests/Feature/SocialImageGeneratorTest.php:59`
- Create: `public/assets/trivia/.meta/*.yaml` (5 files, side effect of regeneration)
- Create: `public/assets/.meta/tilemap.png.yaml` (already untracked in the working tree)

**Interfaces:**
- Consumes: nothing.
- Produces: a green `php artisan test`, and a committed `trivia-tilesheet.png` at 169×29 that later tasks may read.

- [ ] **Step 1: Confirm the failures and their causes**

Run: `php artisan test`

Expected: FAIL, 6 failures — 5 in `TilesheetGeneratorTest`, 1 in `SocialImageGeneratorTest`. Read each message. You should see `actual size 11 matches expected size 8`, a `169` vs `127` mismatch, and `27 is identical to 26`. If you see a different set of failures, stop and report — the repo has moved since this plan was written.

- [ ] **Step 2: Regenerate the committed tilesheet**

Eleven trivia entries now, but the committed sheet holds eight tiles. Regenerating needs only the generator, not a full `php please ssg:generate` (which would also want a vite manifest):

```bash
php artisan tinker --execute='(new App\Support\TilesheetGenerator(
    new App\Support\Tilesheet, public_path(), config("statamic.ssg.output_path")
))->generate();'
```

Expected output: `{"width":169,"height":29}`

- [ ] **Step 3: Verify the regenerated asset on disk**

```bash
php -r '$s=getimagesize("public/assets/trivia-tilesheet.png"); echo "$s[0]x$s[1]\n";'
cat public/assets/.meta/trivia-tilesheet.png.yaml
```

Expected: `169x29`, and the yaml reports `width: 169`, `height: 29`.

- [ ] **Step 4: Re-pin the sheet dimension test**

In `tests/Feature/TilesheetGeneratorTest.php`, replace the body of `test_it_writes_the_sheet_for_every_trivia_icon`:

```php
    public function test_it_writes_the_sheet_for_every_trivia_icon(): void
    {
        // Pinned rather than measured from the icons the generator itself
        // picked, so a broken query can't move both sides of the assertion.
        // 11 icons today (2026-08-22), widest 16px (gobliiins), tallest 14px
        // (commander-keen), so the cell is 16x14 and the sheet wraps to two
        // rows: 10 * (16 + 1) - 1 = 169 wide, 2 * (14 + 1) - 1 = 29 tall.
        // Update when an icon is added or one of those two extremes changes.
        $written = $this->generator()->generate();

        $this->assertSame(['width' => 169, 'height' => 29], $written);
        $this->assertFileExists($this->sheet());
    }
```

- [ ] **Step 5: Re-pin the meta test**

In the same file, in `test_it_writes_asset_meta_describing_the_file_on_disk`, change the two dimension assertions:

```php
        $this->assertSame(169, $meta['width']);
        $this->assertSame(29, $meta['height']);
```

- [ ] **Step 6: Re-pin the ordering test**

The sheet is now two rows, so the last tile is index 10 — the first tile of row two — and the cell grew to 16 wide. `assertTileMatches` computed `$top` as if there were only one row; it now needs the row offset. Replace both methods:

```php
    public function test_it_orders_the_tiles_the_way_the_fortunes_are_ordered(): void
    {
        // Trivia is neither dated nor orderable, so the collection sorts by
        // title ascending -- bio-menace first, volfied last. A bare query
        // would return stache order instead, so this pins the first and last
        // tile pixel for pixel against its source. Volfied is index 10, the
        // first tile of the second row, which also covers the row wrap.
        $this->generator()->generate();

        $this->assertTileMatches(0, 'public/assets/trivia/1-bit-bio-menace-msdos-game.png');
        $this->assertTileMatches(10, 'public/assets/trivia/volfied-1bit-dos-game.png');
    }

    /** Every pixel of the source appears at tile $index's computed offset. */
    private function assertTileMatches(int $index, string $source): void
    {
        $cellWidth = 16;
        $cellHeight = 14;
        $columns = 10;

        $sheet = imagecreatefrompng($this->sheet());
        $icon = imagecreatefrompng(base_path($source));

        $width = imagesx($icon);
        $height = imagesy($icon);

        $left = ($index % $columns) * ($cellWidth + 1) + intdiv($cellWidth - $width, 2);
        $top = intdiv($index, $columns) * ($cellHeight + 1)
            + (int) ceil(($cellHeight - $height) / 2);

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
```

- [ ] **Step 7: Re-pin the icon count test**

In `test_it_resolves_one_absolute_path_per_icon`:

```php
        $this->assertCount(11, $paths);
```

- [ ] **Step 8: Re-pin the social image count**

In `tests/Feature/SocialImageGeneratorTest.php:59`, the routed-entry count drifted when blog posts were added. Unrelated to this feature, but the suite is the gate for every task below:

```php
        $expected = 27;
```

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`

Expected: PASS, 0 failures. `test_it_matches_the_committed_asset_pixel_for_pixel` passing is the proof that step 2 actually landed — it compares generated pixels against the committed file.

- [ ] **Step 10: Commit**

The regeneration also made Statamic write `.meta` yaml for trivia icons that lacked it. Six of eleven were already committed; these are the rest, plus `tilemap.png.yaml` which was already untracked before this work.

```bash
git add public/assets/trivia-tilesheet.png \
        public/assets/.meta/trivia-tilesheet.png.yaml \
        public/assets/.meta/tilemap.png.yaml \
        public/assets/trivia/.meta/ \
        tests/Feature/TilesheetGeneratorTest.php \
        tests/Feature/SocialImageGeneratorTest.php
git commit -m "fix: regenerate trivia tilesheet and re-pin drifted test counts"
```

---

### Task 2: `Upscale`

Integer nearest-neighbour PNG scaling. Pure PHP, no framework.

**Files:**
- Create: `app/Support/Upscale.php`
- Test: `tests/Unit/UpscaleTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Support\Upscale::write(string $destination, string $source, int $factor): array{width: int, height: int}`. Throws `InvalidArgumentException` for `$factor < 1`, `RuntimeException` for unreadable source or unwritable destination.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/UpscaleTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Upscale;
use PHPUnit\Framework\TestCase;

class UpscaleTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $this->directory = dirname(__DIR__, 2).'/storage/framework/testing/upscale';

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*.png') as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    /**
     * A source PNG whose pixels are given as [x, y] => [r, g, b, alpha],
     * alpha in GD's 0 (opaque) to 127 (transparent) range.
     *
     * @param  array<int, array{int, int, int, int, int}>  $pixels  [x, y, r, g, b, a]
     */
    private function source(string $name, int $width, int $height, array $pixels): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        foreach ($pixels as [$x, $y, $r, $g, $b, $a]) {
            imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $a));
        }

        $path = $this->directory.'/'.$name.'.png';
        imagepng($image, $path);

        return $path;
    }

    /** @return array{int, int, int, int} [r, g, b, alpha] at that pixel */
    private function pixel(string $path, int $x, int $y): array
    {
        $colour = imagecolorat(imagecreatefrompng($path), $x, $y);

        return [
            ($colour >> 16) & 0xFF,
            ($colour >> 8) & 0xFF,
            $colour & 0xFF,
            ($colour >> 24) & 0x7F,
        ];
    }

    public function test_it_scales_the_dimensions_by_the_factor(): void
    {
        $source = $this->source('dims', 3, 5, [[0, 0, 255, 255, 255, 0]]);
        $destination = $this->directory.'/dims-out.png';

        $written = (new Upscale)->write($destination, $source, 4);

        $this->assertSame(['width' => 12, 'height' => 20], $written);
        $this->assertFileExists($destination);
    }

    public function test_it_turns_every_source_pixel_into_a_solid_block(): void
    {
        // Dimensions alone would pass with interpolation, which is the failure
        // this class exists to avoid. Assert the far corner of one block and
        // the near corner of its neighbour: under interpolation the boundary
        // pixels would blend toward each other instead of stepping.
        $source = $this->source('blocks', 2, 1, [
            [0, 0, 255, 255, 255, 0],
            [1, 0, 255, 0, 0, 0],
        ]);
        $destination = $this->directory.'/blocks-out.png';

        (new Upscale)->write($destination, $source, 4);

        $this->assertSame([255, 255, 255, 0], $this->pixel($destination, 0, 0));
        $this->assertSame([255, 255, 255, 0], $this->pixel($destination, 3, 3));
        $this->assertSame([255, 0, 0, 0], $this->pixel($destination, 4, 0));
        $this->assertSame([255, 0, 0, 0], $this->pixel($destination, 7, 3));
    }

    public function test_it_preserves_alpha_verbatim(): void
    {
        $source = $this->source('alpha', 2, 1, [
            [0, 0, 0, 0, 0, 127],
            [1, 0, 0, 255, 0, 63],
        ]);
        $destination = $this->directory.'/alpha-out.png';

        (new Upscale)->write($destination, $source, 4);

        // Transparent stays fully transparent across its whole block.
        $this->assertSame(127, $this->pixel($destination, 0, 0)[3]);
        $this->assertSame(127, $this->pixel($destination, 3, 3)[3]);

        // Partial alpha is neither flattened to opaque nor blended.
        $this->assertSame([0, 255, 0, 63], $this->pixel($destination, 4, 0));
        $this->assertSame([0, 255, 0, 63], $this->pixel($destination, 7, 3));
    }

    public function test_a_factor_of_one_copies_the_image(): void
    {
        $source = $this->source('one', 2, 2, [[0, 0, 255, 255, 255, 0]]);
        $destination = $this->directory.'/one-out.png';

        $written = (new Upscale)->write($destination, $source, 1);

        $this->assertSame(['width' => 2, 'height' => 2], $written);
        $this->assertSame([255, 255, 255, 0], $this->pixel($destination, 0, 0));
    }

    public function test_it_rejects_a_factor_below_one(): void
    {
        $source = $this->source('bad', 2, 2, [[0, 0, 255, 255, 255, 0]]);

        foreach ([0, -1] as $factor) {
            try {
                (new Upscale)->write($this->directory.'/bad-out.png', $source, $factor);
                $this->fail("expected factor {$factor} to be rejected");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString((string) $factor, $e->getMessage());
            }
        }
    }

    public function test_it_throws_when_the_source_cannot_be_read(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing\.png/');

        (new Upscale)->write(
            $this->directory.'/out.png',
            $this->directory.'/missing.png',
            4,
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UpscaleTest`

Expected: FAIL — `Class "App\Support\Upscale" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/Upscale.php`:

```php
<?php

namespace App\Support;

/**
 * Scales an image up by a whole number, one source pixel to one solid block.
 *
 * Exists because the icons are 8-16px and have to be shown at a readable size
 * somewhere that cannot be trusted to keep a stylesheet -- itch.io sanitises
 * description HTML, so `image-rendering: pixelated` may be dropped. Scaling
 * ahead of publication means the markup needs no CSS at all.
 *
 * Deliberately free of Laravel and Statamic: it takes paths, so it can be
 * tested without booting anything.
 */
final class Upscale
{
    /**
     * @param  int  $factor  whole-number scale, at least 1
     * @return array{width: int, height: int}
     */
    public function write(string $destination, string $source, int $factor): array
    {
        if ($factor < 1) {
            throw new \InvalidArgumentException(
                "The upscale factor must be at least 1, got [{$factor}]."
            );
        }

        $image = $this->read($source);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $width = $sourceWidth * $factor;
        $height = $sourceHeight * $factor;

        $scaled = imagecreatetruecolor($width, $height);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));

        // imagecopyresized samples nearest neighbour, so at a whole-number
        // factor every source pixel becomes an exact factor x factor block
        // with its alpha verbatim. imagecopyresampled would interpolate, which
        // is precisely the mush this class exists to prevent.
        imagecopyresized(
            $scaled,
            $image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        // Suppressed so a failure surfaces as the exception below rather than
        // as a warning from somewhere inside GD.
        if (! @imagepng($scaled, $destination)) {
            throw new \RuntimeException("Could not write the upscale to [{$destination}].");
        }

        return ['width' => $width, 'height' => $height];
    }

    /** @return \GdImage a source with its alpha preserved as authored */
    private function read(string $source): \GdImage
    {
        $contents = @file_get_contents($source);

        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image) {
            throw new \RuntimeException("Could not read the image at [{$source}].");
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UpscaleTest`

Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Upscale.php tests/Unit/UpscaleTest.php
git commit -m "feat: add integer nearest-neighbour image upscaler"
```

---

### Task 3: `TriviaIcons`

Extract "the icon set, in order" out of `TilesheetGenerator` so the pack and the sheet cannot disagree.

**Files:**
- Create: `app/Support/TriviaIcons.php`
- Create: `tests/Feature/TriviaIconsTest.php`
- Modify: `app/Support/TilesheetGenerator.php:78-118` (delegate `sources()`, drop `entries()`)
- Modify: `app/Providers/AppServiceProvider.php:46-51` (pass the new dependency)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `App\Support\TriviaIcons::all(): list<array{path: string, title: string}>` — published trivia entries, title ascending, first asset of `icon`, deduped by path keeping the first occurrence.
  - `App\Support\TriviaIcons::resolve(iterable $entries): list<array{path: string, title: string}>` — the same mapping over any entry-shaped iterable. `all()` is `resolve($this->entries())`.
  - `TilesheetGenerator::__construct(Tilesheet $tilesheet, TriviaIcons $icons, string $publicPath, string $outputPath)` — note the new second parameter.
  - `TilesheetGenerator::sources(iterable $entries): list<string>` — signature unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TriviaIconsTest.php`:

```php
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

        // 11 published trivia entries today (2026-08-22), each with an icon.
        $this->assertCount(11, $pairs);

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
        $this->assertStringStartsWith('Bio Menace', $pairs[0]['title']);
        $this->assertStringStartsWith('Volfied', $pairs[10]['title']);
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TriviaIconsTest`

Expected: FAIL — `Class "App\Support\TriviaIcons" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/TriviaIcons.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TriviaIconsTest`

Expected: PASS, 5 tests.

- [ ] **Step 5: Delegate from `TilesheetGenerator`**

The generator now takes `TriviaIcons` and stops resolving the set itself. In `app/Support/TilesheetGenerator.php`:

Replace the `use` block additions and constructor:

```php
    public function __construct(
        private Tilesheet $tilesheet,
        private TriviaIcons $icons,
        private string $publicPath,
        private string $outputPath,
    ) {}
```

Replace the `generate()` line that resolved sources. `all()` returns pairs, so take
the path column:

```php
            $written = $this->tilesheet->write(
                $temp,
                array_column($this->icons->all(), 'path'),
            );
```

Replace the whole `sources()` method with a delegation that keeps its published signature:

```php
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
```

Delete the now-unused `private function entries()` and the `COLLECTION` constant's only consumer. Keep `TilesheetGenerator::COLLECTION` if anything else references it — check with `grep -rn "TilesheetGenerator::COLLECTION" app/ tests/` and delete it if that returns nothing. Remove the now-unused imports (`Collection`, `Entry`, `Value`) but keep `YAML`.

- [ ] **Step 6: Update the service provider**

In `app/Providers/AppServiceProvider.php`, the `SSG::after` closure constructs the generator. Add the new argument:

```php
            (new TilesheetGenerator(
                new Tilesheet,
                new TriviaIcons,
                public_path(),
                config('statamic.ssg.output_path'),
            ))->generate();
```

Add `use App\Support\TriviaIcons;` to the imports.

- [ ] **Step 7: Update the generator's test to the new constructor**

In `tests/Feature/TilesheetGeneratorTest.php`, the `generator()` helper:

```php
    private function generator(): TilesheetGenerator
    {
        return new TilesheetGenerator(
            new Tilesheet,
            new TriviaIcons,
            $this->public,
            $this->output,
        );
    }
```

Add `use App\Support\TriviaIcons;` to the imports.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`

Expected: PASS, 0 failures. `TilesheetGeneratorTest` passing unchanged in behaviour is the check on this extraction — the sheet must come out identical to Task 1's committed asset.

- [ ] **Step 9: Commit**

```bash
git add app/Support/TriviaIcons.php app/Support/TilesheetGenerator.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/TriviaIconsTest.php tests/Feature/TilesheetGeneratorTest.php
git commit -m "refactor: extract the trivia icon set into TriviaIcons"
```

---

### Task 4: `Files`, the test trait, and `IconPack`

Stage the directory butler pushes. This task also introduces the two shared
helpers the remaining tasks lean on, because it is the first task that needs
either.

**Files:**
- Create: `app/Support/Files.php`
- Create: `tests/Concerns/RemovesDirectories.php`
- Create: `app/Support/IconPack.php`
- Test: `tests/Unit/IconPackTest.php`

**Interfaces:**
- Consumes: `TriviaIcons`' pair shape `array{path: string, title: string}` (Task 3) — as a plain array, not the class.
- Produces:
  - `App\Support\Files::directory(string $directory): string` — creates it if missing, returns it, throws `RuntimeException` naming the path. Tasks 5 and 7 use this.
  - `App\Support\Files::put(string $path, string $contents): void` — throws `RuntimeException` naming the path. Task 7 uses this.
  - `Tests\Concerns\RemovesDirectories::remove(string $path): void` — recursive delete, tolerates a missing path. Tasks 5 and 7 use this.
  - `App\Support\IconPack::ICONS = 'icons'`, `::README = 'README.txt'` — Tasks 5 and 6 read `ICONS`.
  - `App\Support\IconPack::write(string $staging, array $icons, string $tilesheet): int` — returns the icon count. Writes `<staging>/icons/*.png`, `<staging>/trivia-tilesheet.png`, `<staging>/README.txt`.

- [ ] **Step 1: Create the shared file helper**

The mkdir idiom already exists twice in `app/Support` — as a private method on
`TilesheetGenerator` and inlined in `SocialImageGenerator`. Three more copies were
the alternative. `TilesheetGenerator` and `SocialImageGenerator` are deliberately
left alone: they are tested and passing, and churning them buys nothing here.

Create `app/Support/Files.php`:

```php
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
```

- [ ] **Step 2: Create the test trait**

Three test files in this plan need the same recursive delete. Create
`tests/Concerns/RemovesDirectories.php`:

```php
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
```

Confirm the autoloader picks up `Tests\Concerns`. `composer.json` maps `Tests\` to
`tests/`, so no change should be needed — verify with:

```bash
php -r 'require "vendor/autoload.php"; var_dump(trait_exists("Tests\\Concerns\\RemovesDirectories"));'
```

Expected: `bool(true)`. If it is `false`, run `composer dump-autoload` and retry.

- [ ] **Step 3: Write the failing test**

Create `tests/Unit/IconPackTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Files;
use App\Support\IconPack;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\RemovesDirectories;

class IconPackTest extends TestCase
{
    use RemovesDirectories;

    private string $directory;

    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $this->directory = dirname(__DIR__, 2).'/storage/framework/testing/icon-pack';
        $this->staging = $this->directory.'/pack';

        $this->remove($this->directory);
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);

        parent::tearDown();
    }

    private function pack(): IconPack
    {
        return new IconPack(new Files);
    }

    /** A 2x2 opaque white PNG at $name.png, returned as an absolute path. */
    private function png(string $name): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 0));

        $path = $this->directory.'/'.$name.'.png';
        imagepng($image, $path);

        return $path;
    }

    /** @return list<array{path: string, title: string}> */
    private function icons(): array
    {
        return [
            ['path' => $this->png('alley-cat'), 'title' => 'Alley Cat has a blue-pink palette.'],
            ['path' => $this->png('volfied'), 'title' => 'Volfied had an escaping snake.'],
        ];
    }

    public function test_it_stages_the_icons_the_sheet_and_the_readme(): void
    {
        $sheet = $this->png('sheet');

        $count = $this->pack()->write($this->staging, $this->icons(), $sheet);

        $this->assertSame(2, $count);
        $this->assertFileExists($this->staging.'/icons/alley-cat.png');
        $this->assertFileExists($this->staging.'/icons/volfied.png');
        $this->assertFileExists($this->staging.'/trivia-tilesheet.png');
        $this->assertFileExists($this->staging.'/README.txt');
    }

    public function test_it_ships_the_icons_byte_identical_to_their_sources(): void
    {
        // The pack is the real pixels. The upscales exist for the page, and a
        // buyer dropping a 4x PNG into a tilemap editor would be getting a
        // lossy version of what they paid for.
        $icons = $this->icons();

        $this->pack()->write($this->staging, $icons, $this->png('sheet'));

        foreach ($icons as $icon) {
            $this->assertFileEquals(
                $icon['path'],
                $this->staging.'/icons/'.basename($icon['path']),
            );
        }
    }

    public function test_the_readme_pairs_each_filename_with_its_own_title(): void
    {
        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $readme = file_get_contents($this->staging.'/README.txt');

        $this->assertMatchesRegularExpression(
            '/alley-cat\.png\s+Alley Cat has a blue-pink palette\./',
            $readme,
        );
        $this->assertMatchesRegularExpression(
            '/volfied\.png\s+Volfied had an escaping snake\./',
            $readme,
        );
    }

    public function test_the_readme_lists_the_icons_in_the_order_given(): void
    {
        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $readme = file_get_contents($this->staging.'/README.txt');

        $this->assertLessThan(
            strpos($readme, 'volfied.png'),
            strpos($readme, 'alley-cat.png'),
            'the README should read in sheet order',
        );
    }

    public function test_it_writes_nothing_but_the_pack(): void
    {
        // Anything else in here ships to buyers. page.html in particular is
        // written beside the staging directory, never inside it.
        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $entries = array_values(array_diff(scandir($this->staging), ['.', '..']));
        sort($entries);

        $this->assertSame(['README.txt', 'icons', 'trivia-tilesheet.png'], $entries);
    }

    public function test_it_replaces_a_previous_staging_directory(): void
    {
        // A stale icon left from an earlier run would ship to buyers as part
        // of the download.
        mkdir($this->staging.'/icons', 0755, true);
        file_put_contents($this->staging.'/icons/removed-icon.png', 'stale');

        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $this->assertFileDoesNotExist($this->staging.'/icons/removed-icon.png');
    }

    public function test_it_throws_when_an_icon_cannot_be_read(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing\.png/');

        $this->pack()->write(
            $this->staging,
            [['path' => $this->directory.'/missing.png', 'title' => 'gone']],
            $this->png('sheet'),
        );
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=IconPackTest`

Expected: FAIL — `Class "App\Support\IconPack" not found`.

- [ ] **Step 5: Write the implementation**

Create `app/Support/IconPack.php`:

```php
<?php

namespace App\Support;

/**
 * Stages the directory butler pushes to itch.io.
 *
 * Butler is handed a directory rather than a zip: it diffs per file, so adding
 * one icon uploads one icon, and itch.io compresses each build server-side so
 * a browser download is still a single archive. Pushing a pre-made zip would
 * both defeat the diffing and trip butler's auto-unzip rule.
 *
 * Deliberately free of Laravel and Statamic: it takes paths, so it can be
 * tested without booting anything.
 */
final class IconPack
{
    public const README = 'README.txt';

    public const ICONS = 'icons';

    public function __construct(private Files $files) {}

    /**
     * @param  list<array{path: string, title: string}>  $icons  in sheet order
     * @param  string  $tilesheet  absolute path to the regenerated sheet
     * @return int the number of icons staged
     */
    public function write(string $staging, array $icons, string $tilesheet): int
    {
        // Cleared rather than merged: an icon removed from the collection
        // would otherwise linger from an earlier run and ship to buyers.
        $this->clear($staging);

        $directory = $this->files->directory($staging.'/'.self::ICONS);

        foreach ($icons as $icon) {
            $this->copy($icon['path'], $directory.'/'.basename($icon['path']));
        }

        $this->copy($tilesheet, $staging.'/'.basename($tilesheet));

        $this->files->put($staging.'/'.self::README, $this->readme($icons));

        return count($icons);
    }

    /**
     * Which icon is which. Eleven kebab-case filenames are not self-
     * describing, and a buyer should not have to open all of them to find the
     * one they want.
     *
     * @param  list<array{path: string, title: string}>  $icons
     */
    private function readme(array $icons): string
    {
        $names = array_map(fn (array $icon) => basename($icon['path']), $icons);

        $width = max(array_map('strlen', $names ?: ['']));

        $lines = [
            '1-bit icons from MS-DOS games',
            '',
            'Every icon is white pixels on transparent, at its original size.',
            'trivia-tilesheet.png holds all of them on one grid.',
            '',
        ];

        foreach ($icons as $index => $icon) {
            $lines[] = str_pad($names[$index], $width + 2).$icon['title'];
        }

        return implode("\n", $lines)."\n";
    }

    private function copy(string $source, string $destination): void
    {
        if (! is_file($source)) {
            throw new \RuntimeException("Could not read the icon at [{$source}].");
        }

        if (! @copy($source, $destination)) {
            throw new \RuntimeException("Could not copy [{$source}] to [{$destination}].");
        }
    }

    private function clear(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.'/'.$entry;

            is_dir($child) ? $this->clear($child) : $this->unlink($child);
        }

        if (! @rmdir($path)) {
            throw new \RuntimeException("Could not clear [{$path}].");
        }
    }

    private function unlink(string $path): void
    {
        if (! @unlink($path)) {
            throw new \RuntimeException("Could not remove [{$path}].");
        }
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=IconPackTest`

Expected: PASS, 7 tests.

- [ ] **Step 7: Run the full suite**

The new `Files` class and the trait are shared, so check nothing else moved.

Run: `php artisan test`

Expected: PASS, 0 failures.

- [ ] **Step 8: Commit**

```bash
git add app/Support/Files.php app/Support/IconPack.php \
        tests/Concerns/RemovesDirectories.php tests/Unit/IconPackTest.php
git commit -m "feat: stage the itch.io icon pack directory"
```

---

### Task 5: `ItchAssetsGenerator`

Publish the 4× images the itch page hotlinks, into the SSG output only.

**Files:**
- Create: `app/Support/ItchAssetsGenerator.php`
- Test: `tests/Feature/ItchAssetsGeneratorTest.php`
- Modify: `app/Providers/AppServiceProvider.php` (add to the `SSG::after` closure)

**Interfaces:**
- Consumes: `Upscale::write()` (Task 2), `TriviaIcons::all()` (Task 3).
- Produces:
  - `App\Support\ItchAssetsGenerator::FACTOR = 4`
  - `App\Support\ItchAssetsGenerator::DIRECTORY = 'itch'`
  - `App\Support\ItchAssetsGenerator::SUFFIX = '-4x'`
  - `App\Support\ItchAssetsGenerator::filename(string $path): string` — `volfied-1bit-dos-game.png` → `volfied-1bit-dos-game-4x.png`. Task 6 reads this.
  - `App\Support\ItchAssetsGenerator::__construct(Upscale $upscale, TriviaIcons $icons, string $tilesheet, string $outputPath)`
  - `App\Support\ItchAssetsGenerator::generate(): int` — number of files written, sheet included.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ItchAssetsGeneratorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\Files;
use App\Support\ItchAssetsGenerator;
use App\Support\TriviaIcons;
use App\Support\Upscale;
use Tests\Concerns\RemovesDirectories;
use Tests\TestCase;

class ItchAssetsGeneratorTest extends TestCase
{
    use RemovesDirectories;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = storage_path('framework/testing/itch-output');

        $this->remove($this->output);
    }

    protected function tearDown(): void
    {
        $this->remove($this->output);

        parent::tearDown();
    }

    private function generator(?string $output = null): ItchAssetsGenerator
    {
        return new ItchAssetsGenerator(
            new Upscale,
            new TriviaIcons,
            new Files,
            base_path('public/assets/trivia-tilesheet.png'),
            $output ?? $this->output,
        );
    }

    public function test_it_writes_an_upscale_of_the_sheet_and_of_every_icon(): void
    {
        $written = $this->generator()->generate();

        // 11 icons plus the sheet.
        $this->assertSame(12, $written);

        $this->assertFileExists($this->output.'/itch/trivia-tilesheet-4x.png');
        $this->assertFileExists($this->output.'/itch/icons/volfied-1bit-dos-game-4x.png');
        $this->assertCount(11, glob($this->output.'/itch/icons/*.png'));
    }

    public function test_it_scales_the_sheet_by_four(): void
    {
        // The committed sheet is 169x29 with 11 icons (2026-08-22). Update
        // alongside TilesheetGeneratorTest when an icon is added.
        $this->generator()->generate();

        $size = getimagesize($this->output.'/itch/trivia-tilesheet-4x.png');

        $this->assertSame(169 * 4, $size[0]);
        $this->assertSame(29 * 4, $size[1]);
    }

    public function test_it_scales_each_icon_by_four(): void
    {
        $this->generator()->generate();

        $source = getimagesize(base_path('public/assets/trivia/volfied-1bit-dos-game.png'));
        $scaled = getimagesize($this->output.'/itch/icons/volfied-1bit-dos-game-4x.png');

        $this->assertSame($source[0] * 4, $scaled[0]);
        $this->assertSame($source[1] * 4, $scaled[1]);
    }

    public function test_it_writes_nothing_into_public(): void
    {
        // These images are never shown on the site and public/assets is a
        // Statamic container -- anything landing there would want a .meta
        // yaml committed beside it. They exist only for itch.io to hotlink.
        $before = $this->snapshot(base_path('public/assets'));

        $this->generator()->generate();

        $this->assertSame($before, $this->snapshot(base_path('public/assets')));
        $this->assertDirectoryDoesNotExist(base_path('public/itch'));
    }

    /** @return list<string> every file under $root, relative and sorted */
    private function snapshot(string $root): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), strlen($root));
        }

        sort($files);

        return $files;
    }

    public function test_filename_appends_the_factor_suffix(): void
    {
        // Task 6 builds page URLs from this, so a drift here is a page of
        // broken images.
        $this->assertSame(
            'volfied-1bit-dos-game-4x.png',
            ItchAssetsGenerator::filename('/anywhere/volfied-1bit-dos-game.png'),
        );
    }

    public function test_it_throws_when_the_output_cannot_be_written(): void
    {
        $this->expectException(\RuntimeException::class);

        // A file where the directory needs to be: mkdir cannot succeed.
        $blocked = storage_path('framework/testing/itch-blocked');
        @mkdir(dirname($blocked), 0755, true);
        file_put_contents($blocked, 'not a directory');

        try {
            $this->generator($blocked)->generate();
        } finally {
            unlink($blocked);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ItchAssetsGeneratorTest`

Expected: FAIL — `Class "App\Support\ItchAssetsGenerator" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/ItchAssetsGenerator.php`:

```php
<?php

namespace App\Support;

/**
 * Publishes the upscaled images the itch.io page hotlinks.
 *
 * Written into the SSG output and nowhere else. public/assets is a Statamic
 * asset container, so anything written there wants a .meta yaml committed
 * beside it or the control panel generates one on first view -- twelve derived
 * files would mean twelve more files to keep in step. These images are never
 * shown on the site; they exist only for itch.io to fetch. Regenerating them
 * every deploy also means they cannot go stale the way the committed tilesheet
 * did.
 */
class ItchAssetsGenerator
{
    /** Whole-number scale. 169x29 sheet becomes 676x116. */
    public const FACTOR = 4;

    public const DIRECTORY = 'itch';

    public const SUFFIX = '-4x';

    public function __construct(
        private Upscale $upscale,
        private TriviaIcons $icons,
        private Files $files,
        private string $tilesheet,
        private string $outputPath,
    ) {}

    /**
     * The published name for a source icon. ItchPage builds URLs from this
     * rather than restating the suffix, so the two cannot drift apart into a
     * page of broken images.
     */
    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME).self::SUFFIX.'.png';
    }

    /** @return int the number of files written, the sheet included */
    public function generate(): int
    {
        $root = $this->files->directory($this->outputPath.'/'.self::DIRECTORY);

        $this->upscale->write(
            $root.'/'.self::filename($this->tilesheet),
            $this->tilesheet,
            self::FACTOR,
        );

        $icons = $this->files->directory($root.'/'.IconPack::ICONS);

        $written = 1;

        foreach ($this->icons->all() as $icon) {
            $this->upscale->write(
                $icons.'/'.self::filename($icon['path']),
                $icon['path'],
                self::FACTOR,
            );

            $written++;
        }

        return $written;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ItchAssetsGeneratorTest`

Expected: PASS, 6 tests.

- [ ] **Step 5: Wire it into the SSG build**

In `app/Providers/AppServiceProvider.php`, inside the `SSG::after` closure, after the `TilesheetGenerator` call — it upscales the sheet that generator just wrote, so the order matters:

```php
            // The upscaled images the itch.io page hotlinks. Output only:
            // they are never shown on the site, and public/assets is an asset
            // container that would want a .meta yaml for each one.
            (new ItchAssetsGenerator(
                new Upscale,
                new TriviaIcons,
                new Files,
                public_path('assets/'.TilesheetGenerator::FILENAME),
                config('statamic.ssg.output_path'),
            ))->generate();
```

Add `use App\Support\Files;`, `use App\Support\ItchAssetsGenerator;` and
`use App\Support\Upscale;` to the imports.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`

Expected: PASS, 0 failures.

- [ ] **Step 7: Commit**

```bash
git add app/Support/ItchAssetsGenerator.php \
        tests/Feature/ItchAssetsGeneratorTest.php \
        app/Providers/AppServiceProvider.php
git commit -m "feat: publish upscaled icon images for the itch.io page"
```

---

### Task 6: `ItchPage`

Render the description HTML.

**Files:**
- Create: `app/Support/ItchPage.php`
- Test: `tests/Unit/ItchPageTest.php`

**Interfaces:**
- Consumes: `ItchAssetsGenerator::DIRECTORY`, `::SUFFIX`, `::filename()` (Task 5); `IconPack::ICONS` (Task 4); the pair shape from Task 3.
- Produces: `App\Support\ItchPage::render(array $icons, string $baseUrl, string $tilesheet): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ItchPageTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\ItchPage;
use PHPUnit\Framework\TestCase;

class ItchPageTest extends TestCase
{
    /** @return list<array{path: string, title: string}> */
    private function icons(): array
    {
        return [
            ['path' => '/x/alley-cat-dos-game-1bit.png', 'title' => 'Alley Cat & its palette'],
            ['path' => '/x/globliiins-1bit-ms-dos-game.png', 'title' => 'The three "i" are goblins'],
        ];
    }

    private function render(string $base = 'https://example.test'): string
    {
        return (new ItchPage)->render(
            $this->icons(),
            $base,
            '/x/trivia-tilesheet.png',
        );
    }

    public function test_it_leads_with_the_tilesheet(): void
    {
        $html = $this->render();

        $sheet = strpos($html, 'trivia-tilesheet-4x.png');
        $list = strpos($html, '<ul>');

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($list);
        $this->assertLessThan($list, $sheet, 'the sheet should precede the list');
    }

    public function test_it_builds_absolute_urls_from_the_base(): void
    {
        $html = $this->render('https://mathieuderuiter.nl');

        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/itch/trivia-tilesheet-4x.png"',
            $html,
        );
        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/itch/icons/alley-cat-dos-game-1bit-4x.png"',
            $html,
        );
    }

    public function test_it_trims_a_trailing_slash_from_the_base(): void
    {
        $html = $this->render('https://example.test/');

        $this->assertStringContainsString('https://example.test/itch/', $html);
        $this->assertStringNotContainsString('https://example.test//itch/', $html);
    }

    public function test_it_lists_one_item_per_icon_paired_with_its_title(): void
    {
        $html = $this->render();

        $this->assertSame(2, substr_count($html, '<li>'));

        // The pairing, not merely the presence of both: an off-by-one zip
        // would put the wrong fact beside an icon.
        $this->assertMatchesRegularExpression(
            '#<li><img src="[^"]*alley-cat-dos-game-1bit-4x\.png" alt="" />\s*Alley Cat &amp; its palette</li>#',
            $html,
        );
    }

    public function test_it_escapes_titles(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Alley Cat &amp; its palette', $html);
        $this->assertStringContainsString('The three &quot;i&quot; are goblins', $html);
        $this->assertStringNotContainsString('"i" are goblins', $html);
    }

    public function test_it_emits_nothing_the_itch_sanitiser_can_strip(): void
    {
        // itch.io scrubs description HTML. Anything whose loss would change
        // the layout must not be load-bearing, so none of it is emitted at
        // all -- the images are already the size they should be.
        $html = $this->render();

        foreach (['class=', 'style=', '<style', 'width=', 'height=', '<script'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_the_list_icons_carry_no_duplicate_alt_text(): void
    {
        // The fact beside it is the label; repeating it is noise to a screen
        // reader. The hero sheet does get a real alt.
        $html = $this->render();

        $this->assertSame(2, substr_count($html, 'alt=""'));
        $this->assertMatchesRegularExpression('/trivia-tilesheet-4x\.png" alt="[^"]+"/', $html);
    }

    public function test_it_renders_an_empty_set_without_a_list(): void
    {
        $html = (new ItchPage)->render([], 'https://example.test', '/x/trivia-tilesheet.png');

        $this->assertStringNotContainsString('<ul>', $html);
        $this->assertStringContainsString('trivia-tilesheet-4x.png', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ItchPageTest`

Expected: FAIL — `Class "App\Support\ItchPage" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/ItchPage.php`:

```php
<?php

namespace App\Support;

/**
 * Renders the itch.io page description.
 *
 * itch.io has no API for a page's description, so this is pasted into the
 * editor's HTML mode by hand. That editor sanitises what it is given and
 * mangles content on round-trip, so the markup here is the canonical copy and
 * is deliberately plain: no class, no style, no width, no height. Anything
 * the sanitiser might drop would otherwise be load-bearing, and the page
 * would degrade silently. The images arrive already scaled instead.
 *
 * Deliberately free of Laravel and Statamic: arrays and a string in, a string
 * out, so what it produces is asserted directly.
 */
final class ItchPage
{
    /**
     * @param  list<array{path: string, title: string}>  $icons  in sheet order
     * @param  string  $baseUrl  where the upscales are published
     * @param  string  $tilesheet  path to the sheet, for its published name
     */
    public function render(array $icons, string $baseUrl, string $tilesheet): string
    {
        $base = rtrim($baseUrl, '/').'/'.ItchAssetsGenerator::DIRECTORY;

        $sheet = $base.'/'.ItchAssetsGenerator::filename($tilesheet);

        $lines = [
            '<p><img src="'.$this->escape($sheet).'" alt="'
                .$this->escape('Every icon in the pack on one grid').'" /></p>',
        ];

        if ($icons !== []) {
            $lines[] = '<h2>Every icon</h2>';
            $lines[] = '<ul>';

            foreach ($icons as $icon) {
                $source = $base.'/'.IconPack::ICONS.'/'
                    .ItchAssetsGenerator::filename($icon['path']);

                // alt is empty on purpose: the fact beside it is the label.
                $lines[] = '<li><img src="'.$this->escape($source).'" alt="" /> '
                    .$this->escape($icon['title']).'</li>';
            }

            $lines[] = '</ul>';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * One escape for both attributes and text. ENT_QUOTES covers the quote
     * that would break out of an attribute and ENT_SUBSTITUTE keeps malformed
     * UTF-8 from emptying the string, so splitting this in two would be two
     * identical methods.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ItchPageTest`

Expected: PASS, 8 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/ItchPage.php tests/Unit/ItchPageTest.php
git commit -m "feat: render the itch.io page description"
```

---

### Task 7: `trivia:pack` command

Tie it together behind one command, and keep its output out of git.

**Files:**
- Create: `app/Console/Commands/BuildIconPack.php`
- Test: `tests/Feature/BuildIconPackTest.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: `Tilesheet`, `TriviaIcons`, `IconPack`, `ItchPage`, `ItchAssetsGenerator::filename()`.
- Produces: `php artisan trivia:pack --out=<dir> --base-url=<url>`, writing `<dir>/pack/` and `<dir>/page.html`.

- [ ] **Step 1: Ignore the command's output**

`.gitignore` covers `/public/build` (vite's output) but has no root `build/` entry, so without this every local run leaves untracked noise. Add after the `/.phpunit.cache` line, keeping the file's alphabetical-ish grouping:

```
/build
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/BuildIconPackTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\IconPack;
use App\Support\ItchAssetsGenerator;
use Tests\Concerns\RemovesDirectories;
use Tests\TestCase;

class BuildIconPackTest extends TestCase
{
    use RemovesDirectories;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->out = storage_path('framework/testing/itch-pack');

        $this->remove($this->out);
    }

    protected function tearDown(): void
    {
        $this->remove($this->out);

        parent::tearDown();
    }

    private function run(): void
    {
        $this->artisan('trivia:pack', [
            '--out' => $this->out,
            '--base-url' => 'https://example.test',
        ])->assertSuccessful();
    }

    public function test_it_writes_the_pack_and_the_page(): void
    {
        $this->run();

        $this->assertDirectoryExists($this->out.'/pack/icons');
        $this->assertFileExists($this->out.'/pack/trivia-tilesheet.png');
        $this->assertFileExists($this->out.'/pack/README.txt');
        $this->assertFileExists($this->out.'/page.html');
        $this->assertCount(11, glob($this->out.'/pack/icons/*.png'));
    }

    public function test_the_page_is_not_inside_the_pack(): void
    {
        // Anything under pack/ ships to buyers.
        $this->run();

        $this->assertFileDoesNotExist($this->out.'/pack/page.html');

        $entries = array_values(array_diff(scandir($this->out.'/pack'), ['.', '..']));
        sort($entries);

        $this->assertSame(['README.txt', 'icons', 'trivia-tilesheet.png'], $entries);
    }

    public function test_it_regenerates_the_sheet_rather_than_copying_the_committed_one(): void
    {
        // The committed asset went three icons stale once already. The pack
        // depends on the collection, never on that file, so a stale committed
        // sheet cannot reach a buyer.
        $this->run();

        $packed = getimagesize($this->out.'/pack/trivia-tilesheet.png');

        // 11 icons, cell 16x14, ten columns: 169x29.
        $this->assertSame(169, $packed[0]);
        $this->assertSame(29, $packed[1]);
    }

    public function test_every_image_the_page_references_is_one_the_generator_writes(): void
    {
        // The only test that crosses ItchPage and ItchAssetsGenerator, and so
        // the only one that catches a drift in the -4x naming between them.
        $this->run();

        $html = file_get_contents($this->out.'/page.html');

        preg_match_all('/src="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        $output = storage_path('framework/testing/itch-pack-assets');
        $this->remove($output);

        (new ItchAssetsGenerator(
            new \App\Support\Upscale,
            new \App\Support\TriviaIcons,
            new \App\Support\Files,
            base_path('public/assets/trivia-tilesheet.png'),
            $output,
        ))->generate();

        foreach ($matches[1] as $url) {
            $relative = parse_url($url, PHP_URL_PATH);

            $this->assertFileExists(
                $output.$relative,
                "the page references [{$url}] but nothing publishes it",
            );
        }

        $this->remove($output);
    }

    public function test_the_page_uses_the_base_url_it_was_given(): void
    {
        $this->run();

        $html = file_get_contents($this->out.'/page.html');

        $this->assertStringContainsString(
            'https://example.test/'.ItchAssetsGenerator::DIRECTORY.'/',
            $html,
        );
        $this->assertStringContainsString(IconPack::ICONS.'/', $html);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=BuildIconPackTest`

Expected: FAIL — the `trivia:pack` command does not exist.

- [ ] **Step 4: Write the implementation**

Create `app/Console/Commands/BuildIconPack.php`:

```php
<?php

namespace App\Console\Commands;

use App\Support\Files;
use App\Support\IconPack;
use App\Support\ItchPage;
use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
use App\Support\TriviaIcons;
use Illuminate\Console\Command;

/**
 * Builds everything the itch.io release needs: the directory butler pushes,
 * and the description HTML to paste into the page.
 *
 * The sheet is regenerated here rather than copied from public/assets, because
 * that committed file is only refreshed by an SSG build and has been three
 * icons stale before. The pack depends on the collection, never on the
 * artefact.
 */
class BuildIconPack extends Command
{
    protected $signature = 'trivia:pack
                            {--out=build/itch : where to write the pack and the page}
                            {--base-url= : where the upscales are published, defaults to app.url}';

    protected $description = 'Build the itch.io icon pack and its page description';

    public function handle(
        TriviaIcons $icons,
        Tilesheet $tilesheet,
        IconPack $pack,
        ItchPage $page,
        Files $files,
    ): int {
        $out = $this->option('out');
        $out = str_starts_with($out, '/') ? $out : base_path($out);

        $baseUrl = $this->option('base-url') ?: (string) config('app.url');

        $resolved = $icons->all();

        if ($resolved === []) {
            $this->error('No trivia entry has an icon, so there is nothing to pack.');

            return self::FAILURE;
        }

        $staging = $out.'/pack';

        // $out, not $staging: the sheet is written here before IconPack runs,
        // and IconPack clears $staging as its first act.
        $files->directory($out);

        // Written outside the staging directory: anything inside it ships to
        // buyers as part of the download.
        $sheet = $out.'/'.TilesheetGenerator::FILENAME;

        $written = $tilesheet->write($sheet, array_column($resolved, 'path'));

        $count = $pack->write($staging, $resolved, $sheet);

        $files->put($out.'/page.html', $page->render($resolved, $baseUrl, $sheet));

        // Only the copy inside pack/ is shipped; this one was scratch.
        unlink($sheet);

        $this->info("Packed {$count} icons and a {$written['width']}x{$written['height']} sheet into {$staging}.");
        $this->line("Page description written to {$out}/page.html");

        return self::SUCCESS;
    }
}
```

Note the ordering: `IconPack::write()` clears the staging directory, so the sheet is written to `$out` (outside `pack/`), copied in by `IconPack`, then removed. Writing it inside `pack/` first would have it deleted before it was copied.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=BuildIconPackTest`

Expected: PASS, 5 tests.

- [ ] **Step 6: Run the command for real and read its output**

```bash
php artisan trivia:pack --base-url=https://mathieuderuiter.nl
find build/itch -type f | sort
cat build/itch/pack/README.txt
cat build/itch/page.html
```

Expected: 11 icons under `build/itch/pack/icons/`, a 169×29 sheet, a README whose columns line up, and HTML with 11 `<li>` and no `class`/`style`/`width`/`height`. Confirm `git status` stays clean — the `.gitignore` entry from Step 1 covers `build/`.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`

Expected: PASS, 0 failures.

- [ ] **Step 8: Commit**

```bash
git add .gitignore app/Console/Commands/BuildIconPack.php tests/Feature/BuildIconPackTest.php
git commit -m "feat: add trivia:pack command for the itch.io release"
```

---

### Task 8: The workflow

**Files:**
- Create: `.github/workflows/itch-release.yml`
- Modify: `CONTEXT.md` (add the **Icon Pack** glossary entry)

**Interfaces:**
- Consumes: `php artisan trivia:pack` (Task 7).
- Produces: a push-triggered release, and a `page.html` artifact.

- [ ] **Step 1: Add the glossary entry**

`CONTEXT.md` already distinguishes **Trivia Tilesheet** from the **Dungeon**'s tilemap. The pack is a third thing and needs naming, or the next person will conflate it with the sheet. Insert after the **Trivia Tilesheet** entry, keeping the file's definition-list style:

```markdown
**Icon Pack**
: The **Trivia** icons, their **Trivia Tilesheet** and upscaled copies of both,
  published as a downloadable set on itch.io. Distinct from the tilesheet, which is
  one image on the resources page: the pack is what somebody downloads, and it is
  built from the collection on demand rather than committed.
```

- [ ] **Step 2: Write the workflow**

Create `.github/workflows/itch-release.yml`:

```yaml
# Pushes the trivia icons to itch.io and builds the page description to paste.
#
# itch.io has no API for a page's description, so this automates the half that
# can be: butler pushes the download, and the HTML comes out as an artifact.
name: itch.io release

on:
  push:
    branches: ["main"]
    paths:
      - 'content/collections/trivia/**'
      - 'public/assets/trivia/**'
      - 'app/Support/Tilesheet*.php'
      - 'app/Support/Itch*.php'
      - 'app/Support/IconPack.php'
      - 'app/Support/TriviaIcons.php'
      - 'app/Support/Upscale.php'
      - 'app/Support/Files.php'
      - 'app/Console/Commands/BuildIconPack.php'
      - '.github/workflows/itch-release.yml'
  workflow_dispatch:

permissions:
  contents: read

# Two pushes in quick succession should not race to publish builds.
concurrency:
  group: "itch-release"
  cancel-in-progress: true

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v6

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: gd

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      # No npm build: the command renders no Blade views, so it needs nothing
      # from vite -- only an APP_KEY so the framework will boot.
      - name: Prepare the environment
        run: |
          cp .env.example .env
          php artisan key:generate

      - name: Build the pack and the page
        run: php artisan trivia:pack --out=build/itch --base-url=https://mathieuderuiter.nl

      # Downloaded from itch's own server rather than a marketplace action:
      # this step holds a publishing credential.
      - name: Install butler
        run: |
          curl -sL -o butler.zip https://broth.itch.ovh/butler/linux-amd64/LATEST/archive/default
          unzip -q butler.zip
          chmod +x butler
          ./butler -V

      - name: Push to itch.io
        env:
          BUTLER_API_KEY: ${{ secrets.BUTLER_API_KEY }}
        run: |
          COUNT=$(find build/itch/pack/icons -name '*.png' | wc -l | tr -d ' ')
          ./butler push build/itch/pack \
            casmo/1-bit-icons-from-ms-dos-games:icons \
            --userversion "${COUNT}-${GITHUB_SHA::7}"

      - name: Upload the page description
        uses: actions/upload-artifact@v4
        with:
          name: itch-page
          path: build/itch/page.html
```

- [ ] **Step 3: Validate the workflow parses**

Run:

```bash
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/itch-release.yml')); print('ok')"
```

Expected: `ok`. If PyYAML is unavailable, use `php -r 'var_dump(yaml_parse_file(".github/workflows/itch-release.yml") !== false);'` or skip — the next step catches real errors.

- [ ] **Step 4: Confirm the command line the workflow runs actually works**

The workflow's own `trivia:pack` invocation, run locally with the same arguments:

```bash
php artisan trivia:pack --out=build/itch --base-url=https://mathieuderuiter.nl
find build/itch/pack/icons -name '*.png' | wc -l
```

Expected: exits 0, prints `11`. This is the `COUNT` the `--userversion` uses.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`

Expected: PASS, 0 failures.

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/itch-release.yml CONTEXT.md
git commit -m "feat: add itch.io release workflow"
```

- [ ] **Step 7: Hand back the manual steps**

These cannot be automated and must be reported to the user as remaining work:

1. Add the **`BUTLER_API_KEY`** secret (itch.io → Settings → API keys) to the repository. Until then the workflow fails at the push step.
2. After the first successful run, **remove the existing hand-uploaded zip** from the itch.io page. A butler channel is a separate upload, so the page will otherwise offer two downloads — the old stale zip beside the new build.
3. Download the `itch-page` artifact and paste `page.html` into the page description's HTML mode. Needed again only when the icon set changes.
4. The images the page references are published by `deploy.yml`, not this workflow. If the Pages deploy has not finished, they 404 until it does.

---

## Verification

After Task 8, the whole feature:

- [ ] `php artisan test` — green, and now includes `UpscaleTest`, `IconPackTest`, `ItchPageTest`, `TriviaIconsTest`, `ItchAssetsGeneratorTest`, `BuildIconPackTest`.
- [ ] `php artisan trivia:pack` — writes a pack of 11 icons and a 169×29 sheet.
- [ ] `git status` — clean after running the command.
- [ ] `vendor/bin/pint --test` — the repo has `laravel/pint`; run it and fix anything it flags.
- [ ] A full `php please ssg:generate` writes `storage/static/itch/trivia-tilesheet-4x.png` and 11 files under `storage/static/itch/icons/`, and leaves nothing new in `public/`.
