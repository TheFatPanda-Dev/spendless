# SpendLess

## Requirements

- PHP >= 8.2
- Composer
- Node.js & npm
- MySQL or compatible database

## Installation

1. **Clone the repository:**

    ```bash
    git clone git@github.com:TheFatPanda-Dev/spendless.git
    cd spendless
    ```

2. **Install PHP dependencies:**

    ```bash
    composer install
    ```

3. **Install Node dependencies:**

    ```bash
    npm install
    ```

4. **Copy .env file and set up database:**

    ```bash
    cp .env.example .env
    # Edit .env and set your DB_DATABASE, DB_USERNAME, DB_PASSWORD
    ```

5. **Generate application key:**

    ```bash
    php artisan key:generate
    ```

6. **Run migrations:**

    ```bash
    php artisan migrate
    ```

7. **Start the development server:**
    ```bash
    npm run dev
    ```

## Bank sync (Enable Banking)

SpendLess reads balances and transactions from European and UK banks through
[Enable Banking](https://enablebanking.com/) (PSD2 / Open Banking). It's free for your own accounts in
restricted mode.

- Connect: **Add New Wallet → Connect a bank account**, pick the country and bank, approve at the bank.
- Background sync every 4 hours during the day: 07:00, 11:00, 15:00 and 19:00 Slovenian time (4 a day, the PSD2
  limit for unattended refreshes). **Sync now** syncs immediately, any time. Change the times with
  `ENABLE_BANKING_SYNC_HOURS` / `ENABLE_BANKING_SYNC_TIMEZONE`.
- Access lasts up to 180 days; SpendLess shows a **Reconnect** banner and emails a week before it ends.
- Amounts are signed: money in is positive, money out is negative.

Set up the credentials as described in [docs/ENABLE-BANKING-PRODUCTION.md](docs/ENABLE-BANKING-PRODUCTION.md):

```bash
ENABLE_BANKING_APP_ID=your-application-id
ENABLE_BANKING_PRIVATE_KEY_PATH=storage/app/private/enablebanking.pem
ENABLE_BANKING_COUNTRIES=SI,GB
```

## Statement import

For banks without open banking (e.g. UK accounts): **Add New Wallet → Import bank statement**. OFX/QFX,
CSV, Excel (.xlsx) and Santander's .txt download are supported; Revolut, Barclays and Nationwide layouts are
recognised automatically, any other CSV is mapped once per account. Re-importing overlapping files never
creates duplicates.

## Interactive Brokers

**Add New Wallet → Connect Interactive Brokers** with a Flex Query ID and Flex Web Service token
(IBKR Client Portal → Performance & Reports → Flex Queries). The Activity Flex Query needs Account
Information, Net Asset Value (NAV) in Base, Open Positions, Trades, Cash Transactions and Cash Report,
in XML. SpendLess fetches it daily (end-of-day data): account value counts towards your total, holdings
and trades show on the wallet page, dividends/interest are income and fees/withholding tax are expenses.
The token is stored encrypted; SpendLess asks for a new one when it expires.

## Cash ISA and pension

**Add New Wallet → Add Cash ISA or pension.**

- **Cash ISA (fixed term):** amount, start date, term in years and expected profit rate. Profit is
  amount × rate × years, paid at the end of the term: until then the value shows the profit earned so
  far, and on the maturity date the profit is recorded as income. Recalculated daily.
- **Pension:** enter the value from your provider's app (e.g. PensionBee has no API) and press
  **Update value** whenever you check; the history shows the change between updates.

Both count towards the dashboard total.

## Categories and tags

- **Categories say what a payment was.** New accounts start with 36 main categories and about 50
  subcategories (Housing > Rent, Transportation > Fuel, Food & Drink > Coffee & Cafés…). Bank
  transactions are filed into the most specific one automatically (SI and UK merchants); change any
  of them under **Settings → All Categories**.
- **Tags say which thing it was for:** a car, a motorbike, a flat. Tag a transaction from its
  account page (click it, pick tags or type a new one). **Tags** in the top bar shows what each tag
  cost or earned this month / year / 12 months / all time, split by category, per currency.
- **Tag rules** tag new transactions automatically: from a given account (e.g. the motorbike's card)
  and/or when the payee or description contains some text (e.g. "J. Novak" for a flat's rent). A rule
  can also tag matching past transactions. Rules check each transaction once, so a tag you remove by
  hand stays removed.

## Background work

Everything runs from one cron entry:

```bash
* * * * * cd /path/to/spendless && php artisan schedule:run >> /dev/null 2>&1
```

Locally, `composer run dev` starts the queue listener; run `php artisan schedule:work` to test scheduled syncs.

## Admin area

The first account is the admin (or run `php artisan spendless:make-admin you@example.com`).
**Settings → Admin** has a setup check (cron, Enable Banking, mail, HTTPS…), users, a sign-up switch,
every bank connection's status, and failed background jobs.

## Deployment

See [docs/DEPLOY-NEOSERV.md](docs/DEPLOY-NEOSERV.md) for shared hosting with cPanel + SSH.
