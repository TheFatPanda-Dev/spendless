# Moving Enable Banking from sandbox to production (free, for your own accounts)

The sandbox only has test banks. To read your real Slovenian and UK accounts you need a
**production** application. Enable Banking lets you use it **for free in "restricted mode"**:
it then works only for the bank accounts you link yourself. That's exactly what a personal
SpendLess needs, with no contract or company required.

Sources: [Control Panel guide](https://enablebanking.com/docs/api/control-panel/) ·
[Linked accounts](https://enablebanking.com/docs/api/linked-accounts/). Button names below come
from those docs; the Control Panel itself may word them slightly differently.

## 1. Register the production application

1. Sign in at <https://enablebanking.com/sign-in/> and open **API applications** (<https://enablebanking.com/cp/applications>).
2. Start a new application and choose **Environment: Production**.
3. Keep the default key option: **generate the private key in the browser**. The key never leaves your computer.
4. Fill in the details. Production asks for more than the sandbox did:
   - **Name:** `SpendLess`
   - **Redirect URLs:** `https://your-domain.si/banking/callback`. It must match `APP_URL` exactly, with https.
   - **Description:** shown to you at the bank when you approve access, e.g. "Personal budgeting app".
   - **Data protection email:** your email.
   - **Privacy policy URL:** `https://your-domain.si/privacy`
   - **Terms of service URL:** `https://your-domain.si/terms`
5. Press **Register**. You get an **application ID** (a UUID), and your browser downloads the private key as `<application-id>.pem`.

The application now shows as **Inactive**.

## 2. Activate it by linking your own accounts

1. On the new application, click **Activate by linking accounts**.
2. Accept Enable Banking's end-user terms, choose your bank, and approve at the bank (the bank's normal login + confirmation in NLB Klik / OTP Mobile / your UK banking app).
3. Repeat for every bank you want SpendLess to read: your Slovenian bank(s) **and** your UK bank(s).

After the first link the application becomes **active in restricted mode**. It can only read
accounts you linked here, so other people who sign up to your SpendLess can't connect their banks.
That's why the Admin page suggests closing sign-ups.

> Linking in the Control Panel only whitelists the accounts. You still connect each bank once
> inside SpendLess (step 4); Enable Banking requires that even for the same account.

## 3. Put the key and ID on the server

1. Upload `<application-id>.pem` to the server as `~/spendless/storage/app/private/enablebanking.pem`, outside `public/`, and lock it down:

   ```bash
   chmod 600 ~/spendless/storage/app/private/enablebanking.pem
   ```

2. In the server's `.env`:

   ```dotenv
   ENABLE_BANKING_APP_ID=<application-id>
   ENABLE_BANKING_PRIVATE_KEY_PATH=storage/app/private/enablebanking.pem
   ENABLE_BANKING_COUNTRIES=SI,GB
   ```

3. Run `php artisan config:cache`.
4. Open **Settings → Admin**. The Enable Banking checks should all be green: credentials OK, environment **Production**, application **active**, **bank return link registered**.

Keep the sandbox application too if you like. Point your local `.env` at the sandbox ID and key so you can test without touching real banks.

## 4. Connect your banks in SpendLess

Dashboard → **Add New Wallet → Connect a bank account** → pick **Slovenia** or **United Kingdom** → your bank → approve at the bank. The popup closes, the account appears, and the first 90 days of transactions load within a minute.

Bank access lasts up to **180 days** (some banks allow less). SpendLess shows a **Reconnect** banner and emails you a week before it ends. Reconnecting keeps all history and categories.
