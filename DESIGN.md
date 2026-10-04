# DESIGN.md: Clash Commons

Design direction for antislop and any UI work. **The full, authoritative design system is
[`specs/18-design-system.md`](specs/18-design-system.md)**; this file transcribes its direction
fields and records owner decisions where spec 18 meets an antislop rule. Token values live only in
spec 18: never copy them here.

## Identity

A **game companion, not a dashboard**, for Clash of Clans players (spec 18 §1). Our visual identity
is original, drawn from the genre's conventions (chunky buttons, resource counters, rarity tiers).
Clash of Clans assets appear only to identify game content, unmodified, via `GameAssetResolver`
(spec 18 §2).

## Personality

Bold, chunky, tactile, rewarding. Two registers:
- **Celebrate** (cards, badges, stats, profiles): lean into the game vibe.
- **Work** (forms, tables, settings, admin, moderation): clean, dense and plain, on purpose.

## Palette

Spec 18 §3. Core: **navy** (surfaces), **gold** (primary, actions and highlights), **purple**
(accent, featured and rarity). Functional state colours (success, danger, warning, info) and the
Town Hall tier ramp encode meaning and are always paired with a label or numeral.

## Typography

Spec 18 §3. **Lilita One** for display (headings, badges, stats; never below 16px, never
paragraphs), **Inter** for body, **JetBrains Mono** for player tags only. Self-hosted.

## Mood

Dark, warm-lit game UI: depth from solid bottom borders that compress on press, not soft shadows.
Mobile-first at 375px; most players browse on a phone between attacks.

## Dials

Dial: ENERGY 2 / RHYTHM 2 / MOTION 2

- **ENERGY 2**: bold identity in celebrate surfaces, calm in work surfaces (admin and settings sit
  at ENERGY 1).
- **RHYTHM 2**: consistent grids (base feed, progression grids) with deliberate breaks (hero stats,
  featured bases).
- **MOTION 2**: only the motions listed in spec 18 §7 (card hover lift, like/bookmark pop, stat
  count-up, reward toast, modal/sheet, skeleton shimmer). No page transitions, no scroll-reveal,
  no parallax. Everything off under `prefers-reduced-motion`.

## Owner decisions (antislop R-37 overrides)

Recorded 2026-09-30. Each keeps a spec 18 choice that an antislop rule would otherwise question.

| Element | antislop rule | Decision | Reason |
|---|---|---|---|
| Town Hall tier ramp (7 colours) and state colours | R-29 palette cap | **Keep** | Data encoding, not decoration: every colour is paired with a numeral or label (spec 18 §3). The decorative palette stays at navy + gold + purple accent. |
| Purple accent on dark navy | R-01 purple-and-black | **Keep** | Purple marks featured and rarity, a genre convention; gold, not purple, is the primary. |
| Dark theme only at launch | R-21, R-34 | **Keep** | Game-companion mood and night-time phone use; every colour is a semantic token, so light mode is a Phase 7 token-file addition (spec 18 §3, §9). No toggle ships until then. |
| Gold glow on reward toast, verified ring, top TH tier ring | R-13 glow cap | **Keep** | Reward and status highlights only, never text, never on cards/buttons/backgrounds in bulk. |
| Purple star on the "featured" badge | R-04 generic icons | **Keep** | Labels featured content; it is a status marker, not a decorative feature icon. |
| Uppercase `--text-xs` labels (+0.04em) | R-06 | **Keep** | Small pill and label text; tracking is modest, not the extreme-spacing section-label pattern. |
| Control sizes below 44px (spec 18 §4: buttons 32/40, pills 28, modal close 40) | R-03, spec 18 §8 | **Keep visual size, add hit area** | 2026-09-30 (audit-002): the §4 sizes stay visually; a `.hit-target` 44×44 invisible area covers touch. Text inputs are 44px tall below `sm`, 40px from `sm` up. Adjacent 44px areas may overlap where controls sit 8px apart. |
| Locked units grayed out in progression grids | spec 18 §2.1 condition 2 (unmodified assets) | **Allow** | 2026-10-04: the owner wants locked units to read as in the game (grayscale + reduced opacity, no level chip) and accepted the Fan Content Policy risk. The only filter allowed on game art. |
| Em dashes (R-02) | R-02 | **Scope** | Banned in user-facing copy (Vue templates, translations, emails, notifications, meta text, OG text). Not applied to `specs/`, `docs/`, `tasks/`, code comments or commit messages. |
