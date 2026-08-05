# 2. One screen instead of chrome panels

Date: 2026-08-05

## Status

Accepted

Amends [ADR-0001](0001-terminal-as-the-single-retro-register.md), decisions 2 and 8.

## Context

ADR-0001 put the scanlines on chrome only, in two panels — one around the nav and page
header, one around the fortune — and left the prose between them untextured. It also
kept Atom One Dark's own `#282c34` background behind code fences, on the grounds that
the theme already ships a box and a second one around it would be noise.

Read on a real page, both decisions produced the same artefact: **regions**. The page was
a textured band, then a plain band, then a textured band, with a lighter box floating
inside the plain one wherever a post quoted code. Three backgrounds in one column. The
conceit was a terminal, but the page did not read as one screen — it read as a document
with terminal-styled furniture at each end.

## Decision

**The screen is the page, not the chrome.**

1. **Scanlines are the page background.** One `repeating-linear-gradient` on `body`,
   `background-attachment: fixed`, covering the full height of the site. The two chrome
   panels lose their texture; nothing is textured per region any more, because there are
   no regions.
2. **Painted behind the glyphs, not over them.** ADR-0001's chrome-vs-prose rule existed
   to protect legibility, and that concern was correct — a 30%-black overlay across body
   text is exactly what it warned about. Moving the texture from an overlay
   pseudo-element to a background sidesteps it: the stripes darken the space between
   lines, never the type. Prose keeps full contrast, so the rule it was protecting is
   satisfied rather than overridden. **Glow stays chrome-only** — that one *is* applied
   to text and would smear a paragraph.
3. **`background-attachment: fixed`.** The phosphor grid of a tube does not scroll with
   what is printed on it.
4. **Code blocks keep Atom One Dark's hues but lose its box.** `pre code.hljs` is reset
   to a transparent background and zero padding. The syntax colours are still
   information and still exempt from the one-accent rule (ADR-0001 decision 8 stands on
   that point); the background was never information. Code now sits on the same screen
   and the same left edge as the prose, and colour alone marks it as code.
5. **The panel rules go too.** With one continuous screen, a border under the nav and
   above the fortune drew back the boundaries the change removes. Whitespace separates
   the three prompts.

## Consequences

**The Chrome/Prose split is no longer about texture.** It now governs glow only. As a
distinction it survives, but it carries far less weight than ADR-0001 gave it, and
"chrome" is now mostly a statement about what a region *is* rather than how it is
painted.

**A horizontally scrolling code block has no frame.** `overflow-x: auto` still applies,
but with the box gone there is nothing to signal that a long line continues off-screen.
Accepted for now; a left rule or a gutter marker is the fix if it becomes a problem.

**The unlayered override.** highlight.js's stylesheet is imported unlayered, and an
unlayered rule beats every `@layer` regardless of specificity — so the reset lives
outside `@layer base` on purpose. Moving it into a layer silently restores the theme's
background.

**Fixed backgrounds repaint on scroll.** A single gradient over the viewport, so the cost
is negligible, but it is the reason to keep the pattern this simple.
