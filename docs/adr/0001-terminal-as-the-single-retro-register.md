# 1. Terminal as the single retro register

Date: 2026-08-05

## Status

Accepted

## Context

The site was already retro, but in two unrelated dialects at once:

- a **Unix terminal** conceit — `mathieu@laptop:~/About$ cat introduction.txt` prompts,
  `> Title:` field lines, a blinking cursor
- an **8/16-bit pixel-game** one — the dungeon tilemap footer, the `monogram` bitmap
  font, pixel trivia icons

Neither was dominant and the boundary between them was accidental. Two accent colours
(`lime-500` for nav and labels, `sky-500` for body links) were doing nearly the same
job. The terminal header was hand-typed into markdown on
some pages and templated on others, with the path casing drifting between them
(`~/About` vs `~/reviews`).

The goal was to look *more* nostalgic while keeping the overall feeling intact — so a
wholesale change of register (Web 1.0, DOS 16-colour, monochrome phosphor) was out.

## Decision

**The terminal is the anchor.** The pixel-art layer stays, but submits to the terminal's
palette rather than running its own.

1. **Colour stays, texture changes.** Background `#1f2329` and body `zinc-400` are
   unchanged. `lime-500` becomes the only accent; `sky-500` is retired and links are
   distinguished by an underline instead of a hue. The period feeling comes from
   scanlines, phosphor glow and structure — not from a new palette.
2. **Chrome vs prose, in two panels.** Scanlines and glow apply only to chrome (prompts,
   readouts, footer); article body text is never textured. This is the rule
   that makes the effect affordable on a prose-heavy site. Chrome is textured **per
   panel, not per prompt** — the nav and page header share one continuous panel at the
   top, the fortune has a second at the bottom. Texturing each prompt separately gave a
   thin scanlined strip every few lines with plain background between, which reads as
   flicker rather than as a screen; two panels give the page one transition in and one
   out. Panels run edge to edge, with their contents on the prose column.
3. **One session per page.** Nav renders as `ls`, the page header as `cat`, the footer
   trivia as `fortune` — three prompts that read as one continuous session top to
   bottom. Index listings deliberately get no prompt of their own.
4. **`>` is retired site-wide.** Short single-line data becomes dot-leader rows
   (`Rating ........ 8/10`); anything multi-line becomes a `// section marker`, adopting
   the `// designer's takeaway` idiom that already existed.
5. **The header is a component.** Path and filename derive from the entry, with optional
   `terminal_path` / `terminal_file` overrides for the hand-picked filenames
   (`introduction.txt`, `useful-stuff.txt`) that no field implies. Hand-typed prompt
   lines are removed from content. Paths normalise to lowercase.
6. **Artwork answers to the accent, dimmed — except the trivia icons.** The dungeon's two
   coloured cells (red hearth, yellow key) become a dimmed lime. The 1-bit trivia icons
   were tinted to match at first and then deliberately reverted: they read as artefacts
   of the games they came from, and tinting them to the site palette threw that away for
   no gain. They stay native size, untinted and nearest-neighbour — the single documented
   exception to the one-accent rule.
7. **IBM Plex Mono replaces Space Mono**, at 16px on a 1.75rem character-cell grid.
8. **Code blocks keep Atom One Dark.** A two-tone theme in the site palette (lime for
   keywords and strings, body zinc for the rest) was built and then reverted. On paper
   Atom One Dark is the largest violation of the one-accent rule — five hues on a
   `#282c34` background that does not match the page — but syntax colour is *information*,
   not decoration, and a familiar scheme reads faster than a pretty one. Code is quoted
   material, so it gets the same exemption as the artwork. The theme ships its own
   background, padding and layout for `pre code.hljs`, so the site adds no border,
   padding or scanlines around it — doing so drew a second box around the theme's own.

## Consequences

**Legibility was the deciding factor on the typeface.** Long posts were hard to read:
monospace flattens word shapes, and Space Mono is a display-leaning face with tight
apertures. Plex Mono has a taller x-height and open apertures, and Tailwind's 1.5
leading was replaced with 1.75 — the leading matters as much as the face. That IBM built
the terminals this aesthetic imitates makes the swap *increase* period credibility
rather than spend it. The cost is real: Plex is calmer and less idiosyncratic than Space
Mono, so the site trades some personality for readability.

**TTFs are still kept.** The browser loads woff2 (~62KB for four faces, down from 411KB
of Space Mono TTF), but the OG image generator calls `imagettftext()`, which GD can only
feed a TTF. `resources/fonts/IBMPlexMono-Regular.ttf` and `-Bold.ttf` exist solely for
that: the regular face sets the image's prompt and identity lines, the bold one its title.

**`monogram` is gone**, so the trivia strip is set in the body face. Its 1-bit icons are
now tinted rather than shown as authored.

**Statamic's paired Blade tags constrain the layout.** The body of a
`<statamic:nav:main>` tag must be plain markup and `{{ }}` expressions only — an `@php`
or `@if` directive inside makes its compiler mis-match the closing tag and silently
swallow the rest of the file. Nav-active logic therefore lives in `App\Support\Terminal`
and is called as a single inline expression.

**Section highlighting needs a URL map.** Entry routes do not sit under their section's
URL (`/read/{slug}` vs `/blog`), so Statamic's own `is_current` can never light up the
section you are reading in. `Terminal::inSection()` maps the first path segment back to
a nav URL. Adding a collection with its own route means adding a case there.

**Reversal cost is meaningful.** The register choice is threaded through the stylesheet,
a component set, six templates, three blueprints and four content files — which is why
this is written down.
