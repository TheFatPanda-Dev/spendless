---
name: SpendLess
description: Money made simple. A calm, green personal-finance workspace that leads with what is left to spend.
colors:
  deep-green: "#006447"
  mint-wash: "#ddf4e6"
  mint-hero: "#daf4e5"
  hero-ink: "#103b2a"
  hero-muted: "#36634e"
  plot-green: "#08754f"
  ring-track: "#a9ceb7"
  sage-canvas: "#f6f7f2"
  card-white: "#ffffff"
  sage-tint: "#edf1ec"
  ink: "#151a18"
  muted-ink: "#526158"
  hairline: "#d6ded7"
  control-grey: "#718079"
  budget-red: "#a02d33"
  error-surface: "#ffe5e6"
  amber-warning: "#784500"
  warning-surface: "#ffe9c9"
  series-forest: "#176343"
  series-sage: "#60aa83"
  series-amber: "#e5a251"
  series-slate: "#8aa9bc"
  series-stone: "#abb6ac"
  dark-canvas: "#111714"
  dark-card: "#1b231e"
  dark-tint: "#243029"
  dark-ink: "#f1f5ef"
  dark-muted-ink: "#b0c0b5"
  dark-hairline: "#38473d"
  mint-action: "#09c98c"
  dark-action-ink: "#092419"
  dark-hero: "#193a2b"
  dark-hero-ink: "#e6faed"
  dark-plot: "#57fe90"
  dark-positive: "#66e8a9"
  dark-ring: "#69f0b7"
  dark-red: "#ffb0b3"
  dark-amber: "#ffd096"
typography:
  display:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "4rem"
    fontWeight: 300
    lineHeight: "4.25rem"
    letterSpacing: "-0.02em"
    fontFeature: "\"tnum\", \"lnum\""
  page-title:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2.5rem"
    fontWeight: 400
    lineHeight: "3rem"
    letterSpacing: "-0.01em"
  screen-title:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2rem"
    fontWeight: 400
    lineHeight: "2.375rem"
    letterSpacing: "-0.01em"
  section:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 500
    lineHeight: "1.75rem"
  body:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  control:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 500
    lineHeight: 1.4
  amount:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: 1.5
    fontFeature: "\"tnum\", \"lnum\""
  meta:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  group-label:
    fontFamily: "Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.43
    letterSpacing: "0.1em"
rounded:
  bar: "4px"
  sm: "8px"
  md: "10px"
  control: "12px"
  phone-add: "14px"
  toast: "16px"
  card: "20px"
  pill: "9999px"
spacing:
  hairline-gap: "3px"
  xs: "4px"
  sm: "8px"
  md: "12px"
  control-x: "14px"
  lg: "16px"
  card-phone: "20px"
  card: "24px"
  hero: "32px"
  gutter-desk: "40px"
components:
  button-primary:
    backgroundColor: "{colors.deep-green}"
    textColor: "{colors.card-white}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 20px"
    height: "44px"
  button-primary-dark:
    backgroundColor: "{colors.mint-action}"
    textColor: "{colors.dark-action-ink}"
    rounded: "{rounded.control}"
    height: "44px"
  button-outline:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.ink}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 14px"
    height: "44px"
  button-text:
    textColor: "{colors.deep-green}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "44px"
  button-phone-add:
    backgroundColor: "{colors.deep-green}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.phone-add}"
    size: "44px"
  input:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "4px 14px"
    height: "44px"
  panel:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "24px"
  hero-card:
    backgroundColor: "{colors.mint-hero}"
    textColor: "{colors.hero-ink}"
    rounded: "{rounded.card}"
    padding: "32px"
  nav-item:
    textColor: "{colors.muted-ink}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "48px"
  nav-item-active:
    backgroundColor: "{colors.mint-wash}"
    textColor: "{colors.deep-green}"
  icon-tile:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.muted-ink}"
    rounded: "{rounded.control}"
    size: "40px"
  icon-tile-income:
    backgroundColor: "{colors.mint-hero}"
    textColor: "{colors.deep-green}"
  notice-warning:
    backgroundColor: "{colors.warning-surface}"
    textColor: "{colors.amber-warning}"
    rounded: "{rounded.control}"
    padding: "12px 16px"
  notice-error:
    backgroundColor: "{colors.error-surface}"
    textColor: "{colors.budget-red}"
    rounded: "{rounded.control}"
    padding: "12px 16px"
