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

## Plaid Transactions Integration

This project includes a wallet-centric Plaid Transactions integration with:

- Multiple wallets per user.
- Multiple Plaid connections per wallet.
- Multiple accounts per connection.
- Incremental sync using Plaid `transactions/sync` cursor flow.
- Manual refresh (all connections and single connection).
- Scheduled refresh every 4 hours.
- Webhook-triggered sync for transaction updates.

### Required Environment Variables

Set these values in `.env`:

```bash
PLAID_BASE_URL=https://sandbox.plaid.com
PLAID_ENV=sandbox
PLAID_CLIENT_ID=your_plaid_client_id
PLAID_SECRET=your_plaid_secret
PLAID_VERSION=2020-09-14
PLAID_COUNTRY_CODES=US
PLAID_LANGUAGE=en
PLAID_WEBHOOK_URL="${APP_URL}/plaid/webhook"
PLAID_WEBHOOK_SECRET=your_private_webhook_shared_secret
PLAID_REDIRECT_URI=
PLAID_INITIAL_DAYS_REQUESTED=730
```

### Security and Data Protection

- Plaid access tokens are stored server-side only.
- Sensitive fields are encrypted at rest using Laravel encrypted casts.
- Webhook payload archives are encrypted.
- Sensitive endpoints are rate-limited.

### Scheduler

Run Laravel scheduler in production to enable automatic 4-hour refresh:

```bash
php artisan schedule:work
```

### Queue Worker

Bank sync and refresh operations are queued:

```bash
php artisan queue:work
```

