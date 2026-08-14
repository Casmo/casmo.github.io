# Context

Glossary for mathieuderuiter.nl. Terms only — no implementation detail, no decisions.
Decisions live in [docs/adr/](docs/adr/).

## Presentation

**Session**
: The whole page read top to bottom as one shell session: a **Prompt Line** running
  `ls`, then one running `cat` on the page's file, then one running `fortune`. Exactly
  three prompts per page. A fourth would make it read as a gag rather than a place.

**Prompt Line**
: `mathieu@laptop:~/blog/engineering$ cat scorched-earth.txt`. Made of a host, a
  **Terminal Path**, a sigil, and a command.

**Terminal Path**
: The directory a page appears to live in — `~/about`, `~/blog/{category}`,
  `~/reviews`, `~/books`. Always lowercase, because directories are. Derived from the
  entry, overridable per entry.

**Terminal File**
: The file a page appears to be — `scorched-earth.txt`. Defaults to the entry slug;
  a few pages override it with a hand-picked name (`introduction.txt`,
  `useful-stuff.txt`) that no field implies.

**Screen**
: The scanlined background, running the full height of the site. Painted behind the
  glyphs rather than over them, so it never costs the text any contrast, and fixed to
  the viewport, because a tube's phosphor grid does not scroll with what is printed on
  it. There is one screen and everything sits on it — no region of the page carries a
  background of its own, code blocks included.

**Chrome**
: Everything that frames the writing: prompt lines, the **Readout**, the footer. Chrome
  is what the phosphor glow is allowed on. Code blocks are not chrome — they are quoted
  material, and keep their own syntax colours.

**Prose**
: Article body text. Never glows, so long reads stay legible.

**Panel**
: One continuous run of **Chrome** — the nav and page header share the panel at the top
  of the page, the **Fortune** has the one at the bottom. A panel is space, not a box:
  it has no texture, background or rule of its own, and only exists to keep the page
  reading as header, output, sign-off.

**Shell**
: The reading column. **Panels** run edge to edge, but their contents sit on this so
  they line up with the prose.

**Readout**
: The block of **Leader Rows** under a prompt line, holding a page's metadata.

**Leader Row**
: One row of the readout: a label, dots filling a fixed character width, then a value —
  `Rating ........ 8/10`. For short single-line values only.

**Section Marker**
: `// verdict`. Heads a block or a list. Anything multi-line is a section, never a
  leader row: dots running across to a paragraph read as broken, not retro.

**Accent**
: The single interface hue. Governs all UI and text. Artwork is not interface and
  answers to **Accent Dim** instead. Two things keep their own colours outright: the
  **Fortune** icons and the syntax hues in code blocks.

**Accent Dim**
: The muted accent used by artwork — the **Dungeon**'s lit tiles — so the footer belongs
  to the palette without competing with the nav.

**Character Cell**
: The fixed vertical unit every line-height and vertical margin is a whole multiple of,
  the way a real terminal has one character cell. Nothing sits off-grid.

## Content

**Fortune**
: The random piece of games trivia printed at the foot of every page, centred. Named
  after the Unix program that printed a random quip as you logged out. Its 1-bit icons
  stay as authored — native size, untinted, nearest-neighbour — because they are
  artefacts of the games they came from rather than palette decoration.

**Trivia**
: The collection the **Fortune** draws from. Each entry is one fact plus a 1-bit icon.

**Trivia Tilesheet**
: Every **Trivia** icon on one generated grid, one cell per icon, sized to the largest
  icon in each axis and shown on the resources page. Distinct from the **Dungeon**'s
  tilemap, which is hand-authored artwork: this one is derived from the collection and
  rewritten on every build.

**Dungeon**
: The pixel-art room across the bottom of every page, drawn as a grid of tiles cut from
  a single tilemap and authored by hand as a grid of coordinates. Artwork, not
  interface.

**Palette**
: An image plus its extracted colour swatches, expanded from a `{{ palette }}` snippet
  inside post content.

**Review**
: A game write-up. Carries a **Verdict** (one-line TL;DR), a **Designer's Takeaway**
  (the single design lesson the game teaches) and **Influences** — the books that
  shaped the critique.
