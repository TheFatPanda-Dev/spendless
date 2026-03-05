<x-emails.layout
    title="Reset your SpendLess password"
    :logo-src="$logoSrc"
    :action-url="$resetUrl"
    action-text="Reset password"
    :expiry-text="$expiryText"
>
    <p style="margin:0 0 12px 0;">We received a request to reset your password.</p>
    <p style="margin:0 0 12px 0;">Use the button below to choose a new password.</p>
    <p style="margin:0;">If you did not request this, you can safely ignore this email.</p>
</x-emails.layout>
