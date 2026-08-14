# Trivia tilesheet

Date: 2026-08-14

## Problem

Every trivia entry carries a 1-bit icon in its `icon` field — white pixels on
transparent, between 9×9 and 15×14, lifted from the DOS games the fortunes are
about. A reader only ever sees one at a time: the fortune list picks a single
line at random, so the icons exist as a set that is never displayed as a set.

There is no artefact that collects them, and nothing on the site that shows the
collection as pixel art rather than as decoration on a sentence.

## Goal

A generated tilesheet — one PNG, every trivia icon on a fixed grid — published as
a real asset in the content asset map, refreshed on every deploy, and shown on
the resources page above the Books section.

## Geometry

The cell is the largest source width by the largest source height, measured
across the icons that go on the sheet. Today `scorched-earth` is the widest at
15px and `commander-keen` the tallest at 14px, so the cell is **15×14**. Nothing
is clamped: if a future icon is 16×16 the whole grid grows with it.

Ten columns maximum. Cells are separated by a 1px transparent gutter between
them and nothing around the outside, the same arithmetic `public/assets/tilemap.png`
already uses (339px = 20×16 + 19 gutters):

```
cols  = min(count, 10)
rows  = ceil(count / 10)
width  = cols * (cellW + 1) - 1
height = rows * (cellH + 1) - 1
```

Tile `i` (zero based, filling left to right, top to bottom):

```
col = i % 10          x0 = col * (cellW + 1)
row = floor(i / 10)   y0 = row * (cellH + 1)
```

With the eight icons that exist today the sheet is one row, **127×14**.

### Placement inside a cell

Each icon is centred in its cell, with an odd leftover pixel biased **left and
down**:

```
x = x0 + floor((cellW - w) / 2)
y = y0 +  ceil((cellH - h) / 2)
```

So `volfied` (13×9) sits 1px from its cell's left edge with 3 rows of empty
pixels above it and 2 below — pushed down. A 9px-wide icon in a 16px cell would
get 3px to its left and 4px to its right — pushed left. Even leftovers are
exactly centred.

### Pixels

The sheet is truecolor PNG with an alpha channel, filled fully transparent, with
alpha blending off and `imagesavealpha` on. Tiles are copied with `imagecopy`,
never `imagecopyresampled`: every source pixel and its alpha land verbatim, at an
integer offset. No scaling, no filtering, no colour touched — the icons are
artefacts of the games they came from, and the sheet is a container, not a
treatment.

## Components

Two classes, mirroring the `SocialImage` / `SocialImageGenerator` split.

### `App\Support\Tilesheet`

Pure PHP. No Laravel, no Statamic. Takes a list of absolute PNG paths and a
destination path; measures the sources, lays out the grid above, writes the PNG,
and returns its dimensions:

```php
/** @param list<string> $sources absolute paths to the source PNGs */
public function write(string $destination, array $sources): ?array
// ['width' => int, 'height' => int] — null if $sources is empty
```

An empty source list writes nothing and returns `null`, rather than producing a
zero-width PNG.

Constants: `COLUMNS = 10`, `GUTTER = 1`.

### `App\Support\TilesheetGenerator`

The Statamic half. Constructed with a `Tilesheet`, the public asset root
(`public_path()`) and the SSG output path, mirroring `SocialImageGenerator`'s
shape.

`generate(): ?array` does four things:

1. Resolves the source list (below).
2. Writes `public/assets/trivia-tilesheet.png` via `Tilesheet`.
3. Writes `public/assets/.meta/trivia-tilesheet.png.yaml`.
4. Copies the PNG to `<output>/assets/trivia-tilesheet.png`.

Every write failure throws `RuntimeException` naming the path. A build that
cannot produce the sheet fails rather than shipping a stale one.

### Source list

Published trivia entries in the collection's default order. Trivia is neither
dated nor orderable — `content/collections/trivia.yaml` sets `date_behavior` but
not `dated`, and the entries have no `date` field — so Statamic's default sort
field is `title`, ascending. That is the order the fortune list already renders
in, so the sheet reads left to right in the same order the site does.

From each entry, the first asset in `icon`. Entries without an icon are skipped.
Repeated paths are deduped, keeping the first occurrence: the sheet is the set of
icons, not one tile per entry.

## Publication

The sheet lives at the assets root, beside `tilemap.png`, so `assets/trivia/`
stays exactly what it is now — the source icons and nothing derived from them.

