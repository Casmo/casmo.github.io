# Social image redesign

Date: 2026-08-12

## Problem

Every published entry gets a 1200×630 PNG at build time, used as its `og:image`
and therefore as the LinkedIn link preview. The current image (generated inline
in `AppServiceProvider::boot()`) has three faults:

1. **The text is too small.** LinkedIn renders the preview as a small thumbnail.
   The title caps at 40pt and drops to 20pt for long titles; the date and tags
   are 15pt and illegible once scaled down.
2. **Nothing is aligned.** The title block floats mid-left with no relationship
   to the canvas edges, and the avatar sits off the text's baseline.
3. **It does not look like the site.** The title is pure white, which appears
   nowhere in the palette, and the image carries none of the site's shell
   grammar — no prompt line, no character-cell grid.

The constellation artwork behind it (`resources/img/og-background.png`) stays: it
is the one part that works.

## Goal

An image that reads as a fragment of the same shell session the site is dressed
as, and whose title survives being shrunk to a feed thumbnail.

## Layout

Canvas 1200×630 PNG, unchanged — the Open Graph size and LinkedIn's 1.91:1
target. Everything sits on a 42px character cell (15 cells tall), mirroring the
site's `--cell`. Left and right margins are 72px, giving a 1056px measure.

### Colours

Taken from `resources/css/site.css`:

| Role | Value | Source |
|---|---|---|
| Background | `#1f2329` | `--bg` |
| Prompt line | `#84cc16` | `--accent` |
| Title | `#e4e4e7` | brighter than `--fg`, for thumbnail contrast |
| Identity line | `#6b7280` | `--muted` |
| Block cursor | `#84cc16` | `--accent` |

The title is deliberately brighter than the site's `--fg #a1a1aa`: the image
competes with a feed, the prose does not.

### Background

The existing constellation artwork, resampled to 1200×630, then veiled with
`--bg` at 45% opacity so the lines read at roughly 55% strength. At full
strength the lines cut through the counters of the now much larger glyphs.

No scanlines. The site's screen texture is 3px-period and aliases into moiré
when LinkedIn resamples the image to a thumbnail.

### Bands

Three bands, all sharing the 72px left edge:

| Band | Position | Content |
|---|---|---|
| Prompt | baseline at cell 2 (y 84) | `mathieu@laptop:~/blog/design$ cat the-interface-of-the-future.txt`, fixed 22pt accent |
| Title | cells 2.5–12.5 (y 105–525; 420px, two thirds of the canvas) | bold, `#e4e4e7`, block centred vertically, block cursor after the last word |
| Identity | baseline at cell 14 (y 588) | 56px avatar at the left margin, `mathieuderuiter.nl` beside it, date flush right |

The prompt line carries a 1px halo in accent at 45% alpha in the four cardinal
directions — the flat-renderer stand-in for the site's `text-shadow` glow.

