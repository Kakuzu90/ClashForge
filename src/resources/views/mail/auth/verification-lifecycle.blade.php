<x-mail::message>
# Confirm your email

@if ($warning)
Your Clash Commons account still has an unconfirmed email address. Confirm it before {{ $deadline }} to keep your account.
@else
Confirm your email address to finish setting up your Clash Commons account.

Accounts with an unconfirmed address are removed on or after {{ $deadline }}. We will send a final warning before removing yours.
@endif

<x-mail::button :url="$verificationUrl">
Confirm your email
</x-mail::button>

This link expires in {{ config('platform.auth.verification_link_minutes') }} minutes. If it expires, sign in and ask for a new one.

Removing your account clears your profile and sign-in details. Your username stays reserved and moderation records are retained.

If you did not create this account, you can ignore this email.

Clash Commons
</x-mail::message>
