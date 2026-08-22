# itch.io icon pack

Date: 2026-08-22

## Problem

The trivia icons are published on itch.io as
[1-bit icons from MS-DOS games](https://casmo.itch.io/1-bit-icons-from-ms-dos-games),
and that page is maintained entirely by hand. Adding a trivia entry means
remembering to rebuild a zip, upload it, and retype the icon list in the itch
editor. Nothing connects the page to the collection it is drawn from, so the two
drift: the collection has eleven icons today and the page does not say so.

The **Trivia Tilesheet** makes the drift concrete. `public/assets/trivia-tilesheet.png`
is committed at 127×14 — eight tiles — but there are eleven published entries.
The tilesheet spec put the burden on a human (*"running `php please ssg:generate`
locally is what refreshes the committed asset"*) and the Alley Cat, Lemmings and
Gobliiins commits did not pay it. The deployed site is fine, because
`TilesheetGenerator` runs in `SSG::after` on every build, but the committed PNG
is three icons stale. Anything that packages the committed file ships a lie.

## Goal

One workflow that, whenever the trivia icons change, pushes a fresh download to
itch.io and produces the page markup to go with it.

## The constraint that shapes everything

itch.io's API is read-only. `butler`, the official CLI, pushes builds and nothing
else; the server-side API queries games, purchases and download keys. There is no
endpoint for a page's description, and the [2021 request][pagereq] for one was
never built.

The description editor does have a raw HTML mode, and `<img src="…">` survives
its sanitiser, but the sanitiser is lossy and the editor mangles content on
round-trip. The community's standing advice is to keep the HTML canonical
*outside* itch and paste it in — never to edit in place and copy back out.

So the work splits along the automation ceiling:

| Half | Mechanism | Automated |
|---|---|---|
| The download | `butler push` | fully |
| The page body | generated HTML, pasted once | generation only |

This is not a stopgap awaiting an API. Pasting is the supported path, and the
generated HTML is the artefact that makes it a ten-second job instead of a
retyping job.

[pagereq]: https://itch.io/t/1262867/new-game-page-programatically-using-server-api-or-butler

## Geometry

The set is eleven icons, the smallest 8×8 and none larger than 16×11. Gobliiins is
the widest at 16px and Commander Keen the tallest at 14px, so `Tilesheet`'s cell is
**16×14** — a size no individual icon has — and the sheet is **169×29**: two rows,
ten tiles in the first. The tilesheet spec
anticipated exactly this ("if a future icon is 16×16 the whole grid grows with
it"); Gobliiins is the icon that grew it, from one row to two.

Nothing here changes that arithmetic. This spec consumes `Tilesheet`, it does not
alter it.

### Upscaling

The icons are 8–16px. At natural size they are specks beside a sentence of prose,
and scaling them with `width`/`height` attributes gets them smoothly interpolated
into mush — the one thing you must not do to pixel art. `image-rendering:
pixelated` fixes that in a browser, but it is exactly the kind of declaration
itch's sanitiser is entitled to drop, and the page would silently degrade.

So the images are upscaled *before* they are published, at an integer factor, by
nearest neighbour. The markup then carries no CSS at all and cannot degrade.

**Factor 4.** Sheet becomes 676×116; the tallest icon becomes 56px, which reads
as pixel art beside a line of text without dominating it.

`App\Support\Upscale` does one thing:

```php
/** @return array{width: int, height: int} */
public function write(string $destination, string $source, int $factor): array
```

A truecolor destination with `imagealphablending(false)` and `imagesavealpha(true)`,
filled fully transparent, then `imagecopyresized` — which samples nearest
neighbour, so at an integer factor every source pixel becomes an exact `factor ×
factor` block with its alpha verbatim. Never `imagecopyresampled`: that
interpolates, which is the failure being avoided. A factor below 1 throws.

## Components

Six units: three new pure-PHP pieces (`Upscale` above, `IconPack` and `ItchPage`
below), one extraction from existing code, and two pieces of Statamic/CLI wiring.

### `App\Support\TriviaIcons`

`TilesheetGenerator` already knows what "the icon set, in order" means: published
trivia entries, sorted by the collection's own sort field, first asset of `icon`,
deduped by resolved path keeping the first occurrence. That knowledge is
currently split across its private `entries()` and public `sources()`, and
`sources()` discards the titles.

The README and the page list both need `{path, title}` pairs, in the same order,
under the same dedupe. Duplicating that resolution would let the pack disagree
with the sheet it ships — a silent, plausible bug. So it moves into one place:

```php
/** @return list<array{path: string, title: string}> */
public function all(): array
```

Trivia is neither dated nor orderable — `content/collections/trivia.yaml` sets
`date_behavior` but no `sort_by` and no `dated` — so Statamic's default sort field
is `title` ascending. The sort stays explicit, because a bare query returns stache
order.

`TilesheetGenerator::sources()` becomes a thin map over `all()`. Its behaviour is
unchanged and its existing tests must stay green; that is the check on this
extraction.

A note on the data: trivia titles are *facts*, not game names — "Volfied first
level had a snake that could escape the level." The list on the page pairs each
icon with its fact, which is what makes it worth reading rather than a filename
dump.

### `App\Support\IconPack`

Pure PHP. Given the `{path, title}` list, a tilesheet path and a destination
directory, it stages the download:

```
<staging>/
├─ icons/                    11 PNGs, original filenames, 1× as authored
├─ trivia-tilesheet.png      169×29, regenerated
└─ README.txt                filename → trivia title
```

The icons ship at **1×**, as authored. The upscales exist so the *page* can show
them; a buyer wants the real pixels, and a 4× PNG is a lossy thing to hand
someone who is going to drop it into a tilemap editor.

`README.txt` is the mapping, in sheet order, so a buyer can tell
`scorched-earth-dos-game-1bit.png` from `volfied-1bit-dos-game.png` without
opening both. Header, then one aligned row per icon.

### `App\Support\ItchPage`

Pure PHP: the `{path, title}` list and a base URL in, one HTML string out. No
file writing, so what it produces is asserted directly.

Structure — the tilesheet as a hero, then the list:

```html
<p><img src="{base}/itch/trivia-tilesheet-4x.png" alt="Every icon in the pack" /></p>
<h2>Every icon</h2>
<ul>
  <li><img src="{base}/itch/icons/volfied-1bit-dos-game-4x.png" alt="" />
      Volfied first level had a snake that could escape the level.</li>
  …
</ul>
```

Constraints on the markup, all of them consequences of the sanitiser:

- No `<style>`, no `class`, no `style` attributes. Nothing that can be stripped.
- No `width`/`height`. The images are already the size they should be, so a
  stripped attribute cannot change the layout.
- `alt=""` on the list icons: the fact beside it is the text, and a duplicate
  label is noise to a screen reader. The hero sheet gets a real `alt`.
- `<h2>` for the heading, which is what itch's own design guide recommends.

Titles are HTML-escaped. Gobliiins' fact contains double quotes and Lemmings'
contains parentheses.

### `App\Support\ItchAssetsGenerator`

Writes the upscales for the page to hotlink. Constructed with an `Upscale`, a
`TriviaIcons`, the tilesheet's path and the SSG output path.

```
<output>/itch/trivia-tilesheet-4x.png
<output>/itch/icons/<name>-4x.png        one per icon
```

`ItchPage` builds URLs for these same files, so the factor, the `itch/` prefix and
the `-4x` suffix are three facts two classes must agree on. A typo in either
yields a page of broken images that no unit test catches, because each class is
individually self-consistent. They live as constants on this class — `FACTOR = 4`,
`DIRECTORY = 'itch'`, `SUFFIX = '-4x'` — and `ItchPage` reads them rather than
restating the strings.

**Straight into the SSG output, and nowhere else.** This is the one place this
spec departs from the tilesheet's publication model, and the reasoning matters:

- `config/statamic/ssg.php` copies only `public/assets`, `public/build`,
  `public/css` and `public/js`, so `public/itch/` would not ship without a config
  change.
- `public/assets` *is* a Statamic asset container. Anything written there wants a
  `.meta` yaml or the control panel generates one on first view — which is the
  untracked `tilemap.png.yaml` already sitting in `git status`. Twelve derived
  files there means twelve more meta files to commit and keep in step.
- These images are never shown on the site. They exist only for itch.io to
  hotlink. Committing them would be committing build output.

So they are not committed and not written to `public/`. Every deploy regenerates
them, which also means they cannot go stale the way the tilesheet did — the bug
in the Problem section is structurally impossible here.

Runs in the `SSG::after` closure in `AppServiceProvider::boot()`, after
`TilesheetGenerator`, because it upscales the sheet that generator just wrote.
Every write failure throws `RuntimeException` naming the path.

### `App\Console\Commands\BuildIconPack`

```
php artisan trivia:pack {--out=build/itch} {--base-url=}
```

Writes:

```
<out>/pack/          the staging directory butler pushes
<out>/page.html      the markup to paste
```

`page.html` sits *beside* `pack/`, never inside it, or it would ship to buyers as
part of the download.

The command regenerates the tilesheet into `<out>/pack/` with `Tilesheet::write()`
directly, rather than copying `public/assets/trivia-tilesheet.png`. That is the
whole answer to the staleness in the Problem section: depend on the collection,
never on the committed artefact.

`--base-url` defaults to `config('app.url')` and is passed explicitly in CI,
because `.env.example` does not carry the production URL.

Renders no Blade views, so it needs PHP, composer and an `APP_KEY` — no npm, no
vite manifest.

## Publication

| Path | Committed | Written by |
|---|---|---|
| `build/itch/pack/**` | no | `trivia:pack` |
| `build/itch/page.html` | no | `trivia:pack` |
| `storage/static/itch/**` | no | `ItchAssetsGenerator` |

`/storage/static` is already gitignored. `/build` is **not** — `.gitignore` covers
`/public/build` (vite's output) but has no root `build/` entry, so implementation
must add one. Without it, `trivia:pack` leaves the staging directory and
`page.html` as untracked noise in `git status` on every local run.

Nothing this feature produces is committed, which is the point: every artefact is
derived from the collection on demand.

### Pushing

```
butler push <out>/pack casmo/1-bit-icons-from-ms-dos-games:icons \
  --userversion "<count>-<short-sha>"
```

A **directory**, not a zip, though the buyer still downloads one zip: itch.io
compresses each build server-side, and a browser download of a multi-file build
is a server-generated archive of its contents. Three reasons this is the right
input:

- Butler diffs per file, so adding one icon uploads one icon.
- itch's own guidance is to push uncompressed, because a pre-compressed input
  makes patches disproportionately large — a one-pixel change cascades through
  the compressed stream and butler cannot see past it.
- It sidesteps butler's auto-unzip rule, which unpacks a directory containing
  exactly one `.zip` and nothing else. With a zip input the shipped layout would
  depend on how many files happened to sit beside it.

`--ignore` is available as a second guard on stray files reaching buyers, but the
first guard is that `page.html` is written outside `pack/` to begin with.
`butler push-preview` diffs the staging directory against the channel's last
build without uploading, which is the dry run to reach for when a push looks
wrong.

The channel is `icons`. Channel names containing `win`, `linux`, `mac`, `osx` or
`android` make itch auto-tag a platform, which is wrong for an asset pack;
`icons` collides with none of them. It is also a *fresh* channel, deliberately:
switching an existing channel between single-file and directory pushes is a
[known cause][chanquirk] of downloads served under a stale filename.

[chanquirk]: https://github.com/itchio/itch/issues/1306

`--userversion` is the icon count and the commit, so a build on the page can be
traced to a commit and the count is legible at a glance.

Butler is downloaded from itch's own `broth.itch.ovh` rather than pulled from a
marketplace action. This step holds a publishing credential, and the official
binary is one fewer third party to trust with it.

## Workflow

`.github/workflows/itch-release.yml`.

```yaml
on:
  push:
    branches: [main]
    paths:
      - 'content/collections/trivia/**'
      - 'public/assets/trivia/**'
      - 'app/Support/Tilesheet*.php'
      - 'app/Support/Itch*.php'
      - 'app/Support/TriviaIcons.php'
      - 'app/Support/Upscale.php'
      - '.github/workflows/itch-release.yml'
  workflow_dispatch:
```

Steps mirror `deploy.yml`'s PHP half — checkout, `shivammathur/setup-php@v2` at
8.4, `composer install`, `cp .env.example .env`, `php artisan key:generate` — then
`trivia:pack`, then butler, then upload `page.html` as an artifact.

`concurrency: group: itch-release, cancel-in-progress: true`. Two pushes in quick
succession should not race to push builds; the later one wins.

Requires one new secret, **`BUTLER_API_KEY`**, from itch.io → Settings → API keys.
The workflow does not commit anything back to the repo: a push-triggered workflow
that pushes is a loop waiting to happen, and the artifact gets the HTML where it
is needed anyway.

### First run

Three one-time manual steps, none of which the workflow can do for itself:

1. Add the `BUTLER_API_KEY` secret.
2. Paste `page.html` into the description's HTML mode. Needed again only when the
   icon set changes.
3. **Remove the existing hand-uploaded zip from the page.** A butler channel is a
   separate upload from anything added through the web form, so the first push
   leaves the page offering two downloads — the old stale zip and the new build.
   Butler will not clean that up, and an eight-icon zip sitting beside an
   eleven-icon build is worse than either alone.

### Ordering

`deploy.yml` and `itch-release.yml` both fire on the same push and run
independently. The upscales the page hotlinks are published by `deploy.yml`, so
for as long as the Pages deploy is still running, a freshly pasted page can point
at an image that is not live yet. It resolves itself when the deploy lands, and
the paste is manual anyway — a human is in the loop and will see it. Not worth
serialising two workflows over.

### Hotlinking

The page points at `mathieuderuiter.nl`. GitHub Pages does not block hotlinking,
so this is stable, but it does mean the itch page's images depend on the site
staying up. The alternative — uploading twelve images through itch's editor by
hand on every change — is the thing this spec exists to remove.

## Vocabulary

`CONTEXT.md` gains an **Icon Pack** entry: the trivia icons, their tilesheet and
their upscales, published as a downloadable set on itch.io. It sits beside
**Trivia Tilesheet** and must not be confused with it — the tilesheet is one
image shown on the resources page, the pack is what a buyer downloads.

## Out of scope

- **The stale committed tilesheet.** `public/assets/trivia-tilesheet.png` stays at
  127×14 until something regenerates it. This spec routes around it rather than
  fixing it, and no artefact here reads it. Fixing it is a one-command commit,
  separate from this work.
- **The resources page overflowing.** At 169px the sheet is 676px at the page's
  `zoom: 4`, past the 608px reading measure — the "future problem for whoever
  adds the icon that fills the sheet" the tilesheet spec called out. Gobliiins
  triggered it. It is a display bug on the site, unrelated to the pack.
- Price, tags, screenshots and every other itch page field. No API, and unlike
  the description they do not change when an icon is added.

## Tests

`tests/Unit/UpscaleTest.php`, against generated fixtures:

- A 2×2 source at factor 4 is 8×8, and each source pixel became an exact 4×4
  block — assert the corners of two adjacent blocks, not just the dimensions.
  Dimensions alone pass with interpolation.
- A transparent source pixel yields sixteen transparent pixels; an opaque white
  one yields sixteen opaque white ones.
- Factor 1 returns the image unchanged. Factor 0 and −1 throw.

`tests/Unit/IconPackTest.php`:

- Layout: `icons/` holds every source at its authored size, the sheet is at the
  root, `README.txt` exists.
- `README.txt` pairs each filename with its own title, in sheet order.
- Icons are byte-identical to their sources — the pack ships 1×, unmodified.

`tests/Unit/ItchPageTest.php`:

- The tilesheet `<img>` precedes the list.
- One `<li>` per icon, each pairing the right upscale filename with the right
  title.
- A title containing `"` and `&` is escaped.
- No `style` attribute, no `class`, no `width`, no `height` anywhere in the
  output. This is the guard on the sanitiser reasoning above, so it is asserted,
  not assumed.

`tests/Feature/TriviaIconsTest.php`:

- Title-ascending order.
- An entry with no `icon` is skipped.
- Two entries sharing one icon yield one pair, keeping the first title.
- Titles are paired to the correct path — not merely present in the list.

`tests/Feature/ItchAssetsGeneratorTest.php`:

- The sheet upscale and one file per icon are written under `<output>/itch/`.
- Nothing is written to `public/`.
- An unwritable output throws, naming the path.

`tests/Feature/BuildIconPackTest.php`:

- `page.html` is written beside `pack/`, never inside it — the guard on stray
  files reaching buyers.
- The sheet in `pack/` is regenerated, not copied: with a stale
  `public/assets/trivia-tilesheet.png` in place, the packed sheet still has one
  tile per icon. This is the regression test for the Problem section.
- Every `<img src>` in `page.html` resolves to a file `ItchAssetsGenerator`
  actually writes. This is the only test that crosses the two classes, and the
  only one that catches a naming drift between them.

The existing `TilesheetTest` and `TilesheetGeneratorTest` are the regression check
on the `TriviaIcons` extraction and must pass untouched.
