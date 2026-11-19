# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Today:** the owner, a person living between Slovenia and the UK with money in both: a Slovenian bank account,
  UK current accounts and a credit card, an Interactive Brokers portfolio, a UK Cash ISA and a UK pension.
- **Later:** the product may open to other people (friends or the public), so it should behave and read like a
  product, not a private script. Sign-ups are currently closed; the owner is the only account and the admin.
- Jobs, all first-class:
  - **Quick check on the phone:** glance at balances and recent spending, often.
  - **Monthly review on the desktop:** see where the money went, fix categories and tags, compare periods.
  - **Net worth overview:** banks, investments, Cash ISA and pension together, in euros and pounds.
  - **Track things:** what each car, the motorbike and each flat costs, through tags.

## Product Purpose

SpendLess gathers a person's money from banks, brokers and savings in different countries into one place, keeps it
current with as little manual work as possible, and shows where it goes. Success: the owner trusts the numbers enough
to stop checking each bank app, and a monthly review takes minutes.

## Positioning

A personal-finance tracker built for a life split across the EU and the UK: EU banks sync through open banking
(Enable Banking), UK banks through Plaid where available and statement import (CSV/OFX/XLSX) otherwise, investments
from Interactive Brokers, plus a fixed-term Cash ISA and a pension tracked by value. Euros and pounds stay side by side.
It runs cheaply on ordinary shared hosting.

## Operating Context

- Background sync on fixed daily slots (EU banks 07/11/15/19 Slovenian time; UK/Plaid once a day; IBKR daily),
  plus "Sync now". Bank consents expire (up to 180 days) and need a reconnect; the app warns before.
- Statement files are downloaded from Revolut, Barclays, Nationwide and Santander and imported with a preview.
- Transactions are auto-categorised (36 main categories, ~50 subcategories, SI and UK merchant keywords); manual
  categories are never overwritten. Tags (cars, motorbike, flats) can be set by hand or by rules.
- Admin tasks (users, sign-ups, bank connections, sync health, failed jobs) live in a separate admin panel
  (Filament), not in the everyday app.

## Capabilities and Constraints

- Stack: Laravel 12, Inertia + React 19, TypeScript, Tailwind v4 for the user-facing app; Filament for the admin panel.
  Hosted on neoserv shared hosting (PHP 8.3, MySQL/MariaDB, one cron job, no Node on the server; assets are built in
  GitHub Actions).
- Amounts are signed: money in positive, money out negative. Multi-currency (EUR, GBP, others); a base currency and
  a number locale are per-user settings.
- Wallet kinds: bank (synced), imported statement account, cash wallet (manual), IBKR investments, Cash ISA, pension.
- Budgets (added with the 2026-11 redesign, confirmed by the owner): a monthly total budget plus optional
  per-category budgets, in the user's base currency; spending in other currencies is converted at the day's rate.
  Income is excluded from category spending.
- Free-tier limits are real product constraints: Enable Banking restricted mode only reaches accounts the owner
  linked; Plaid's free allowance is 200 calls and the UK is not yet enabled on the owner's Plaid team.
- Undecided: whether and when sign-ups open; how bank sync would work for other users (would need paid provider plans).

## Brand Commitments

- Name **SpendLess** with a capital L; the two-tone wordmark and the coin-with-open-rings symbol (the owner's logo).
  Tagline: "Money made simple".
- **The SpendLess identity kit is binding** (supplied by the owner, 2026-11-19; archived in `design/identity-kit/`):
  logo masters and lockups, app icons, Roboto Light/Regular/Medium, the semantic light/dark colour roles and design
  tokens, spacing and radii, component states, chart rules, 24px interface icons, and the reference screens
  (web and phone overview, activity, transaction entry). Its brand guide is the authority for the look.
- Voice (from the kit): short, factual sentences; give the amount, the period and a useful next action; describe
  spending without judging the person ("Dining is €12.00 over its November budget." → "Review dining expenses").
- Product promise (from the kit): "See what went out. Know what's left." Lead with the amount the person can still
  spend; keep the period, the budget and the transactions within reach.
- The owner chose the clean category-standard look on purpose; quality bar: YNAB and Lunch Money.

## Evidence on Hand

- Real data exists only in the owner's own account. There are no customers, testimonials, reviews or usage numbers;
  do not invent any.

## Product Principles

1. **Numbers you can trust.** Every figure traces back to a bank sync, an import or a manual entry, and its currency
   and freshness are always clear.
2. **Honest about the plumbing.** Sync status, consent expiry and call allowances are shown plainly, with the next step.
3. **Glance and review are both first-class.** The phone answers "how am I doing?" in seconds; the desktop supports
   careful review and correction.
4. **Low upkeep.** Background sync, auto-categorisation and rules do the routine work; a user's correction always wins.
5. **Two countries, two currencies.** Euros and pounds sit side by side without silent conversion surprises.
