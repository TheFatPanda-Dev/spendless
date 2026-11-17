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
- Background sync every 6 hours (4 times a day, the PSD2 limit for unattended refreshes); **Sync now** any time.
- Access lasts up to 180 days; SpendLess shows a **Reconnect** banner and emails a week before it ends.
- Amounts are signed: money in is positive, money out is negative.

Set up the credentials as described in [docs/ENABLE-BANKING-PRODUCTION.md](docs/ENABLE-BANKING-PRODUCTION.md):

```bash
ENABLE_BANKING_APP_ID=your-application-id
ENABLE_BANKING_PRIVATE_KEY_PATH=storage/app/private/enablebanking.pem
ENABLE_BANKING_COUNTRIES=SI,GB
```

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
