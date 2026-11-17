<x-emails.layout
    title="Reconnect {{ $institutionName }} to keep syncing"
    :logo-src="$logoSrc"
    :action-url="$reconnectUrl"
    action-text="Reconnect bank"
>
    <p style="margin:0 0 12px 0;">SpendLess's access to your <strong>{{ $institutionName }}</strong> accounts ends on <strong>{{ $expiresOn }}</strong>.</p>
    <p style="margin:0 0 12px 0;">Banks only allow access for a limited time. Reconnect now to keep your balances and transactions up to date. Your history and categories are kept.</p>
    <p style="margin:0;">If you no longer want SpendLess to read this bank, you can ignore this email and the connection will simply stop syncing.</p>
</x-emails.layout>
