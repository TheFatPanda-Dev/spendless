---
version: 1
slug: "resources-js-pages-dashboard-tsx"
primary_target: "resources/js/pages/dashboard.tsx"
related_targets: ["resources/js/layouts/app-layout.tsx","resources/js/pages/activity/index.tsx","resources/js/pages/budgets/index.tsx"]
---

# Overview (dashboard) and app shell

Mode: Operate.

Scope: the signed-in app shell (rail on desktop, compact rail on tablet, top bar + bottom tabs on phone) and the
Overview screen; Activity, Budgets, Wallets, Tags, Imports and Settings inherit this world.

Audience and job: the owner checks "how am I doing this month?" on the phone in seconds and reviews the month on the
desktop. Overview answers: what's left to spend, where it went, what happened lately, and where the money sits
(€ and £ side by side).

Constraints: light and dark themes (system default, user override); month-by-month browsing; inline transaction
editing stays; every number in its own currency or clearly converted to the base currency.

## Direction contract

THESIS: A calmer look at spending: the screen leads with the amount you can still spend this month, then where it went
and what happened, in the identity kit's quiet green system; it refuses the dark neon dashboard tile wall and the
trading-app red/green ticker.

OWN-WORLD: Sage canvas #F6F7F2 with white 20px-radius cards and #D6DED7 hairlines; deep green #006447 for the single
primary action, selection and positive amounts; a mint hero card #DAF4E5 with #103B2A text; category series
#176343/#60AA83/#E5A251/#8AA9BC/#ABB6AC; dark theme #111714/#1B231E with mint #09C98C action. Roboto Light for
display amounts, Regular/Medium for UI; tabular lining figures; 1.75px rounded-stroke icons; 12px controls.

STORY: The visitor sees the month and what is left, understands which categories took the money and what moved
recently, trusts it because each figure shows its period and source, and acts: add or fix a transaction, review a
category, sync or reconnect a bank.

FIRST VIEWPORT: Desktop 1440: fixed 230px rail (compact lockup, Overview selected as a mint pill, Activity, Budgets,
Wallets, Tags, Imports; Settings at the foot). Main: "PERSONAL WORKSPACE" label over a 40px "Overview" title; month
picker and the one primary "Add transaction" button top-right. Row 1: mint "Monthly budget" hero (left to spend in
64px Light, open ring with % used, spent so far and budget below a divider) beside "Spending by category" (one
stacked bar on a shared scale plus five labelled rows). Row 2: "Recent activity" table with icon tiles, category,
date and right-aligned signed amounts. Phone 390: top bar with app tile, wordmark and a round "+" button; title and
month; the hero; category bar; recent activity; bottom tabs Overview, Activity, Budgets, Wallets.

FORM: The owner's SpendLess identity kit (design/identity-kit, guide/SpendLess-Brand-Guide.pdf and
screens/web-overview-*.png), the category standard executed at full craft; quality bar YNAB and Lunch Money.
seed: none (pinned) — owner: "i have a zip with design option if you like to see it i like it a little better"; the
kit names its world (guide pages 02-10) and its first surface (screens/web-overview-light.png).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and
every shipping raster carrying its provenance
