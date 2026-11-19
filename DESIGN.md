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

<!-- impeccable:design-map -->
Name: SpendLess
Colors: primary: deep-green #006447; secondary: mint-hero #daf4e5; tertiary: series-forest #176343; neutral: sage-canvas #f6f7f2; status: budget-red #a02d33; primary: mint-action #09c98c (+13 more in the sidecar)
Fonts: Roboto (display, page-title, screen-title, section, body, control, amount, meta, group-label)
Spacing: hairline-gap 3px, xs 4px, sm 8px, md 12px, control-x 14px, lg 16px, card-phone 20px, card 24px, hero 32px, gutter-desk 40px
Radius: bar 4px, sm 8px, md 10px, control 12px, phone-add 14px, toast 16px, card 20px, pill 9999px
North Star: "The Quiet Ledger"
Rules:
- The One Green Action Rule. Each screen has exactly one filled deep-green button.
- The Meaning-Only Colour Rule. Red appears only when a budget is exceeded or something failed.
- The Ranked Series Rule. Category colours follow spend rank (chart-1 is the largest category this period), not a fixed category-to-colour mapping, so the biggest share is always the darkest green.
- The Tabular Money Rule. Every amount uses the `amount` utility (tabular, lining figures) and `formatMoney`: two decimals always, a true minus sign "−" (U+2212) for outflows, "+" for inflows where the sign carries meaning, and the currency of the amount itself (€, £, US$...
- The Light Is For Money Rule. Weight 300 is used only for large monetary figures.
- The Hairline Not Shadow Rule. Separation between content is a 1px hairline or a tonal step.
Elevation:
- Float: 0 8px 24px -12px rgb(0 0 0 / 0.35)
Don't:
- Don't put an eyebrow or kicker label above page titles; the kit's "PERSONAL WORKSPACE" label is deliberately omitted.
- Don't add a second filled deep-green button to a screen.
- Don't colour ordinary spending red or use a red/green ticker for amounts; red means over budget or failure.
- Don't use Roboto Light for text, or swap Roboto for another family; the kit pins it.
- Don't add shadows to cards, panels or the rail.
- Don't use a hyphen-minus for negative amounts, or drop the cents.
- Don't use unicode glyphs as icons; use lucide outlines at 1.75 stroke.
Do:
- Do lead every money screen with the amount left to spend, in the Light display figure inside the mint hero card.
- (+6 more)
Map: design/identity-kit/
Map: resources/css/app.css
Full: .impeccable/design-full.md (read a section when you need its detail)
