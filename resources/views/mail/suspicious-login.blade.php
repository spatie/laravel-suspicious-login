<x-mail::message>
# {{ $greeting ?: __('Suspicious login detected') }}

{{ __('We noticed a login to your account that looks unusual.') }}

<p style="margin: 0 0 24px; line-height: 1.6; overflow-wrap: break-word; word-break: break-word;">
<strong>{{ __('Time:') }}</strong> {{ $loginAttempt->created_at->toDayDateTimeString() }}<br>
<strong>{{ __('IP address:') }}</strong> {{ $loginAttempt->ip_address }}<br>
<strong>{{ __('Location:') }}</strong> {{ $place }}<br>
<strong>{{ __('Device:') }}</strong> {{ $loginAttempt->user_agent ?: __('Unknown') }}
</p>

@if($loginAttempt->reasons)
## {{ __('Why this login looks unusual') }}

@foreach($loginAttempt->reasons as $reason)
- {{ $reason['reason'] }}
@endforeach

@endif
{{ __('If this was you, you can ignore this email. If it wasn\'t, change your password right away.') }}

@if($salutation)
{{ $salutation }}
@else
{{ __('Regards,') }}<br>
{{ config('app.name') }}
@endif
</x-mail::message>
