---
name: resize-images
description: Shrink oversized images under public/assets/ to the site's limits (max 1232px wide, under 400KB) in place, keeping the original file extension and format. Use when adding or committing images, when a JPG/PNG/WebP asset is too large or too wide, or when asked to optimize, resize, compress, or shrink images for casmo.github.io.
---

# Resize images (casmo.github.io)

Site image limits: **max 1232px wide**, **under 400KB**. The script enforces both
**in place** and **keeps the original extension** (png→png, jpg→jpg, webp→webp),
so existing references and `.meta` sidecars stay valid.

## Quick start

```bash
# preview without writing
php .claude/skills/resize-images/resize.php --dry-run public/assets/games/*.png

# resize in place
php .claude/skills/resize-images/resize.php public/assets/games/big.png
```

Pass any number of files. Output is one line per file: `ok`, `dry`, or `skip`,
with before→after size, final width, and the lever used (e.g. `q70`, `128 colors`).

## Workflow

1. **Find candidates** — images wider than 1232px or over 400KB:
   ```bash
   find public/assets -type f \( -iname '*.jpg' -o -iname '*.png' -o -iname '*.webp' \) \
     -size +400k
   ```
2. **Dry-run first** to see what would change.
3. **Run in place.** The script overwrites files; git is the safety net, so run
   on a clean working tree or review the diff after.
4. **Done.** Don't rename files or change extensions — that breaks Markdown
   references in `content/` and the cached `public/assets/.meta/*.yaml` sidecars
   (the generator refreshes those on the next build).

## How it hits the targets

Applied in order, stopping as soon as the file is under 400KB:

1. **Downscale width** to 1232px (never upscales; aspect ratio preserved).
2. **JPEG / WebP** — step quality down (85→35) until under budget.
3. **PNG** — re-save lossless first; if still over, reduce the colour palette
   (256→32 colors) keeping the `.png` extension.

Already-compliant images (≤1232px and <400KB) are **skipped untouched** — no
re-encode, no quality loss. Transparency is preserved for PNG and WebP.

## Notes

- Engine is PHP's GD (PHP 8.4, WebP read+write enabled). No ImageMagick or
  `cwebp` needed; `sips` is avoided because it cannot write WebP.
- If a line prints `OVER-BUDGET`, the image couldn't get under 400KB at the
  minimum quality/palette — usually a very busy full-width screenshot. Crop it
  or accept it; flag it to the user rather than mangling it further.