---

# Design System: SpendLess

## Overview

**Creative North Star: "The Quiet Ledger"**

SpendLess is a calm sage-and-green workspace that answers one question first: how much is left to spend this month. Everything else (where the money went, what happened lately, where it sits) follows in white cards on a warm sage canvas, separated by hairlines rather than shadows. The look is the owner's SpendLess identity kit (`design/identity-kit/`), executed as a category-standard finance app at the quality bar of YNAB and Lunch Money. The kit is binding; this document records how the shipped code implements it.

Density is moderate: generous 20px-radius cards, 44px controls, one primary action per screen. Numbers carry the hierarchy, not decoration: the remaining budget is the only Light 64px figure on a screen, amounts are tabular and signed with a true minus, and colour is reserved for meaning (green for money in and the primary action, red only for over budget, amber for warnings). The direction contract refuses the dark neon dashboard tile wall and the trading-app red/green ticker; spending is not painted red.

The system has a light theme (sage) and a dark theme (deep green-black with a mint action), switched by the `.dark` class from the user's appearance setting. Both share every token role; only values change.

Coverage boundary: the finish review shipped and approved Overview (desktop light and dark, phone), Activity, Budgets and an Account page. The add-transaction sheet, inline transaction editing, auth screens, Tags, Imports, Settings and phone dark mode were not reviewed. Those surfaces inherit the tokens below, but no rule here was derived from them; treat their current look as unverified. The admin area is moving to Filament and is outside this system.

**Key Characteristics:**
- Sage canvas, white cards, hairline borders; no structural shadows.
- One mint hero card per screen carrying the "Left to spend" figure in Roboto Light.
- Deep green is the single action colour; everything else is ink, muted ink or a series colour.
- Tabular lining figures for every amount; true minus "−" for outflows, "+" for inflows.
- 44px touch targets everywhere, including phone icon buttons and the sheet close button.
- Three layouts from one source: 230px rail (desk), 92px compact rail (tablet), top bar plus bottom tabs (phone).

## Colors

A restrained green family on warm sage neutrals, with one amber and one red held back for warnings and overspend. Every colour is a semantic role in `resources/css/app.css` (`:root` for light, `.dark` for dark); components use roles, never raw hex.

### Primary
- **Deep Ledger Green** (deep-green): the one primary action per screen ("Add transaction", "Show results"), the phone "+" button, focus ring, positive amounts and text links ("View all activity", "Details"). In dark mode its role is taken by **Mint Action** (mint-action) with **dark-action-ink** text.
- **Mint Wash** (mint-wash): selected state. The active rail item, active bottom tab pill, selected month in the picker, segmented control selection. Paired with deep-green text.

### Secondary
- **Mint Hero** (mint-hero) with **Hero Ink** (hero-ink) and **Hero Muted** (hero-muted): the hero card's surface, figure and supporting labels. Also the income icon tile and the success toast. Dark: dark-hero / dark-hero-ink.
- **Plot Green** (plot-green) on **Ring Track** (ring-track): the budget ring's arc and track, and the fill of a category budget bar. Dark: dark-plot on a darker track.

### Tertiary
- **Category series** (series-forest, series-sage, series-amber, series-slate, series-stone; `--chart-1..5`): category spending, assigned by rank of spend, largest first. The fifth (stone) is also "Other". Dark mode lifts chart-1 and darkens chart-5; the middle three are shared.