| Path | Committed | Written by |
|---|---|---|
| `public/assets/trivia-tilesheet.png` | yes | `TilesheetGenerator` |
| `public/assets/.meta/trivia-tilesheet.png.yaml` | yes | `TilesheetGenerator` |
| `storage/static/assets/trivia-tilesheet.png` | no | `TilesheetGenerator` |

The `.meta` yaml takes the shape the container already writes, so Statamic's
control panel sees a fully known asset rather than generating meta on first view
(values below are illustrative — all five are read back from the written file):

```yaml
data: {}
size: 168
last_modified: 1786541048
width: 127
height: 14
mime_type: image/png
duration: null
```

It is written after the PNG, so `size` and `last_modified` describe the file that
is actually on disk.

Missing directories are created as needed, tolerating a concurrent `mkdir` the
way `SocialImageGenerator` already does.

The third write exists because of ordering inside the SSG package:
`Generator::generate()` runs `copyFiles()` — which copies `public/assets` into
the output — *before* it calls the `after` closure. Writing only to `public/` would
land the new sheet one deploy late. So the generator writes both, and the output
copy is what ships.

Both writes happen in the `SSG::after` closure in `AppServiceProvider::boot()`,
next to the social images:

```php
SSG::after(function () {
    (new SocialImageGenerator(...))->generate();

    (new TilesheetGenerator(
        new Tilesheet(),
        public_path(),
        config('statamic.ssg.output_path'),
    ))->generate();
});
```

CI checks out, builds, uploads `storage/static` and throws the workspace away, so
the `public/` write is scratch there. Running `php please ssg:generate` locally is
what refreshes the committed asset — and because it is committed, a fresh clone
and a CI run both start from a valid sheet.

## Display

One line of HTML in `content/collections/pages/resources.md`, immediately above
`## Books`, in the same inline style the book covers on that page already use:

```html
<img class="trivia-sheet" src="/assets/trivia-tilesheet.png" alt="Every icon from the fortunes, in a grid" />
```

Inline HTML rather than a Blade component because `pages/page.blade.php` renders
the whole body in one `@content($content)` call and the Books heading is inside
that blob — a template-level component cannot sit between the links list and
Books without either a `{{ ... }}` snippet mechanism or hoisting Books out of the
markdown. Neither is worth it for one image whose position is a content decision.

No `width` or `height` attributes: the sheet's dimensions change when icons are
added, and a stale hardcoded width would distort it. `resources/css/site.css`
gets a rule in the components layer:

```css
.trivia-sheet {
  image-rendering: pixelated;
  zoom: 4;
}

@media (max-width: 767px) {
  .trivia-sheet { zoom: 2; }
}
```

`zoom` rather than a width because it scales by an exact integer whatever the
intrinsic size turns out to be, so no pixel is ever half a pixel; `transform:
scale()` would scale the paint and leave a 127px hole in the flow. At 4× the
sheet is 508px wide today and 636px at a full ten columns, both inside the
`.shell` measure of 42rem. The 2× step under 768px keeps it off the edge of a
phone, still on whole pixels.

The sheet is untinted, the same exemption from the single-accent rule that
`.fortune__icon` already documents.

## Vocabulary

`CONTEXT.md` gains a **Trivia Tilesheet** entry. The glossary already names the
dungeon tilemap; this is a second sheet with different rules, and the two should
not be confused.

## Tests

`tests/Unit/TilesheetTest.php`, against small generated fixtures:

- Cell size is the max width and max height across mixed sources, not the first
  source's dimensions.
- Sheet dimensions include gutters between cells and none outside.
- Twelve sources wrap to two rows, ten in the first.
- **Left-and-down bias**: for a source with odd leftovers in both axes, assert the
  specific pixels — the column immediately left of the icon is transparent, and
  so is the row immediately above it, with the icon's own corner pixel opaque.
  Dimensions alone would pass with the bias inverted.
- Alpha survives: a transparent source pixel is transparent on the sheet, and an
  opaque white one is still opaque white.
- An empty source list returns `null` and writes no file.

`tests/Feature/TilesheetGeneratorTest.php`:

- Both the public asset and the output copy are written.
- The `.meta` yaml carries the sheet's real width and height.
- An entry with no `icon` is skipped.
- Two entries sharing one icon produce one tile.
- An unwritable destination throws.