The identity line's date uses the site's own format, `F j, Y` ("August 1,
2026"), matching the readout. Both ends of the line are anchored to the margins;
that anchoring is what makes the composition read as aligned.

The block cursor is a filled accent rectangle following the final word: offset
half a character advance from the end of the line, 0.9 advances wide, 78% of the
cap height tall, sitting on the baseline.

**The cursor never influences the wrap or the chosen size.** It is drawn only
when it fits inside the right margin after the last word, and omitted otherwise —
title size wins over decoration. Across the 26 current titles it is omitted on
five. The alternatives were both worse: reserving room for it on every line costs
`The interface of the future` a whole step of the ladder, and treating it as a
trailing word drops the longest title from 64pt to 54pt to buy a line holding
nothing but a green block.

### Type

Bold IBM Plex Mono for the title, regular for the prompt and identity lines. The
site's own headings are bold 700, so this follows it. GD cannot read the `.woff2`
files the browser loads, so `resources/fonts/IBMPlexMono-Bold.ttf` is added
alongside the existing `IBMPlexMono-Regular.ttf` — build-time only, never served
to browsers.

### The size ladder

Sizes, in the points GD takes: **118 / 96 / 78 / 64 / 54 / 46 / 40**.

Word-wrap the title to the 1056px measure at each size in turn and take the
first whose wrapped block fits the 420px band at a 1.35 line-height. A size is
also disqualified if any resulting line still exceeds the measure, which happens
only when a single word is wider than the measure at that size. At the 40pt
floor the title truncates to the last fitting word plus `…`, and a single word
too wide even at 40pt is hard-broken mid-word.

Measured by rendering all 26 titles in `blog`, `games` and `pages`:

| Title | Result |
|---|---|
| `Nostalgia` (9 chars) | 118pt, 1 row |
| `Game flow of Kabonk!` (20) | 118pt, 2 rows |
| `The interface of the future` (27) | 96pt, 2 rows |
| `Build your Statamic static website via GitHub actions` (52) | 64pt, 3 rows |
| `Generate default Open Graph images for each Entry in your your Statamic site` (76) | 54pt, 4 rows |

Every title lands between 54pt and 118pt; nothing reaches the 40pt floor, and
the distribution clusters at 64–96pt. Compare today: 40pt for short titles,
20pt for anything over 70 characters. Short titles growing to 118pt is what keeps
a one-word review from leaving a hole in the canvas.

The prompt line does not use the ladder. It is **always 22pt**, and the filename
is elided to `head….txt` until the line fits the measure. It never wraps — a
wrapped prompt stops reading as a command.

Eliding the filename is enough for every path this site produces, but it cannot
save a line whose `host:path$ cat ` prefix is itself wider than the measure. Two
further steps run only when needed, so the line can never cross the margin:

1. Drop the leading path segments, keeping the last —
   `~/blog/nested-directory/…/niagara` becomes `~/…/niagara`, the way a shell
   abbreviates a long path — then elide the filename again.
2. If even that overruns (one enormous segment, or a very long host), truncate
   the whole line to the measure with a trailing `…`.

A fixed size was chosen over shrinking to fit after measuring the real slugs:
shrinking from 22pt to a 14pt floor put 7 of the 26 prompts at 14pt (two of them
still needing elision) while 8 sat at 22pt, so the top line's weight and legibility
would swing image to image — the opposite of the brief.

At a fixed 22pt the measure holds about 70 characters, so 18 of the 26 filenames
elide. That is accepted: slugs are long, and the title's size matters more than
the tail of a filename.

```
mathieu@laptop:~/reviews$ cat volfied.txt
mathieu@laptop:~/blog/design$ cat the-interface-of-the-fu….txt
mathieu@laptop:~/blog/php$ cat composer-update-hanging-on….txt
```

The rejected alternative was a backslash line continuation carrying the full
filename on a second row, as a real shell wraps a long command. It costs one 42px
cell from the title band, which drops roughly 6 of the 26 titles a step down the
ladder — the wrong trade when the brief is "text as big as possible".

## Content

### Prompt path and file

The prompt must show the same path and filename the page itself shows, or the
image advertises a directory that does not exist. Those rules currently live
inline in five Blade files:

| View | Path segments |
|---|---|
| `blog.blade.php` | `['blog', $category?->slug]` |
| `games.blade.php` | `['reviews']` |
| `pages/page.blade.php`, `pages/blog.blade.php`, `pages/reviews.blade.php` | `[$title]` |

Each also honours the `terminal_path` and `terminal_file` overrides.

`Terminal::forEntry($entry)` joins these rules in the class that already owns
them, returning the path and file for an entry. Both the generator and those five
Blade calls use it, so the image and the page cannot drift apart.

The `user@laptop` half comes from the entry's author name, falling back to
`mathieu`, exactly as `x-terminal.prompt` already does.

### Which entries get an image

Published entries **that have a URL**. The current loop takes every published
entry, which includes the seven unrouted `trivia` fortunes — titles such as
"Commander Keen was first released on December 14, 1990" — each producing a PNG
nothing links to.

An entry with no date renders the identity line with the domain alone, rather
than an empty right edge.

## Code structure

Keep PHP GD. It does everything the design needs — measured wrapping, the size
ladder, the cursor, compositing artwork and avatar — and adds nothing to the
deploy job. A headless-browser renderer would reuse the site's real CSS but puts
Chromium in CI for one PNG.

| File | Role |
|---|---|
| `app/Support/SocialImage.php` | new. Renders one PNG from resolved fields (title, prompt, domain, date, and the artwork/avatar/font paths). Its text-fitting step is a separate public method returning the chosen size and the wrapped lines, so the ladder is testable without GD or the filesystem. |
| `app/Support/Terminal.php` | gains `forEntry()`. |
| `app/Support/SocialImageGenerator.php` | new. Queries the published entries that have a URL, maps each through `Terminal`, and hands it to `SocialImage`. This loop was originally specified as living in the provider; it moved out because a closure registered on `SSG::after` cannot be called from a test, and "which entries get an image" is the rule most likely to regress silently. |
| `app/Providers/AppServiceProvider.php` | keeps only the `SSG::after` wiring. The ~80 lines of GD leave the provider. |
| `resources/views/*.blade.php` (5 files) | switch their `Terminal::path` / `Terminal::file` calls to `forEntry()`. |
| `resources/fonts/IBMPlexMono-Bold.ttf` | new, build-time only. |

Output path and the `og:image` meta tag are unchanged:
`/assets/pages/{slug}.png`.

## Failure behaviour

A missing or unreadable font, artwork or avatar throws, failing the deploy.
Today a GD failure emits a PHP warning into the build log and the site ships
blank or absent images — you find out from LinkedIn.

## Tests

The repo carries only the stock `ExampleTest`s, so this stays proportionate:

- The fitting ladder: `Nostalgia` → 118pt, 1 row; the 76-char title → 54pt,
  4 rows; an absurdly long title → 40pt, truncated with `…`; a single word wider
  than the measure at 40pt → hard-broken.
- The cursor rule: drawn when the last row leaves room, omitted when it does not,
  and in neither case does the chosen size change.
- `Terminal::forEntry()` across blog (with and without a category), games and
  pages, plus `terminal_path` / `terminal_file` overrides.
- One smoke test rendering a card to a temporary file, asserting a 1200×630 PNG
  is produced.

## Out of scope

- **Slug-keyed collisions.** Images are keyed by slug alone, so a blog post and a
  review sharing a slug would overwrite each other. Pre-existing; no collisions
  today.
- **Taxonomy pages** get no image. `default.blade.php` builds the URL from the slug
  regardless, and there is no site-level image behind it, so a routed term page would
  advertise a preview that 404s. No term page is routed today; if one ever is, it needs
  either its own generated image or a real site-level default.
- **Per-entry custom images.** The `$page->image` override in `default.blade.php`
  already covers this and is untouched.

## Rejected

| Option | Why not |
|---|---|
| Terminal window chrome (title bar, border) around the content | The site deliberately has no boxes — ADR 0002. |
| Dot-leader readout rows (`Date ..... August 1, 2026`) at the bottom | Unreadable at thumbnail size; the space is better spent on the title. |
| Tags in the identity line | Same, and the date is the more useful of the two. |
| All-phosphor-green palette | Most distinctive in a feed, but costs the title its contrast. |
| One fixed title size for every post | Consistent, but either wastes the canvas on short titles or truncates long ones. |
| Scanlines over the canvas | Moiré under LinkedIn's thumbnail resampling. |
| Headless browser rendering | Chromium in the deploy job for a single 1200×630 PNG. |
