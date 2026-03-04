<x-emails.layout
    title="Confirm your new email address"
    :logo-src="$logoSrc"
    :action-url="$confirmationUrl"
    action-text="Confirm new email address"
    expiry-text="This confirmation link expires in 5 minutes."
>
    <p style="margin:0 0 12px 0;">You requested to change the email address on your <span style="color:#111827;">Spend</span><span style="color:#10b981;">Less</span> account.</p>
    <p style="margin:0 0 12px 0;">Please confirm this new email address to complete the update.</p>
    <p style="margin:0;">If you did not request this change, no action is needed.</p>
</x-emails.layout>
