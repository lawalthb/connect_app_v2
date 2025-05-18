@component('mail::message')
# Verify Your Email Address

Hi {{ $user->name }},

Thank you for registering with {{ config('app.name') }}. To complete your registration, please use the following verification code:

@component('mail::panel')
<div style="text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 8px;">
{{ $otp }}
</div>
@endcomponent

This code will expire in 60 minutes. If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
