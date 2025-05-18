@component('mail::message')
# Welcome to {{ config('app.name') }}!

Hi {{ $user->name }},

Thank you for joining our community. We're excited to have you on board!

@component('mail::button', ['url' => config('app.url')])
Visit Your Dashboard
@endcomponent

Feel free to explore and connect with other users. If you have any questions, please don't hesitate to contact our support team.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