### Neutral
- **Sage Canvas** (sage-canvas): page background. Dark: dark-canvas.
- **Card White** (card-white): panels, inputs, outline buttons, rail. Dark: dark-card.
- **Sage Tint** (sage-tint): muted fills, hover rows, empty bar tracks, desktop day-header rows at 45%. Dark: dark-tint.
- **Ink** (ink): primary text and outflow amounts. Dark: dark-ink.
- **Muted Ink** (muted-ink): metadata, column headers, inactive nav, the select chevron. Dark: dark-muted-ink.
- **Hairline** (hairline): every border and divider. Dark: dark-hairline.
- **Control Grey** (control-grey): control borders at 70% opacity (inputs, selects, outline buttons). Its own role so controls read as interactive without darkening the hairlines.

### Status
- **Budget Red** (budget-red) on **error-surface**: over-budget bars, overspend text, error toasts and notices. Dark: dark-red.
- **Amber Warning** (amber-warning) on **warning-surface**: the over-budget notice on Overview, consent and sync warnings. Dark: dark-amber.

### Named Rules
**The One Green Action Rule.** Each screen has exactly one filled deep-green button. Secondary actions are outline buttons or green text buttons.

**The Meaning-Only Colour Rule.** Red appears only when a budget is exceeded or something failed. Ordinary spending is ink with a minus sign, never red; income is green with a plus sign.

**The Ranked Series Rule.** Category colours follow spend rank (chart-1 is the largest category this period), not a fixed category-to-colour mapping, so the biggest share is always the darkest green.

## Typography

**Display Font:** Roboto (self-hosted Light 300, Regular 400, Medium 500, from the identity kit; fallback ui-sans-serif, system-ui, sans-serif)
**Body Font:** Roboto
**Label/Mono Font:** none distinct

**Character:** One family in three weights. Light is reserved for big money figures so they read as calm quantities; Regular for titles; Medium for row names, amounts in lists, section titles and buttons. Roboto is pinned by the owner's kit and stays even though the detector flags it as overused.

### Hierarchy
- **Display** (300, 4rem / 4.25rem, -0.02em, tabular): the "Left to spend" figure only. 3.5rem on phones; `HeroFigure` shrinks it further through a container query so long amounts never clip. Large secondary figures (hero sub-figures, wallet totals, account balance at 2.75rem) also use Light.
- **Page title** (400, 2.5rem / 3rem, -0.01em): the screen title from tablet up. No eyebrow above it.
- **Screen title** (400, 2rem / 2.375rem): the same title on phones, sharing a row with the month.
- **Section** (500, 1.375rem / 1.75rem): panel titles ("Spending by category", "Recent activity"), sheet titles.
- **Body** (400, 16px): row names (at 500), category and wallet columns, inputs.
- **Control** (500, 15px): buttons, nav items, notices, picker text. 15px is the system's UI size.
- **Meta** (400, 14px): row subtitles, "updated 18 Nov", page descriptions (15px, max 65ch).
- **Group label** (500, 14px, 0.1em, uppercase): day headers in the ledger ("TUESDAY 17 NOVEMBER"). Table column headers use the same treatment at 12px and 0.12em. These label data groups and columns; they are not decorative eyebrows.

### Named Rules
**The Tabular Money Rule.** Every amount uses the `amount` utility (tabular, lining figures) and `formatMoney`: two decimals always, a true minus sign "−" (U+2212) for outflows, "+" for inflows where the sign carries meaning, and the currency of the amount itself (€, £, US$ side by side, never silently converted).

**The Light Is For Money Rule.** Weight 300 is used only for large monetary figures. Text never goes Light.

## Layout

Three layouts from one source, at the kit breakpoints `tablet` (48rem / 768px) and `desk` (68.75rem / 1100px):

