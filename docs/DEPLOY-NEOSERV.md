# Running SpendLess on neoserv (Zeleni paket, cPanel + SSH)

SpendLess runs fine on shared hosting. The only thing shared hosting can't do is keep a
background process running, so **one cron job** drives everything else: bank syncs four times a
day, the background job queue, reconnect reminders and clean-up.

Paths below assume the app lives in `~/spendless` with the domain's document root pointing at
`~/spendless/public`. Adjust them to your setup.

## 1. One-time setup

1. **PHP version.** In cPanel → *Select PHP Version* (or *MultiPHP Manager*) pick PHP **8.2 or newer** for the domain.
2. **Find the PHP binary for cron.** Over SSH:

   ```bash
   which php && php -v
   ```

   Use that full path in the cron line below. If `php -v` shows an old version, use the
   versioned binary, e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.
3. **`.env` on the server.** Start from `.env.example`. The important values:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.si        # exact public address, https
   DB_CONNECTION=mysql                   # plus DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   MAIL_MAILER=smtp                      # plus MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD
   ENABLE_BANKING_APP_ID=...             # see ENABLE-BANKING-PRODUCTION.md
   ENABLE_BANKING_PRIVATE_KEY_PATH=storage/app/private/enablebanking.pem
   ENABLE_BANKING_COUNTRIES=SI,GB
   ```

4. **The cron job.** cPanel → *Cron Jobs* → *Add New Cron Job*, "Once Per Minute" (`* * * * *`), command:

   ```bash
   cd ~/spendless && /full/path/to/php artisan schedule:run >> /dev/null 2>&1
   ```

   That's the only cron entry needed. Within two minutes **Settings → Admin → Setup check**
   should show "Cron job" in green. The Admin page also prints the exact line for your server.

## 2. Deploying an update

Build the frontend on your own computer (shared hosting has too little memory for Vite):

```bash
npm ci && npm run build
```

Upload `public/build/` to the server (rsync, SFTP or cPanel File Manager), then over SSH:

```bash
cd ~/spendless
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Re-run `config:cache` after every `.env` change, because Laravel reads the cached copy.

### This update specifically

The `2026_11_17_000001_remove_plaid_and_normalize_banking` migration **deletes all Plaid
connections, their accounts and transactions** (Plaid is no longer used) and merges a few
duplicate columns. Manual cash wallets, their transactions, categories and users are kept. The
oldest user becomes admin. Take a database backup first (cPanel → *Backup* or phpMyAdmin →
*Export*).

## 3. What runs in the background

| What | When |
|---|---|
| Bank sync (balances + transactions) | Every 6 hours (00, 06, 12, 18 UTC). That's 4 a day, the PSD2 limit for refreshes without you present |
| "Sync now" / "Sync All" buttons | Immediately; they don't count toward the 4 a day |
| Queue worker | Started every minute by the cron job, stops when the queue is empty |
| "Reconnect your bank" email | 7 days before a bank's 180-day access ends (09:00 UTC) |
| Clean-up | Daily: sync history older than 30 days, failed jobs older than 30 days |

## 4. Troubleshooting

Open **Settings → Admin**. Every check is either green or explains what to change. Common ones:

- **Cron job red:** the cron line is missing or uses the wrong PHP path. Run the command by hand over SSH to see the error.
- **Bank return link red:** add `https://your-domain.si/banking/callback` to the application's redirect URLs in the Enable Banking Control Panel.
- **APP_URL warning:** `APP_URL` must be exactly the address you open in the browser, because the bank return link is built from it.
- **Logs:** `storage/logs/laravel.log`.
