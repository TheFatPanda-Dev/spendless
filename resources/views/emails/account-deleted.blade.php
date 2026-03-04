<x-emails.layout
    title="Your SpendLess account was deleted"
    :logo-src="$logoSrc"
>
    <p style="margin:0 0 12px 0;">Hi {{ $userName ?: 'there' }}, your SpendLess account has been successfully deleted.</p>
    <p style="margin:0 0 12px 0;">This email is a confirmation that the deletion request was completed.</p>
    <p style="margin:0;">If you did not request this action, please contact support immediately.</p>
</x-emails.layout>
