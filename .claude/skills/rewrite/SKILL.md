---
name: rewrite
description: Tighten and copyedit a draft post for casmo.github.io in Mathieu's voice. Cut fluff, fix grammar and flow, preserve the author's wording and structure. Use when asked to rewrite, copyedit, tighten, polish, or proofread a blog/game/page entry under content/collections/, or when a draft needs a light editorial pass before publishing.
---

# Rewrite (casmo.github.io)

Lightest-touch editorial pass. **Tighten and copyedit — do not restructure or rewrite from scratch.** Keep the author's words; remove what gets in the way.

## Workflow (propose, then apply)

1. **Read the file**, including frontmatter. Note the `blueprint` (`blog`, `game`, or `page`) — it sets the voice mode below.
2. **Propose first.** Show what you'd change and why — either the full rewritten body or a tight list of edits with before/after for anything non-trivial. Do not edit yet.
3. **Apply on confirmation.** Edit the `.md` in place. Preserve all frontmatter fields and IDs untouched (except an obvious typo in a `title`/`verdict`-type field, which you flag separately).
4. Never invent facts, opinions, ratings, or links the author didn't write.

## Voice (all content types)

- First-person, direct, conversational. Say it plainly; no marketing gloss, no hype adjectives ("amazing", "powerful", "seamless").
- Lead with the point. The author opens posts mid-thought, not with throat-clearing.
- Short sentences over long ones. Cut filler ("simply", "just", "in order to", "very").
- Keep links generous and inline — never strip the author's links.
- Keep it short. Don't pad to length or add a summary the author didn't intend. Posts end when the thought ends.
- Preserve the author's casual edges and personality; fix errors, don't sand off voice.

## What "copyedit" fixes

Grammar, spelling, verb agreement, awkward word order, doubled words, possessive/plural slips (e.g. "combo's" → "combos"), and run-ons. Examples of real slips to catch: "intensifly", "In X is a new take" → "X is a new take", "My fingers are not used at clicking" → "are not used to clicking".

## Per-blueprint notes

- **`blog` — technical tutorials:** Keep code blocks and their language fences verbatim — never reword code. Tighten only the prose framing around them. Keep the step ordering the author chose.
- **`blog`/`page` — personal essays:** Reflective, image-heavy. Preserve the image markup and captions. Light touch on the prose; protect the wistful tone.
- **`game` — reviews:** Body often uses `**Bold label**` mini-sections (Game flow, Art style, What I would improve) and bullet lists — keep that pattern. Copyedit the `verdict`, `designers_takeaway`, and `what_id_steal` frontmatter fields too, but flag any wording change there for explicit sign-off since they're opinions.

## Don't

- Don't change titles, slugs, dates, or IDs unless fixing a clear typo (and flag it).
- Don't add headings, intros, or outros the author didn't write (that's restructuring, out of scope).
- Don't Americanize/Britishise spelling beyond consistency within the post.