- **Desk (≥1100px):** fixed 230px white rail with the full lockup, primary destinations (Overview, Activity, Budgets, Wallets, Tags, Imports) and Admin/Settings at the foot above a hairline. Main column max 1200px, 40px side gutters, 48px top padding.
- **Tablet (768–1099px):** 92px compact rail, symbol only, icon over a 12px label. 24px gutters.
- **Phone (<768px):** sticky 64px top bar (lockup left, the screen's 44px "+" add action right) and a fixed bottom tab bar (Overview, Activity, Budgets, Wallets, More). More opens a bottom sheet with the remaining destinations and Log out. 20px gutters, 112px bottom padding to clear the tabs.

Page rhythm: page header (title left; month picker and the one primary action right), then a 24px/32px gap, then cards stacked or in a two-column row (hero beside "Spending by category" on desk). Panels pad 20px on phones and 24px from tablet; the hero pads 32px from tablet.

Phone specifics, all deliberate:
- Title and month share one row; the month picker becomes quiet text ("Nov 2026 ⌄", no border, no calendar icon) and loses its arrow buttons.
- The hero drops its "Monthly budget" heading and leads with "Left to spend"; the ring shrinks.
- The transaction ledger leaves its panel: rows sit directly on the canvas with a hairline between day groups.
- Activity's filters collapse into a single "Filters" control that opens a bottom sheet (type segmented control, search, wallet, category, tag; "Clear all" and "Show results" at the foot).
- Toasts sit above the tab bar (bottom 96px); from tablet they sit bottom-right, max 28rem.

## Elevation & Depth

Flat by default. Depth comes from tonal layering (sage canvas, white card, mint hero) and 1px hairlines, not shadows. Cards, the rail and the ledger carry no shadow in the approved screens. Shadow appears only on floating layers that sit over content: toasts, sheets, dropdown menus.

### Shadow Vocabulary
- **Float** (`box-shadow: 0 8px 24px -12px rgb(0 0 0 / 0.35)`): toasts.

### Named Rules
**The Hairline Not Shadow Rule.** Separation between content is a 1px hairline or a tonal step. If an element sits in the page flow, it has no shadow.

## Shapes

Soft, consistent rounding in two main steps: 12px for controls (buttons, inputs, selects, nav items, notices, icon tiles at `rounded-xl`) and 20px for cards and sheet tops (`rounded-card`). The phone "+" is 14px, toasts 16px. Pills (fully round) are reserved for the active bottom-tab indicator, budget bars, tag chips and legend dots. The budget ring is an open three-quarter arc with round caps. Icons are lucide outlines at 1.75 stroke, 20–24px, always inside a 44px target when interactive; native selects get the same 1.75-stroke chevron drawn in CSS.

## Components

### Buttons
Quiet and solid; one filled button per screen.
- **Shape:** 12px radius, 44px tall, 15px Medium.
- **Primary:** deep-green fill, white text, 20px horizontal padding, leading plus icon where it adds. Hover at 90% opacity; press nudges down 1px. Dark mode: mint-action fill with dark-action-ink text.
- **Outline:** card fill, control-grey border at 70%, ink text; hover fills sage-tint. Used for month arrows, "Sync all", "Add wallet", "Clear all".
- **Text:** deep-green text on no fill, 44px tall, hover sage-tint ("Change", "Set budget", "Details").
- **Phone add:** 44px square, 14px radius, deep-green, 24px plus; the screen's add action in the phone top bar. Press scales to 0.96.
- **Focus:** 2px ring-coloured outline with a 2px offset on every focusable element.

### Chips
- **Tag chip:** pill with an 8px colour dot and the tag name, hairline border on a translucent canvas fill, small text. Read-only under a ledger row; as a filter it is a pressable toggle.
- **Segmented control:** a card-white track with hairline border; the selected segment fills mint-wash with deep-green text (Filters sheet: All / Expenses / Income).

### Cards / Containers
- **Panel:** card-white, 1px hairline, 20px radius, 20px padding on phones and 24px from tablet, optional section title with a right-aligned text action.
- **Hero card:** mint-hero, no border, 20px radius, 32px padding from tablet. "Left to spend" label, display figure, the budget ring to the right, then a hero-ink divider and two sub-figures (spent so far, budget). One per screen, Overview and Budgets only.
- **Notices:** 12px radius, 12px by 16px padding, 15px text with a leading icon and a trailing text action ("Review transportation expenses ›"). Warning uses amber on warning-surface; overspend errors use red on error-surface.

### Inputs / Fields
- **Style:** 44px, card fill, control-grey border at 70%, 12px radius, 14px horizontal padding, 16px text, muted-ink placeholder, deep-green caret.
- **Select:** same box with the CSS-drawn chevron 14px from the right and 40px right padding, unlayered so utilities cannot push text under it.
- **Focus:** the global 2px outline, 2px offset.
- **Error / Disabled:** invalid fields take a destructive border; disabled fills sage-tint with muted text.

### Navigation
- **Rail item:** 48px tall (56px stacked in the compact rail), 12px radius, 22px icon, 15px text. Inactive is muted-ink, hover is half mint-wash with ink text, active is mint-wash with deep-green Medium text.
- **Bottom tabs:** five equal columns on a 95% canvas with backdrop blur and a top hairline; each tab is a 56px target with a 32 by 56px pill behind the icon that fills mint-wash when active, 12px label below.

### Transaction Ledger (signature)
The shared transaction list on Activity and Account pages.
- **Desk and tablet:** one panel. Day headers are full-width rows tinted sage-tint at 45% with the group-label type and hairlines above and below. Rows are a grid with a fixed 2.5rem icon column and a fixed 8.5rem right-aligned amount column, so category and wallet columns align across days. Rows have hairline dividers, hover sage-tint, and open their editor in place (open row tinted mint-wash at 40%).
- **Phone:** no panel; rows sit on the canvas, category and wallet collapse into one meta line under the payee, and a hairline separates day groups.
- **Row:** 40px icon tile (card fill with hairline for spending; mint-hero with green icon for income), payee in 16px Medium, amount in 16px Medium tabular, green with "+" for income and ink with "−" for spending.

### Budget Ring
An open three-quarter ring (stroke 7.5% of its size, round caps) on the ring-track, filled with plot-green in proportion to budget used. The percentage and "used" sit in the centre, scaled by container query. Over 100% the arc caps at full, turns budget-red, and the text states the real percentage.

### Category Bars
- **Spending by category:** one stacked bar, 24px tall, segments in ranked series colours separated by a 3px gap, outer ends rounded; five labelled rows below with colour dots and amounts. One shared linear scale.
- **Budget bar:** each category measured against its own budget. A 10px pill track in sage-tint; the plot-green fill is what has gone out. Over budget the whole bar is budget-red, with a 2px ink tick standing proud of the bar where the budget ran out. The row text repeats it in words ("€76.51 over budget · 151% used").

### Sheets and Toasts
- **Bottom sheet:** 20px top radius; header with a section-size title and a 44px close button (12px radius, hover sage-tint).
- **Toast:** 16px radius, 15px text with a leading icon, Float shadow. Success uses mint-hero/hero-ink for 4s; errors use error-surface/red for 6s and announce as alerts.

## Do's and Don'ts

### Do:
- **Do** lead every money screen with the amount left to spend, in the Light display figure inside the mint hero card.
- **Do** format every amount with `formatMoney` and the `amount` utility: two decimals, "−" for outflows, "+" for inflows, the amount's own currency.
- **Do** keep every interactive element at least 44px (h-11), including icon buttons, picker arrows and sheet close buttons.
- **Do** use the semantic roles (`bg-card`, `text-muted-foreground`, `border-control/70`, `bg-hero`) so light and dark both work; never hard-code hex in components.
- **Do** colour categories by spend rank with chart-1..5 and keep one shared scale per chart.
- **Do** separate content with 1px hairlines and tonal steps; reserve shadow for floating layers.
- **Do** collapse dense controls on phones into a sheet (Filters, More) rather than wrapping rows of selects.

### Don't:
- **Don't** put an eyebrow or kicker label above page titles; the kit's "PERSONAL WORKSPACE" label is deliberately omitted.
- **Don't** add a second filled deep-green button to a screen.
- **Don't** colour ordinary spending red or use a red/green ticker for amounts; red means over budget or failure.
- **Don't** use Roboto Light for text, or swap Roboto for another family; the kit pins it.
- **Don't** add shadows to cards, panels or the rail.
- **Don't** use a hyphen-minus for negative amounts, or drop the cents.
- **Don't** use unicode glyphs as icons; use lucide outlines at 1.75 stroke.
