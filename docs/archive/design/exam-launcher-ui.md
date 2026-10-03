# The exam launcher: UI principles

Written after walking medofast.ir's real launcher (not its marketing pages) at
1280px and at 375px, on the owner's instruction to extract what it does well
before changing anything of ours.

This is the standard the FANOOS launcher is built against. It is not a
description of medofast's visuals — a copied look would be someone else's
product — but of the *principles* underneath them, which are general.

---

## 1. Hierarchy is carried by weight and colour, not by size alone

Their stem is bold and their choices are regular, at sizes only a step apart.
The reading order is obvious from two paces away without anything being large.

Ours used one weight almost everywhere and leaned on size, so a question, its
options and its footnotes all read as the same kind of text.

**Rule.** Every text role declares a size *and* a weight *and* a colour tier.
Three weights ship (400/600/700); a role that does not use weight is a role
that has not been designed.

## 2. One type scale, not ad-hoc sizes

The launcher stylesheet contained nine different font sizes — 0.75, 0.78,
0.8, 0.82, 0.85, 0.88, 0.9, 1, 1.05rem — none of which referred to each
other. That is not a scale, it is nine decisions taken separately.

**Rule.** Sizes come from `--text-*` tokens. A literal `rem` in a page
stylesheet is a bug, exactly as a literal colour is.

## 3. Numbers are furniture: they must not shift

Counters, timers, scores and question numbers all change in place. With
proportional digits every change nudges the layout.

**Rule.** Anything numeric that updates carries `font-variant-numeric:
tabular-nums`.

## 4. The ground is a surface, not a void

Their page has a faint woven texture. Cards rest *on* something.

**Rule.** The ground keeps its weave; cards are the only elevated objects; a
card never sits on another card.

## 5. A card is a sequence of sections, not a padded box

Theirs runs: progress strip → header → hairline → facts grid → hairline →
titled statistics block → hairline → stem → choices → action. Each section
has its own vertical rhythm and its own ground where it helps.

**Rule.** Sections own their padding; hairlines separate them; the card owns
only the frame.

## 6. Every fact gets an icon, and the icon carries the colour

Their facts are `icon + label + value` rows in a two-column grid, each icon a
different colour. The text stays near-black; the colour lives in the icons.

This is what makes a block of metadata skimmable rather than a paragraph.

**Rule.** A fact is a row: coloured icon, tertiary label, primary value. Hue
names a *kind of information* — never a mood, never decoration.

## 7. Empty slots stay visible

They are not logged in, so their per-question statistics read «؟ بار» — the
row, its icon and its label are all still there. The shape of the page does
not change when the data arrives.

This is the owner's explicit instruction: we do not collect difficulty or
per-question statistics yet, and the layout must already have the place for
them.

**Rule.** A fact we expect to have later renders with an explicit "not
recorded" value, never omitted and never faked with a zero. A placeholder
says *unknown*; a zero says *none*, and those are different claims.

## 8. Choices are a list, not a row of buttons

Theirs are unboxed rows with a radio mark. Ours were 2px-bordered filled
boxes, which read as five competing call-to-action buttons stacked above the
actual call to action.

**Rule.** A choice is quiet until it is chosen or ruled out. Selection,
strike-through and verdict are the only states that may add weight.

## 9. Mobile is a different layout, not a narrower one

At 375px they keep the two-column facts grid, go nearly full-bleed with small
gutters, and move navigation into a fixed bottom bar. On desktop that bar does
not exist; the same actions are inline.

The owner's instruction: both must be optimised, and they are allowed to
differ.

**Rule.** Primary navigation is thumb-reachable and fixed on small screens,
inline on large ones. Grids reflow by content width, not by breakpoint
guesswork. Nothing essential is hidden on mobile — it moves.

## 10. Motion states what happened, and never repeats itself

**Rule.** Entrances are ≤200ms and ≤16px of travel; they fire on a real
change of content, never on an incidental re-render; everything collapses
under `prefers-reduced-motion`.

---

## What we deliberately do not copy

- **Their watermark.** We protect the bank by serving one question per
  request and pacing it, which works; a diagonal watermark over the stem
  costs legibility for a student who is reading it honestly.
- **Their brand hue.** Crimson is medofast's identity, not ours.
- **Emoji icons.** They are inconsistent across platforms and cannot take a
  theme; ours are inline SVG that inherit `currentColor`.
